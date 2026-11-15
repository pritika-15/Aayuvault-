/**
 * ============================================================
 * AayuVault — Patient Directory (directory.js)
 * Read-Only Verified Directory for Patients
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
};

function qs(selector, parent = document) { return parent.querySelector(selector); }
function qsa(selector, parent = document) { return [...parent.querySelectorAll(selector)]; }

async function apiCall(action, params = {}) {
  try {
    let url = `${API}?action=${encodeURIComponent(action)}`;
    const searchParams = new URLSearchParams(params);
    const qsStr = searchParams.toString();
    if (qsStr) url += `&${qsStr}`;
    const res = await fetch(url);
    return await res.json();
  } catch (err) {
    console.error(`API Error [${action}]:`, err);
    return { success: false, message: 'Network error.' };
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  initTabs();
  initSearch();
  initModals();
  await loadFilterMeta();
  await loadDoctors();
});

// ── TABS ────────────────────────────────────────────────────
function initTabs() {
  qsa('.patient-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      qsa('.patient-tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.currentTab = btn.dataset.tab;

      if (state.currentTab === 'doctors') {
        qs('#patientDoctorsView').style.display = 'block';
        qs('#patientFacilitiesView').style.display = 'none';
        loadDoctors();
      } else {
        qs('#patientDoctorsView').style.display = 'none';
        qs('#patientFacilitiesView').style.display = 'block';
        loadFacilities();
      }
    });
  });
}

// ── FILTER METADATA ─────────────────────────────────────────
async function loadFilterMeta() {
  const data = await apiCall('get_filter_meta');
  if (data && data.success) {
    state.specializations = data.specializations || [];
    state.facilityTypes   = data.facility_types || [];
    state.facilitiesList  = data.facilities || [];

    const docSpec = qs('#patientDocSpecFilter');
    if (docSpec) {
      docSpec.innerHTML = '<option value="">All Specializations</option>' +
        state.specializations.map(s => `<option value="${s}">${s}</option>`).join('');
    }

    const docFac = qs('#patientDocFacFilter');
    if (docFac) {
      docFac.innerHTML = '<option value="">All Hospitals / Clinics</option>' +
        state.facilitiesList.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
    }

    const facType = qs('#patientFacTypeFilter');
    if (facType) {
      facType.innerHTML = '<option value="">All Facility Types</option>' +
        state.facilityTypes.map(t => `<option value="${t}">${t}</option>`).join('');
    }
  }
}

// ── SEARCH & FILTERS ────────────────────────────────────────
function initSearch() {
  let docDebounce;
  qs('#patientDocSearch')?.addEventListener('input', () => {
    clearTimeout(docDebounce);
    docDebounce = setTimeout(loadDoctors, 300);
  });
  qs('#patientDocSpecFilter')?.addEventListener('change', loadDoctors);
  qs('#patientDocFacFilter')?.addEventListener('change', loadDoctors);

  let facDebounce;
  qs('#patientFacSearch')?.addEventListener('input', () => {
    clearTimeout(facDebounce);
    facDebounce = setTimeout(loadFacilities, 300);
  });
  qs('#patientFacTypeFilter')?.addEventListener('change', loadFacilities);
  qs('#patientFacLocFilter')?.addEventListener('input', () => {
    clearTimeout(facDebounce);
    facDebounce = setTimeout(loadFacilities, 350);
  });
}

// ── LOAD DOCTORS (READ-ONLY) ────────────────────────────────
async function loadDoctors() {
  const search = qs('#patientDocSearch')?.value.trim() || '';
  const specialization = qs('#patientDocSpecFilter')?.value || '';
  const facility_id = qs('#patientDocFacFilter')?.value || '';

  const grid = qs('#patientDoctorsGrid');
  if (grid) {
    grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:40px; color:var(--txt-sec);"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><p style="margin-top:8px;">Finding verified specialists...</p></div>`;
  }

  const data = await apiCall('get_doctors', { search, specialization, facility_id, status: 'Verified', is_active: 1 });
  if (data && data.success) {
    state.doctors = data.doctors || [];
    renderDoctorsGrid(state.doctors);
  } else {
    if (grid) grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:40px; color:var(--red);">Could not load doctors directory.</div>`;
  }
}

function renderDoctorsGrid(doctors) {
  const grid = qs('#patientDoctorsGrid');
  if (!grid) return;

  if (doctors.length === 0) {
    grid.innerHTML = `
      <div style="grid-column: 1/-1;" class="empty-state">
        <i class="fa-solid fa-user-doctor"></i>
        <h4>No verified doctors match your criteria</h4>
        <p>Try searching for a different specialty or location.</p>
      </div>`;
    return;
  }

  grid.innerHTML = doctors.map(doc => {
    const fee = doc.consultation_fee ? `₹${parseFloat(doc.consultation_fee).toLocaleString('en-IN')}` : 'Fee on Enquiry';
    const facName = doc.facility_name ? htmlEscape(doc.facility_name) : 'Independent Practice';

    return `
      <div class="patient-card">
        <div class="card-top">
          <div class="doctor-avatar">
            <i class="fa-solid fa-user-doctor"></i>
          </div>
          <div class="card-headings">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span class="badge-status verified"><i class="fa-solid fa-circle-check"></i> Verified</span>
              <span style="font-size:11.5px; font-weight:700; color:var(--gold);">${fee}</span>
            </div>
            <h3 class="doctor-title">${htmlEscape(doc.name)}</h3>
            <p class="doctor-sub">${htmlEscape(doc.qualification)}</p>
          </div>
        </div>

        <div class="card-details">
          <div class="detail-row">
            <span class="detail-label"><i class="fa-solid fa-stethoscope"></i> Specialization</span>
            <span class="detail-val" style="color:var(--gold); font-weight:700;">${htmlEscape(doc.specialization)} (${doc.experience_years} yrs exp)</span>
          </div>
          <div class="detail-row">
            <span class="detail-label"><i class="fa-solid fa-hospital"></i> Facility / Hospital</span>
            <span class="detail-val">${facName}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label"><i class="fa-solid fa-calendar-days"></i> Available Days</span>
            <span class="detail-val">${htmlEscape(doc.available_days)}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label"><i class="fa-solid fa-clock"></i> Timings</span>
            <span class="detail-val">${htmlEscape(doc.available_time)}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label"><i class="fa-solid fa-location-dot"></i> Location</span>
            <span class="detail-val">${htmlEscape(doc.location)}</span>
          </div>
        </div>

        <div class="card-footer">
          <button class="btn-card-secondary" onclick="viewDoctorDetails(${doc.id})">
            <i class="fa-regular fa-eye"></i> View Profile
          </button>
          <button class="btn-card-primary" onclick="bookWithDoctor(${doc.id})">
            <i class="fa-solid fa-calendar-plus"></i> Book Appt
          </button>
        </div>
      </div>
    `;
  }).join('');
}

// ── LOAD FACILITIES (READ-ONLY) ─────────────────────────────
async function loadFacilities() {
  const search = qs('#patientFacSearch')?.value.trim() || '';
  const type = qs('#patientFacTypeFilter')?.value || '';
  const location = qs('#patientFacLocFilter')?.value.trim() || '';

  const grid = qs('#patientFacilitiesGrid');
  if (grid) {
    grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:40px; color:var(--txt-sec);"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><p style="margin-top:8px;">Finding verified medical facilities...</p></div>`;
  }

  const data = await apiCall('get_facilities', { search, type, location, status: 'Verified', is_active: 1 });
  if (data && data.success) {
    state.facilities = data.facilities || [];
    renderFacilitiesGrid(state.facilities);
  } else {
    if (grid) grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:40px; color:var(--red);">Could not load medical facilities directory.</div>`;
  }
}

function renderFacilitiesGrid(facilities) {
  const grid = qs('#patientFacilitiesGrid');
  if (!grid) return;

  if (facilities.length === 0) {
    grid.innerHTML = `
      <div style="grid-column: 1/-1;" class="empty-state">
        <i class="fa-solid fa-hospital"></i>
        <h4>No medical facilities found</h4>
        <p>Try searching for a different city or facility type.</p>
      </div>`;
    return;
  }

  grid.innerHTML = facilities.map(fac => {
    const docCount = fac.doctor_count || 0;

    return `
      <div class="patient-card">
        <div class="card-top">
          <div class="doctor-avatar" style="background:var(--gold-bg); color:var(--gold);">
            <i class="fa-solid fa-hospital"></i>
          </div>
          <div class="card-headings">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span class="facility-type-pill">${htmlEscape(fac.type)}</span>
              <span class="badge-status verified"><i class="fa-solid fa-circle-check"></i> Verified</span>
            </div>
            <h3 class="doctor-title">${htmlEscape(fac.name)}</h3>
            <p class="doctor-sub">${htmlEscape(fac.city)}, ${htmlEscape(fac.state)}</p>
          </div>
        </div>

        <div class="card-details">
          <div class="detail-row">
            <span class="detail-label"><i class="fa-solid fa-user-doctor"></i> Available Doctors</span>
            <span class="detail-val" style="color:var(--blue); font-weight:700;">${docCount} Verified Specialist${docCount === 1 ? '' : 's'}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label"><i class="fa-solid fa-clock"></i> Hours</span>
            <span class="detail-val">${htmlEscape(fac.opening_hours)}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label"><i class="fa-solid fa-phone"></i> Contact</span>
            <span class="detail-val">${htmlEscape(fac.contact_number)}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label"><i class="fa-solid fa-location-dot"></i> Address</span>
            <span class="detail-val">${htmlEscape(fac.address)}</span>
          </div>
        </div>

        <div class="card-footer">
          <button class="btn-card-secondary" style="width:100%; justify-content:center;" onclick="viewFacilityDetails(${fac.id})">
            <i class="fa-regular fa-eye"></i> View Facility &amp; Specialists
          </button>
        </div>
      </div>
    `;
  }).join('');
}

// ── MODAL DETAILS VIEW ──────────────────────────────────────
function initModals() {
  qsa('.modal-close-btn, .btn-close-modal').forEach(btn => {
    btn.addEventListener('click', closeAllModals);
  });
  qsa('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeAllModals();
    });
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

async function viewDoctorDetails(id) {
  const res = await apiCall('get_doctor', { id });
  if (res && res.success && res.doctor) {
    const d = res.doctor;
    const feeText = d.consultation_fee ? `₹${parseFloat(d.consultation_fee).toLocaleString('en-IN')}` : 'Contact for fee';

    const sheet = qs('#patientViewDoctorContent');
    sheet.innerHTML = `
      <div class="view-detail-sheet">
        <div class="view-hero">
          <div class="view-avatar">
            <i class="fa-solid fa-user-doctor"></i>
          </div>
          <div>
            <h4>${htmlEscape(d.name)}</h4>
            <p>${htmlEscape(d.qualification)} • <strong style="color:var(--gold);">${htmlEscape(d.specialization)}</strong></p>
            <div style="margin-top:4px; font-size:12.5px; color:var(--txt-sec);">
              ${d.experience_years} Years Professional Clinical Experience
            </div>
          </div>
        </div>

        <div class="view-grid">
          <div class="view-item">
            <div class="item-lbl">Hospital / Clinic</div>
            <div class="item-val">${d.facility_name ? htmlEscape(d.facility_name) : 'Independent Practice'}</div>
            ${d.facility_type ? `<div style="font-size:11.5px; color:var(--txt-sec);">${htmlEscape(d.facility_type)} • ${htmlEscape(d.facility_city || '')}</div>` : ''}
          </div>
          <div class="view-item">
            <div class="item-lbl">Consultation Fee</div>
            <div class="item-val" style="color:var(--gold);">${feeText}</div>
          </div>
          <div class="view-item">
            <div class="item-lbl">Consultation Days</div>
            <div class="item-val">${htmlEscape(d.available_days)}</div>
          </div>
          <div class="view-item">
            <div class="item-lbl">Available Timings</div>
            <div class="item-val">${htmlEscape(d.available_time)}</div>
          </div>
          <div class="view-item" style="grid-column: span 2;">
            <div class="item-lbl">Practice Location</div>
            <div class="item-val">${htmlEscape(d.location)}</div>
          </div>
        </div>

        <div style="margin-top:16px; display:flex; justify-content:flex-end;">
          <button class="btn-primary" onclick="bookWithDoctor(${d.id})">
            <i class="fa-solid fa-calendar-check"></i> Book Appointment with ${htmlEscape(d.name)}
          </button>
        </div>
      </div>
    `;
    openModal('#patientViewDoctorModal');
  }
}

async function viewFacilityDetails(id) {
  const res = await apiCall('get_facility', { id });
  if (res && res.success && res.facility) {
    const f = res.facility;
    const doctors = f.doctors || [];

    const sheet = qs('#patientViewFacilityContent');
    sheet.innerHTML = `
      <div class="view-detail-sheet">
        <div class="view-hero">
          <div class="view-avatar" style="background:var(--gold-bg); color:var(--gold);">
            <i class="fa-solid fa-hospital"></i>
          </div>
          <div>
            <h4>${htmlEscape(f.name)}</h4>
            <p><span class="facility-type-pill">${htmlEscape(f.type)}</span> • ${htmlEscape(f.city)}, ${htmlEscape(f.state)}</p>
          </div>
        </div>

        <div class="view-grid">
          <div class="view-item">
            <div class="item-lbl">Contact Phone</div>
            <div class="item-val">${htmlEscape(f.contact_number)}</div>
          </div>
          <div class="view-item">
            <div class="item-lbl">Email Address</div>
            <div class="item-val">${htmlEscape(f.email)}</div>
          </div>
          <div class="view-item" style="grid-column: span 2;">
            <div class="item-lbl">Address</div>
            <div class="item-val">${htmlEscape(f.address)}, ${htmlEscape(f.city)}, ${htmlEscape(f.state)}</div>
          </div>
          <div class="view-item" style="grid-column: span 2;">
            <div class="item-lbl">Operating Hours</div>
            <div class="item-val">${htmlEscape(f.opening_hours)}</div>
          </div>
          <div class="view-item" style="grid-column: span 2;">
            <div class="item-lbl">Clinical Departments &amp; Services</div>
            <div class="item-val">${htmlEscape(f.departments || 'General Clinical Care')}</div>
          </div>
        </div>

        <div style="margin-top:14px;">
          <h4 style="font-size:15px; font-weight:700; color:var(--navy); margin-bottom:10px; display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-user-doctor" style="color:var(--gold);"></i> Doctors at this Facility (${doctors.length})
          </h4>
          ${doctors.length > 0 ? `
            <div class="associated-doctors-list">
              ${doctors.map(d => `
                <div class="associated-doc-card">
                  <div>
                    <div class="doc-name">${htmlEscape(d.name)}</div>
                    <div style="font-size:12px; color:var(--txt-sec);">${htmlEscape(d.qualification || '')} • ${htmlEscape(d.specialization)}</div>
                  </div>
                  <div>
                    <button class="btn-card-primary" style="padding:6px 12px; font-size:12px;" onclick="bookWithDoctor(${d.id})">
                      <i class="fa-solid fa-calendar-plus"></i> Book
                    </button>
                  </div>
                </div>
              `).join('')}
            </div>
          ` : `
            <div style="background:#f8fafc; border:1px dashed var(--border); padding:16px; border-radius:8px; text-align:center; color:var(--txt-sec); font-size:13px;">
              No doctors currently listed under this facility.
            </div>
          `}
        </div>
      </div>
    `;
    openModal('#patientViewFacilityModal');
  }
}

function bookWithDoctor(doctorId) {
  window.location.href = `appointment.html?doctor_id=${doctorId}`;
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
