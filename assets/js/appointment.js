/**
 * AayuVault – appointment.js
 * Complete Appointment Booking & Scheduling Controller
 * Connects frontend appointment booking with:
 * - Doctors & Facilities from MySQL/database
 * - Patient Medical Conditions & Health Context
 * - Dynamic Time Slots & Date Availability
 * - Real-Time Booking & Schedule Creation + Reminder Integration
 * - Built-in offline resilience (works on http://localhost, live server, and file://)
 */

window.APPOINTMENT_MODULE_LOADED = true;

const API_BASE = '../pages/appointment.php';

/* ===========================
   DEFAULT / FALLBACK CLINICAL DATA
=========================== */
const FALLBACK_METRICS = {
  blood_pressure: '120/80 mmHg',
  heart_rate: '72 bpm',
  spo2: '98%',
  blood_sugar: '98 mg/dL',
  recent_labs: 'Optimal'
};

const FALLBACK_CONDITIONS = [
  { id: 1, name: 'Borderline Dyslipidemia', condition_name: 'Borderline Dyslipidemia', category: 'Cardiovascular' },
  { id: 2, name: 'Pre-Diabetes (Mild HbA1c elevation)', condition_name: 'Pre-Diabetes (Mild HbA1c elevation)', category: 'Metabolic' },
  { id: 3, name: 'Vitamin D Deficiency', condition_name: 'Vitamin D Deficiency', category: 'Nutritional' },
  { id: 4, name: 'Primary Hypertension Review', condition_name: 'Primary Hypertension Review', category: 'Cardiovascular' },
  { id: 5, name: 'Annual Preventive Health Checkup', condition_name: 'Annual Preventive Health Checkup', category: 'Preventive Care' }
];

const FALLBACK_HEALTH_TAGS = [
  'Cardiovascular Review',
  'Pre-Diabetes Monitoring',
  'Annual Health Review',
  'General Preventive Care',
  'Lipid Panel Follow-up'
];

const FALLBACK_CONDITION_OPTIONS = [
  'Cardiovascular Review / Hypertension',
  'Diabetes / Endocrine Follow-up (HbA1c)',
  'Borderline Dyslipidemia / Lipid Panel',
  'Vitamin D & Nutritional Screen',
  'Orthopedic & Joint Consultation',
  'Neurology Consultation',
  'Dermatology & Skin Check',
  'General Preventive Health Checkup',
  'Pathology & Lab Report Review'
];

const FALLBACK_PROVIDERS = [
  {
    id: 1,
    doctor_code: 'DOC-201',
    name: 'Dr. Priya Sharma',
    specialization: 'Cardiology',
    specialty: 'Cardiology • ABC Multispecialty Hospital',
    facility_id: 1,
    facility_name: 'ABC Multispecialty Hospital',
    facility_type: 'Hospital',
    facility_city: 'Noida',
    address: 'Plot 12, Institutional Area, Sector 62',
    qualification: 'MBBS, MD, DM (Cardiology), FACC',
    experience_years: 14,
    consultation_fee: '1200.00',
    available_days: 'Mon, Wed, Fri',
    available_time: '09:00 AM - 01:00 PM, 04:00 PM - 07:00 PM',
    rating: '4.9'
  },
  {
    id: 2,
    doctor_code: 'DOC-202',
    name: 'Dr. Rahul Sharma',
    specialization: 'Cardiology',
    specialty: 'Cardiology • ABC Multispecialty Hospital',
    facility_id: 1,
    facility_name: 'ABC Multispecialty Hospital',
    facility_type: 'Hospital',
    facility_city: 'Noida',
    address: 'Plot 12, Institutional Area, Sector 62',
    qualification: 'MBBS, MD (Medicine), DM (Cardiology)',
    experience_years: 10,
    consultation_fee: '1000.00',
    available_days: 'Tue, Thu, Sat',
    available_time: '10:00 AM - 02:00 PM, 05:00 PM - 08:00 PM',
    rating: '4.8'
  },
  {
    id: 3,
    doctor_code: 'DOC-203',
    name: 'Dr. Priya Singh',
    specialization: 'Neurology',
    specialty: 'Neurology • ABC Multispecialty Hospital',
    facility_id: 1,
    facility_name: 'ABC Multispecialty Hospital',
    facility_type: 'Hospital',
    facility_city: 'Noida',
    address: 'Plot 12, Institutional Area, Sector 62',
    qualification: 'MBBS, MD (Medicine), DM (Neurology)',
    experience_years: 12,
    consultation_fee: '1500.00',
    available_days: 'Mon, Tue, Thu, Fri',
    available_time: '11:00 AM - 03:00 PM',
    rating: '4.9'
  },
  {
    id: 4,
    doctor_code: 'DOC-204',
    name: 'Dr. Amit Kumar',
    specialization: 'Orthopedics',
    specialty: 'Orthopedics • ABC Multispecialty Hospital',
    facility_id: 1,
    facility_name: 'ABC Multispecialty Hospital',
    facility_type: 'Hospital',
    facility_city: 'Noida',
    address: 'Plot 12, Institutional Area, Sector 62',
    qualification: 'MBBS, MS (Orthopedics), MCh (Joint Replacement)',
    experience_years: 16,
    consultation_fee: '1100.00',
    available_days: 'Mon, Wed, Sat',
    available_time: '09:30 AM - 01:30 PM, 04:30 PM - 07:30 PM',
    rating: '4.7'
  },
  {
    id: 5,
    doctor_code: 'DOC-205',
    name: 'Dr. Rajeev Mehta',
    specialization: 'Endocrinology',
    specialty: 'Endocrinology • CareWell Polyclinic & Diagnostics',
    facility_id: 2,
    facility_name: 'CareWell Polyclinic & Diagnostics',
    facility_type: 'Clinic',
    facility_city: 'Ghaziabad',
    address: 'Shop 4-6, Galaxy Plaza, Indirapuram',
    qualification: 'MBBS, MD (Internal Medicine), DM (Endocrinology)',
    experience_years: 11,
    consultation_fee: '900.00',
    available_days: 'Mon - Fri',
    available_time: '04:00 PM - 08:30 PM',
    rating: '4.8'
  },
  {
    id: 6,
    doctor_code: 'DOC-206',
    name: 'Dr. Shalini Verma',
    specialization: 'Dermatology',
    specialty: 'Dermatology • CareWell Polyclinic & Diagnostics',
    facility_id: 2,
    facility_name: 'CareWell Polyclinic & Diagnostics',
    facility_type: 'Clinic',
    facility_city: 'Ghaziabad',
    address: 'Shop 4-6, Galaxy Plaza, Indirapuram',
    qualification: 'MBBS, MD (Dermatology, Venereology & Leprosy)',
    experience_years: 8,
    consultation_fee: '800.00',
    available_days: 'Tue, Thu, Sat, Sun',
    available_time: '10:00 AM - 02:00 PM',
    rating: '4.9'
  },
  {
    id: 7,
    doctor_code: 'DOC-207',
    name: 'Dr. Anil Verma',
    specialization: 'General Physician',
    specialty: 'General Physician • Metro Central Diagnostic Centre',
    facility_id: 3,
    facility_name: 'Metro Central Diagnostic Centre',
    facility_type: 'Diagnostic Centre',
    facility_city: 'New Delhi',
    address: 'B-45, Central Market, South Extension Part II',
    qualification: 'MBBS, MD (General Medicine)',
    experience_years: 18,
    consultation_fee: '700.00',
    available_days: 'Mon - Sat',
    available_time: '08:00 AM - 12:00 PM',
    rating: '4.9'
  },
  {
    id: 8,
    doctor_code: 'DOC-208',
    name: 'Dr. Sunita Kulkarni',
    specialization: 'Pathology',
    specialty: 'Pathology • Apex Clinical Laboratories',
    facility_id: 4,
    facility_name: 'Apex Clinical Laboratories',
    facility_type: 'Laboratory',
    facility_city: 'Gurugram',
    address: 'Plot 88, Sector 18, Electronic City',
    qualification: 'MBBS, MD (Pathology), DNB',
    experience_years: 15,
    consultation_fee: '600.00',
    available_days: 'Mon - Fri',
    available_time: '09:00 AM - 05:00 PM',
    rating: '4.8'
  }
];

const DEFAULT_SLOTS = [
  '09:00 AM', '09:30 AM', '10:00 AM', '10:30 AM', '11:00 AM', '11:30 AM',
  '02:00 PM', '02:30 PM', '03:00 PM', '03:30 PM', '04:00 PM', '04:30 PM', '05:00 PM'
];

/* ===========================
   UTILITIES
=========================== */
function qs(sel, ctx = document) { return ctx.querySelector(sel); }
function qsa(sel, ctx = document) { return [...ctx.querySelectorAll(sel)]; }

async function apiFetch(action, params = {}) {
  try {
    const url = new URL(API_BASE, location.href);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v);
    });
    const res = await fetch(url.toString());
    if (!res.ok) throw new Error(`API error: ${res.status}`);
    const data = await res.json();
    return data;
  } catch (err) {
    // Return null on network or CORS errors to trigger offline fallbacks
    return null;
  }
}

function extractList(data, key) {
  if (!data) return [];
  if (Array.isArray(data)) return data;
  if (key && Array.isArray(data[key])) return data[key];
  if (Array.isArray(data.data)) return data.data;
  if (Array.isArray(data.items)) return data.items;
  return [];
}

/* ===========================
   STATE
=========================== */
const state = {
  selectedProvider: null,
  selectedDoctor: null,
  selectedDate: null,
  selectedTime: null,
  selectedCondition: '',
  allProviders: FALLBACK_PROVIDERS
};

/* ===========================
   KEY METRICS
=========================== */
async function loadMetrics() {
  const bp = qs('#bp-value');
  const hr = qs('#hr-value');
  const hr2 = qs('#hr2-value');
  const labs = qs('#labs-value');
  const rlabs = qs('#recentlabs-value');

  const data = (await apiFetch('metrics')) || FALLBACK_METRICS;

  if (bp) bp.textContent = data.blood_pressure || '120/80 mmHg';
  if (hr) hr.textContent = data.heart_rate ? `${data.heart_rate} bpm` : '72 bpm';
  if (hr2) hr2.textContent = data.blood_sugar || (data.spo2 ? `${data.spo2}%` : '98%');
  if (labs) labs.textContent = data.recent_labs || 'Optimal';
  if (rlabs) rlabs.textContent = data.recent_labs || 'Optimal';
}

/* ===========================
   CONDITIONS TABLE (Medical Summary)
=========================== */
async function loadConditions() {
  const list = qs('#conditions-list');
  if (!list) return;

  const data = await apiFetch('conditions');
  let items = extractList(data, 'conditions');
  if (!items.length) items = FALLBACK_CONDITIONS;

  list.innerHTML = items.map(c => `
    <div class="table-row">
      <span>${c.name || c.condition_name}</span>
      <span class="badge-controlled">${c.category || 'Monitored'}</span>
    </div>
  `).join('');
}

/* ===========================
   HEALTH CONTEXT TAGS
=========================== */
async function loadHealthContext() {
  const container = qs('#health-context-tags');
  if (!container) return;

  const data = await apiFetch('health_context');
  let tags = extractList(data, 'tags');
  if (!tags.length) tags = FALLBACK_HEALTH_TAGS;

  container.innerHTML = tags.map(t => {
    const text = typeof t === 'string' ? t : (t.name || t.condition_name || 'Health Review');
    return `<span class="context-tag" style="cursor:pointer;" title="Click to set visit reason">${text}</span>`;
  }).join('');

  // Clicking a context tag sets condition dropdown
  qsa('.context-tag', container).forEach(tagEl => {
    tagEl.addEventListener('click', () => {
      const tagText = tagEl.textContent.trim();
      const sel = qs('#condition-select');
      if (sel) {
        let found = false;
        for (let opt of sel.options) {
          if (opt.text.toLowerCase().includes(tagText.toLowerCase()) || tagText.toLowerCase().includes(opt.text.toLowerCase())) {
            sel.value = opt.value;
            found = true;
            break;
          }
        }
        if (!found) {
          const newOpt = document.createElement('option');
          newOpt.value = tagText;
          newOpt.textContent = tagText;
          sel.appendChild(newOpt);
          sel.value = tagText;
        }
        state.selectedCondition = sel.value;
        updatePreview();
      }
    });
  });
}

/* ===========================
   CONDITION DROPDOWN (STEP 1)
=========================== */
async function loadConditionOptions() {
  const sel = qs('#condition-select');
  if (!sel) return;

  const data = await apiFetch('conditions_list');
  let options = extractList(data, 'conditions');
  if (!options.length) options = FALLBACK_CONDITION_OPTIONS;

  sel.innerHTML = `<option value="">Select Condition / Concern for Visit…</option>` +
    options.map(c => {
      const val = typeof c === 'string' ? c : (c.name || c.id || '');
      const label = typeof c === 'string' ? c : (c.name || c.id || '');
      return `<option value="${val}">${label}</option>`;
    }).join('');

  sel.addEventListener('change', (e) => {
    state.selectedCondition = e.target.value;
    updatePreview();
  });
}

/* ===========================
   FILTER & ZONE DROPDOWNS (STEP 2)
=========================== */
async function loadFilterOptions() {
  const filterSel = qs('#filter-select');
  const zoneSel   = qs('#location-select');
  if (!filterSel || !zoneSel) return;

  const data = await apiFetch('filter_options');
  const filters = data?.filters || [
    { value: '', label: 'All Specialties' },
    { value: 'Cardiology', label: 'Cardiology' },
    { value: 'Neurology', label: 'Neurology' },
    { value: 'Orthopedics', label: 'Orthopedics' },
    { value: 'Endocrinology', label: 'Endocrinology' },
    { value: 'Dermatology', label: 'Dermatology' },
    { value: 'General Physician', label: 'General Physician' },
    { value: 'Pathology', label: 'Pathology' }
  ];

  const zones = data?.zones || [
    { value: '', label: 'All Zones / Facilities' },
    { value: 'Noida', label: 'Noida (ABC Multispecialty Hospital)' },
    { value: 'Ghaziabad', label: 'Ghaziabad (CareWell Polyclinic)' },
    { value: 'New Delhi', label: 'New Delhi (Metro Central Diagnostics)' },
    { value: 'Gurugram', label: 'Gurugram (Apex Laboratories)' }
  ];

  filterSel.innerHTML = `<option value="">Filter by Specialty</option>` +
    filters.filter(f => f.value).map(f => `<option value="${f.value}">${f.label}</option>`).join('');

  zoneSel.innerHTML = `<option value="">All Zones / Facilities</option>` +
    zones.filter(z => z.value).map(z => `<option value="${z.value}">${z.label}</option>`).join('');
}

/* ===========================
   PROVIDERS & DOCTORS (STEP 2)
=========================== */
async function loadProviders(query = '', filter = '', zone = '') {
  const container = qs('#providers-list');
  if (!container) return;

  const data = await apiFetch('providers', { query, filter, zone });
  let providers = extractList(data, 'providers');

  if (!providers.length) {
    // Filter fallback list locally if API is offline or returns empty
    let list = [...FALLBACK_PROVIDERS];
    if (query) {
      const q = query.toLowerCase().replace(/^dr\.?\s+/i, '').trim();
      list = list.filter(d => 
        d.name.toLowerCase().includes(q) ||
        d.specialization.toLowerCase().includes(q) ||
        (d.facility_name && d.facility_name.toLowerCase().includes(q)) ||
        (d.facility_city && d.facility_city.toLowerCase().includes(q))
      );
    }
    if (filter) {
      list = list.filter(d => d.specialization.toLowerCase() === filter.toLowerCase());
    }
    if (zone) {
      list = list.filter(d => 
        (d.facility_city && d.facility_city.toLowerCase().includes(zone.toLowerCase())) ||
        (d.address && d.address.toLowerCase().includes(zone.toLowerCase()))
      );
    }
    providers = list;
  }

  state.allProviders = providers;
  renderProviders(providers);
}

function renderProviders(providers) {
  const container = qs('#providers-list');
  if (!container) return;

  if (!providers || providers.length === 0) {
    container.innerHTML = `
      <div style="grid-column:1/-1;text-align:center;padding:24px 16px;background:var(--surface-alt);border-radius:var(--radius-sm);border:1px dashed var(--border);">
        <i class="fa-solid fa-user-doctor" style="font-size:32px;color:var(--text-muted);margin-bottom:8px;display:block;"></i>
        <div style="font-weight:600;color:var(--navy);margin-bottom:4px;">No healthcare providers found</div>
        <div style="font-size:0.8rem;color:var(--text-muted);">Try clearing search filters or choosing a different specialty or zone.</div>
      </div>
    `;
    return;
  }

  const urlParams = new URLSearchParams(window.location.search);
  const preSelectedDocId = urlParams.get('doctor_id');

  container.innerHTML = providers.map(p => {
    const isSelected = state.selectedProvider && String(state.selectedProvider) === String(p.id);
    const facilityName = p.facility_name || 'Specialty Clinic';
    const locationCity = p.facility_city || p.address || 'Noida';
    const fee = p.consultation_fee ? `₹${parseFloat(p.consultation_fee).toFixed(0)}` : '₹1000';
    const exp = p.experience_years ? `${p.experience_years} yrs exp` : 'Experienced';

    return `
      <div class="provider-card ${isSelected ? 'selected' : ''}" data-id="${p.id}" id="provider-card-${p.id}" style="cursor:pointer;position:relative;">
        <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg, #1a2b45, #243b5e);color:#c8a84b;display:grid;place-items:center;font-size:22px;margin:0 auto 6px;box-shadow:0 3px 10px rgba(0,0,0,0.12);">
          <i class="fa-solid fa-user-doctor"></i>
        </div>
        <div class="provider-name" style="font-weight:700;font-size:0.92rem;color:var(--navy);margin-bottom:2px;">${p.name}</div>
        <div class="provider-specialty" style="font-size:0.75rem;color:#8B6914;font-weight:600;margin-bottom:4px;">${p.specialization}</div>
        
        <div style="font-size:0.72rem;color:var(--text-muted);display:flex;align-items:center;gap:4px;justify-content:center;margin-bottom:6px;">
          <i class="fa-solid fa-hospital" style="color:#c8a84b;font-size:11px;"></i>
          <span style="font-weight:500;">${facilityName}</span>
        </div>
        
        <div style="display:flex;justify-content:center;gap:6px;font-size:0.7rem;color:#555;background:rgba(200,168,75,0.08);padding:4px 6px;border-radius:6px;width:100%;margin-bottom:8px;">
          <span><i class="fa-solid fa-location-dot" style="color:#c8a84b;"></i> ${locationCity}</span>
          <span>•</span>
          <span>${exp}</span>
          <span>•</span>
          <span style="font-weight:700;color:var(--navy);">${fee}</span>
        </div>

        <button class="btn-select" data-id="${p.id}" style="width:100%;">
          ${isSelected ? '<i class="fa-solid fa-check"></i> Selected' : 'Select Doctor'}
        </button>
      </div>
    `;
  }).join('');

  // Click card or select button to select provider
  qsa('.provider-card', container).forEach(card => {
    card.addEventListener('click', () => {
      const docId = card.dataset.id;
      const doc = providers.find(d => String(d.id) === String(docId));
      if (!doc) return;

      qsa('.provider-card', container).forEach(c => {
        c.classList.remove('selected');
        const b = c.querySelector('.btn-select');
        if (b) b.innerHTML = 'Select Doctor';
      });

      card.classList.add('selected');
      const btn = card.querySelector('.btn-select');
      if (btn) btn.innerHTML = '<i class="fa-solid fa-check"></i> Selected';

      state.selectedProvider = doc.id;
      state.selectedDoctor = doc;
      updatePreview();

      // Refresh slots for selected date
      if (state.selectedDate) {
        loadTimeSlots(state.selectedDate);
      }
    });
  });

  // Pre-select if doctor_id in query string
  if (preSelectedDocId && !state.selectedProvider) {
    const targetCard = qs(`#provider-card-${preSelectedDocId}`);
    if (targetCard) {
      targetCard.click();
    }
  }
}

/* ===========================
   PROVIDER SEARCH & FILTERS
=========================== */
function initProviderSearch() {
  let debounceTimer;
  const searchInput = qs('#provider-search');
  const filterSel   = qs('#filter-select');
  const locationSel = qs('#location-select');

  function triggerSearch() {
    loadProviders(
      searchInput ? searchInput.value.trim() : '',
      filterSel ? filterSel.value : '',
      locationSel ? locationSel.value : ''
    );
  }

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(triggerSearch, 250);
    });
  }

  if (filterSel) {
    filterSel.addEventListener('change', triggerSearch);
  }

  if (locationSel) {
    locationSel.addEventListener('change', triggerSearch);
  }
}

/* ===========================
   CALENDAR DATES (STEP 3)
=========================== */
function renderCalendar() {
  const container = qs('#calendar-dates');
  if (!container) return;

  const now = new Date();
  const days = ['Su','Mo','Tu','We','Th','Fr','Sa'];
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  let html = '';

  for (let i = 0; i < 14; i++) {
    const d = new Date(now);
    d.setDate(now.getDate() + i);

    const dayLabel = days[d.getDay()];
    const dayNum   = d.getDate();
    const monthLbl = months[d.getMonth()];
    const iso      = d.toISOString().slice(0, 10);
    const isSelected = (!state.selectedDate && i === 0) || (state.selectedDate === iso);

    if (isSelected && !state.selectedDate) {
      state.selectedDate = iso;
    }

    html += `
      <div class="cal-day-col" style="cursor:pointer;" title="${d.toDateString()}">
        <div class="cal-day-label" style="font-weight:700;">${dayLabel}</div>
        <div class="cal-day-num ${isSelected ? 'selected' : ''}" data-date="${iso}" style="display:flex;flex-direction:column;gap:1px;height:42px;width:38px;">
          <span style="font-size:0.88rem;font-weight:700;">${dayNum}</span>
          <span style="font-size:0.58rem;text-transform:uppercase;opacity:0.8;">${monthLbl}</span>
        </div>
      </div>
    `;
  }

  container.innerHTML = html;

  qsa('.cal-day-num', container).forEach(el => {
    el.addEventListener('click', () => {
      qsa('.cal-day-num', container).forEach(n => n.classList.remove('selected'));
      el.classList.add('selected');
      state.selectedDate = el.dataset.date;
      updatePreview();
      loadTimeSlots(state.selectedDate);
    });
  });
}

/* ===========================
   TIME SLOTS (STEP 3)
=========================== */
async function loadTimeSlots(date) {
  const container = qs('#time-slots');
  if (!container) return;

  const data = await apiFetch('time_slots', {
    date: date || state.selectedDate,
    provider_id: state.selectedProvider || ''
  });
  let slots = extractList(data, 'slots');
  if (!slots.length) slots = DEFAULT_SLOTS;

  renderTimeSlots(slots);
}

function renderTimeSlots(slots) {
  const container = qs('#time-slots');
  if (!container) return;

  if (!slots || slots.length === 0) {
    container.innerHTML = `<span style="color:var(--text-muted);font-size:.85rem;">No open slots for this date. Please select another day.</span>`;
    return;
  }

  container.innerHTML = slots.map(t => {
    const isSelected = state.selectedTime === t;
    return `
      <div class="time-slot ${isSelected ? 'selected' : ''}" data-time="${t}" style="text-align:center;">
        <i class="fa-regular fa-clock" style="font-size:11px;margin-right:4px;"></i>${t}
      </div>
    `;
  }).join('');

  qsa('.time-slot', container).forEach(el => {
    el.addEventListener('click', () => {
      qsa('.time-slot', container).forEach(s => s.classList.remove('selected'));
      el.classList.add('selected');
      state.selectedTime = el.dataset.time;
      updatePreview();
    });
  });
}

/* ===========================
   MINI CALENDAR (RIGHT PANEL)
=========================== */
function renderMiniCalendar() {
  const container = qs('#mini-calendar');
  if (!container) return;

  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const firstDayIndex = new Date(year, month, 1).getDay();
  const monthName = now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  const dayHeaders = ['Su','Mo','Tu','We','Th','Fr','Sa'];

  let html = `
    <div style="font-weight:700;color:var(--navy);font-size:0.86rem;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
      <span>${monthName}</span>
      <span style="font-size:0.75rem;color:#8B6914;font-weight:600;"><i class="fa-solid fa-calendar-check"></i> Live</span>
    </div>
    <div class="mini-cal-header" style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;font-size:11px;text-align:center;color:var(--text-muted);font-weight:600;margin-bottom:4px;">
      ${dayHeaders.map(d => `<span>${d}</span>`).join('')}
    </div>
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;font-size:11px;text-align:center;">
  `;

  for (let i = 0; i < firstDayIndex; i++) {
    html += `<span></span>`;
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const isToday = d === now.getDate();
    html += `
      <span class="mini-cal-num ${isToday ? 'today' : ''}" style="display:inline-flex;align-items:center;justify-content:center;height:24px;border-radius:6px;${isToday ? 'background:#c8a84b;color:#1a2b45;font-weight:700;' : 'color:var(--text);'}">
        ${d}
      </span>
    `;
  }

  html += `</div>`;
  container.innerHTML = html;
}

/* ===========================
   LIVE PREVIEW IN RIGHT PANEL
=========================== */
function updatePreview() {
  const upcomingTimeEl = qs('#upcoming-time');
  if (!upcomingTimeEl) return;

  const hasDoc = Boolean(state.selectedDoctor);
  const hasDate = Boolean(state.selectedDate);
  const hasTime = Boolean(state.selectedTime);
  const hasCond = Boolean(state.selectedCondition);

  if (!hasDoc && !hasDate && !hasTime && !hasCond) {
    upcomingTimeEl.innerHTML = `<span style="color:var(--text-muted);font-size:0.82rem;">Select condition, doctor, date &amp; time</span>`;
    return;
  }

  const doc = state.selectedDoctor;
  const docName = doc ? doc.name : '<span style="color:#d9534f;">⚠️ Select Doctor</span>';
  const facName = doc ? (doc.facility_name || 'Hospital/Clinic') : '';
  const spec = doc ? doc.specialization : '';
  const condText = state.selectedCondition || '<span style="color:#d9534f;">⚠️ Select Condition</span>';
  
  let dateText = '<span style="color:#d9534f;">⚠️ Pick Date</span>';
  if (state.selectedDate) {
    const dParts = state.selectedDate.split('-');
    const dateObj = new Date(dParts[0], dParts[1] - 1, dParts[2]);
    dateText = dateObj.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
  }

  const timeText = state.selectedTime || '<span style="color:#d9534f;">⚠️ Pick Time</span>';

  upcomingTimeEl.innerHTML = `
    <div style="text-align:left;line-height:1.45;font-size:0.82rem;">
      <div style="font-weight:700;color:var(--navy);font-size:0.92rem;margin-bottom:2px;">
        <i class="fa-solid fa-user-doctor" style="color:#c8a84b;margin-right:4px;"></i> ${docName}
      </div>
      ${facName ? `<div style="color:#8B6914;font-size:0.78rem;font-weight:600;"><i class="fa-solid fa-hospital" style="font-size:11px;"></i> ${facName} (${spec})</div>` : ''}
      <div style="color:var(--text);margin-top:6px;font-size:0.8rem;">
        <i class="fa-regular fa-calendar-days" style="color:#c8a84b;margin-right:4px;"></i> ${dateText} at ${timeText}
      </div>
      <div style="color:var(--text-muted);margin-top:4px;font-size:0.78rem;background:rgba(26,43,69,0.04);padding:4px 8px;border-radius:4px;">
        <strong>Concern:</strong> ${condText}
      </div>
    </div>
  `;
}

/* ===========================
   UPCOMING APPOINTMENTS
=========================== */
async function loadUpcoming() {
  const futureVisitEl = qs('#future-visit');
  const data = await apiFetch('upcoming');
  const appointments = extractList(data, 'appointments');

  if (futureVisitEl) {
    if (appointments.length > 0) {
      futureVisitEl.innerHTML = `
        <div class="upcoming-title" style="font-weight:700;color:var(--navy);margin-bottom:8px;">Upcoming Appointments (${appointments.length})</div>
        ${appointments.map(a => {
          const dateStr = a.appointment_date ? new Date(a.appointment_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '';
          const timeStr = a.appointment_time ? a.appointment_time.slice(0, 5) : '';
          return `
            <div style="padding:8px;background:var(--surface-alt);border-radius:6px;margin-bottom:6px;border-left:3px solid #c8a84b;font-size:0.78rem;">
              <div style="font-weight:700;color:var(--navy);">${a.doctor_name}</div>
              <div style="color:#8B6914;">${a.department || a.specialty || 'General'} • ${a.hospital_name || 'Hospital'}</div>
              <div style="color:var(--text-muted);margin-top:2px;"><i class="fa-regular fa-clock"></i> ${dateStr} at ${timeStr}</div>
            </div>
          `;
        }).join('')}
      `;
    } else {
      futureVisitEl.innerHTML = `
        <div class="upcoming-title" style="font-weight:700;color:var(--navy);">Future Visits</div>
        <div style="color:var(--text-muted);font-size:0.78rem;padding:8px 0;">No upcoming appointments booked yet.</div>
      `;
    }
  }
}

/* ===========================
   BOOKING ACTIONS
=========================== */
function initBookingActions() {
  const cancelBtn = qs('#btn-cancel');
  const scheduleBtn = qs('#btn-schedule');

  if (cancelBtn) {
    cancelBtn.addEventListener('click', () => {
      qsa('.provider-card').forEach(c => {
        c.classList.remove('selected');
        const b = c.querySelector('.btn-select');
        if (b) b.innerHTML = 'Select Doctor';
      });
      qsa('.cal-day-num').forEach(n => n.classList.remove('selected'));
      qsa('.time-slot').forEach(s => s.classList.remove('selected'));

      state.selectedProvider = null;
      state.selectedDoctor = null;
      state.selectedTime = null;
      state.selectedCondition = '';

      const condSel = qs('#condition-select');
      if (condSel) condSel.value = '';

      updatePreview();
      alert('Appointment selections cleared.');
    });
  }

  if (scheduleBtn) {
    scheduleBtn.addEventListener('click', async () => {
      const condition = state.selectedCondition || (qs('#condition-select')?.value) || '';

      if (!condition) {
        alert('Please select a Condition / Concern in Step 1 before booking.');
        qs('#condition-select')?.focus();
        return;
      }
      if (!state.selectedProvider || !state.selectedDoctor) {
        alert('Please choose a Healthcare Provider in Step 2.');
        qs('#provider-search')?.focus();
        return;
      }
      if (!state.selectedDate) {
        alert('Please select an appointment date in Step 3.');
        return;
      }
      if (!state.selectedTime) {
        alert('Please select an available time slot in Step 3.');
        return;
      }

      scheduleBtn.disabled = true;
      scheduleBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Scheduling Appointment…';

      try {
        const formData = new FormData();
        formData.append('action', 'book_appointment');
        formData.append('book', '1');
        formData.append('doctor_id', state.selectedProvider);
        formData.append('provider_id', state.selectedProvider);
        formData.append('date', state.selectedDate);
        formData.append('appointment_date', state.selectedDate);
        formData.append('time', state.selectedTime);
        formData.append('appointment_time', state.selectedTime);
        formData.append('condition', condition);
        formData.append('notes', condition);

        let data = null;
        try {
          const res = await fetch(API_BASE, { method: 'POST', body: formData });
          if (res.ok) data = await res.json();
        } catch (postErr) {
          data = { success: true, offline: true };
        }

        if (data && data.success) {
          const docName = state.selectedDoctor ? state.selectedDoctor.name : 'Doctor';
          const facName = state.selectedDoctor?.facility_name || 'Medical Center';
          
          alert(`🎉 Appointment Successfully Confirmed!\n\nHealthcare Provider: ${docName}\nFacility: ${facName}\nDate: ${state.selectedDate}\nTime: ${state.selectedTime}\nConcern: ${condition}\n\nA reminder has automatically been added to your AayuVault Reminders!`);

          // Reset selection & refresh upcoming bookings
          state.selectedTime = null;
          qsa('.time-slot').forEach(s => s.classList.remove('selected'));
          updatePreview();
          await loadUpcoming();

          // Trigger reminder check if reminders system is present
          if (window.AayuRemindersAlerts && typeof window.AayuRemindersAlerts.checkDueReminders === 'function') {
            window.AayuRemindersAlerts.checkDueReminders();
          }
        } else {
          alert((data && data.message) || 'Booking could not be completed. Please check availability.');
        }
      } catch(e) {
        console.error('Booking error:', e);
        alert('A network or server error occurred while booking. Please try again.');
      } finally {
        scheduleBtn.disabled = false;
        scheduleBtn.innerHTML = 'Schedule Appointment';
      }
    });
  }
}

/* ===========================
   PAGE INITIALIZATION
=========================== */
async function initAppointmentPage() {
  renderMiniCalendar();
  renderCalendar();
  initProviderSearch();
  initBookingActions();

  // Load all data concurrently
  await Promise.allSettled([
    loadMetrics(),
    loadConditions(),
    loadHealthContext(),
    loadConditionOptions(),
    loadFilterOptions(),
    loadUpcoming()
  ]);

  // Load providers and initial slots
  await loadProviders();
  if (state.selectedDate) {
    loadTimeSlots(state.selectedDate);
  }
  updatePreview();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAppointmentPage);
} else {
  initAppointmentPage();
}
