/* ============================================================
   AayuVault – My Profile (View / Read-Only)  |  profile-view.js
   All display values are empty on load.
   Call the populate* helpers below once your API responds.
   ============================================================ */


/* ──────────────────────────────────────────────────────────
   SECTION 1 ▸ DATA POPULATION HELPERS
   Wire these up to your API fetch response.
────────────────────────────────────────────────────────── */

/**
 * Populate Personal Information (read-only display).
 * @param {Object} data
 * @param {string} data.firstName
 * @param {string} data.middleName
 * @param {string} data.lastName
 * @param {string} data.gender
 * @param {string} data.dob          — display string e.g. "04/12/1945"
 * @param {string} data.primaryLanguage
 * @param {string} data.address      — full formatted address string
 */
function populatePersonalInfo(data) {
  const fullName = [data.firstName, data.middleName, data.lastName]
    .filter(Boolean).join(' ');
  setText('fullName',        fullName);
  setText('gender',          data.gender          || '');
  setText('dob',             data.dob             || '');
  setText('primaryLanguage', data.primaryLanguage || '');
  setText('address',         data.address         || '');
}

/**
 * Populate Account Security section.
 * @param {Object} data
 * @param {string}  data.username
 * @param {boolean} data.twoFAEnabled
 */
function populateAccountSecurity(data) {
  setText('username', data.username || '');

  const twoFAEl = document.getElementById('twoFAStatus');
  if (twoFAEl) {
    twoFAEl.textContent = data.twoFAEnabled ? 'Enabled' : 'Disabled';
    twoFAEl.className   = 'info-value ' + (data.twoFAEnabled ? 'twofa-enabled' : 'twofa-disabled');
  }
}

/**
 * Populate Emergency Contact as a bullet list.
 * @param {Object} data
 * @param {string} data.name
 * @param {string} data.relationship
 * @param {string} data.primaryPhone
 * @param {string} data.secondaryPhone
 */
function populateEmergencyContact(data) {
  const list = document.getElementById('ecList');
  if (!list) return;

  const items = [
    { key: 'Name',            value: data.name           },
    { key: 'Relationship',    value: data.relationship   },
    { key: 'Primary Phone',   value: data.primaryPhone   },
    { key: 'Secondary Phone', value: data.secondaryPhone },
  ];

  list.innerHTML = items.map(item => `
    <li>
      <span class="ec-key">- ${esc(item.key)}:</span>
      ${item.value ? ' ' + esc(item.value) : ''}
    </li>
  `).join('');
}

/**
 * Populate the Login History table.
 * @param {Array<{table: string, lastName: string, loginHistory: string}>} rows
 */
function populateLoginHistory(rows) {
  const tbody = document.getElementById('loginHistoryBody');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (!rows || rows.length === 0) {
    tbody.innerHTML = `<tr><td colspan="3" style="text-align:center;color:var(--text-light);padding:14px">No login history found</td></tr>`;
    return;
  }

  rows.forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(row.table)}</td>
      <td>${esc(row.lastName)}</td>
      <td>${esc(row.loginHistory)}</td>
    `;
    tbody.appendChild(tr);
  });
}

/**
 * Populate the Authorized Medical Access table.
 * @param {Array<{name: string, role: string, dob: string, status: 'Active'|'Inactive'}>} rows
 */
function populateAccessTable(rows) {
  const tbody = document.getElementById('accessTableBody');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (!rows || rows.length === 0) {
    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-light);padding:14px">No records found</td></tr>`;
    return;
  }

  rows.forEach(row => {
    const isActive = (row.status || '').toLowerCase() === 'active';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(row.name)}</td>
      <td>${esc(row.role)}</td>
      <td>${esc(row.dob)}</td>
      <td><span class="status-badge ${isActive ? 'active' : 'inactive'}">${esc(row.status)}</span></td>
    `;
    tbody.appendChild(tr);
  });
}

/**
 * Set a profile avatar image URL (optional).
 * @param {string} src
 */
function setAvatarImage(src) {
  const wrap = document.getElementById('avatarWrap');
  if (!wrap || !src) return;
  wrap.innerHTML = `<img src="${esc(src)}" alt="Profile Photo"/>`;
}

/**
 * Show skeleton loaders on all info-value elements while data loads.
 * Call this immediately, then remove skeletons once populate* runs.
 */
function showSkeletons() {
  document.querySelectorAll('.info-value:not(#twoFAStatus)').forEach(el => {
    el.classList.add('skeleton');
    el.dataset.originalText = el.textContent;
    el.textContent = '\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0';
  });
}

function hideSkeletons() {
  document.querySelectorAll('.info-value.skeleton').forEach(el => {
    el.classList.remove('skeleton');
  });
}


/* ──────────────────────────────────────────────────────────
   SECTION 2 ▸ UI INTERACTIONS
────────────────────────────────────────────────────────── */

/** Toggle Login History accordion open/closed. */
function toggleLoginHistory() {
  const panel   = document.getElementById('loginHistoryPanel');
  const chevron = document.getElementById('loginChevron');
  if (!panel) return;

  const isOpen = panel.classList.toggle('open');
  if (chevron) {
    chevron.className = isOpen
      ? 'fa-solid fa-chevron-up'
      : 'fa-solid fa-chevron-down';
  }
}


/* ──────────────────────────────────────────────────────────
   SECTION 3 ▸ UTILITIES
────────────────────────────────────────────────────────── */

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function esc(str) {
  return String(str ?? '')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;');
}

let _toastTimer;
function showToast(message, isWarning = false) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = message;
  toast.style.borderLeftColor = isWarning ? '#e53e3e' : '#c9a227';
  toast.classList.add('show');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => toast.classList.remove('show'), 3200);
}

// Subtle navbar scroll shadow
window.addEventListener('scroll', () => {
  const navbar = document.querySelector('.navbar');
  if (navbar) {
    navbar.style.boxShadow = window.scrollY > 20
      ? '0 4px 24px rgba(0,0,0,0.28)'
      : '0 2px 16px rgba(0,0,0,0.18)';
  }
}, { passive: true });


/* ──────────────────────────────────────────────────────────
   SECTION 4 ▸ INIT
   Replace the block below with your real API fetch.
   The login history accordion starts open by default.
────────────────────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', () => {
  // Show loading skeletons while waiting for data
  showSkeletons();

  // ── Replace this with your real API call ──────────────────
  //
  // ../pages/profile.php
  //   .then(r => r.json())
  //   .then(data => {
  //     hideSkeletons();
  //     populatePersonalInfo(data.personalInfo);
  //     populateAccountSecurity(data.accountSecurity);
  //     populateEmergencyContact(data.emergencyContact);
  //     populateLoginHistory(data.loginHistory);
  //     populateAccessTable(data.authorizedAccess);
  //     if (data.avatarUrl) setAvatarImage(data.avatarUrl);
  //   })
  //   .catch(err => {
  //     hideSkeletons();
  //     showToast('⚠ Failed to load profile data', true);
  //     console.error(err);
  //   });
  //
  // ─────────────────────────────────────────────────────────

  // Remove skeletons after timeout if no API is wired (dev mode)
  setTimeout(hideSkeletons, 1200);
});