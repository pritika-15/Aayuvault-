/**
 * ============================================================
 * AayuVault — Admin Directory Manager (admin_directory.js)
 * Doctor & Medical Facility Management Logic
 * ============================================================
 */

const API = '../pages/directory_api.php';

const state = {
  currentTab: 'doctors', // 'doctors' or 'facilities'
  specializations: [],
  facilityTypes: [],
  facilitiesList: [],
  doctors: [],
  facilities: [],
  stats: {},
};

// ── UTILITIES ───────────────────────────────────────────────
function qs(selector, parent = document) { return parent.querySelector(selector); }
function qsa(selector, parent = document) { return [...parent.querySelectorAll(selector)]; }

function showToast(message, type = 'success') {
  let toast = qs('#adminToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'adminToast';
    toast.className = 'toast-msg';
    document.body.appendChild(toast);
  }
  toast.className = `toast-msg show ${type}`;
  toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i> <span>${message}</span>`;
  setTimeout(() => {
    toast.classList.remove('show');
  }, 3500);
}

async function apiCall(action, params = {}, method = 'GET', body = null) {
  try {
    let url = `${API}?action=${encodeURIComponent(action)}`;
    let options = { method };

    if (method === 'GET') {
      const searchParams = new URLSearchParams(params);
      const qsStr = searchParams.toString();
      if (qsStr) url += `&${qsStr}`;
    } else if (method === 'POST') {
      if (body instanceof FormData) {
        options.body = body;
      } else {
        const formData = new FormData();
        Object.entries(params).forEach(([k, v]) => {
          if (v !== null && v !== undefined) formData.append(k, v);
        });
        options.body = formData;
      }
    }

    const res = await fetch(url, options);
    return await res.json();
  } catch (err) {
    console.error(`API Error [${action}]:`, err);
    return { success: false, message: 'Network or server error.' };
  }
}

// ── INITIALIZATION ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  initTabs();
  initSearchAndFilters();
  initModals();
  await loadFilterMeta();
  await loadStats();
  await refreshCurrentView();
});

// ── TABS ────────────────────────────────────────────────────
function initTabs() {
  qsa('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      qsa('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.currentTab = btn.dataset.tab;

      if (state.currentTab === 'doctors') {
        qs('#doctorsSection').style.display = 'block';
        qs('#facilitiesSection').style.display = 'none';
        loadDoctors();
      } else {
        qs('#doctorsSection').style.display = 'none';
        qs('#facilitiesSection').style.display = 'block';
        loadFacilities();
      }
    });
  });
}

// ── FILTER METADATA & STATS ─────────────────────────────────
async function loadFilterMeta() {
  const data = await apiCall('get_filter_meta');
  if (data && data.success) {
    state.specializations = data.specializations || [];
    state.facilityTypes = data.facility_types || [];
    state.facilitiesList = data.facilities || [];

    // Populate Doctor specialization filter
    const docSpecSel = qs('#filterDocSpecialization');
    if (docSpecSel) {
      docSpecSel.innerHTML = '<option value="">All Specializations</option>' +
        state.specializations.map(s => `<option value="${s}">${s}</option>`).join('');
    }

    // Populate Doctor facility filter
    const docFacSel = qs('#filterDocFacility');
    if (docFacSel) {
      docFacSel.innerHTML = '<option value="">All Facilities</option>' +
        state.facilitiesList.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
    }

    // Populate Doctor modal facility select
    const modalDocFacSel = qs('#docFacilityId');
    if (modalDocFacSel) {
      modalDocFacSel.innerHTML = '<option value="">Select Associated Facility</option>' +
        state.facilitiesList.map(f => `<option value="${f.id}">${f.name} (${f.city})</option>`).join('');
    }

    // Populate Doctor modal specialization select
    const modalDocSpecSel = qs('#docSpecialization');
    if (modalDocSpecSel) {
      modalDocSpecSel.innerHTML = '<option value="">Select Specialization</option>' +
        state.specializations.map(s => `<option value="${s}">${s}</option>`).join('');
    }

    // Populate Facility type filter
    const facTypeSel = qs('#filterFacType');
    if (facTypeSel) {
      facTypeSel.innerHTML = '<option value="">All Facility Types</option>' +
        state.facilityTypes.map(t => `<option value="${t}">${t}</option>`).join('');
    }

    // Populate Facility modal type select
    const modalFacTypeSel = qs('#facType');
    if (modalFacTypeSel) {
      modalFacTypeSel.innerHTML = state.facilityTypes.map(t => `<option value="${t}">${t}</option>`).join('');
    }
  }
}

async function loadStats() {
  const data = await apiCall('get_stats');
  if (data && data.success && data.stats) {
    state.stats = data.stats;
    if (qs('#statTotalDocs')) qs('#statTotalDocs').textContent = data.stats.total_doctors ?? 0;
    if (qs('#statVerifiedDocs')) qs('#statVerifiedDocs').textContent = data.stats.verified_doctors ?? 0;
    if (qs('#statTotalFacs')) qs('#statTotalFacs').textContent = data.stats.total_facilities ?? 0;
    if (qs('#statVerifiedFacs')) qs('#statVerifiedFacs').textContent = data.stats.verified_facilities ?? 0;
    
    if (qs('#badgeDocCount')) qs('#badgeDocCount').textContent = data.stats.total_doctors ?? 0;
    if (qs('#badgeFacCount')) qs('#badgeFacCount').textContent = data.stats.total_facilities ?? 0;
  }
}

async function refreshCurrentView() {
  if (state.currentTab === 'doctors') {
    await loadDoctors();
  } else {
    await loadFacilities();
  }
}

// ── SEARCH & FILTER EVENTS ──────────────────────────────────
function initSearchAndFilters() {
  let docDebounce;
  qs('#searchDocs')?.addEventListener('input', () => {
    clearTimeout(docDebounce);
    docDebounce = setTimeout(loadDoctors, 300);
  });
  qs('#filterDocSpecialization')?.addEventListener('change', loadDoctors);
  qs('#filterDocFacility')?.addEventListener('change', loadDoctors);
  qs('#filterDocStatus')?.addEventListener('change', loadDoctors);

  let facDebounce;
  qs('#searchFacs')?.addEventListener('input', () => {
    clearTimeout(facDebounce);
    facDebounce = setTimeout(loadFacilities, 300);
  });
  qs('#filterFacType')?.addEventListener('change', loadFacilities);
  qs('#filterFacLocation')?.addEventListener('input', () => {
    clearTimeout(facDebounce);
    facDebounce = setTimeout(loadFacilities, 350);
  });
  qs('#filterFacStatus')?.addEventListener('change', loadFacilities);
}

// ── DOCTORS MANAGEMENT ──────────────────────────────────────
async function loadDoctors() {
  const search = qs('#searchDocs')?.value.trim() || '';
  const specialization = qs('#filterDocSpecialization')?.value || '';
  const facility_id = qs('#filterDocFacility')?.value || '';
  const status = qs('#filterDocStatus')?.value || '';

  const tableBody = qs('#doctorsTableBody');
  if (tableBody) {
    tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--txt-sec);"><i class="fa-solid fa-spinner fa-spin"></i> Loading doctors directory...</td></tr>`;
  }

  const data = await apiCall('get_doctors', { search, specialization, facility_id, status });
  if (data && data.success) {
    state.doctors = data.doctors || [];
    renderDoctorsTable(state.doctors);
  } else {
    if (tableBody) tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--red);">Failed to load doctors.</td></tr>`;
  }
}

function renderDoctorsTable(doctors) {
  const tableBody = qs('#doctorsTableBody');
  if (!tableBody) return;

  if (doctors.length === 0) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="empty-state">
            <i class="fa-solid fa-user-doctor"></i>
            <h4>No doctors found</h4>
            <p>Try refining your search terms or filter selection.</p>
          </div>
        </td>
      </tr>`;
    return;
  }

  tableBody.innerHTML = doctors.map(doc => {
    const isAct = Boolean(Number(doc.is_active));
    const statusClass = (doc.verification_status || 'verified').toLowerCase();
    const feeText = doc.consultation_fee ? `₹${parseFloat(doc.consultation_fee).toLocaleString('en-IN')}` : '—';
    const facName = doc.facility_name ? htmlEscape(doc.facility_name) : '<span style="color:var(--txt-mute);">Independent</span>';

    return `
      <tr>
        <td>
          <div style="font-weight:700; color:var(--navy); font-size:12px;">${htmlEscape(doc.doctor_code || `DOC-${doc.id}`)}</div>
        </td>
        <td>
          <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:34px; height:34px; border-radius:8px; background:var(--blue-bg); color:var(--blue); display:grid; place-items:center; font-size:14px; font-weight:700;">
              <i class="fa-solid fa-user-doctor"></i>
            </div>
            <div>
              <div style="font-weight:700; color:var(--navy);">${htmlEscape(doc.name)}</div>
              <div style="font-size:12px; color:var(--txt-sec);">${htmlEscape(doc.qualification || '')}</div>
            </div>
          </div>
        </td>
        <td>
          <span style="font-weight:600; color:var(--navy);">${htmlEscape(doc.specialization)}</span>
          <div style="font-size:11.5px; color:var(--txt-sec);">${doc.experience_years} yrs exp</div>
        </td>
        <td>
          <div style="font-weight:600; color:var(--navy);">${facName}</div>
          <div style="font-size:11.5px; color:var(--txt-sec);">${htmlEscape(doc.location || '')}</div>
        </td>
        <td>
          <div style="font-weight:600; color:var(--navy);">${feeText}</div>
          <div style="font-size:11.5px; color:var(--txt-sec);">${htmlEscape(doc.available_days || '')}</div>
        </td>
        <td>
          <span class="badge-status ${statusClass}">${htmlEscape(doc.verification_status || 'Verified')}</span>
          <div style="margin-top:3px;">
            <span class="badge-status ${isAct ? 'active' : 'inactive'}">${isAct ? 'Active' : 'Deactivated'}</span>
          </div>
        </td>
        <td>
          <div class="table-actions">
            <button class="action-btn view" title="View Details" onclick="viewDoctor(${doc.id})"><i class="fa-regular fa-eye"></i></button>
            <button class="action-btn edit" title="Edit Doctor" onclick="editDoctor(${doc.id})"><i class="fa-solid fa-pen"></i></button>
            <button class="action-btn toggle" title="${isAct ? 'Deactivate' : 'Activate'}" onclick="toggleDoctorStatus(${doc.id}, ${isAct ? 0 : 1})"><i class="fa-solid ${isAct ? 'fa-ban' : 'fa-check'}"></i></button>
            <button class="action-btn delete" title="Delete Doctor" onclick="deleteDoctor(${doc.id}, '${htmlEscape(doc.name)}')"><i class="fa-regular fa-trash-can"></i></button>
          </div>
        </td>
      </tr>`;
  }).join('');
}

// ── FACILITIES MANAGEMENT ───────────────────────────────────
async function loadFacilities() {
  const search = qs('#searchFacs')?.value.trim() || '';
  const type = qs('#filterFacType')?.value || '';
  const location = qs('#filterFacLocation')?.value.trim() || '';
  const status = qs('#filterFacStatus')?.value || '';

  const tableBody = qs('#facilitiesTableBody');
  if (tableBody) {
    tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--txt-sec);"><i class="fa-solid fa-spinner fa-spin"></i> Loading facilities...</td></tr>`;
  }

  const data = await apiCall('get_facilities', { search, type, location, status });
  if (data && data.success) {
    state.facilities = data.facilities || [];
    renderFacilitiesTable(state.facilities);
  } else {
    if (tableBody) tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--red);">Failed to load medical facilities.</td></tr>`;
  }
}

function renderFacilitiesTable(facilities) {
  const tableBody = qs('#facilitiesTableBody');
  if (!tableBody) return;

  if (facilities.length === 0) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="empty-state">
            <i class="fa-solid fa-hospital"></i>
            <h4>No medical facilities found</h4>
            <p>Try adjusting your search criteria or filter options.</p>
          </div>
        </td>
      </tr>`;
    return;
  }

  tableBody.innerHTML = facilities.map(fac => {
    const isAct = Boolean(Number(fac.is_active));
    const statusClass = (fac.verification_status || 'verified').toLowerCase();
    const docCount = fac.doctor_count || 0;

    return `
      <tr>
        <td>
          <div style="font-weight:700; color:var(--navy); font-size:12px;">${htmlEscape(fac.facility_code || `FAC-${fac.id}`)}</div>
        </td>
        <td>
          <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:34px; height:34px; border-radius:8px; background:var(--gold-bg); color:var(--gold); display:grid; place-items:center; font-size:14px; font-weight:700;">
              <i class="fa-solid fa-hospital"></i>
            </div>
            <div>
              <div style="font-weight:700; color:var(--navy);">${htmlEscape(fac.name)}</div>
              <div style="font-size:12px; color:var(--txt-sec);">${htmlEscape(fac.city)}, ${htmlEscape(fac.state)}</div>
            </div>
          </div>
        </td>
        <td>
          <span class="facility-type-pill">${htmlEscape(fac.type)}</span>
        </td>
        <td>
          <div style="font-weight:600; color:var(--navy);">${htmlEscape(fac.contact_number)}</div>
          <div style="font-size:11.5px; color:var(--txt-sec);">${htmlEscape(fac.email)}</div>
        </td>
        <td>
          <span style="display:inline-flex; align-items:center; gap:4px; font-weight:700; color:var(--blue); background:var(--blue-bg); padding:2px 8px; border-radius:12px; font-size:11.5px;">
            <i class="fa-solid fa-user-doctor"></i> ${docCount} Doctor${docCount === 1 ? '' : 's'}
          </span>
        </td>
        <td>
          <span class="badge-status ${statusClass}">${htmlEscape(fac.verification_status || 'Verified')}</span>
          <div style="margin-top:3px;">
            <span class="badge-status ${isAct ? 'active' : 'inactive'}">${isAct ? 'Active' : 'Deactivated'}</span>
          </div>
        </td>
        <td>
          <div class="table-actions">
            <button class="action-btn view" title="View Details & Associated Doctors" onclick="viewFacility(${fac.id})"><i class="fa-regular fa-eye"></i></button>
            <button class="action-btn edit" title="Edit Facility" onclick="editFacility(${fac.id})"><i class="fa-solid fa-pen"></i></button>
            <button class="action-btn toggle" title="${isAct ? 'Deactivate' : 'Activate'}" onclick="toggleFacilityStatus(${fac.id}, ${isAct ? 0 : 1})"><i class="fa-solid ${isAct ? 'fa-ban' : 'fa-check'}"></i></button>
            <button class="action-btn delete" title="Delete Facility" onclick="deleteFacility(${fac.id}, '${htmlEscape(fac.name)}')"><i class="fa-regular fa-trash-can"></i></button>
          </div>
        </td>
      </tr>`;
  }).join('');
}

// ── MODAL HANDLING ──────────────────────────────────────────
function initModals() {
  qsa('.modal-close-btn, .btn-close-modal').forEach(btn => {
    btn.addEventListener('click', closeAllModals);
  });

  qsa('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeAllModals();
    });
  });

  // Doctor Form Submit
  qs('#doctorForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const oldText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;

    const res = await apiCall('save_doctor', {}, 'POST', formData);
    submitBtn.innerHTML = oldText;
    submitBtn.disabled = false;

    if (res && res.success) {
      showToast(res.message || 'Doctor saved successfully!');
      closeAllModals();
      await loadFilterMeta();
      await loadStats();
      await loadDoctors();
    } else {
      showToast(res?.message || 'Error saving doctor.', 'error');
    }
  });

  // Facility Form Submit
  qs('#facilityForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    const submitBtn = form.querySelector('button[type="submit"]');
    const oldText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;

    const res = await apiCall('save_facility', {}, 'POST', formData);
    submitBtn.innerHTML = oldText;
    submitBtn.disabled = false;

    if (res && res.success) {
      showToast(res.message || 'Medical facility saved successfully!');
      closeAllModals();
      await loadFilterMeta();
      await loadStats();
      await loadFacilities();
    } else {
      showToast(res?.message || 'Error saving medical facility.', 'error');
    }
  });
}

function openModal(modalId) {
  closeAllModals();
  const modal = qs(modalId);
  if (modal) modal.classList.add('active');
}

function closeAllModals() {
  qsa('.modal-overlay').forEach(m => m.classList.remove('active'));
}

// ── DOCTOR ACTIONS ──────────────────────────────────────────
function openAddDoctorModal() {
  const form = qs('#doctorForm');
  if (form) form.reset();
  qs('#docModalTitle').innerHTML = '<i class="fa-solid fa-user-plus"></i> Add Verified Doctor';
  qs('#docId').value = '';
  qs('#docDoctorCode').value = '';
  openModal('#doctorModal');
}

async function editDoctor(id) {
  const res = await apiCall('get_doctor', { id });
  if (res && res.success && res.doctor) {
    const d = res.doctor;
    qs('#docModalTitle').innerHTML = '<i class="fa-solid fa-user-pen"></i> Edit Doctor Details';
    qs('#docId').value = d.id;
    qs('#docDoctorCode').value = d.doctor_code || '';
    qs('#docName').value = d.name || '';
    qs('#docSpecialization').value = d.specialization || '';
    qs('#docQualification').value = d.qualification || '';
    qs('#docExperience').value = d.experience_years || 0;
    qs('#docContact').value = d.contact_number || '';
    qs('#docEmail').value = d.email || '';
    qs('#docFacilityId').value = d.facility_id || '';
    qs('#docFee').value = d.consultation_fee || '';
    qs('#docDays').value = d.available_days || '';
    qs('#docTime').value = d.available_time || '';
    qs('#docLocation').value = d.location || '';
    qs('#docVerificationStatus').value = d.verification_status || 'Verified';
    qs('#docIsActive').value = d.is_active !== undefined ? d.is_active : 1;

    openModal('#doctorModal');
  } else {
    showToast('Failed to load doctor profile.', 'error');
  }
}

async function viewDoctor(id) {
  const res = await apiCall('get_doctor', { id });
  if (res && res.success && res.doctor) {
    const d = res.doctor;
    const isAct = Boolean(Number(d.is_active));
    const statusClass = (d.verification_status || 'verified').toLowerCase();
    const feeText = d.consultation_fee ? `₹${parseFloat(d.consultation_fee).toLocaleString('en-IN')}` : 'Not Specified';

    const sheet = qs('#viewDoctorContent');
    sheet.innerHTML = `
      <div class="view-detail-sheet">
        <div class="view-hero">
          <div class="view-avatar">
            <i class="fa-solid fa-user-doctor"></i>
          </div>
          <div>
            <h4>${htmlEscape(d.name)}</h4>
            <p>${htmlEscape(d.qualification)} • <strong style="color:var(--gold);">${htmlEscape(d.specialization)}</strong> (${d.experience_years} Years Experience)</p>
            <div style="margin-top:6px; display:flex; gap:6px;">
              <span class="badge-status ${statusClass}">${htmlEscape(d.verification_status || 'Verified')}</span>
              <span class="badge-status ${isAct ? 'active' : 'inactive'}">${isAct ? 'Active Status' : 'Deactivated'}</span>
              <span style="font-size:11.5px; font-weight:600; padding:2px 8px; background:#f1f3f7; border-radius:6px; color:var(--navy);">ID: ${htmlEscape(d.doctor_code || `DOC-${d.id}`)}</span>
            </div>
          </div>
        </div>

        <div class="view-grid">
          <div class="view-item">
            <div class="item-lbl">Associated Medical Facility</div>
            <div class="item-val">${d.facility_name ? htmlEscape(d.facility_name) : 'Independent Practice'}</div>
            ${d.facility_type ? `<div style="font-size:11.5px; color:var(--txt-sec);">${htmlEscape(d.facility_type)} • ${htmlEscape(d.facility_city || '')}</div>` : ''}
          </div>
          <div class="view-item">
            <div class="item-lbl">Consultation Fee</div>
            <div class="item-val" style="color:var(--gold);">${feeText}</div>
          </div>
          <div class="view-item">
            <div class="item-lbl">Available Consultation Days</div>
            <div class="item-val">${htmlEscape(d.available_days || 'Not set')}</div>
          </div>
          <div class="view-item">
            <div class="item-lbl">Available Timings</div>
            <div class="item-val">${htmlEscape(d.available_time || 'Not set')}</div>
          </div>
          <div class="view-item">
            <div class="item-lbl">Contact Number</div>
            <div class="item-val">${htmlEscape(d.contact_number)}</div>
          </div>
          <div class="view-item">
            <div class="item-lbl">Email Address</div>
            <div class="item-val">${htmlEscape(d.email)}</div>
          </div>
          <div class="view-item" style="grid-column: span 2;">
            <div class="item-lbl">Practice Location / Address</div>
            <div class="item-val">${htmlEscape(d.location)}</div>
          </div>
        </div>
      </div>
    `;
    openModal('#viewDoctorModal');
  } else {
    showToast('Failed to fetch doctor details.', 'error');
  }
}

async function toggleDoctorStatus(id, newStatus) {
  const res = await apiCall('toggle_doctor_status', { id, field: 'is_active', value: newStatus }, 'POST');
  if (res && res.success) {
    showToast(`Doctor marked as ${newStatus ? 'Active' : 'Deactivated'}.`);
    await loadStats();
    await loadDoctors();
  } else {
    showToast('Failed to update doctor status.', 'error');
  }
}

async function deleteDoctor(id, name) {
  if (!confirm(`Are you sure you want to permanently remove ${name}? This action cannot be undone.`)) {
    return;
  }
  const res = await apiCall('delete_doctor', { id }, 'POST');
  if (res && res.success) {
    showToast(res.message || 'Doctor removed.');
    await loadFilterMeta();
    await loadStats();
    await loadDoctors();
  } else {
    showToast(res?.message || 'Failed to delete doctor.', 'error');
  }
}

// ── FACILITY ACTIONS ────────────────────────────────────────
function openAddFacilityModal() {
  const form = qs('#facilityForm');
  if (form) form.reset();
  qs('#facModalTitle').innerHTML = '<i class="fa-solid fa-square-plus"></i> Register Medical Facility';
  qs('#facId').value = '';
  qs('#facFacilityCode').value = '';
  openModal('#facilityModal');
}

async function editFacility(id) {
  const res = await apiCall('get_facility', { id });
  if (res && res.success && res.facility) {
    const f = res.facility;
    qs('#facModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit Facility Details';
    qs('#facId').value = f.id;
    qs('#facFacilityCode').value = f.facility_code || '';
    qs('#facName').value = f.name || '';
    qs('#facType').value = f.type || 'Hospital';
    qs('#facAddress').value = f.address || '';
    qs('#facCity').value = f.city || '';
    qs('#facState').value = f.state || '';
    qs('#facContact').value = f.contact_number || '';
    qs('#facEmail').value = f.email || '';
    qs('#facDepartments').value = f.departments || '';
    qs('#facOpeningHours').value = f.opening_hours || '';
    qs('#facVerificationStatus').value = f.verification_status || 'Verified';
    qs('#facIsActive').value = f.is_active !== undefined ? f.is_active : 1;

    openModal('#facilityModal');
  } else {
    showToast('Failed to load facility data.', 'error');
  }
}

async function viewFacility(id) {
  const res = await apiCall('get_facility', { id });
  if (res && res.success && res.facility) {
    const f = res.facility;
    const isAct = Boolean(Number(f.is_active));
    const statusClass = (f.verification_status || 'verified').toLowerCase();
    const doctors = f.doctors || [];

    const sheet = qs('#viewFacilityContent');
    sheet.innerHTML = `
      <div class="view-detail-sheet">
        <div class="view-hero">
          <div class="view-avatar" style="background:var(--gold-bg); color:var(--gold);">
            <i class="fa-solid fa-hospital"></i>
          </div>
          <div>
            <h4>${htmlEscape(f.name)}</h4>
            <p><span class="facility-type-pill">${htmlEscape(f.type)}</span> • ${htmlEscape(f.city)}, ${htmlEscape(f.state)}</p>
            <div style="margin-top:6px; display:flex; gap:6px;">
              <span class="badge-status ${statusClass}">${htmlEscape(f.verification_status || 'Verified')}</span>
              <span class="badge-status ${isAct ? 'active' : 'inactive'}">${isAct ? 'Active' : 'Deactivated'}</span>
              <span style="font-size:11.5px; font-weight:600; padding:2px 8px; background:#f1f3f7; border-radius:6px; color:var(--navy);">ID: ${htmlEscape(f.facility_code || `FAC-${f.id}`)}</span>
            </div>
          </div>
        </div>

        <div class="view-grid">
          <div class="view-item">
            <div class="item-lbl">Contact Number</div>
            <div class="item-val">${htmlEscape(f.contact_number)}</div>
          </div>
          <div class="view-item">
            <div class="item-lbl">Official Email</div>
            <div class="item-val">${htmlEscape(f.email)}</div>
          </div>
          <div class="view-item" style="grid-column: span 2;">
            <div class="item-lbl">Full Address</div>
            <div class="item-val">${htmlEscape(f.address)}, ${htmlEscape(f.city)}, ${htmlEscape(f.state)}</div>
          </div>
          <div class="view-item" style="grid-column: span 2;">
            <div class="item-lbl">Operating Hours</div>
            <div class="item-val">${htmlEscape(f.opening_hours)}</div>
          </div>
          <div class="view-item" style="grid-column: span 2;">
            <div class="item-lbl">Departments & Services</div>
            <div class="item-val">${htmlEscape(f.departments || 'General Healthcare Services')}</div>
          </div>
        </div>

        <!-- 1:N Relationship Doctors List -->
        <div style="margin-top:10px;">
          <h4 style="font-size:15px; font-weight:700; color:var(--navy); margin-bottom:10px; display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-user-doctor" style="color:var(--gold);"></i> Associated Doctors (${doctors.length})
          </h4>
          ${doctors.length > 0 ? `
            <div class="associated-doctors-list">
              ${doctors.map(d => `
                <div class="associated-doc-card">
                  <div>
                    <div class="doc-name">${htmlEscape(d.name)}</div>
                    <div style="font-size:12px; color:var(--txt-sec);">${htmlEscape(d.qualification || '')} • ${d.experience_years} yrs exp</div>
                  </div>
                  <div style="text-align:right;">
                    <div class="doc-spec">${htmlEscape(d.specialization)}</div>
                    <div style="font-size:11.5px; color:var(--txt-sec);">${htmlEscape(d.available_days || '')}</div>
                  </div>
                </div>
              `).join('')}
            </div>
          ` : `
            <div style="background:#f8fafc; border:1px dashed var(--border); padding:16px; border-radius:8px; text-align:center; color:var(--txt-sec); font-size:13px;">
              No doctors are currently associated with this medical facility.
            </div>
          `}
        </div>
      </div>
    `;
    openModal('#viewFacilityModal');
  } else {
    showToast('Failed to load facility profile.', 'error');
  }
}

async function toggleFacilityStatus(id, newStatus) {
  const res = await apiCall('toggle_facility_status', { id, field: 'is_active', value: newStatus }, 'POST');
  if (res && res.success) {
    showToast(`Facility marked as ${newStatus ? 'Active' : 'Deactivated'}.`);
    await loadStats();
    await loadFacilities();
  } else {
    showToast('Failed to update facility status.', 'error');
  }
}

async function deleteFacility(id, name) {
  if (!confirm(`Are you sure you want to remove "${name}"? Associated doctors will become unlinked.`)) {
    return;
  }
  const res = await apiCall('delete_facility', { id }, 'POST');
  if (res && res.success) {
    showToast(res.message || 'Facility removed.');
    await loadFilterMeta();
    await loadStats();
    await loadFacilities();
    if (state.currentTab === 'doctors') await loadDoctors();
  } else {
    showToast(res?.message || 'Failed to delete facility.', 'error');
  }
}

function htmlEscape(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
