/**
 * remainder.js — AayuVault Patient Reminder Management & Notification System
 *
 * Implements:
 * 1. Permanent database storage & retrieval with MySQL (via patient_reminders_api.php)
 * 2. Clean 3-section dashboard:
 *    - Upcoming Reminders (all future items)
 *    - Past Reminders (past scheduled and missed items)
 *    - Completed Reminders (items marked as finished)
 * 3. Dedicated Blood Test scheduling with clinical presets and distinct styling
 * 4. Automated in-app notification alerts when reminder times arrive
 * 5. Offline missed reminder detection and notification upon next login
 * 6. Full CRUD: Add, View, Edit, Delete, Mark Completed, Reopen
 */

const RemindersSystem = (function () {
  const API = '../pages/patient_reminders_api.php';
  const OVERVIEW_API = '../pages/reminders_api.php';

  // State
  let remindersData = {
    upcoming: [],
    past: [],
    completed: [],
    missed: [],
    all: [],
    counts: { total: 0, upcoming: 0, past: 0, completed: 0, missed: 0, blood_tests: 0, medicines: 0, appointments: 0 }
  };
  let activeTab = 'all'; // 'all', 'upcoming', 'past', 'completed'
  let activeTypeFilter = 'all'; // 'all', 'Blood Test', 'Medicine', 'Doctor Appointment', 'Health Checkup', 'Other'
  let searchQuery = '';
  let dueCheckInterval = null;
  const notifiedIds = new Set();

  // ── Network Helpers ─────────────────────────────────────────────
  async function apiGet(action, params = {}) {
    const qs = new URLSearchParams({ action, ...params });
    const res = await fetch(`${API}?${qs.toString()}`);
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.error || `HTTP error ${res.status}`);
    }
    return await res.json();
  }

  async function apiPost(action, body = {}) {
    const res = await fetch(`${API}?action=${encodeURIComponent(action)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
      throw new Error(data.error || 'Server operation failed');
    }
    return data;
  }

  // ── Audio Cue Synthesizer ───────────────────────────────────────
  function playReminderChime() {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
      osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // A5

      gain.gain.setValueAtTime(0.001, ctx.currentTime);
      gain.gain.linearRampToValueAtTime(0.25, ctx.currentTime + 0.05);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.5);

      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.55);
    } catch (e) {
      // Audio playback blocked by browser policy until gesture
    }
  }

  // ── 1. Load Data from MySQL Database ────────────────────────────
  async function loadReminders() {
    try {
      const params = {};
      if (activeTypeFilter !== 'all') params.type = activeTypeFilter;
      if (searchQuery.trim() !== '') params.search = searchQuery.trim();

      const res = await apiGet('get_reminders', params);
      if (res && res.success && res.data) {
        remindersData = res.data;
        updateCountsAndBadges();
        renderDashboardSections();
        renderSidebarCounts();
        renderRightPanelSpotlight();
      }
    } catch (err) {
      console.error('[AayuVault Reminders] Load error:', err);
      showToast('Could not load reminders from database: ' + err.message, 'error');
    }
  }

  // ── 2. Render Three Dashboard Sections ──────────────────────────
  function renderDashboardSections() {
    const secUpcoming = document.getElementById('section-upcoming');
    const secPast = document.getElementById('section-past');
    const secCompleted = document.getElementById('section-completed');
    const emptyNotice = document.getElementById('timeline-empty');

    const upList = document.getElementById('upcoming-reminders-list');
    const pastList = document.getElementById('past-reminders-list');
    const compList = document.getElementById('completed-reminders-list');

    // Filter datasets by search query if applicable
    const query = searchQuery.toLowerCase().trim();
    const filterFn = r => {
      const matchesSearch = !query ||
        r.title.toLowerCase().includes(query) ||
        (r.description && r.description.toLowerCase().includes(query)) ||
        r.reminder_type.toLowerCase().includes(query) ||
        r.reminder_date.includes(query);
      const matchesType = (activeTypeFilter === 'all') || (r.reminder_type === activeTypeFilter);
      return matchesSearch && matchesType;
    };

    const filteredUpcoming = (remindersData.upcoming || []).filter(filterFn);
    const filteredPast = (remindersData.past || []).filter(filterFn);
    const filteredCompleted = (remindersData.completed || []).filter(filterFn);

    const totalVisible = filteredUpcoming.length + filteredPast.length + filteredCompleted.length;

    // Control Section Visibility Based on Active Tab
    if (secUpcoming) secUpcoming.style.display = (activeTab === 'all' || activeTab === 'upcoming') ? 'block' : 'none';
    if (secPast) secPast.style.display = (activeTab === 'all' || activeTab === 'past') ? 'block' : 'none';
    if (secCompleted) secCompleted.style.display = (activeTab === 'all' || activeTab === 'completed') ? 'block' : 'none';

    // Update Section Count Badges
    const badgeUp = document.getElementById('badge-upcoming-count');
    if (badgeUp) badgeUp.textContent = `${filteredUpcoming.length} Upcoming`;

    const badgePast = document.getElementById('badge-past-count');
    if (badgePast) badgePast.textContent = `${filteredPast.length} Past / Missed`;

    const badgeComp = document.getElementById('badge-completed-count');
    if (badgeComp) badgeComp.textContent = `${filteredCompleted.length} Completed`;

    // Render Cards in Each Section
    if (upList) renderCardList(upList, filteredUpcoming, 'No upcoming reminders scheduled.');
    if (pastList) renderCardList(pastList, filteredPast, 'No past reminders.');
    if (compList) renderCardList(compList, filteredCompleted, 'No completed reminders yet.');

    // Overall Empty State
    if (emptyNotice) {
      if (totalVisible === 0) {
        emptyNotice.classList.remove('hidden');
      } else {
        emptyNotice.classList.add('hidden');
      }
    }
  }

  function renderCardList(container, items, emptyMessage) {
    if (!items || items.length === 0) {
      container.innerHTML = `
        <div style="padding:16px; text-align:center; color:#94a3b8; font-size:12.5px; background:#f8fafc; border-radius:10px; border:1px dashed #cbd5e1;">
          <i class="fa-regular fa-calendar-check" style="margin-right:6px;"></i> ${escapeHtml(emptyMessage)}
        </div>`;
      return;
    }

    container.innerHTML = items.map(r => buildReminderCardHtml(r)).join('');
  }

  // ── 3. Build Card HTML with Distinct Blood Test Styling ──────────
  function buildReminderCardHtml(r) {
    const isBloodTest = (r.reminder_type === 'Blood Test');
    const isCompleted = (r.reminder_status === 'Completed');
    const isMissed    = (r.reminder_status === 'Missed');

    // Type classes and icons
    let typeClass = 'type-other';
    let typeIcon = '<i class="fa-solid fa-bell"></i>';

    if (isBloodTest) {
      typeClass = 'type-blood-test';
      typeIcon = '<i class="fa-solid fa-vial"></i>';
    } else if (r.reminder_type === 'Medicine') {
      typeClass = 'type-medicine';
      typeIcon = '<i class="fa-solid fa-pills"></i>';
    } else if (r.reminder_type === 'Doctor Appointment') {
      typeClass = 'type-doctor-appointment';
      typeIcon = '<i class="fa-solid fa-user-doctor"></i>';
    } else if (r.reminder_type === 'Health Checkup') {
      typeClass = 'type-health-checkup';
      typeIcon = '<i class="fa-solid fa-heart-pulse"></i>';
    }

    // Status Badge
    let statusBadge = '<span class="rem-status-badge status-badge-pending"><i class="fa-regular fa-clock"></i> Pending</span>';
    if (isCompleted) {
      statusBadge = '<span class="rem-status-badge status-badge-completed"><i class="fa-solid fa-check"></i> Completed</span>';
    } else if (isMissed) {
      statusBadge = '<span class="rem-status-badge status-badge-missed"><i class="fa-solid fa-triangle-exclamation"></i> Missed</span>';
    }

    let cardExtraClass = '';
    if (isCompleted) cardExtraClass = 'status-completed-card';
    if (isMissed) cardExtraClass = 'status-missed-card';

    return `
      <div class="patient-rem-card ${typeClass} ${cardExtraClass}" id="rem-card-${r.id}" data-id="${r.id}">
        <div class="rem-card-top">
          <div class="rem-card-left">
            <div class="rem-type-icon">
              ${typeIcon}
            </div>
            <div class="rem-card-title-group">
              <h4>${escapeHtml(r.title)}</h4>
              <div style="display:flex; align-items:center; gap:6px;">
                <span class="rem-type-pill">${escapeHtml(r.reminder_type)}</span>
                ${isBloodTest ? '<span style="font-size:11px; font-weight:700; color:#dc2626;"><i class="fa-solid fa-droplet"></i> Blood Work</span>' : ''}
              </div>
            </div>
          </div>
          <div>
            ${statusBadge}
          </div>
        </div>

        ${r.description ? `<div class="rem-card-desc"><i class="fa-regular fa-note-sticky" style="color:#94a3b8; margin-right:4px;"></i> ${escapeHtml(r.description)}</div>` : ''}

        <div class="rem-card-meta">
          <span><i class="fa-regular fa-calendar-days" style="color:var(--gold);"></i> <strong>${r.date_formatted}</strong></span>
          <span><i class="fa-regular fa-clock" style="color:var(--gold);"></i> <strong>${r.time_formatted}</strong></span>
          ${isBloodTest && r.description && r.description.toLowerCase().includes('fasting') ? '<span style="color:#b91c1c; font-weight:700;"><i class="fa-solid fa-utensils"></i> Fasting Required</span>' : ''}
        </div>

        <div class="rem-card-actions">
          ${!isCompleted ? `
            <button class="btn-card-action btn-action-complete" onclick="markReminderStatus(${r.id}, 'Completed')" title="Mark reminder as completed">
              <i class="fa-solid fa-check"></i> Mark as Completed
            </button>
          ` : `
            <button class="btn-card-action btn-action-reopen" onclick="markReminderStatus(${r.id}, 'Pending')" title="Mark as pending again">
              <i class="fa-solid fa-arrow-rotate-left"></i> Reopen
            </button>
          `}

          <button class="btn-card-action btn-action-edit" onclick="openEditReminderModal(${r.id})" title="Edit reminder details">
            <i class="fa-solid fa-pen-to-square"></i> Edit
          </button>

          <button class="btn-card-action btn-action-delete" onclick="deleteReminder(${r.id})" title="Delete reminder">
            <i class="fa-solid fa-trash"></i> Delete
          </button>
        </div>
      </div>
    `;
  }

  // ── 4. Update Header Tabs & Sidebar Counts ───────────────────────
  function updateCountsAndBadges() {
    const c = remindersData.counts || {};

    setTxt('tab-count-all', c.total || 0);
    setTxt('tab-count-upcoming', c.upcoming || 0);
    setTxt('tab-count-past', (c.past || 0));
    setTxt('tab-count-completed', c.completed || 0);

    setTxt('count-all', c.total || 0);
    setTxt('count-upcoming', c.upcoming || 0);
    setTxt('count-past', (c.past || 0));
    setTxt('count-completed', c.completed || 0);
    setTxt('count-blood-tests', c.blood_tests || 0);
    setTxt('count-medicines', c.medicines || 0);
    setTxt('count-appointments', c.appointments || 0);
  }

  function renderSidebarCounts() {
    const categories = document.querySelectorAll('#reminder-categories .cat-item');
    categories.forEach(item => {
      item.onclick = () => {
        categories.forEach(c => c.classList.remove('active'));
        item.classList.add('active');
        const filter = item.dataset.filter;

        if (filter === 'all') {
          activeTab = 'all';
          activeTypeFilter = 'all';
        } else if (filter === 'upcoming') {
          activeTab = 'upcoming';
          activeTypeFilter = 'all';
        } else if (filter === 'past') {
          activeTab = 'past';
          activeTypeFilter = 'all';
        } else if (filter === 'completed') {
          activeTab = 'completed';
          activeTypeFilter = 'all';
        } else if (filter === 'blood_test') {
          activeTab = 'all';
          activeTypeFilter = 'Blood Test';
        } else if (filter === 'medicine') {
          activeTab = 'all';
          activeTypeFilter = 'Medicine';
        } else if (filter === 'appointment') {
          activeTab = 'all';
          activeTypeFilter = 'Doctor Appointment';
        }

        // Sync dropdown
        const sel = document.getElementById('type-select-filter');
        if (sel) sel.value = activeTypeFilter;

        // Sync tab buttons
        updateTabNavButtons();
        renderDashboardSections();
      };
    });
  }

  function updateTabNavButtons() {
    const btnAll = document.getElementById('tab-btn-all');
    const btnUp = document.getElementById('tab-btn-upcoming');
    const btnPast = document.getElementById('tab-btn-past');
    const btnComp = document.getElementById('tab-btn-completed');

    [btnAll, btnUp, btnPast, btnComp].forEach(b => b && b.classList.remove('active'));

    if (activeTab === 'all' && btnAll) btnAll.classList.add('active');
    if (activeTab === 'upcoming' && btnUp) btnUp.classList.add('active');
    if (activeTab === 'past' && btnPast) btnPast.classList.add('active');
    if (activeTab === 'completed' && btnComp) btnComp.classList.add('active');
  }

  // ── 5. Right Panel Spotlight (Today's Items) ────────────────────
  function renderRightPanelSpotlight() {
    const container = document.getElementById('sideTodaySpotlight');
    if (!container) return;

    const todayStr = new Date().toISOString().slice(0, 10);
    const todayItems = (remindersData.all || []).filter(r => r.reminder_date === todayStr);

    if (!todayItems.length) {
      container.innerHTML = `
        <div style="font-size:12px; color:#64748b; padding:4px 0;">
          <i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:4px;"></i> No tests or reminders due today.
        </div>`;
      return;
    }

    container.innerHTML = todayItems.map(item => `
      <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 10px; background:#fdf6ea; border-radius:8px; margin-bottom:6px; border:1px solid #ebd9b6;">
        <div>
          <div style="font-size:12.5px; font-weight:700; color:#1a1a1a;">
            ${item.reminder_type === 'Blood Test' ? '🩸 ' : (item.reminder_type === 'Medicine' ? '💊 ' : '🩺 ')}
            ${escapeHtml(item.title)}
          </div>
          <div style="font-size:11px; color:#78716c;">${item.time_formatted} · ${item.reminder_status}</div>
        </div>
        ${item.reminder_status !== 'Completed' ? `
          <button class="btn-card-action btn-action-complete" style="padding:3px 8px; font-size:11px;" onclick="markReminderStatus(${item.id}, 'Completed')">Done</button>
        ` : `
          <span style="color:#059669; font-size:11px; font-weight:700;"><i class="fa-solid fa-check"></i></span>
        `}
      </div>
    `).join('');
  }

  // ── 6. In-App Notification System & Automated Due Checking ───────
  async function checkDueReminders() {
    try {
      const res = await apiGet('check_due');
      if (!res || !res.success) return;

      // Handle Reminders that are Due Right Now
      if (res.due_reminders && res.due_reminders.length > 0) {
        res.due_reminders.forEach(due => {
          if (!notifiedIds.has(due.id)) {
            notifiedIds.add(due.id);
            playReminderChime();
            showInAppNotificationToast(due, false);
          }
        });
      }

      // Handle Reminders that arrived while offline (Missed Reminders)
      if (res.missed_alerts && res.missed_alerts.length > 0) {
        const unnotifiedMissed = res.missed_alerts.filter(m => !notifiedIds.has('missed_' + m.id));
        if (unnotifiedMissed.length > 0) {
          unnotifiedMissed.forEach(m => notifiedIds.add('missed_' + m.id));
          displayMissedAlertsBanner(unnotifiedMissed);
        }
      }

      // Sync unread badge count in Navbar
      const badge = document.getElementById('navNotifBadge');
      if (badge && res.unread_count !== undefined) {
        badge.textContent = res.unread_count;
        badge.style.display = res.unread_count > 0 ? 'grid' : 'none';
      }
    } catch (e) {
      console.warn('[AayuVault Reminders] Due check note:', e);
    }
  }

  function showInAppNotificationToast(reminder, isMissed = false) {
    const container = document.getElementById('inAppToastContainer');
    if (!container) return;

    const toastId = 'toast-rem-' + reminder.id + '-' + Date.now();
    const isBloodTest = (reminder.reminder_type === 'Blood Test');
    const toastClass = isBloodTest ? 'toast-blood-test' : (isMissed ? 'toast-missed' : '');

    const toast = document.createElement('div');
    toast.className = `inapp-notif-toast ${toastClass}`;
    toast.id = toastId;

    toast.innerHTML = `
      <div class="toast-header">
        <div class="toast-title-row">
          <span class="toast-badge">${isBloodTest ? '🩸 Blood Test Due' : (isMissed ? '⚠️ Missed Reminder' : '🔔 Reminder Due')}</span>
          <span style="font-size:11px; color:#64748b;">${reminder.time_formatted || ''}</span>
        </div>
        <button class="toast-close-btn" onclick="document.getElementById('${toastId}').remove()">&times;</button>
      </div>
      <div class="toast-body-title">${escapeHtml(reminder.title)}</div>
      ${reminder.description ? `<div class="toast-body-text">${escapeHtml(reminder.description)}</div>` : ''}
      <div class="toast-actions-row">
        <button class="btn-card-action btn-action-complete" style="font-size:11px; padding:4px 10px;" onclick="markReminderStatus(${reminder.id}, 'Completed'); document.getElementById('${toastId}').remove();">
          <i class="fa-solid fa-check"></i> Mark as Completed
        </button>
        <button class="btn-card-action" style="font-size:11px; padding:4px 10px;" onclick="document.getElementById('${toastId}').remove()">
          Dismiss
        </button>
      </div>
    `;

    container.appendChild(toast);

    // Auto-remove after 14 seconds unless dismissed
    setTimeout(() => {
      if (toast && toast.parentNode) {
        toast.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(60px)';
        setTimeout(() => toast.remove(), 400);
      }
    }, 14000);
  }

  function displayMissedAlertsBanner(missedItems) {
    const banner = document.getElementById('missedAlertsBanner');
    const list = document.getElementById('missedAlertsList');
    if (!banner || !list) return;

    list.innerHTML = missedItems.slice(0, 3).map(m => `
      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
        <span><strong>${escapeHtml(m.title)}</strong> (${m.reminder_type}) scheduled for ${m.date_formatted} at ${m.time_formatted}</span>
        <button class="btn-card-action btn-action-complete" style="padding:2px 8px; font-size:11px;" onclick="markReminderStatus(${m.id}, 'Completed')">Mark Completed</button>
      </div>
    `).join('');

    banner.classList.remove('hidden');
  }

  // ── 7. CRUD Actions (Add, Edit, Delete, Status) ───────────────────
  async function saveNewReminder(payload) {
    try {
      const res = await apiPost('add_reminder', payload);
      showToast(res.message || 'Reminder permanently saved to database!', 'success');
      await loadReminders();
      return res;
    } catch (err) {
      console.error('[Save Reminder Error]:', err);
      showToast(err.message || 'Failed to save reminder.', 'error');
      throw err;
    }
  }

  async function updateReminder(id, payload) {
    try {
      const res = await apiPost('update_reminder', { id, ...payload });
      showToast(res.message || 'Reminder updated successfully.', 'success');
      await loadReminders();
      return res;
    } catch (err) {
      console.error('[Update Reminder Error]:', err);
      showToast(err.message || 'Failed to update reminder.', 'error');
      throw err;
    }
  }

  async function deleteReminderAction(id) {
    if (!confirm('Are you sure you want to permanently delete this reminder?')) {
      return;
    }
    try {
      const res = await apiPost('delete_reminder', { id });
      showToast(res.message || 'Reminder deleted from database.', 'success');
      await loadReminders();
    } catch (err) {
      console.error('[Delete Reminder Error]:', err);
      showToast(err.message || 'Failed to delete reminder.', 'error');
    }
  }

  async function updateReminderStatus(id, newStatus) {
    try {
      const res = await apiPost('mark_status', { id, status: newStatus });
      showToast(`Reminder marked as ${newStatus}.`, 'success');
      await loadReminders();
    } catch (err) {
      console.error('[Status Update Error]:', err);
      showToast(err.message || 'Failed to update reminder status.', 'error');
    }
  }

  // ── Toast Notification Helper ────────────────────────────────────
  function showToast(message, type = 'info') {
    const existing = document.getElementById('aayu-app-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'aayu-app-toast';
    const bg = type === 'success' ? '#10b981' : (type === 'error' ? '#ef4444' : '#c49a2a');

    toast.style.cssText = `
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: ${bg};
      color: #fff;
      padding: 12px 20px;
      border-radius: 10px;
      font-size: 13.5px;
      font-weight: 600;
      box-shadow: 0 8px 24px rgba(0,0,0,0.18);
      z-index: 10000;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: fadeIn 0.25s ease;
      font-family: inherit;
    `;
    const icon = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-circle-exclamation' : 'fa-bell');
    toast.innerHTML = `<i class="fa-solid ${icon}"></i> <span>${escapeHtml(message)}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => {
      if (toast && toast.parentNode) {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => toast.remove(), 300);
      }
    }, 4000);
  }

  function setTxt(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // ── Init on DOM Ready ───────────────────────────────────────────
  function init() {
    loadReminders();

    // Check due items immediately and poll every 20 seconds
    checkDueReminders();
    if (dueCheckInterval) clearInterval(dueCheckInterval);
    dueCheckInterval = setInterval(checkDueReminders, 20000);

    // Search bar listener
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        searchQuery = e.target.value;
        renderDashboardSections();
      });
    }
  }

  return {
    init,
    loadReminders,
    saveNewReminder,
    updateReminder,
    deleteReminderAction,
    updateReminderStatus,
    checkDueReminders,
    getRemindersData: () => remindersData,
    setTab: (tab) => {
      activeTab = tab;
      updateTabNavButtons();
      renderDashboardSections();
    },
    setTypeFilter: (type) => {
      activeTypeFilter = type;
      loadReminders();
    }
  };
})();

// ── GLOBAL MODAL & INTERACTION HANDLERS ────────────────────────────

function openNewReminderModal(defaultType = 'Blood Test') {
  const modal = document.getElementById('modal-patient-reminder');
  const typeSelect = document.getElementById('rem-input-type');
  const dateInput = document.getElementById('rem-input-date');
  const timeInput = document.getElementById('rem-input-time');
  const titleInput = document.getElementById('rem-input-title');
  const descInput = document.getElementById('rem-input-desc');

  if (titleInput) titleInput.value = '';
  if (descInput) descInput.value = '';

  // Default to today
  const today = new Date().toISOString().slice(0, 10);
  if (dateInput) {
    dateInput.value = today;
    dateInput.min = today;
  }

  // Default to next upcoming round hour
  if (timeInput) {
    const now = new Date();
    now.setHours(now.getHours() + 1, 0, 0, 0);
    const hh = String(now.getHours()).padStart(2, '0');
    const mm = String(now.getMinutes()).padStart(2, '0');
    timeInput.value = `${hh}:${mm}`;
  }

  if (typeSelect) {
    typeSelect.value = defaultType;
    onModalTypeChange(defaultType);
  }

  if (modal) modal.classList.remove('hidden');
}

function closeReminderModal() {
  const modal = document.getElementById('modal-patient-reminder');
  if (modal) modal.classList.add('hidden');
}

function onModalTypeChange(type) {
  const helper = document.getElementById('modalBloodTestHelper');
  if (!helper) return;
  helper.style.display = (type === 'Blood Test') ? 'block' : 'none';
}

function selectPresetBloodTest(name, instructions) {
  const title = document.getElementById('rem-input-title');
  const desc = document.getElementById('rem-input-desc');
  if (title) title.value = name;
  if (desc && instructions) desc.value = instructions;
}

function quickFillBloodTest(name, instructions) {
  openNewReminderModal('Blood Test');
  selectPresetBloodTest(name, instructions);
}

function setTimePreset(timeStr) {
  const timeInput = document.getElementById('rem-input-time');
  if (timeInput) timeInput.value = timeStr;
}

async function handleSaveReminder(e) {
  e.preventDefault();
  const title = document.getElementById('rem-input-title').value.trim();
  const type = document.getElementById('rem-input-type').value;
  const date = document.getElementById('rem-input-date').value;
  const time = document.getElementById('rem-input-time').value;
  const desc = document.getElementById('rem-input-desc').value.trim();

  if (!title || !date || !time) {
    alert('Please provide a title, date, and time for this reminder.');
    return;
  }

  const btn = document.getElementById('btn-save-patient-rem');
  if (btn) btn.disabled = true;

  try {
    await RemindersSystem.saveNewReminder({
      title,
      reminder_type: type,
      reminder_date: date,
      reminder_time: time,
      description: desc,
      reminder_status: 'Pending'
    });
    closeReminderModal();
  } finally {
    if (btn) btn.disabled = false;
  }
}

// ── EDIT MODAL ───────────────────────────────────────────────────

function openEditReminderModal(id) {
  const all = RemindersSystem.getRemindersData().all || [];
  const reminder = all.find(r => r.id === id);
  if (!reminder) {
    alert('Reminder could not be found.');
    return;
  }

  document.getElementById('edit-rem-id').value = reminder.id;
  document.getElementById('edit-rem-title').value = reminder.title;
  document.getElementById('edit-rem-type').value = reminder.reminder_type;
  document.getElementById('edit-rem-status').value = reminder.reminder_status;
  document.getElementById('edit-rem-date').value = reminder.reminder_date;
  document.getElementById('edit-rem-time').value = reminder.reminder_time.slice(0, 5);
  document.getElementById('edit-rem-desc').value = reminder.description || '';

  const modal = document.getElementById('modal-edit-reminder');
  if (modal) modal.classList.remove('hidden');
}

function closeEditModal() {
  const modal = document.getElementById('modal-edit-reminder');
  if (modal) modal.classList.add('hidden');
}

async function handleUpdateReminder(e) {
  e.preventDefault();
  const id = parseInt(document.getElementById('edit-rem-id').value, 10);
  const title = document.getElementById('edit-rem-title').value.trim();
  const type = document.getElementById('edit-rem-type').value;
  const status = document.getElementById('edit-rem-status').value;
  const date = document.getElementById('edit-rem-date').value;
  const time = document.getElementById('edit-rem-time').value;
  const desc = document.getElementById('edit-rem-desc').value.trim();

  const btn = document.getElementById('btn-update-patient-rem');
  if (btn) btn.disabled = true;

  try {
    await RemindersSystem.updateReminder(id, {
      title,
      reminder_type: type,
      reminder_status: status,
      reminder_date: date,
      reminder_time: time,
      description: desc
    });
    closeEditModal();
  } finally {
    if (btn) btn.disabled = false;
  }
}

function deleteReminder(id) {
  RemindersSystem.deleteReminderAction(id);
}

function markReminderStatus(id, status) {
  RemindersSystem.updateReminderStatus(id, status);
}

function setDashboardTab(tab) {
  RemindersSystem.setTab(tab);
}

function onTypeFilterChange(type) {
  RemindersSystem.setTypeFilter(type);
}

function dismissMissedBanner() {
  const banner = document.getElementById('missedAlertsBanner');
  if (banner) banner.classList.add('hidden');
}

// Global alias for compatibility
window.RemindersPage = RemindersSystem;

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  RemindersSystem.init();
});
