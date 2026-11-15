/* ============================================================
   records.js — AayuVault Medical Records & AI Analysis
   ============================================================ */

// ── RECORD TYPES SIDEBAR ──────────────────────────────────────
const recordTypes = [
  { label: "All Records",          type: "all",         active: true },
  { label: "Prescriptions",        type: "prescription",active: false },
  { label: "Lipid Profiles",       type: "lipid",       active: false },
  { label: "Diabetes & Glycemic",  type: "glycemic",    active: false },
  { label: "Complete Blood Count", type: "hematology",  active: false },
  { label: "Metabolic & Organs",   type: "metabolic",   active: false },
];

const syncProviders = [
  { abbr: "Q+",     name: "Quest Diagnostics", synced: true  },
  { abbr: "LC",     name: "LabCorp",           synced: true  },
  { abbr: "NS",     name: "NaviSite Health",   synced: true  },
  { abbr: "NC",     name: "NovaCare",          synced: true  },
  { abbr: "AB",     name: "ABDM Health Net",   synced: true  },
];

const syncStatuses = [
  { text: "Quest Diagnostics – Synced 1 hour ago",  ok: true },
  { text: "LabCorp Diagnostics – Synced 3 hours ago", ok: true },
  { text: "NaviSite Health Labs – Synced yesterday",  ok: true },
];

let allLoadedRecords = [];

// ── SVG ICONS ────────────────────────────────────────────────
function svg(paths, opts = {}) {
  const { w = 24, h = 24, sw = 2, fill = "none", stroke = "currentColor" } = opts;
  return `<svg viewBox="0 0 ${w} ${h}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}">${paths}</svg>`;
}

const icons = {
  lab:          svg('<path d="M9 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2h-4"/><path d="M9 3a2 2 0 004 0"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/>'),
  pdf:          svg('<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>'),
  doctor:       svg('<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>'),
  text:         svg('<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>'),
  prescription: svg('<path d="M19 14V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6"/><path d="M17 19v-6"/><path d="M14 16h6"/><path d="M9 8h6"/><path d="M9 12h4"/>'),
};

// ── RENDER: RECORD TYPES ──────────────────────────────────────
function renderRecordTypes() {
  const ul = document.getElementById("record-types");
  if (!ul) return;
  ul.innerHTML = "";

  recordTypes.forEach((rt) => {
    const li = document.createElement("li");
    li.className = `record-type-item${rt.active ? " active" : ""}`;
    li.innerHTML = `
      <span>${rt.label}</span>
      <i class="fa-solid fa-chevron-right" style="font-size:11px;opacity:0.6;"></i>
    `;
    li.addEventListener("click", () => {
      document.querySelectorAll(".record-type-item").forEach(el => el.classList.remove("active"));
      li.classList.add("active");
      filterRecordsByType(rt.type);
    });
    ul.appendChild(li);
  });
}

function filterRecordsByType(type) {
  if (type === 'all') {
    renderTimeline(allLoadedRecords);
    return;
  }
  const filtered = allLoadedRecords.filter(r => {
    const title = (r.title || '').toLowerCase();
    const sub = (r.subtitle || '').toLowerCase();
    const recType = (r.type || '').toLowerCase();
    if (type === 'prescription') {
      return recType === 'prescription' || title.includes('prescription') || title.includes('rx') || sub.includes('rx') || sub.includes('tab') || sub.includes('mg');
    }
    if (type === 'lipid') return title.includes('lipid') || sub.includes('cholesterol');
    if (type === 'glycemic') return title.includes('glycemic') || title.includes('hba1c') || sub.includes('glucose');
    if (type === 'hematology') return title.includes('blood count') || title.includes('cbc') || sub.includes('hemoglobin');
    if (type === 'metabolic') return title.includes('metabolic') || title.includes('liver') || sub.includes('creatinine');
    return true;
  });
  renderTimeline(filtered);
}

// ── RENDER: TIMELINE ENTRY ────────────────────────────────────
function buildEntry(rec) {
  const entry = document.createElement("div");
  entry.className = "tl-entry";
  entry.dataset.title = (rec.title || "").toLowerCase();
  entry.dataset.subtitle = (rec.subtitle || "").toLowerCase();
  entry.dataset.date = (rec.date || "").toLowerCase();
  entry.dataset.id = rec.id || 1;

  const isPrescription = (rec.type === 'prescription' || (rec.title || '').toLowerCase().includes('prescription'));
  const typeBadge = isPrescription 
    ? `<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;margin-left:8px;border:1px solid #fde68a;"><i class="fa-solid fa-pills"></i> Prescription (Rx)</span>`
    : `<span style="background:#ecfdf5;color:#065f46;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;margin-left:8px;border:1px solid #a7f3d0;"><i class="fa-solid fa-flask"></i> Verified Report</span>`;

  const docLine = (rec.doctor || rec.facility) ? `
    <div style="font-size:0.8rem;color:#4b5563;margin:4px 0 6px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      ${rec.doctor ? `<span><i class="fa-solid fa-user-doctor" style="color:#c49a2a;margin-right:4px;"></i><strong>${rec.doctor}</strong></span>` : ''}
      ${rec.facility ? `<span><i class="fa-solid fa-hospital" style="color:#c49a2a;margin-right:4px;"></i>${rec.facility}</span>` : ''}
    </div>` : '';

  const pdfBlock = (rec.hasPdf || rec.has_pdf) ? `
    <div class="pdf-preview" style="cursor:pointer;" onclick="viewRecordDoc(${rec.id || 1})">
      <div class="pdf-icon">${icons.pdf}</div>
      <div class="pdf-meta">
        <strong>${rec.title} – ${isPrescription ? 'Stored Prescription Document' : 'Clinical Diagnostic Report'}</strong>
        Document stored & synchronized in Vault • Click to view
      </div>
    </div>` : "";

  const dateFormatted = (rec.date || "Recent").replace("\\n", "<br>").replace("\n", "<br>");

  const actionButtons = isPrescription ? `
    <button class="btn-ai" style="background:#0d1b2a;" onclick="viewRecordDetails(${rec.id || 1})">
      <i class="fa-solid fa-prescription-bottle-medical" style="color:#C9A84C;"></i> View Prescription
    </button>
    <button class="btn-doctor" onclick="openDoctorDiscussion(${rec.id || 1})">
      ${icons.doctor} Doctor Guide
    </button>
    <button class="btn-text" onclick="AayuAIReportModal.showFromRecordId(${rec.id || 1})">
      <i class="fa-solid fa-shield-heart" style="color:var(--gold);"></i> Vital Score
    </button>
  ` : `
    <button class="btn-ai" onclick="AayuAIReportModal.showFromRecordId(${rec.id || 1})">
      <i class="fa-solid fa-wand-magic-sparkles"></i> AI Health Analysis
    </button>
    <button class="btn-doctor" onclick="openDoctorDiscussion(${rec.id || 1})">
      ${icons.doctor} Doctor Guide
    </button>
    <button class="btn-text" onclick="AayuAIReportModal.showFromRecordId(${rec.id || 1})">
      <i class="fa-solid fa-file-waveform" style="color:var(--gold);"></i> Vital Score
    </button>
  `;

  entry.innerHTML = `
    <div class="tl-date">${dateFormatted}</div>
    <div class="tl-dot"></div>
    <div class="tl-body">
      <div class="tl-record-header" style="display:flex;align-items:center;flex-wrap:wrap;">
        <div class="tl-record-icon">${icons[rec.type] || icons.lab}</div>
        <div class="tl-record-title">${rec.title}</div>
        ${typeBadge}
      </div>
      ${docLine}
      <div class="tl-record-sub">${rec.subtitle || 'Clinical laboratory analysis'}</div>
      ${pdfBlock}
      <div class="tl-actions">
        ${actionButtons}
      </div>
    </div>
  `;
  return entry;
}

function openDoctorDiscussion(recordId) {
  AayuAIReportModal.showFromRecordId(recordId);
  setTimeout(() => {
    AayuAIReportModal.switchTab('doctor');
  }, 100);
}

function renderTimeline(records) {
  const timeline = document.getElementById("timeline");
  const empty = document.getElementById("timeline-empty");
  if (!timeline) return;

  timeline.innerHTML = "";

  if (!records || records.length === 0) {
    if (empty) empty.classList.remove("hidden");
    return;
  }
  if (empty) empty.classList.add("hidden");

  records.forEach((rec, i) => {
    const entry = buildEntry(rec);
    entry.style.animationDelay = `${0.05 + i * 0.06}s`;
    timeline.appendChild(entry);
  });
}

// ── SEARCH ───────────────────────────────────────────────────
function initSearch() {
  const input = document.getElementById("search-input");
  const btn = document.getElementById("search-btn");
  if (!input) return;

  function doSearch() {
    const q = input.value.trim().toLowerCase();
    const entries = document.querySelectorAll(".tl-entry");
    let visible = 0;
    entries.forEach(entry => {
      const matches =
        entry.dataset.title.includes(q) ||
        entry.dataset.subtitle.includes(q) ||
        entry.dataset.date.includes(q);
      entry.style.display = (q === "" || matches) ? "" : "none";
      if (entry.style.display !== "none") visible++;
    });
    const empty = document.getElementById("timeline-empty");
    if (empty) empty.classList.toggle("hidden", visible > 0 || q === "");
  }

  if (btn) btn.addEventListener("click", doSearch);
  input.addEventListener("keyup", e => { if (e.key === "Enter") doSearch(); });
  input.addEventListener("input", doSearch);
}

// ── RENDER SYNC PROVIDERS & STATUSES ─────────────────────────
function renderSyncProviders() {
  const wrap = document.getElementById("sync-providers");
  if (!wrap) return;
  wrap.innerHTML = "";
  syncProviders.forEach(p => {
    const badge = document.createElement("div");
    badge.className = "provider-badge";
    badge.title = p.name;
    badge.innerHTML = `
      ${p.abbr}
      ${p.synced ? `<span class="sync-check">✓</span>` : ""}
    `;
    wrap.appendChild(badge);
  });
}

function renderSyncStatuses() {
  const list = document.getElementById("sync-status-list");
  if (!list) return;
  list.innerHTML = "";
  syncStatuses.forEach(s => {
    const item = document.createElement("div");
    item.className = "sync-status-item";
    item.innerHTML = `
      <span class="sync-dot">✓</span>
      <span>${s.text}</span>
    `;
    list.appendChild(item);
  });
}

// ── RENDER AI INSIGHTS ───────────────────────────────────────
async function loadInsights() {
  const ul = document.getElementById("insights-list");
  if (!ul) return;

  try {
    const res = await fetch('../pages/records.php?action=get_insights');
    const data = await res.json();
    if (data && data.insights) {
      ul.innerHTML = data.insights.map(ins => `
        <li class="insight-item">
          <div class="insight-icon">${ins.icon || '🩺'}</div>
          <div>
            <strong style="display:block;font-size:0.84rem;color:#1b263b;margin-bottom:2px;">${ins.title}</strong>
            <span style="font-size:0.8rem;color:#4b5563;">${ins.text}</span>
          </div>
        </li>
      `).join('');
      return;
    }
  } catch (e) {}

  ul.innerHTML = `
    <li class="insight-item">
      <div class="insight-icon">🫀</div>
      <div>
        <strong style="display:block;font-size:0.84rem;color:#1b263b;margin-bottom:2px;">Cardiovascular Alert</strong>
        <span style="font-size:0.8rem;color:#4b5563;">Total Cholesterol is 218 mg/dL with LDL at 138 mg/dL. Increasing dietary soluble fiber by 10g/day is recommended.</span>
      </div>
    </li>
    <li class="insight-item">
      <div class="insight-icon">🩸</div>
      <div>
        <strong style="display:block;font-size:0.84rem;color:#1b263b;margin-bottom:2px;">Glycemic Advisory</strong>
        <span style="font-size:0.8rem;color:#4b5563;">HbA1c is 5.9% (Pre-diabetic zone). A 15-minute post-meal brisk walk improves insulin sensitivity.</span>
      </div>
    </li>
    <li class="insight-item">
      <div class="insight-icon">🛡️</div>
      <div>
        <strong style="display:block;font-size:0.84rem;color:#1b263b;margin-bottom:2px;">Organ Health</strong>
        <span style="font-size:0.8rem;color:#4b5563;">Liver enzymes and Renal eGFR (>90 mL/min) are in optimal condition with excellent filtration.</span>
      </div>
    </li>
  `;
}

// ── UPLOAD HANDLER FOR RECORDS PAGE ──────────────────────────
async function handleRecordUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  const statusEl = document.getElementById("recordUploadStatus");
  if (statusEl) {
    statusEl.style.display = "block";
    statusEl.style.background = "#fef3c7";
    statusEl.style.color = "#92400e";
    statusEl.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Uploading & Analyzing "<strong>${file.name}</strong>" with OpenAI...`;
  }

  const formData = new FormData();
  formData.append('report', file);

  try {
    const res = await fetch('../pages/upload.php', {
      method: 'POST',
      body: formData,
    });
    const data = await res.json();

    if (data && data.success && data.analysis) {
      if (statusEl) {
        statusEl.style.background = "#d1fae5";
        statusEl.style.color = "#065f46";
        statusEl.innerHTML = `✅ "<strong>${file.name}</strong>" successfully analyzed! Vital Score: <strong>${data.analysis.vital_score}/100</strong>`;
      }

      const newRec = {
        id: data.analysis.record_id || Date.now(),
        date: "Today",
        title: data.analysis.report_title || file.name,
        subtitle: data.analysis.summary_text || "AI Analyzed Record",
        type: "lab",
        hasPdf: true,
        has_pdf: true,
      };
      allLoadedRecords.unshift(newRec);
      renderTimeline(allLoadedRecords);

      setTimeout(() => {
        AayuAIReportModal.show(data.analysis);
      }, 500);
    } else {
      throw new Error(data.message || 'Upload failed');
    }
  } catch (err) {
    if (statusEl) {
      statusEl.style.background = "#fee2e2";
      statusEl.style.color = "#991b1b";
      statusEl.innerHTML = `❌ Error: ${err.message || 'Upload failed'}`;
    }
  }
}

// ── PASTE REPORT TEXT HANDLERS ──────────────────────────────
window.openPasteReportModal = function() {
  const modal = document.getElementById('pasteReportModal');
  if (modal) modal.style.display = 'flex';
};

window.submitPastedReport = async function() {
  const title = document.getElementById('pasteReportTitle').value.trim() || 'Pasted Medical Lab Report';
  const text = document.getElementById('pasteReportContent').value.trim();
  const statusEl = document.getElementById('pasteModalStatus');

  if (!text) {
    alert('Please enter or paste your medical report text.');
    return;
  }

  statusEl.style.display = 'block';
  statusEl.style.background = '#eff6ff';
  statusEl.style.color = '#1e40af';
  statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> OpenAI is parsing biomarkers and evaluating clinical ranges...';

  try {
    const res = await fetch('../pages/analyze_report.php?action=analyze_text', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: title, text: text })
    });
    const data = await res.json();

    if (data && data.success && data.analysis) {
      statusEl.style.background = '#ecfdf5';
      statusEl.style.color = '#065f46';
      statusEl.innerHTML = `✅ Successfully extracted <strong>${data.analysis.biomarkers?.length || 0} biomarkers</strong>!`;

      const newRec = {
        id: data.analysis.record_id || Date.now(),
        date: "Today",
        title: data.analysis.report_title || title,
        subtitle: data.analysis.summary_text || "Custom Clinical Report Analyzed",
        type: "lab",
        hasPdf: true,
        has_pdf: true,
      };
      allLoadedRecords.unshift(newRec);
      renderTimeline(allLoadedRecords);

      setTimeout(() => {
        document.getElementById('pasteReportModal').style.display = 'none';
        AayuAIReportModal.show(data.analysis);
      }, 700);
    } else {
      throw new Error(data.error || 'Failed to analyze report text.');
    }
  } catch (err) {
    statusEl.style.background = '#fef2f2';
    statusEl.style.color = '#991b1b';
    statusEl.innerHTML = `❌ Error: ${err.message}`;
  }
};

// ── FETCH RECORDS FROM API ────────────────────────────────────
async function loadRecords() {
  try {
    const res = await fetch('../pages/records.php?action=get_records');
    if (!res.ok) throw new Error("Network response not ok");
    const data = await res.json();
    allLoadedRecords = data.records || [];
    renderTimeline(allLoadedRecords);
  } catch (err) {
    allLoadedRecords = [
      {
        id: 1,
        date: "Apr 02,\n2025",
        title: "Comprehensive Lipid Panel",
        subtitle: "Total Cholesterol: 218 mg/dL (High), LDL: 138 mg/dL, HDL: 44 mg/dL",
        type: "lab",
        hasPdf: true,
      },
      {
        id: 2,
        date: "Mar 18,\n2025",
        title: "Glycemic & HbA1c Panel",
        subtitle: "HbA1c: 5.9% (Pre-diabetic range), Fasting Glucose: 104 mg/dL",
        type: "lab",
        hasPdf: true,
      },
      {
        id: 3,
        date: "Feb 28,\n2025",
        title: "Complete Blood Count (CBC)",
        subtitle: "Hemoglobin: 14.2 g/dL (Normal), WBC: 6,800 /µL, Platelets: 245k",
        type: "lab",
        hasPdf: true,
      },
      {
        id: 4,
        date: "Jan 15,\n2025",
        title: "Comprehensive Metabolic & Liver Panel",
        subtitle: "ALT/SGPT: 38 U/L, Creatinine: 0.95 mg/dL, eGFR: >90, Bilirubin: 0.8",
        type: "lab",
        hasPdf: true,
      },
      {
        id: 5,
        date: "Apr 05,\n2025",
        title: "Cardiology Prescription (Dr. Priya Sharma)",
        subtitle: "Rx: Atorvastatin 20mg (1 Tab OD at night), Aspirin 75mg (1 Tab OD after lunch). Duration: 30 days.",
        type: "prescription",
        doctor: "Dr. Priya Sharma",
        facility: "Quest Heart Clinic",
        hasPdf: true,
      }
    ];
    renderTimeline(allLoadedRecords);
  }
}

// ── STORE RECORD & PRESCRIPTION MODAL HANDLERS ────────────────
window.openStoreRecordModal = function(defaultType = 'prescription') {
  const modal = document.getElementById('storeRecordModal');
  if (!modal) return;
  document.getElementById('storeRecordForm')?.reset();
  const fileLabel = document.getElementById('storeFileNamePreview');
  if (fileLabel) fileLabel.innerHTML = '<span style="color:#6b7280;"><i class="fa-solid fa-cloud-arrow-up"></i> Click or drag document/slip here (PDF, JPG, PNG)</span>';
  const dateInput = document.getElementById('storeRecordDate');
  if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];
  const statusEl = document.getElementById('storeModalStatus');
  if (statusEl) statusEl.style.display = 'none';
  selectStoreType(defaultType);
  modal.style.display = 'flex';
};

window.closeStoreRecordModal = function() {
  const modal = document.getElementById('storeRecordModal');
  if (modal) modal.style.display = 'none';
};

window.selectStoreType = function(type) {
  document.querySelectorAll('.store-type-pill').forEach(pill => {
    pill.classList.toggle('active', pill.dataset.type === type);
  });
  const typeInput = document.getElementById('storeRecordType');
  if (typeInput) typeInput.value = type;

  const titleInput = document.getElementById('storeRecordTitle');
  const notesLabel = document.getElementById('storeNotesLabel');
  const notesText = document.getElementById('storeRecordNotes');

  if (type === 'prescription') {
    if (titleInput && (!titleInput.value || titleInput.dataset.auto === 'true')) {
      titleInput.placeholder = 'e.g. Cardiology Prescription – Dr. Sharma';
    }
    if (notesLabel) notesLabel.innerHTML = '<i class="fa-solid fa-pills" style="color:#C9A84C;"></i> Prescribed Medicines & Dosage Instructions';
    if (notesText) notesText.placeholder = 'List prescribed medications, dosage (e.g. Atorvastatin 20mg 1 tab OD night, Aspirin 75mg OD after food), duration, refills, or physician guidance...';
  } else {
    if (titleInput && (!titleInput.value || titleInput.dataset.auto === 'true')) {
      titleInput.placeholder = 'e.g. Comprehensive Metabolic Panel (CMP)';
    }
    if (notesLabel) notesLabel.innerHTML = '<i class="fa-solid fa-file-waveform" style="color:#C9A84C;"></i> Report Findings & Lab Observations';
    if (notesText) notesText.placeholder = 'Enter or paste test values, laboratory observations, clinical impressions, or diagnosis...';
  }
};

window.handleStoreFileSelect = function(e) {
  const file = e.target.files[0];
  const preview = document.getElementById('storeFileNamePreview');
  if (!preview) return;
  if (file) {
    preview.innerHTML = `<span style="color:#059669;font-weight:600;"><i class="fa-solid fa-file-circle-check"></i> Attached: <strong>${file.name}</strong> (${(file.size / 1024).toFixed(1)} KB)</span>`;
  } else {
    preview.innerHTML = '<span style="color:#6b7280;"><i class="fa-solid fa-cloud-arrow-up"></i> Click or drag document/slip here (PDF, JPG, PNG)</span>';
  }
};

window.submitStoreRecord = async function() {
  const title = (document.getElementById('storeRecordTitle')?.value || '').trim();
  const type = document.getElementById('storeRecordType')?.value || 'prescription';
  const doctor = (document.getElementById('storeRecordDoctor')?.value || '').trim();
  const facility = (document.getElementById('storeRecordFacility')?.value || '').trim();
  const date = document.getElementById('storeRecordDate')?.value || '';
  const notes = (document.getElementById('storeRecordNotes')?.value || '').trim();
  const fileInput = document.getElementById('storeRecordFile');
  const file = fileInput?.files?.[0];
  const statusEl = document.getElementById('storeModalStatus');

  if (!title) {
    alert('Please enter a Title for this ' + (type === 'prescription' ? 'prescription' : 'medical record') + '.');
    document.getElementById('storeRecordTitle')?.focus();
    return;
  }

  if (statusEl) {
    statusEl.style.display = 'block';
    statusEl.style.background = '#eff6ff';
    statusEl.style.color = '#1e40af';
    statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Storing into your AayuVault...';
  }

  const formData = new FormData();
  formData.append('title', title);
  formData.append('type', type);
  formData.append('doctor', doctor);
  formData.append('facility', facility);
  formData.append('date', date);
  formData.append('notes', notes);
  if (file) {
    formData.append('file', file);
  }

  try {
    const res = await fetch('../pages/records.php?action=store_record', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();

    if (data && data.success && data.record) {
      if (statusEl) {
        statusEl.style.background = '#ecfdf5';
        statusEl.style.color = '#065f46';
        statusEl.innerHTML = `<i class="fa-solid fa-circle-check"></i> ${data.message || 'Saved successfully!'}`;
      }

      allLoadedRecords.unshift(data.record);
      renderTimeline(allLoadedRecords);

      const banner = document.getElementById('recordUploadStatus');
      if (banner) {
        banner.style.display = 'block';
        banner.style.background = '#d1fae5';
        banner.style.color = '#065f46';
        banner.innerHTML = `✅ Successfully stored "<strong>${data.record.title}</strong>" in your Vault!`;
        setTimeout(() => { banner.style.display = 'none'; }, 6000);
      }

      setTimeout(() => {
        closeStoreRecordModal();
      }, 700);
    } else {
      throw new Error(data.message || 'Failed to store record.');
    }
  } catch (err) {
    if (statusEl) {
      statusEl.style.background = '#fef2f2';
      statusEl.style.color = '#991b1b';
      statusEl.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> Error: ${err.message}`;
    }
  }
};

window.viewRecordDoc = function(recordId) {
  const rec = allLoadedRecords.find(r => r.id == recordId);
  if (rec && rec.pdf_path) {
    window.open('../' + rec.pdf_path, '_blank');
  } else {
    AayuAIReportModal.showFromRecordId(recordId);
  }
};

window.viewRecordDetails = function(recordId) {
  const rec = allLoadedRecords.find(r => r.id == recordId);
  if (rec && rec.raw_text) {
    alert(`Prescription Details for ${rec.title}:\n\nDoctor: ${rec.doctor || 'Physician'}\nFacility: ${rec.facility || 'Clinic'}\nDate: ${rec.date_raw || 'Recorded'}\n\nPrescribed Medications / Instructions:\n${rec.raw_text}`);
  } else {
    AayuAIReportModal.showFromRecordId(recordId);
  }
};

// ── INIT ─────────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  renderRecordTypes();
  renderSyncProviders();
  renderSyncStatuses();
  loadInsights();
  loadRecords();
  initSearch();
});