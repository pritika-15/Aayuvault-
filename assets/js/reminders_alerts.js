/**
 * reminders_alerts.js — AayuVault Medicine Reminder & Appointment Alert Controller
 *
 * Implements:
 * 1. Medicine Due Now alerts with [Mark as Taken] & [Remind Me Later] (Snooze)
 * 2. Missed medicines handling with clinical safe dosage guidance
 * 3. Doctor Appointment Today banner with live countdown timers & status updates
 * 4. Upcoming appointments & medicines
 * 5. Notification center dropdown & history
 * 6. Browser notifications (permission handling & dispatch)
 * 7. Real-time background sync every 30 seconds
 */

const RemindersAlerts = (function () {
  const API_ENDPOINT = '../pages/reminders_api.php';
  let csrfToken = '';
  let pollTimer = null;
  let countdownTimer = null;
  let currentOverviewData = null;

  // ── CSRF Fetch Helper ──────────────────────────────────────────
  async function apiPost(action, bodyData = {}) {
    try {
      const headers = {
        'Content-Type': 'application/json',
      };
      if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
      }
      bodyData.csrf_token = csrfToken;

      const res = await fetch(`${API_ENDPOINT}?action=${encodeURIComponent(action)}`, {
        method: 'POST',
        headers,
        body: JSON.stringify(bodyData)
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.error || 'Server request failed');
      }
      return data;
    } catch (err) {
      console.error('[RemindersAlerts] API Error:', err);
      showToast(err.message || 'Request failed', 'error');
      throw err;
    }
  }

  async function apiGet(action, params = {}) {
    try {
      const qs = new URLSearchParams({ action, ...params });
      const res = await fetch(`${API_ENDPOINT}?${qs.toString()}`);
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.error || 'Fetch failed');
      }
      return data;
    } catch (err) {
      console.warn('[RemindersAlerts] GET Error:', err);
      return null;
    }
  }

  // ── Main Overview Loader ───────────────────────────────────────
  async function refreshOverview() {
    const res = await apiGet('get_overview');
    if (!res || !res.success || !res.data) return;

    currentOverviewData = res.data;
    if (res.data.csrf_token) {
      csrfToken = res.data.csrf_token;
    }

    // 1. Update Notification Badges
    updateBadges(res.data.unread_badge_count || 0);

    // 2. Render Cards in Dashboard Section (if present on page)
    renderDueMedicines(res.data.medicine_due_now || []);
    renderUpcomingMedicines(res.data.upcoming_medicines || []);
    renderMissedMedicines(res.data.missed_medicines || []);
    renderTodayAppointments(res.data.today_appointments || []);
    renderUpcomingAppointments(res.data.upcoming_appointments || []);
    renderBloodTestsAndReminders(res.data.patient_reminders || {});
    renderNotificationHistory(res.data.notification_history || []);

    // 3. Render In-App Notification Dropdown
    renderNotificationDropdown(res.data);

    // 4. Trigger Browser Notifications for Due Now items
    triggerBrowserAlertsIfSupported(res.data);
  }

  // ── Badge Counter ──────────────────────────────────────────────
  function updateBadges(count) {
    const badges = document.querySelectorAll('.notif-btn .badge, #navNotifBadge, .rem-nav-badge');
    badges.forEach(b => {
      if (count > 0) {
        b.textContent = count;
        b.style.display = 'grid';
      } else {
        b.textContent = '0';
        b.style.display = 'none';
      }
    });
  }

  // ── 1. Render Medicine Due Now ─────────────────────────────────
  function renderDueMedicines(dueItems) {
    const container = document.getElementById('remDueList');
    const countPill = document.getElementById('remDueCount');
    if (!container) return;

    if (countPill) {
      countPill.textContent = dueItems.length;
      countPill.className = dueItems.length > 0 ? 'rem-count-pill alert' : 'rem-count-pill';
    }

    if (!dueItems.length) {
      container.innerHTML = `
        <div class="rem-empty-state">
          <i class="fa-solid fa-circle-check" style="color:#10b981;"></i>
          <p>No medicines due right now. You are all caught up!</p>
        </div>`;
      return;
    }

    container.innerHTML = dueItems.map(item => `
      <div class="rem-due-item ${item.is_snoozed ? 'snoozed' : ''}" id="dose-card-${item.dose_id}">
        <div class="rem-item-top">
          <div class="rem-med-name">${escapeHtml(item.medicine_name)}</div>
          <span class="rem-med-dose">${escapeHtml(item.dosage)}</span>
        </div>
        <div class="rem-item-meta">
          <span><i class="fa-regular fa-clock"></i> Scheduled: <strong>${item.scheduled_time_formatted}</strong></span>
          ${item.instructions ? `<span><i class="fa-solid fa-circle-info"></i> ${escapeHtml(item.instructions)}</span>` : ''}
          ${item.is_snoozed ? `<span style="color:#2563eb;"><i class="fa-solid fa-bell-slash"></i> Snoozed (Due again soon)</span>` : ''}
        </div>
        <div class="rem-actions-row">
          <button class="btn-take-now" onclick="RemindersAlerts.markTaken(${item.dose_id})">
            <i class="fa-solid fa-check"></i> Mark as Taken
          </button>
          <button class="btn-snooze-dose" onclick="RemindersAlerts.snooze(${item.dose_id})">
            <i class="fa-solid fa-clock-rotate-left"></i> Remind Me Later
          </button>
        </div>
      </div>
    `).join('');
  }

  // ── 2. Render Upcoming Medicines ───────────────────────────────
  function renderUpcomingMedicines(items) {
    const container = document.getElementById('remUpcomingMedsList');
    const countPill = document.getElementById('remUpcomingCount');
    if (!container) return;

    if (countPill) countPill.textContent = items.length;

    if (!items.length) {
      container.innerHTML = `
        <div class="rem-empty-state">
          <i class="fa-regular fa-calendar-check"></i>
          <p>No further doses scheduled for today.</p>
        </div>`;
      return;
    }

    container.innerHTML = items.map(m => `
      <div style="display:flex; justify-content:space-between; align-items:center; padding:9px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:7px;">
        <div>
          <strong style="color:#1e293b; font-size:13px;">${escapeHtml(m.medicine_name)}</strong>
          <span style="font-size:11.5px; color:#64748b; margin-left:6px;">(${escapeHtml(m.dosage)})</span>
          ${m.instructions ? `<div style="font-size:11px; color:#64748b; margin-top:2px;">${escapeHtml(m.instructions)}</div>` : ''}
        </div>
        <span style="font-size:12px; font-weight:700; color:#c9973a; background:#fdf6ea; padding:3px 8px; border-radius:6px;">
          ${m.scheduled_time_formatted}
        </span>
      </div>
    `).join('');
  }

  // ── 3. Render Missed Medicines ─────────────────────────────────
  function renderMissedMedicines(missed) {
    const container = document.getElementById('remMissedList');
    const countPill = document.getElementById('remMissedCount');
    if (!container) return;

    if (countPill) {
      countPill.textContent = missed.length;
      countPill.className = missed.length > 0 ? 'rem-count-pill alert' : 'rem-count-pill';
    }

    if (!missed.length) {
      container.innerHTML = `
        <div class="rem-empty-state">
          <i class="fa-solid fa-shield-heart" style="color:#10b981;"></i>
          <p>No missed doses recorded. Great adherence!</p>
        </div>`;
      return;
    }

    container.innerHTML = `
      <div class="safe-disclaimer-box">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>
          <strong>Safe Medication Advisory:</strong> Do not take a double dose to make up for a missed dose.
          Please consult your physician or take your next dose at the scheduled time.
        </div>
      </div>
      ${missed.map(item => `
        <div class="rem-missed-item" id="missed-dose-${item.dose_id}">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
            <strong style="font-size:13.5px; color:#991b1b;">${escapeHtml(item.medicine_name)}</strong>
            <span style="font-size:11px; font-weight:700; color:#ea580c; background:#ffedd5; padding:2px 7px; border-radius:4px;">Missed</span>
          </div>
          <div style="font-size:11.5px; color:#78350f; margin-bottom:6px;">
            Dosage: ${escapeHtml(item.dosage)} · Scheduled for: ${item.scheduled_time_formatted}
          </div>
          <button class="btn-log-missed" onclick="RemindersAlerts.markMissedTaken(${item.dose_id})">
            <i class="fa-solid fa-check-double"></i> I took this dose now
          </button>
        </div>
      `).join('')}
    `;
  }

  // ── 4. Render Today's Appointments ─────────────────────────────
  function renderTodayAppointments(appts) {
    const container = document.getElementById('remTodayApptsList');
    const countPill = document.getElementById('remTodayApptCount');
    if (!container) return;

    if (countPill) {
      countPill.textContent = appts.length;
      countPill.className = appts.length > 0 ? 'rem-count-pill alert' : 'rem-count-pill';
    }

    if (!appts.length) {
      container.innerHTML = `
        <div class="rem-empty-state">
          <i class="fa-regular fa-calendar-xmark"></i>
          <p>No doctor appointments scheduled for today.</p>
        </div>`;
      return;
    }

    container.innerHTML = appts.map(a => `
      <div class="appt-today-banner" id="appt-banner-${a.id}">
        <span class="appt-today-badge">
          <i class="fa-solid fa-bell"></i> Today's Appointment
        </span>
        <div class="appt-today-name">
          <i class="fa-solid fa-user-doctor" style="color:#e0b05a; margin-right:6px;"></i> ${escapeHtml(a.doctor_name)}
        </div>
        <div class="appt-today-details">
          <div><i class="fa-solid fa-stethoscope"></i> ${escapeHtml(a.department)} · ${escapeHtml(a.hospital_name || 'Clinic')}</div>
          <div><i class="fa-regular fa-clock"></i> Time: <strong>${a.time_formatted}</strong> (${escapeHtml(a.appointment_type)})</div>
          ${a.location ? `<div><i class="fa-solid fa-location-dot"></i> ${escapeHtml(a.location)}</div>` : ''}
          ${a.consultation_link ? `<div><i class="fa-solid fa-video"></i> <a href="${escapeHtml(a.consultation_link)}" target="_blank" style="color:#e0b05a; font-weight:700;">Join Online Consultation</a></div>` : ''}
        </div>
        <div>
          <span class="appt-countdown-tag" id="appt-timer-${a.id}">
            <i class="fa-solid fa-hourglass-half"></i> ${a.time_remaining}
          </span>
          <span style="font-size:11px; margin-left:8px; opacity:0.85;">Status: <strong>${escapeHtml(a.status)}</strong></span>
        </div>
        <div class="appt-actions-bar">
          <button class="btn-appt-complete" onclick="RemindersAlerts.updateApptStatus(${a.id}, 'completed')">
            <i class="fa-solid fa-circle-check"></i> Mark Completed
          </button>
          <button class="btn-appt-cancel" onclick="RemindersAlerts.updateApptStatus(${a.id}, 'cancelled')">
            <i class="fa-solid fa-ban"></i> Cancel
          </button>
        </div>
      </div>
    `).join('');
  }

  // ── 5. Render Upcoming Appointments ────────────────────────────
  function renderUpcomingAppointments(appts) {
    const container = document.getElementById('remUpcomingApptsList');
    if (!container) return;

    if (!appts.length) {
      container.innerHTML = `
        <div class="rem-empty-state">
          <i class="fa-regular fa-calendar"></i>
          <p>No upcoming appointments booked for future dates.</p>
        </div>`;
      return;
    }

    container.innerHTML = appts.map(a => `
      <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:#fdfbf7; border:1px solid #ebd9b6; border-radius:8px; margin-bottom:8px;">
        <div>
          <strong style="color:#0d1b2a; font-size:13px;">${escapeHtml(a.doctor_name)}</strong>
          <div style="font-size:11.5px; color:#6b7690;">${escapeHtml(a.department)} · ${escapeHtml(a.hospital_name || 'Clinic')}</div>
          <div style="font-size:11px; color:#854d0e; margin-top:2px;">
            <i class="fa-regular fa-calendar"></i> ${a.date_formatted} at ${a.time_formatted}
          </div>
        </div>
        <span style="font-size:11px; font-weight:700; color:#1a2540; background:#f0f2f6; padding:4px 8px; border-radius:12px;">
          ${a.time_remaining}
        </span>
      </div>
    `).join('');
  }

    // ── 5b. Render Blood Tests & Patient Reminders ───────────────
  function renderBloodTestsAndReminders(premData) {
    const container = document.getElementById('remBloodTestsList');
    if (!container) return;

    const tests = premData.blood_tests || [];
    const upcoming = premData.upcoming || [];
    const items = tests.length > 0 ? tests : upcoming;

    if (!items.length) {
      container.innerHTML = `
        <div class="rem-empty-state">
          <i class="fa-solid fa-vial" style="color:#f87171;"></i>
          <p>No upcoming blood tests or health reminders scheduled.</p>
        </div>`;
      return;
    }

    container.innerHTML = items.slice(0, 4).map(item => `
      <div style="display:flex; justify-content:space-between; align-items:center; padding:9px 12px; background:#fffbfb; border:1px solid #fed7d7; border-radius:8px; margin-bottom:7px;">
        <div>
          <div style="display:flex; align-items:center; gap:6px;">
            <i class="fa-solid ${item.reminder_type === 'Blood Test' ? 'fa-vial' : 'fa-bell'}" style="color:${item.reminder_type === 'Blood Test' ? '#dc2626' : '#c9973a'}; font-size:12px;"></i>
            <strong style="color:#0f172a; font-size:12.5px;">${escapeHtml(item.title)}</strong>
          </div>
          <div style="font-size:11px; color:#78716c; margin-top:2px;">
            ${item.date_formatted} at ${item.time_formatted} · <span style="text-transform:capitalize; font-weight:600;">${escapeHtml(item.reminder_status)}</span>
          </div>
        </div>
        <button class="btn-rem-action" style="font-size:11px; padding:3px 8px;" onclick="window.location.href='remainder.html'">View</button>
      </div>
    `).join('');
  }

  // ── 6. Render Notification History ─────────────────────────────
  function renderNotificationHistory(notifs) {
    const container = document.getElementById('remNotifHistoryList');
    if (!container) return;

    if (!notifs.length) {
      container.innerHTML = `
        <div class="rem-empty-state">
          <i class="fa-regular fa-bell-slash"></i>
          <p>No past notifications recorded yet.</p>
        </div>`;
      return;
    }

    container.innerHTML = notifs.map(n => {
      const type = n.notification_type || 'system';
      const icon = type === 'medicine' ? 'fa-pills' : (type === 'appointment' ? 'fa-user-doctor' : 'fa-bell');
      return `
        <div class="notif-item">
          <div class="notif-item-icon ${type}">
            <i class="fa-solid ${icon}"></i>
          </div>
          <div class="notif-item-content">
            <div class="notif-item-title">${escapeHtml(n.title)}</div>
            <div style="color:#475569; font-size:11.5px; line-height:1.3;">${escapeHtml(n.message)}</div>
            <div class="notif-item-time"><i class="fa-regular fa-clock"></i> ${n.time_ago} · Status: <strong>${escapeHtml(n.status)}</strong></div>
          </div>
        </div>
      `;
    }).join('');
  }

  // ── 7. Render In-App Notification Dropdown ──────────────────────
  function renderNotificationDropdown(data) {
    const listEl = document.getElementById('notifDropdownBody');
    if (!listEl) return;

    const due = data.medicine_due_now || [];
    const appts = data.today_appointments || [];
    const missed = data.missed_medicines || [];
    const prem = data.patient_reminders || {};
    const bloodTests = prem.blood_tests || [];
    const patientUpcoming = prem.upcoming || [];

    if (!due.length && !appts.length && !missed.length && !bloodTests.length && !patientUpcoming.length) {
      listEl.innerHTML = `
        <div style="padding:20px; text-align:center; color:#6b7690; font-size:12px;">
          <i class="fa-solid fa-circle-check" style="color:#10b981; font-size:24px; margin-bottom:6px; display:block;"></i>
          All reminders are up to date!
        </div>`;
      return;
    }

    let html = '';

    // Due medicines
    due.forEach(m => {
      html += `
        <div style="padding:10px; background:#ffffff; border-left:3px solid #c9973a; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <div style="display:flex; justify-content:space-between; font-weight:700; font-size:12.5px; color:#1a2540;">
            <span>${escapeHtml(m.medicine_name)} (${escapeHtml(m.dosage)})</span>
            <span style="color:#c9973a;">${m.scheduled_time_formatted}</span>
          </div>
          <div style="font-size:11px; color:#64748b; margin:4px 0;">${escapeHtml(m.instructions || 'Take as prescribed')}</div>
          <div style="display:flex; gap:6px; margin-top:6px;">
            <button style="flex:1; background:#3d9c47; color:#fff; border:none; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700; cursor:pointer;" onclick="RemindersAlerts.markTaken(${m.dose_id})">Taken</button>
            <button style="flex:1; background:#e2e8f0; color:#334155; border:none; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:600; cursor:pointer;" onclick="RemindersAlerts.snooze(${m.dose_id})">Snooze</button>
          </div>
        </div>
      `;
    });

    // Today appts
    appts.forEach(a => {
      html += `
        <div style="padding:10px; background:#ffffff; border-left:3px solid #3b82f6; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <div style="font-weight:700; font-size:12.5px; color:#1e3a8a;">Appt Today: ${escapeHtml(a.doctor_name)}</div>
          <div style="font-size:11px; color:#475569;">${a.time_formatted} · ${escapeHtml(a.department)}</div>
        </div>
      `;
    });

    // Blood tests
    bloodTests.forEach(bt => {
      html += `
        <div style="padding:10px; background:#fff5f5; border-left:3px solid #dc2626; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <div style="display:flex; justify-content:space-between; font-weight:700; font-size:12.5px; color:#991b1b;">
            <span><i class="fa-solid fa-vial"></i> ${escapeHtml(bt.title)}</span>
            <span style="font-size:11px; color:#dc2626;">${bt.time_formatted}</span>
          </div>
          <div style="font-size:11px; color:#7f1d1d; margin:3px 0;">${bt.date_formatted} · Status: <strong>${escapeHtml(bt.reminder_status)}</strong></div>
          <button style="width:100%; margin-top:4px; background:#dc2626; color:#fff; border:none; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:600; cursor:pointer;" onclick="window.location.href='remainder.html'">Manage Blood Test</button>
        </div>
      `;
    });

    // Missed
    missed.forEach(m => {
      html += `
        <div style="padding:10px; background:#fff7ed; border-left:3px solid #ea580c; border-radius:6px;">
          <div style="font-weight:700; font-size:12px; color:#9a3412;">Missed: ${escapeHtml(m.medicine_name)}</div>
          <div style="font-size:10.5px; color:#7c2d12;">${m.scheduled_time_formatted} · Do not double dose</div>
        </div>
      `;
    });

    listEl.innerHTML = html;
  }

  // ── Actions ────────────────────────────────────────────────────

  async function markTaken(doseId) {
    try {
      const data = await apiPost('mark_taken', { dose_id: doseId });
      if (data.success) {
        showToast(data.message || 'Dose recorded as taken!', 'success');
        refreshOverview();
      }
    } catch (e) {
      showToast('Could not record dose taken.', 'error');
    }
  }

  async function snooze(doseId) {
    try {
      const data = await apiPost('snooze_dose', { dose_id: doseId, minutes: 15 });
      if (data.success) {
        showToast(data.message || 'Reminder snoozed for 15 minutes.', 'info');
        refreshOverview();
      }
    } catch (e) {
      showToast('Could not snooze reminder.', 'error');
    }
  }

  async function markMissedTaken(doseId) {
    try {
      const data = await apiPost('mark_missed_taken', { dose_id: doseId });
      if (data.success) {
        showToast(data.message || 'Missed dose recorded as taken.', 'success');
        refreshOverview();
      }
    } catch (e) {
      showToast('Could not update missed dose.', 'error');
    }
  }

  async function updateApptStatus(apptId, status) {
    if (status === 'cancelled') {
      if (!confirm('Are you sure you want to cancel this appointment? Reminders for cancelled appointments will be stopped.')) {
        return;
      }
    }
    try {
      const data = await apiPost('update_appointment_status', { appointment_id: apptId, status });
      if (data.success) {
        showToast(data.message || `Appointment status set to ${status}.`, 'info');
        refreshOverview();
      }
    } catch (e) {
      showToast('Could not update appointment status.', 'error');
    }
  }

  // ── Browser Notifications ──────────────────────────────────────
  function initBrowserNotificationBanner() {
    const banner = document.getElementById('browserNotificationBanner');
    if (!banner) return;

    if (!('Notification' in window)) {
      banner.style.display = 'none';
      return;
    }

    if (Notification.permission === 'granted') {
      banner.style.display = 'none';
    } else if (Notification.permission === 'denied') {
      banner.style.display = 'block';
      banner.innerHTML = `
        <div style="display:flex; align-items:center; gap:8px;">
          <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;"></i>
          <span>Browser alerts are blocked in your site settings. Enable notifications in your browser URL bar for audible dose alerts.</span>
        </div>`;
    } else {
      banner.style.display = 'flex';
    }
  }

  async function requestBrowserPermission() {
    if (!('Notification' in window)) {
      alert('This browser does not support desktop notifications.');
      return;
    }
    const perm = await Notification.requestPermission();
    if (perm === 'granted') {
      showToast('Browser notifications enabled successfully!', 'success');
      new Notification('AayuVault Healthcare Alerts Active', {
        body: 'You will receive timely alerts for prescribed medicines and doctor appointments.',
        icon: '../assets/logo.png',
      });
      initBrowserNotificationBanner();
    } else {
      showToast('Notification permission was not granted.', 'warning');
      initBrowserNotificationBanner();
    }
  }

  function triggerBrowserAlertsIfSupported(data) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;

    const due = data.medicine_due_now || [];
    due.forEach(m => {
      const tag = `med_due_${m.dose_id}`;
      // Prevent spamming in-memory
      if (sessionStorage.getItem(tag)) return;
      sessionStorage.setItem(tag, '1');

      try {
        new Notification(`Time to Take Your Medicine: ${m.medicine_name}`, {
          body: `Prescribed dose: ${m.dosage}. Scheduled: ${m.scheduled_time_formatted}. ${m.instructions || ''}`,
          icon: '../assets/logo.png',
          tag: tag,
          requireInteraction: true
        });
      } catch (e) {
        console.warn('Browser notification error:', e);
      }
    });

    const appts = data.today_appointments || [];
    appts.forEach(a => {
      const tag = `appt_today_${a.id}`;
      if (sessionStorage.getItem(tag)) return;
      sessionStorage.setItem(tag, '1');

      try {
        new Notification(`Doctor Appointment Today!`, {
          body: `With ${a.doctor_name} (${a.department}) at ${a.time_formatted}.`,
          icon: '../assets/logo.png',
          tag: tag,
          requireInteraction: true
        });
      } catch (e) {
        console.warn('Browser notification error:', e);
      }
    });
  }

  // ── Toast Utility ──────────────────────────────────────────────
  function showToast(msg, type = 'info') {
    let container = document.getElementById('aayuToastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'aayuToastContainer';
      container.style.cssText = `
        position: fixed;
        bottom: 24px;
        left: 24px;
        z-index: 999999;
        display: flex;
        flex-direction: column;
        gap: 8px;
        pointer-events: none;
      `;
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const bg = type === 'success' ? '#10b981' : (type === 'error' ? '#ef4444' : '#c9973a');
    toast.style.cssText = `
      background: ${bg};
      color: #ffffff;
      padding: 10px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      pointer-events: auto;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
      max-width: 380px;
    `;
    toast.innerHTML = `<i class="fa-solid fa-circle-info"></i> <span>${escapeHtml(msg)}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  }

  function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
  }

  // ── Toggle In-App Notification Dropdown ─────────────────────────
  function toggleNotificationDropdown() {
    const dd = document.getElementById('aayuNotifDropdown');
    if (!dd) return;
    dd.classList.toggle('show');
  }

  // ── Init Controller ────────────────────────────────────────────
  function init() {
    refreshOverview();
    initBrowserNotificationBanner();

    // Attach click listener for bell icons
    const bellBtns = document.querySelectorAll('.notif-btn, .nav-notif-btn');
    bellBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        // If not explicitly on remainder.html page, toggle dropdown
        if (!window.location.href.includes('remainder.html') && !window.location.href.includes('reminders.html')) {
          e.preventDefault();
          toggleNotificationDropdown();
        }
      });
    });

    // Close dropdown on click outside
    document.addEventListener('click', (e) => {
      const dd = document.getElementById('aayuNotifDropdown');
      if (dd && dd.classList.contains('show')) {
        if (!dd.contains(e.target) && !e.target.closest('.notif-btn') && !e.target.closest('.nav-notif-btn')) {
          dd.classList.remove('show');
        }
      }
    });

    // Auto-poll every 30 seconds
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(refreshOverview, 30000);
  }

  return {
    init,
    refreshOverview,
    markTaken,
    snooze,
    markMissedTaken,
    updateApptStatus,
    requestBrowserPermission,
    toggleNotificationDropdown,
  };
})();

// Auto-run on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', RemindersAlerts.init);
} else {
  RemindersAlerts.init();
}
