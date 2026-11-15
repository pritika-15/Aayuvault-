// ═══════════════════════════════════════════════
//  AayuVault — app.js
//  Connects HTML templates to PHP backend & OpenAI AI Engine
// ═══════════════════════════════════════════════

const API = '../pages/dashboard.php';  // main API file

// ── Fetch helper ─────────────────────────────────
async function api(action, params = {}) {
  try {
    const qs = new URLSearchParams({ action, ...params });
    const r  = await fetch(`${API}?${qs}`);
    return await r.json();
  } catch (e) {
    console.warn('API error:', e);
    return null;
  }
}

// ═══════════════════════════════════════════════
//  DASHBOARD PAGE
// ═══════════════════════════════════════════════
async function loadDashboard() {
  if (!document.getElementById('bp-value') && !document.getElementById('bp')) return;

  // ── Metrics ──────────────────────────────────
  const m = await api('metrics');
  if (m && !m.error) {
    const setIf = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '—'; };
    setIf('bp-value', m.blood_pressure);
    setIf('bp', m.blood_pressure);
    setIf('hr-value', m.heart_rate);
    setIf('hr', m.heart_rate);
    setIf('hr2-value', m.spo2);
    setIf('spo2', m.spo2);
    setIf('labs-value', m.labs_status);
    setIf('labs', m.labs_status);
    setIf('labs2-value', m.labs_count);
    setIf('labs2', m.labs_count);
  }

  // ── Medical Summary ───────────────────────────
  const summary = await api('summary');
  const summaryBody = document.getElementById('summary-body') || document.getElementById('summaryBody');
  if (summaryBody && summary && summary.length) {
    if (summaryBody.tagName === 'TBODY') {
      summaryBody.innerHTML = summary.map(r => `
        <tr>
          <td><strong>${r.condition}</strong></td>
          <td><span class="badge badge-subtle">${r.category}</span></td>
        </tr>`).join('');
    } else {
      summaryBody.innerHTML = summary.map(r => `
        <div class="summary-row" style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(0,0,0,0.06);">
          <span style="font-weight:600;">${r.condition}</span>
          <span style="color:#8B6914;font-size:0.85rem;">${r.category}</span>
        </div>`).join('');
    }
  }

  // ── Health Timeline ───────────────────────────
  const timeline = await api('timeline');
  const tl = document.getElementById('timeline') || document.getElementById('timelineContent');
  if (tl && timeline && timeline.length) {
    tl.innerHTML = timeline.map((t, idx) => `
      <div class="timeline-item" style="display:flex;gap:12px;margin-bottom:14px;align-items:flex-start;cursor:pointer;" onclick="AayuAIReportModal.showFromRecordId(${idx + 1})">
        <div class="timeline-dot" style="width:10px;height:10px;border-radius:50%;background:#c49a2a;margin-top:6px;flex-shrink:0;"></div>
        <div class="timeline-content" style="flex:1;">
          <div style="font-weight:600;color:#0d1b2a;font-size:0.92rem;">${t.title}</div>
          <div style="font-size:0.8rem;color:#6b7280;margin-top:2px;">${t.date} • <span style="color:#c49a2a;"><i class="fa-solid fa-sparkles"></i> View AI Analysis</span></div>
        </div>
      </div>`).join('');
  }

  // ── Appointments ──────────────────────────────
  const appts = await api('appointments');
  const apptList = document.getElementById('appt-list') || document.getElementById('apptContent');
  if (apptList && appts && appts.length) {
    apptList.innerHTML = appts.map(a => `
      <div class="appt-item" style="display:flex;gap:10px;margin-bottom:10px;align-items:center;background:#f8f9fa;padding:10px 12px;border-radius:8px;">
        <div class="appt-icon" style="color:#c49a2a;font-size:18px;"><i class="fa-solid fa-user-doctor"></i></div>
        <div class="appt-info">
          <div style="font-weight:600;font-size:0.88rem;">${a.name}</div>
          <div style="font-size:0.78rem;color:#666;">${a.time}</div>
        </div>
      </div>`).join('');
  }

  // ── Insights Sidebar Box ──────────────────────
  const insightsBox = document.getElementById('insightsContent');
  if (insightsBox) {
    insightsBox.innerHTML = `
      <div style="background:#fefbf2; border:1px solid #e5dbc6; border-radius:10px; padding:12px; margin-bottom:10px;">
        <div style="font-weight:700; color:#8B6914; font-size:0.86rem; margin-bottom:4px;"><i class="fa-solid fa-heart-pulse"></i> Cardiovascular Alert</div>
        <div style="font-size:0.8rem; color:#4b5563; line-height:1.4;">Total Cholesterol is 218 mg/dL with LDL at 138 mg/dL. Soluble dietary fiber & 150m weekly cardio advised.</div>
      </div>
      <div style="background:#fefbf2; border:1px solid #e5dbc6; border-radius:10px; padding:12px;">
        <div style="font-weight:700; color:#8B6914; font-size:0.86rem; margin-bottom:4px;"><i class="fa-solid fa-droplet"></i> Glycemic Advisory</div>
        <div style="font-size:0.8rem; color:#4b5563; line-height:1.4;">HbA1c is 5.9% (Pre-diabetic zone). A 15-minute post-meal brisk walk helps reverse insulin resistance.</div>
      </div>
    `;
  }

  // ── Mini Calendar ─────────────────────────────
  buildMiniCalendar();
}

// ── Mini Calendar builder ─────────────────────
function buildMiniCalendar() {
  const el = document.getElementById('cal-days');
  if (!el) return;
  const now   = new Date();
  const year  = now.getFullYear();
  const month = now.getMonth();
  const days  = new Date(year, month + 1, 0).getDate();
  const first = new Date(year, month, 1).getDay();

  let html = '';
  for (let i = 0; i < first; i++) html += '<span></span>';
  for (let d = 1; d <= days; d++) {
    const isToday = d === now.getDate();
    html += `<span class="${isToday ? 'today' : ''}">${d}</span>`;
  }
  el.innerHTML = html;
}

// ═══════════════════════════════════════════════
//  APPOINTMENT PAGE
// ═══════════════════════════════════════════════
const APPT_API = '../pages/appointment.php';

async function apptApi(action, params = {}) {
  try {
    const qs = new URLSearchParams({ action, ...params });
    const r  = await fetch(`${APPT_API}?${qs}`);
    return await r.json();
  } catch (e) {
    console.warn('Appt API error:', e);
    return null;
  }
}

const apptState = {
  selectedDoctor : null,
  selectedDate   : null,
  selectedTime   : null,
};

async function loadAppointmentPage() {
  if (!document.getElementById('condition-select')) return;
  if (window.APPOINTMENT_MODULE_LOADED) return;

  // Metrics
  const m = await apptApi('get_metrics');
  if (m) {
    setEl('bp-value',        m.blood_pressure);
    setEl('hr-value',        m.heart_rate);
    setEl('hr2-value',       m.blood_sugar);
    setEl('labs-value',      m.recent_labs);
    setEl('recentlabs-value',m.recent_labs);
  }

  // Conditions table
  const condsRes = await apptApi('get_conditions');
  const conds = Array.isArray(condsRes) ? condsRes : (condsRes?.conditions || condsRes?.data || []);
  const condList = document.getElementById('conditions-list');
  if (condList && conds && conds.length) {
    condList.innerHTML = conds.map(c => `
      <div class="table-row">
        <span>${c.name || c.condition_name}</span>
        <span>${c.category || 'Monitored'}</span>
      </div>`).join('');
  }

  // Health context tags
  const tagsRes = await apptApi('get_health_context');
  const tags = Array.isArray(tagsRes) ? tagsRes : (tagsRes?.tags || tagsRes?.data || []);
  const tagEl = document.getElementById('health-context-tags');
  if (tagEl && tags && tags.length) {
    tagEl.innerHTML = tags.map(t => `<span class="context-tag">${typeof t === 'string' ? t : (t.name || t.condition_name)}</span>`).join('');
  }

  // Condition dropdown
  const optsRes = await apptApi('get_condition_options');
  const opts = Array.isArray(optsRes) ? optsRes : (optsRes?.conditions || optsRes?.options || optsRes?.data || []);
  const sel  = document.getElementById('condition-select');
  if (sel && opts && opts.length) {
    sel.innerHTML = '<option value="">— Select condition —</option>' +
      opts.map(o => {
        const val = typeof o === 'string' ? o : (o.name || o.id);
        return `<option value="${val}">${val}</option>`;
      }).join('');
  }

  // Providers
  loadProviders();

  // Date picker
  buildDatePicker();

  // Mini calendar
  buildApptMiniCalendar();

  // Upcoming
  loadUpcoming();
}

async function loadProviders() {
  const search   = document.getElementById('provider-search')?.value || '';
  const filter   = document.getElementById('filter-select')?.value   || '';
  const location = document.getElementById('location-select')?.value || '';

  const docsRes = await apptApi('get_providers', { search, filter, location });
  const docs = Array.isArray(docsRes) ? docsRes : (docsRes?.providers || docsRes?.data || []);
  const el   = document.getElementById('providers-list');
  if (!el) return;

  if (!docs || !docs.length) {
    el.innerHTML = '<p style="padding:1rem;color:#888;">No providers found.</p>';
    return;
  }
  el.innerHTML = docs.map(d => `
    <div class="provider-card" data-id="${d.id}" data-name="${d.name}"
         onclick="selectDoctor(${d.id}, '${d.name.replace(/'/g,"\\'")}', this)">
      <div class="provider-avatar" style="width:40px;height:40px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#8B6914;">
        <i class="fa-solid fa-user-doctor" style="font-size: 20px;"></i>
      </div>
      <div class="provider-info">
        <div class="provider-name">${d.name}</div>
        <div class="provider-spec">${d.specialization || 'General Physician'}</div>
        <div class="provider-meta">⭐ ${d.rating || '4.0'} · ${d.experience_years || 0} yrs</div>
      </div>
    </div>`).join('');
}

function selectDoctor(id, name, el) {
  apptState.selectedDoctor = { id, name };
  document.querySelectorAll('.provider-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  if (apptState.selectedDate) loadSlots();
}

function buildDatePicker() {
  const el    = document.getElementById('calendar-dates');
  if (!el) return;
  const today = new Date();
  el.innerHTML = '';
  for (let i = 0; i < 7; i++) {
    const d   = new Date(today);
    d.setDate(today.getDate() + i);
    const iso = d.toISOString().split('T')[0];
    const btn = document.createElement('button');
    btn.className   = 'date-btn' + (i === 0 ? ' active' : '');
    btn.dataset.iso = iso;
    btn.innerHTML   = `<span>${d.toLocaleDateString('en-US',{weekday:'short'})}</span>
                       <span>${d.getDate()}</span>`;
    btn.onclick = () => {
      document.querySelectorAll('.date-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      apptState.selectedDate = iso;
      if (apptState.selectedDoctor) loadSlots();
    };
    el.appendChild(btn);
  }
  apptState.selectedDate = today.toISOString().split('T')[0];
}

async function loadSlots() {
  if (!apptState.selectedDoctor || !apptState.selectedDate) return;
  const slotsRes = await apptApi('get_slots', {
    doctor_id : apptState.selectedDoctor.id,
    date      : apptState.selectedDate
  });
  const slots = Array.isArray(slotsRes) ? slotsRes : (slotsRes?.data || slotsRes?.slots || []);
  const el = document.getElementById('time-slots');
  if (!el || !slots || !slots.length) return;
  el.innerHTML = slots.map(s => {
    const timeVal = typeof s === 'string' ? s : s.time;
    const isAvail = typeof s === 'string' ? true : (s.available !== false);
    return `
    <button class="slot-btn ${isAvail ? '' : 'unavailable'}"
            ${isAvail ? '' : 'disabled'}
            onclick="selectSlot('${timeVal}', this)">
      ${timeVal}
    </button>`;
  }).join('');
}

function selectSlot(time, btn) {
  apptState.selectedTime = time;
  document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

async function loadUpcoming() {
  const listRes = await apptApi('get_upcoming');
  const list = Array.isArray(listRes) ? listRes : (listRes?.appointments || listRes?.data || []);
  const el   = document.getElementById('upcoming-time');
  if (!el) return;
  if (list && list.length) {
    const n = list[0];
    el.innerHTML = `<strong>${n.doctor_name}</strong><br>${n.appointment_date} at ${n.appointment_time}`;
  } else {
    el.textContent = 'No upcoming appointments';
  }
}

function buildApptMiniCalendar() {
  const el = document.getElementById('mini-calendar');
  if (!el) return;
  const now   = new Date();
  const days  = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
  const first = new Date(now.getFullYear(), now.getMonth(), 1).getDay();
  const monthName = now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

  let html = `<div style="font-weight:600;margin-bottom:6px;">${monthName}</div>
              <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;font-size:11px;">`;
  ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(d => { html += `<span style="text-align:center;color:#888;">${d}</span>`; });
  for (let i = 0; i < first; i++) html += '<span></span>';
  for (let d = 1; d <= days; d++) {
    const isToday = d === now.getDate();
    html += `<span style="text-align:center;padding:2px;border-radius:50%;${isToday ? 'background:#c8a84b;color:#fff;font-weight:600;' : ''}">${d}</span>`;
  }
  html += '</div>';
  el.innerHTML = html;
}

// Book appointment
document.addEventListener('click', async (e) => {
  if (e.target.id === 'btn-schedule' || e.target.closest('#btn-schedule')) {
    const condition = document.getElementById('condition-select')?.value;
    if (!apptState.selectedDoctor) { alert('Please select a doctor.'); return; }
    if (!apptState.selectedDate)   { alert('Please select a date.'); return; }
    if (!apptState.selectedTime)   { alert('Please select a time slot.'); return; }
    if (!condition)                { alert('Please select a condition.'); return; }

    const body = new FormData();
    body.append('book',      '1');
    body.append('doctor_id', apptState.selectedDoctor.id);
    body.append('date',      apptState.selectedDate);
    body.append('time',      apptState.selectedTime);
    body.append('condition', condition);

    const res  = await fetch(APPT_API, { method: 'POST', body });
    const data = await res.json();
    alert(data.message);
    if (data.success) loadUpcoming();
  }

  if (e.target.id === 'btn-cancel') {
    apptState.selectedDoctor = apptState.selectedDate = apptState.selectedTime = null;
    document.querySelectorAll('.provider-card,.date-btn,.slot-btn').forEach(c => c.classList.remove('selected','active'));
    document.getElementById('time-slots').innerHTML = '';
  }
});

// Provider search listeners
['provider-search','filter-select','location-select'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', loadProviders);
});

// ═══════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════
function setEl(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val || '—';
}

function formatMarkdown(text) {
  if (!text) return '';
  let html = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

  // Bold **text**
  html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  // Italic *text*
  html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
  // Headers ###
  html = html.replace(/^### (.*?)$/gm, '<h4 style="margin:8px 0 4px;color:#1b263b;">$1</h4>');
  html = html.replace(/^## (.*?)$/gm, '<h3 style="margin:10px 0 6px;color:#1b263b;">$1</h3>');
  // Bullet points
  html = html.replace(/^[•\-\*] (.*?)$/gm, '<div style="margin-left:10px;margin-bottom:4px;"><i class="fa-solid fa-circle" style="font-size:5px;vertical-align:middle;color:#C9A84C;margin-right:6px;"></i>$1</div>');
  // Newlines to <br>
  html = html.replace(/\n\n/g, '<div style="height:8px;"></div>').replace(/\n/g, '<br/>');

  return html;
}

// ═══════════════════════════════════════════════
//  AUTH PAGE — Login form
// ═══════════════════════════════════════════════
function initAuthPage() {
  const form = document.getElementById('loginForm');
  if (!form) return;

  form.action = '../pages/auth.php';

  const togglePw = document.getElementById('togglePw');
  const pwInput  = document.getElementById('password');

  if (togglePw && pwInput) {
    togglePw.addEventListener('click', () => {
      pwInput.type = pwInput.type === 'password' ? 'text' : 'password';
    });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn     = document.getElementById('submitBtn');
    const spinner = document.getElementById('spinner');
    const msgEl   = document.getElementById('serverMsg');

    btn.disabled = true;
    if (spinner) spinner.hidden = false;

    const body = new FormData(form);
    try {
      const res  = await fetch('../pages/auth.php', { method: 'POST', body });
      const data = await res.json();
      if (msgEl) {
        msgEl.textContent = data.message;
        msgEl.style.color = data.success ? 'green' : 'red';
      }
      if (data.success && data.redirect) {
        window.location.href = data.redirect;
      }
    } catch (err) {
      if (msgEl) msgEl.textContent = 'Connection error. Please try again.';
    } finally {
      btn.disabled = false;
      if (spinner) spinner.hidden = true;
    }
  });
}

// ═══════════════════════════════════════════════
//  AI CONFIGURATION MODAL (OPENAI SETTINGS)
// ═══════════════════════════════════════════════
window.openAISettingsModal = async function() {
  let modal = document.getElementById('aayuAISettingsModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'aayuAISettingsModal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(13,27,42,0.7);z-index:999999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);';
    modal.innerHTML = `
      <div style="background:#ffffff;border-radius:14px;width:90%;max-width:500px;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.3);border:1px solid #e5dbc6;">
        <div style="background:linear-gradient(135deg,#0d1b2a,#1b263b);color:#ffffff;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
          <div style="display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-brain" style="color:#C9A84C;font-size:20px;"></i>
            <h3 style="margin:0;font-size:1.1rem;font-weight:700;">OpenAI API Settings</h3>
          </div>
          <button onclick="document.getElementById('aayuAISettingsModal').style.display='none'" style="background:none;border:none;color:#ffffff;font-size:20px;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:20px;">
          <p style="font-size:0.86rem;color:#4b5563;margin-top:0;line-height:1.5;">
            Configure your OpenAI API key to power real-time clinical report parsing, longitudinal data forecasting, and interactive AI health coaching.
          </p>
          <div style="margin-bottom:14px;">
            <label style="display:block;font-size:0.82rem;font-weight:700;color:#1b263b;margin-bottom:6px;">OpenAI API Key (sk-...)</label>
            <input type="password" id="aiModalApiKey" placeholder="sk-proj-..." style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:0.88rem;box-sizing:border-box;" />
          </div>
          <div style="margin-bottom:14px;">
            <label style="display:block;font-size:0.82rem;font-weight:700;color:#1b263b;margin-bottom:6px;">OpenAI Model</label>
            <select id="aiModalModel" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:0.88rem;box-sizing:border-box;">
              <option value="gpt-4o-mini">gpt-4o-mini (Recommended — Fast, Smart & Cost-Efficient)</option>
              <option value="gpt-4o">gpt-4o (High-Precision Clinical Reasoning)</option>
              <option value="gpt-3.5-turbo">gpt-3.5-turbo (Legacy Standard)</option>
            </select>
          </div>
          <div id="aiModalStatus" style="display:none;padding:10px 12px;border-radius:8px;font-size:0.82rem;margin-bottom:14px;"></div>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <button onclick="testAISettings()" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;padding:9px 15px;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">
              <i class="fa-solid fa-plug"></i> Test Connection
            </button>
            <button onclick="saveAISettings()" style="background:#C9A84C;border:none;color:#ffffff;padding:9px 18px;border-radius:8px;font-size:0.85rem;font-weight:700;cursor:pointer;">
              <i class="fa-solid fa-save"></i> Save Settings
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  } else {
    modal.style.display = 'flex';
  }

  // Fetch current status
  try {
    const res = await fetch('../pages/ai_settings.php?action=status');
    const data = await res.json();
    if (data.configured) {
      document.getElementById('aiModalApiKey').placeholder = data.masked_key;
      document.getElementById('aiModalModel').value = data.model || 'gpt-4o-mini';
    }
  } catch(e){}
};

window.testAISettings = async function() {
  const key = document.getElementById('aiModalApiKey').value.trim();
  const statusEl = document.getElementById('aiModalStatus');
  statusEl.style.display = 'block';
  statusEl.style.background = '#eff6ff';
  statusEl.style.color = '#1e40af';
  statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing connection to OpenAI...';

  try {
    const res = await fetch('../pages/ai_settings.php?action=test_connection', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ api_key: key })
    });
    const data = await res.json();
    if (data.success) {
      statusEl.style.background = '#ecfdf5';
      statusEl.style.color = '#065f46';
      statusEl.innerHTML = `<i class="fa-solid fa-circle-check"></i> ${data.message}`;
    } else {
      statusEl.style.background = '#fef2f2';
      statusEl.style.color = '#991b1b';
      statusEl.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> ${data.message || 'Connection failed'}`;
    }
  } catch(e) {
    statusEl.style.background = '#fef2f2';
    statusEl.style.color = '#991b1b';
    statusEl.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Error contacting server.';
  }
};

window.saveAISettings = async function() {
  const key = document.getElementById('aiModalApiKey').value.trim();
  const model = document.getElementById('aiModalModel').value;
  const statusEl = document.getElementById('aiModalStatus');
  statusEl.style.display = 'block';
  statusEl.style.background = '#eff6ff';
  statusEl.style.color = '#1e40af';
  statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving settings...';

  try {
    const res = await fetch('../pages/ai_settings.php?action=save_settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ api_key: key, model: model })
    });
    const data = await res.json();
    if (data.success) {
      statusEl.style.background = '#ecfdf5';
      statusEl.style.color = '#065f46';
      statusEl.innerHTML = `<i class="fa-solid fa-circle-check"></i> ${data.message}`;
      setTimeout(() => {
        document.getElementById('aayuAISettingsModal').style.display = 'none';
      }, 1500);
    }
  } catch(e) {
    statusEl.style.background = '#fef2f2';
    statusEl.style.color = '#991b1b';
    statusEl.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Failed to save settings.';
  }
};

// ═══════════════════════════════════════════════
//  OPENAI AI HEALTH ASSISTANT WIDGET
// ═══════════════════════════════════════════════
function initChatbot() {
  if (document.getElementById('aayuAssistLauncher')) return;

  const chatHistory = [];

  // Inject FontAwesome if missing
  if (!document.querySelector('link[href*="font-awesome"]')) {
    const faLink = document.createElement('link');
    faLink.rel = 'stylesheet';
    faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css';
    document.head.appendChild(faLink);
  }

  // Inject Widget CSS
  const style = document.createElement('style');
  style.innerHTML = `
    .aayu-bubble {
      position: fixed;
      bottom: 24px;
      right: 24px;
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, #C9A84C, #8B6914);
      border-radius: 50%;
      box-shadow: 0 8px 25px rgba(139, 105, 20, 0.4);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 99999;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .aayu-bubble:hover { transform: scale(1.08) translateY(-2px); box-shadow: 0 12px 30px rgba(139, 105, 20, 0.5); }
    .aayu-bubble i { color: #ffffff; font-size: 26px; }
    
    .aayu-panel {
      position: fixed;
      bottom: 96px;
      right: 24px;
      width: 390px;
      max-width: calc(100vw - 32px);
      height: 540px;
      max-height: calc(100vh - 120px);
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 15px 40px rgba(13, 27, 42, 0.25);
      display: none;
      flex-direction: column;
      z-index: 99999;
      overflow: hidden;
      border: 1px solid rgba(197, 167, 76, 0.3);
      font-family: 'DM Sans', system-ui, sans-serif;
    }
    .aayu-panel.open { display: flex; animation: aayuFadeUp 0.25s ease-out; }
    @keyframes aayuFadeUp {
      from { opacity: 0; transform: translateY(15px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .aayu-header {
      background: linear-gradient(135deg, #0d1b2a, #1b263b);
      color: #ffffff;
      padding: 14px 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 2px solid #C9A84C;
    }
    .aayu-header-title { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 0.95rem; }
    .aayu-header-sub { font-size: 0.72rem; color: #C9A84C; font-weight: 500; }
    .aayu-header-actions { display: flex; align-items: center; gap: 12px; }
    .aayu-header-actions i { cursor: pointer; opacity: 0.85; transition: opacity 0.2s; }
    .aayu-header-actions i:hover { opacity: 1; }
    
    .aayu-body {
      flex: 1;
      padding: 16px;
      overflow-y: auto;
      background: #fdfbf7;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    
    .aayu-msg {
      max-width: 86%;
      padding: 10px 14px;
      border-radius: 14px;
      font-size: 0.88rem;
      line-height: 1.5;
      word-wrap: break-word;
    }
    .aayu-msg.bot {
      background: #ffffff;
      color: #1b263b;
      align-self: flex-start;
      border-bottom-left-radius: 4px;
      border: 1px solid #ebd9b4;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .aayu-msg.user {
      background: linear-gradient(135deg, #C9A84C, #8B6914);
      color: #ffffff;
      align-self: flex-end;
      border-bottom-right-radius: 4px;
      box-shadow: 0 2px 8px rgba(139, 105, 20, 0.2);
    }
    
    .aayu-quick-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      padding: 8px 16px;
      background: #fdfbf7;
      border-top: 1px solid rgba(0,0,0,0.05);
    }
    .aayu-chip {
      background: #ffffff;
      border: 1px solid #ebd9b4;
      color: #8B6914;
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 0.76rem;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.2s;
      white-space: nowrap;
    }
    .aayu-chip:hover { background: #C9A84C; color: #ffffff; border-color: #C9A84C; }
    
    .aayu-input-area {
      display: flex;
      border-top: 1px solid #e5dbc6;
      padding: 10px 14px;
      background: #ffffff;
      align-items: center;
      gap: 8px;
    }
    .aayu-input-area input {
      flex: 1;
      border: 1px solid #d1d5db;
      padding: 10px 14px;
      border-radius: 22px;
      outline: none;
      font-size: 0.88rem;
      font-family: inherit;
      transition: border-color 0.2s;
    }
    .aayu-input-area input:focus { border-color: #C9A84C; }
    .aayu-input-area button {
      background: linear-gradient(135deg, #C9A84C, #8B6914);
      color: #ffffff;
      border: none;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: transform 0.2s;
    }
    .aayu-input-area button:hover { transform: scale(1.05); }
  `;
  document.head.appendChild(style);

  // Inject UI DOM
  const assistDOM = document.createElement('div');
  assistDOM.innerHTML = `
    <div class="aayu-bubble" id="aayuAssistLauncher" title="Open AI Health Assistant">
      <i class="fa-solid fa-stethoscope"></i>
    </div>
    <div class="aayu-panel" id="aayuAssistWindow">
      <div class="aayu-header">
        <div class="aayu-header-title">
          <i class="fa-solid fa-brain" style="color:#C9A84C;"></i>
          <div>
            <div>AayuVault AI Assistant</div>
            <div class="aayu-header-sub">Powered by OpenAI Clinical Engine</div>
          </div>
        </div>
        <div class="aayu-header-actions">
          <i class="fa-solid fa-xmark" id="aayuAssistClose" title="Close Assistant"></i>
        </div>
      </div>
      <div class="aayu-body" id="aayuAssistBody">
        <div class="aayu-msg bot">
          Hello! I am your <strong>AayuVault AI Health Assistant</strong>. I have secure access to your clinical lab records, vitals, and appointments.<br/><br/>
          How can I help you analyze your health data today?
        </div>
      </div>
      <div class="aayu-quick-chips">
        <button class="aayu-chip" onclick="aayuSendQuick('How is my cholesterol and lipid panel?')">🫀 Cholesterol Analysis</button>
        <button class="aayu-chip" onclick="aayuSendQuick('Explain my HbA1c and blood sugar')">🩸 Glycemic & HbA1c</button>
        <button class="aayu-chip" onclick="aayuSendQuick('What diet is recommended for my test results?')">🥗 Diet Guidance</button>
        <button class="aayu-chip" onclick="aayuSendQuick('Summarize all my recent lab reports')">📋 Health Summary</button>
      </div>
      <div class="aayu-input-area">
        <input type="text" id="aayuAssistInput" placeholder="Ask about your health data..." />
        <button id="aayuAssistSend" title="Send Question"><i class="fa-solid fa-paper-plane"></i></button>
      </div>
    </div>
  `;
  document.body.appendChild(assistDOM);

  // Widget Logic
  const launcher = document.getElementById('aayuAssistLauncher');
  const win = document.getElementById('aayuAssistWindow');
  const closeBtn = document.getElementById('aayuAssistClose');
  const sendBtn = document.getElementById('aayuAssistSend');
  const inputEl = document.getElementById('aayuAssistInput');
  const bodyEl = document.getElementById('aayuAssistBody');

  launcher.addEventListener('click', () => { win.classList.add('open'); launcher.style.display = 'none'; inputEl.focus(); });
  closeBtn.addEventListener('click', () => { win.classList.remove('open'); launcher.style.display = 'flex'; });

  window.aayuSendQuick = function(promptText) {
    inputEl.value = promptText;
    sendToAssistant();
  };

  async function sendToAssistant() {
    const text = inputEl.value.trim();
    if (!text) return;

    bodyEl.innerHTML += `<div class="aayu-msg user">${text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>`;
    chatHistory.push({ role: 'user', content: text });
    inputEl.value = '';
    bodyEl.scrollTop = bodyEl.scrollHeight;

    const waitId = 'wait_' + Date.now();
    bodyEl.innerHTML += `<div class="aayu-msg bot" id="${waitId}"><i class="fa-solid fa-spinner fa-spin" style="color:#C9A84C;"></i> Analyzing with OpenAI...</div>`;
    bodyEl.scrollTop = bodyEl.scrollHeight;

    try {
      const response = await fetch('../pages/openai_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, history: chatHistory })
      });
      const data = await response.json();

      const waitEl = document.getElementById(waitId);
      if (waitEl) waitEl.remove();

      if (data && data.reply) {
        bodyEl.innerHTML += `<div class="aayu-msg bot">${formatMarkdown(data.reply)}</div>`;
        chatHistory.push({ role: 'assistant', content: data.reply });
      } else {
        bodyEl.innerHTML += `<div class="aayu-msg bot">I apologize, I could not process your request at this moment.</div>`;
      }
    } catch (error) {
      const waitEl = document.getElementById(waitId);
      if (waitEl) waitEl.remove();

      // Offline friendly fallback
      bodyEl.innerHTML += `<div class="aayu-msg bot">
        📊 <strong>AayuVault Health Intelligence:</strong><br/>
        Based on your profile, your cholesterol (218 mg/dL) and HbA1c (5.9%) are active watchlist parameters. Aerobic exercise and soluble fiber are recommended.
      </div>`;
    }
    bodyEl.scrollTop = bodyEl.scrollHeight;
  }

  sendBtn.addEventListener('click', sendToAssistant);
  inputEl.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendToAssistant(); });
}

// ═══════════════════════════════════════════════
//  INIT ALL PAGES
// ═══════════════════════════════════════════════
function initAllPages() {
  loadDashboard();
  loadAppointmentPage();
  initAuthPage();
  initChatbot();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAllPages);
} else {
  initAllPages();
}