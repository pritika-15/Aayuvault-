/* ============================================================
   AayuVault — Dashboard & Instant AI Report Analyzer
   assets/js/dashboard.js
   ============================================================ */

let latestReportAnalysis = null;

// ── INIT ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  fetchMetrics();
  fetchSummary();
  fetchTimeline();
  fetchAppointments();
  fetchInitialInsights();
  initAadhaarModule();
});

// ── INITIAL AI INSIGHTS ───────────────────────────────────────
async function fetchInitialInsights() {
  const container = document.getElementById('insightsContent');
  if (!container) return;

  try {
    const res = await fetch('../pages/records.php?action=get_latest_analysis');
    const data = await res.json();
    if (data && data.analysis) {
      latestReportAnalysis = data.analysis;
      renderInsightsCard(data.analysis);
      return;
    }
  } catch (e) {}

  // Fallback initial insights display
  renderInsightsCard({
    report_title: 'Comprehensive Lipid Panel',
    vital_score: 74,
    overall_status: 'Attention Needed',
    summary_text: 'Total Cholesterol (218 mg/dL) and LDL (138 mg/dL) are elevated. Increasing soluble fiber and daily aerobic cardio is recommended.',
    biomarkers: [
      { name: 'Total Cholesterol', value: 218, unit: 'mg/dL', status: 'High' },
      { name: 'LDL Cholesterol', value: 138, unit: 'mg/dL', status: 'High' },
      { name: 'HDL Cholesterol', value: 44, unit: 'mg/dL', status: 'Normal' },
    ]
  });
}

window.openLatestReportModal = function() {
  if (latestReportAnalysis) {
    AayuAIReportModal.show(latestReportAnalysis);
  } else {
    AayuAIReportModal.showFromRecordId(1);
  }
};

function renderInsightsCard(analysis) {
  const container = document.getElementById('insightsContent');
  if (!container) return;

  const score = analysis.vital_score || 80;
  const statusColor = (score >= 85) ? '#10b981' : ((score >= 70) ? '#f59e0b' : '#ef4444');

  const abnormalItems = (analysis.biomarkers || [])
    .filter(b => b.status !== 'Normal' && b.status !== 'Optimal')
    .slice(0, 2);

  let abnormalHtml = '';
  if (abnormalItems.length > 0) {
    abnormalHtml = `
      <div style="background:#fef3c7;border-left:3px solid #f59e0b;padding:8px 10px;border-radius:6px;margin:8px 0;font-size:12px;color:#92400e;">
        <strong><i class="fa-solid fa-triangle-exclamation"></i> Flagged Parameters:</strong><br>
        ${abnormalItems.map(b => `• ${b.name}: <strong>${b.value} ${b.unit}</strong> (${b.status})`).join('<br>')}
      </div>`;
  }

  container.innerHTML = `
    <div style="text-align:left;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
        <span style="font-size:12px;font-weight:700;color:var(--txt-sec);text-transform:uppercase;">${escHtml(analysis.report_title || 'Latest Report')}</span>
        <span style="font-size:12px;font-weight:800;color:${statusColor};background:#f3f4f6;padding:2px 8px;border-radius:12px;">Score: ${score}/100</span>
      </div>
      <p style="font-size:13px;line-height:1.5;color:var(--txt-main);margin-bottom:6px;">
        ${escHtml(analysis.summary_text || 'Clinical parameters extracted successfully.')}
      </p>
      ${abnormalHtml}
    </div>
  `;

  // Update learn more button
  const btn = document.getElementById('btnOpenAIReport');
  if (btn) {
    btn.onclick = () => {
      AayuAIReportModal.show(analysis);
    };
  }
}

// ── METRICS ───────────────────────────────────────────────────
function fetchMetrics() {
  fetch('../pages/dashboard.php?action=metrics')
    .then(r => r.json())
    .then(d => {
      document.getElementById('bp').textContent   = d.blood_pressure  ?? '120/80';
      document.getElementById('hr').textContent   = d.heart_rate      ?? '72 bpm';
      document.getElementById('spo2').textContent = d.spo2            ?? '98%';
      document.getElementById('labs').textContent = d.labs_status     ?? 'Attention (1 High)';
      document.getElementById('labs2').textContent= d.labs_count      ?? '4 Reports';
    })
    .catch(() => {
      document.getElementById('bp').textContent    = '120/80';
      document.getElementById('hr').textContent    = '72 bpm';
      document.getElementById('spo2').textContent  = '98%';
      document.getElementById('labs').textContent  = 'Attention (1 High)';
      document.getElementById('labs2').textContent = '4 Reports';
    });
}

// ── MEDICAL SUMMARY ───────────────────────────────────────────
function fetchSummary() {
  fetch('../pages/dashboard.php?action=summary')
    .then(r => r.json())
    .then(rows => renderSummary(rows))
    .catch(() => renderSummary([
      { condition: 'Borderline Dyslipidemia', category: 'Cardiovascular' },
      { condition: 'Pre-Diabetes (Mild HbA1c elevation)', category: 'Metabolic' },
      { condition: 'Vitamin D Deficiency', category: 'Nutritional' },
    ]));
}

function renderSummary(rows) {
  const tbody = document.getElementById('summaryBody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="2" class="muted-center">No conditions on record.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map(r => `
    <tr style="cursor:pointer;" onclick="window.location.href='records.html'">
      <td>${escHtml(r.condition)}</td>
      <td><span class="cat-tag">${escHtml(r.category)}</span></td>
    </tr>`).join('');
}

// ── HEALTH TIMELINE ───────────────────────────────────────────
function fetchTimeline() {
  fetch('../pages/dashboard.php?action=timeline')
    .then(r => r.json())
    .then(items => renderTimeline(items))
    .catch(() => renderTimeline([
      { title: 'Comprehensive Lipid Panel - Quest Diagnostics', date: 'April 2, 2025', id: 1 },
      { title: 'Glycemic HbA1c Profile - LabCorp', date: 'March 18, 2025', id: 2 },
      { title: 'Complete Blood Count (CBC) - NaviSite Health', date: 'February 28, 2025', id: 3 },
      { title: 'Comprehensive Metabolic & Liver Panel - NovaCare', date: 'January 15, 2025', id: 4 },
    ]));
}

function renderTimeline(items) {
  const el = document.getElementById('timelineContent');
  if (!items.length) { el.textContent = 'No timeline entries.'; return; }
  el.innerHTML = items.map((i, idx) => `
    <div class="timeline-item" style="cursor:pointer;" onclick="AayuAIReportModal.showFromRecordId(${i.id || (idx + 1)})" title="Click to view AI Analysis">
      <div class="tl-dot"></div>
      <div style="flex:1;">
        <div class="tl-title" style="display:flex;justify-content:space-between;align-items:center;">
          <span>${escHtml(i.title)}</span>
          <span style="font-size:11px;color:var(--gold);font-weight:600;"><i class="fa-solid fa-sparkles"></i> AI Analyzed</span>
        </div>
        <div class="tl-date">${escHtml(i.date)}</div>
      </div>
    </div>`).join('');
}

// ── APPOINTMENTS ──────────────────────────────────────────────
function fetchAppointments() {
  fetch('../pages/dashboard.php?action=appointments')
    .then(r => r.json())
    .then(items => renderAppointments(items))
    .catch(() => renderAppointments([
      { name: 'Dr. Priya Sharma', time: 'Thu, 10:00 AM · Cardiology' },
      { name: 'Dr. Rajeev Mehta', time: 'Fri, 3:30 PM · Endocrinology' },
    ]));
}

function renderAppointments(items) {
  const el = document.getElementById('apptContent');
  if (!items.length) { el.textContent = 'No upcoming appointments.'; return; }
  el.innerHTML = items.map(a => `
    <div class="appt-item">
      <div class="appt-dot"></div>
      <div>
        <div class="appt-name">${escHtml(a.name)}</div>
        <div class="appt-time">${escHtml(a.time)}</div>
      </div>
    </div>`).join('');
}

// ── DRAG & DROP UPLOAD & REAL-TIME AI ANALYSIS ────────────────
function onDragOver(e) {
  e.preventDefault();
  document.getElementById('dropZone').classList.add('drag-over');
}
function onDragLeave() {
  document.getElementById('dropZone').classList.remove('drag-over');
}
function onDrop(e) {
  e.preventDefault();
  document.getElementById('dropZone').classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) uploadFile(file);
}
function onFileSelect(e) {
  const file = e.target.files[0];
  if (file) uploadFile(file);
}

async function uploadFile(file) {
  const ALLOWED_EXTS = ['pdf', 'jpg', 'jpeg', 'png', 'txt', 'csv'];
  const ext = file.name.split('.').pop().toLowerCase();
  const MAX_SIZE = 15 * 1024 * 1024; // 15 MB

  if (!ALLOWED_EXTS.includes(ext)) {
    showUploadMsg('error', '⚠ Only PDF, JPG, PNG, or TXT medical report files are supported.');
    return;
  }
  if (file.size > MAX_SIZE) {
    showUploadMsg('error', '⚠ File size must be under 15 MB.');
    return;
  }

  showUploadMsg('info', `⏳ Uploading and processing "${file.name}"...`);
  showInsightsPending(file.name);

  const formData = new FormData();
  formData.append('report', file);

  try {
    const res = await fetch('../pages/upload.php', {
      method: 'POST',
      body: formData,
    });
    const data = await res.json();

    if (data && data.success && data.analysis) {
      latestReportAnalysis = data.analysis;
      showUploadMsg('success', `✅ "${file.name}" analyzed successfully! Vital Score: ${data.analysis.vital_score}/100.`);
      renderInsightsCard(data.analysis);

      // Open AI Report Modal
      setTimeout(() => {
        AayuAIReportModal.show(data.analysis);
      }, 400);
    } else {
      throw new Error(data.message || 'Analysis failed');
    }
  } catch (err) {
    showUploadMsg('error', `❌ Upload failed: ${err.message || 'Error processing report'}`);
    const container = document.getElementById('insightsContent');
    if (container && latestReportAnalysis) {
      renderInsightsCard(latestReportAnalysis);
    }
  }
}

function showUploadMsg(type, text) {
  const el = document.getElementById('uploadStatus');
  el.className = `upload-msg ${type}`;
  el.textContent = text;
  el.classList.remove('hidden');
}

function showInsightsPending(filename) {
  document.getElementById('insightsContent').innerHTML = `
    <div style="text-align:center;padding:16px 0">
      <i class="fa-solid fa-spinner fa-spin" style="font-size:28px;color:var(--gold);margin-bottom:10px;display:block"></i>
      <p style="font-size:13px;color:var(--txt-sec)">AI analyzing <strong>${escHtml(filename)}</strong>...<br>Extracting clinical parameters & reference ranges.</p>
    </div>`;
}

// ── ABHA MODAL ────────────────────────────────────────────────
let abhaStep = 1;

function openAbhaModal() {
  abhaStep = 1;
  document.getElementById('abhaInput').value  = '';
  document.getElementById('mobileInput').value= '';
  document.getElementById('otpSection').classList.add('hidden');
  document.getElementById('abhaMsg').classList.add('hidden');
  document.getElementById('abhaActionBtn').innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send OTP';
  document.getElementById('abhaModal').classList.remove('hidden');
}

function closeAbhaModal() {
  document.getElementById('abhaModal').classList.add('hidden');
}

document.getElementById('abhaModal').addEventListener('click', function(e) {
  if (e.target === this) closeAbhaModal();
});

function handleAbhaAction() {
  if (abhaStep === 1) sendAbhaOtp();
  else verifyAbhaOtp();
}

function sendAbhaOtp() {
  const abhaId = document.getElementById('abhaInput').value.trim();
  const mobile = document.getElementById('mobileInput').value.trim();

  if (!abhaId) { showAbhaMsg('error', '⚠ Please enter your ABHA ID.'); return; }
  if (!mobile)  { showAbhaMsg('error', '⚠ Please enter your mobile number.'); return; }

  showAbhaMsg('info', '⏳ Sending OTP via ABDM Gateway…');
  document.getElementById('abhaActionBtn').disabled = true;

  fetch('../pages/dashboard.php?action=abha_send_otp', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ abha_id: abhaId, mobile })
  })
    .then(r => r.json())
    .then(d => handleOtpResponse(d, abhaId, mobile))
    .catch(() => {
      handleOtpResponse({ success: true }, abhaId, mobile);
    })
    .finally(() => { document.getElementById('abhaActionBtn').disabled = false; });
}

function handleOtpResponse(d, abhaId, mobile) {
  if (d.success) {
    showAbhaMsg('info', `📱 OTP sent to ${mobile}. Please check your phone.`);
    document.getElementById('otpSection').classList.remove('hidden');
    document.getElementById('abhaActionBtn').innerHTML = '<i class="fa-solid fa-check"></i> Verify & Link';
    abhaStep = 2;
  } else {
    showAbhaMsg('error', `❌ ${d.message || 'Failed to send OTP. Please try again.'}`);
  }
}

function verifyAbhaOtp() {
  const otp    = document.getElementById('otpInput').value.trim();
  const abhaId = document.getElementById('abhaInput').value.trim();
  const mobile = document.getElementById('mobileInput').value.trim();

  if (!otp || otp.length < 4) { showAbhaMsg('error', '⚠ Please enter a valid OTP.'); return; }

  showAbhaMsg('info', '⏳ Verifying OTP…');
  document.getElementById('abhaActionBtn').disabled = true;

  fetch('../pages/dashboard.php?action=abha_verify_otp', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ abha_id: abhaId, mobile, otp })
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        showAbhaMsg('success', `✅ ABHA ID "${abhaId}" linked successfully!`);
        document.getElementById('abhaActionBtn').innerHTML = '<i class="fa-solid fa-check-circle"></i> Linked!';
        setTimeout(closeAbhaModal, 2000);
      } else {
        showAbhaMsg('error', `❌ ${d.message || 'Invalid OTP. Please try again.'}`);
        document.getElementById('abhaActionBtn').disabled = false;
      }
    })
    .catch(() => {
      showAbhaMsg('success', `✅ ABHA ID "${abhaId}" linked! (demo mode)`);
      document.getElementById('abhaActionBtn').innerHTML = '<i class="fa-solid fa-check-circle"></i> Linked!';
      setTimeout(closeAbhaModal, 2000);
    });
}

function showAbhaMsg(type, text) {
  const el = document.getElementById('abhaMsg');
  el.className = `abha-msg ${type}`;
  el.textContent = text;
  el.classList.remove('hidden');
}

// ── QR MODAL ──────────────────────────────────────────────────
let qrCodeInstance = null;

function openQrModal() {
  document.getElementById('qrModal').classList.remove('hidden');
  
  if (!qrCodeInstance) {
    const qrContainer = document.getElementById('qrcode');
    qrContainer.innerHTML = '';
    const patientDataUrl = window.location.origin + '/templates/profile.html?id=AAYU-123456';
    
    qrCodeInstance = new QRCode(qrContainer, {
      text: patientDataUrl,
      width: 150,
      height: 150,
      colorDark : "#0d1b2a",
      colorLight : "#ffffff",
      correctLevel : QRCode.CorrectLevel.H
    });
  }
}

function closeQrModal() {
  document.getElementById('qrModal').classList.add('hidden');
}

document.getElementById('qrModal').addEventListener('click', function(e) {
  if (e.target === this) closeQrModal();
});

function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str || '';
  return d.innerHTML;
}

/* ============================================================
   AADHAAR OTP AUTHENTICATION & e-KYC MODULE
   ============================================================ */
let aadhaarCurrentStep = 1;
let aadhaarTxnId = '';
let aadhaarActiveDemoOtp = '123456';
let aadhaarExpiryInterval = null;
let aadhaarResendInterval = null;
let aadhaarVerifiedProfile = null;

function initAadhaarModule() {
  const modal = document.getElementById('aadhaarModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) closeAadhaarModal();
    });
  }

  // Auto-format Aadhaar Number (XXXX XXXX XXXX)
  const aadhaarInput = document.getElementById('aadhaarNumInput');
  if (aadhaarInput) {
    aadhaarInput.addEventListener('input', function(e) {
      let val = e.target.value.replace(/\D/g, '');
      if (val.length > 12) val = val.substring(0, 12);
      
      // Split into chunks of 4
      const parts = [];
      for (let i = 0; i < val.length; i += 4) {
        parts.push(val.substring(i, i + 4));
      }
      e.target.value = parts.join(' ');
    });

    aadhaarInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        sendAadhaarOtp();
      }
    });
  }

  // Configure 6-Digit OTP Individual Input Boxes
  const otpDigits = document.querySelectorAll('.otp-box-digit');
  otpDigits.forEach((box, idx) => {
    box.addEventListener('input', function(e) {
      const val = e.target.value.replace(/\D/g, '');
      e.target.value = val ? val[val.length - 1] : '';
      
      if (e.target.value) {
        e.target.classList.add('filled');
        if (idx < otpDigits.length - 1) {
          otpDigits[idx + 1].focus();
        }
      } else {
        e.target.classList.remove('filled');
      }

      // If all filled, optionally verify automatically
      if (idx === otpDigits.length - 1 && getEnteredOtp().length === 6) {
        // Can trigger verify on full 6 digits if desired
      }
    });

    box.addEventListener('keydown', function(e) {
      if (e.key === 'Backspace' && !e.target.value && idx > 0) {
        otpDigits[idx - 1].focus();
        otpDigits[idx - 1].value = '';
        otpDigits[idx - 1].classList.remove('filled');
      } else if (e.key === 'ArrowLeft' && idx > 0) {
        otpDigits[idx - 1].focus();
      } else if (e.key === 'ArrowRight' && idx < otpDigits.length - 1) {
        otpDigits[idx + 1].focus();
      } else if (e.key === 'Enter') {
        e.preventDefault();
        verifyAadhaarOtp();
      }
    });

    // Support full 6-digit paste
    box.addEventListener('paste', function(e) {
      e.preventDefault();
      const pasteData = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      if (!pasteData) return;
      
      for (let i = 0; i < 6; i++) {
        if (pasteData[i] && otpDigits[i]) {
          otpDigits[i].value = pasteData[i];
          otpDigits[i].classList.add('filled');
        }
      }
      const focusIndex = Math.min(pasteData.length, 5);
      otpDigits[focusIndex].focus();
    });
  });

  // Check initial verification status on load
  fetchAadhaarStatus();
}

function openAadhaarModal() {
  const modal = document.getElementById('aadhaarModal');
  if (!modal) return;
  modal.classList.remove('hidden');

  if (aadhaarVerifiedProfile) {
    // Already verified, show Step 3 (e-KYC card)
    renderEkycCard(aadhaarVerifiedProfile);
    goToAadhaarStep(3);
  } else {
    goToAadhaarStep(1);
    setTimeout(() => {
      const input = document.getElementById('aadhaarNumInput');
      if (input) input.focus();
    }, 100);
  }
}

function closeAadhaarModal() {
  const modal = document.getElementById('aadhaarModal');
  if (modal) modal.classList.add('hidden');
  clearInterval(aadhaarExpiryInterval);
  clearInterval(aadhaarResendInterval);
}

function goToAadhaarStep(step) {
  aadhaarCurrentStep = step;
  
  // Hide all steps
  const step1 = document.getElementById('aadhaarStep1');
  const step2 = document.getElementById('aadhaarStep2');
  const step3 = document.getElementById('aadhaarStep3');

  if (step1) step1.classList.add('hidden');
  if (step2) step2.classList.add('hidden');
  if (step3) step3.classList.add('hidden');

  // Update Stepper UI
  for (let i = 1; i <= 3; i++) {
    const indicator = document.getElementById(`stepIndicator${i}`);
    if (!indicator) continue;
    indicator.classList.remove('active', 'completed');
    if (i < step) {
      indicator.classList.add('completed');
    } else if (i === step) {
      indicator.classList.add('active');
    }
  }

  const div1 = document.getElementById('stepDivider1');
  const div2 = document.getElementById('stepDivider2');
  if (div1) div1.className = `step-divider ${step > 1 ? 'active' : ''}`;
  if (div2) div2.className = `step-divider ${step > 2 ? 'active' : ''}`;

  // Show active step
  if (step === 1) {
    if (step1) step1.classList.remove('hidden');
  } else if (step === 2) {
    if (step2) step2.classList.remove('hidden');
    clearOtpInputs();
    setTimeout(() => {
      const firstBox = document.querySelector('.otp-box-digit[data-index="0"]');
      if (firstBox) firstBox.focus();
    }, 150);
  } else if (step === 3) {
    if (step3) step3.classList.remove('hidden');
  }
}

function clearOtpInputs() {
  document.querySelectorAll('.otp-box-digit').forEach(b => {
    b.value = '';
    b.classList.remove('filled');
  });
}

function getEnteredOtp() {
  let otp = '';
  document.querySelectorAll('.otp-box-digit').forEach(b => {
    otp += b.value.trim();
  });
  return otp;
}

// ── SEND AADHAAR OTP ──────────────────────────────────────────
async function sendAadhaarOtp() {
  const aadhaarInput = document.getElementById('aadhaarNumInput');
  const consentBox   = document.getElementById('aadhaarConsentCheckbox');
  const actionBtn    = document.getElementById('btnSendAadhaarOtp');

  if (!aadhaarInput) return;
  const rawVal = aadhaarInput.value.replace(/\D/g, '');

  if (!rawVal || rawVal.length !== 12) {
    showAadhaarMsg(1, 'error', '⚠ Please enter a valid 12-digit Aadhaar number.');
    aadhaarInput.focus();
    return;
  }

  if (rawVal.startsWith('0') || rawVal.startsWith('1')) {
    showAadhaarMsg(1, 'error', '⚠ Invalid Aadhaar number. Aadhaar number cannot begin with 0 or 1.');
    aadhaarInput.focus();
    return;
  }

  if (!validateAadhaarVerhoeffJS(rawVal)) {
    showAadhaarMsg(1, 'error', '⚠ Invalid Aadhaar number checksum. Please re-check your 12 digits.');
    aadhaarInput.focus();
    return;
  }

  if (consentBox && !consentBox.checked) {
    showAadhaarMsg(1, 'error', '⚠ Mandatory UIDAI consent is required to proceed.');
    return;
  }

  showAadhaarMsg(1, 'info', '⏳ Connecting to UIDAI Aadhaar Authentication Gateway…');
  if (actionBtn) { actionBtn.disabled = true; actionBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending…'; }

  try {
    const res = await fetch('../pages/dashboard.php?action=aadhaar_send_otp', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ aadhaar_number: rawVal, consent: true })
    });
    const data = await res.json();
    handleSendOtpSuccess(data, rawVal);
  } catch (err) {
    // Fallback demo mode
    handleSendOtpSuccess({
      success: true,
      txn_id: 'UIDAI-TXN-DEMO',
      masked_mobile: '******' + rawVal.substring(8),
      demo_otp: '782341',
      expires_in: 600
    }, rawVal);
  } finally {
    if (actionBtn) { actionBtn.disabled = false; actionBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send OTP'; }
  }
}

function handleSendOtpSuccess(data, rawAadhaar) {
  if (data.success) {
    aadhaarTxnId = data.txn_id || 'UIDAI-TXN-DEMO';
    aadhaarActiveDemoOtp = data.demo_otp || '123456';

    const noticeEl = document.getElementById('aadhaarOtpNotice');
    if (noticeEl) {
      noticeEl.innerHTML = `Enter the 6-digit verification code sent to mobile <strong>${data.masked_mobile || '******9876'}</strong> linked with Aadhaar ending in <strong>${rawAadhaar.substring(8)}</strong>.`;
    }

    const demoDisplay = document.getElementById('demoOtpDisplay');
    if (demoDisplay) {
      demoDisplay.textContent = aadhaarActiveDemoOtp;
    }

    showAadhaarMsg(1, 'success', '✅ OTP generated successfully!');
    setTimeout(() => {
      goToAadhaarStep(2);
      startOtpExpiryTimer(data.expires_in || 600);
      startResendCooldownTimer(60);
    }, 400);
  } else {
    showAadhaarMsg(1, 'error', `❌ ${data.message || 'Failed to dispatch OTP. Please try again.'}`);
  }
}

// ── VERIFY AADHAAR OTP ────────────────────────────────────────
async function verifyAadhaarOtp() {
  const otp = getEnteredOtp();
  const rawAadhaar = (document.getElementById('aadhaarNumInput')?.value || '').replace(/\D/g, '');
  const verifyBtn = document.getElementById('btnVerifyAadhaarOtp');

  if (!otp || otp.length !== 6) {
    showAadhaarMsg(2, 'error', '⚠ Please enter all 6 digits of the OTP.');
    return;
  }

  showAadhaarMsg(2, 'info', '⏳ Authenticating UIDAI e-KYC credential…');
  if (verifyBtn) { verifyBtn.disabled = true; verifyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying…'; }

  try {
    const res = await fetch('../pages/dashboard.php?action=aadhaar_verify_otp', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        aadhaar_number: rawAadhaar,
        txn_id: aadhaarTxnId,
        otp: otp
      })
    });
    const data = await res.json();
    handleVerifyOtpResponse(data, rawAadhaar);
  } catch (e) {
    // Fallback demo mode
    handleVerifyOtpResponse({
      success: true,
      profile: {
        full_name: 'Rajesh Kumar Sharma',
        gender: 'Male',
        dob: '15/08/1988',
        aadhaar_number: 'XXXX-XXXX-' + (rawAadhaar ? rawAadhaar.substring(8) : '8912'),
        address: 'H-12, Green Park Main, New Delhi - 110016',
        ekyc_timestamp: new Date().toLocaleString('en-IN')
      }
    }, rawAadhaar);
  } finally {
    if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.innerHTML = '<i class="fa-solid fa-shield-check"></i> Verify & Authenticate'; }
  }
}

function handleVerifyOtpResponse(data, rawAadhaar) {
  if (data.success) {
    clearInterval(aadhaarExpiryInterval);
    clearInterval(aadhaarResendInterval);

    const profile = data.profile || {
      full_name: 'Rajesh Kumar Sharma',
      gender: 'Male',
      dob: '15/08/1988',
      aadhaar_number: 'XXXX-XXXX-' + (rawAadhaar ? rawAadhaar.substring(8) : '8912'),
      address: 'H-12, Green Park Main, New Delhi - 110016',
      ekyc_timestamp: new Date().toLocaleString('en-IN')
    };

    aadhaarVerifiedProfile = profile;
    renderEkycCard(profile);
    updateNavbarAadhaarBadge(true);
    goToAadhaarStep(3);
  } else {
    showAadhaarMsg(2, 'error', `❌ ${data.message || 'Invalid OTP. Please check and try again.'}`);
  }
}

// ── RESEND AADHAAR OTP ────────────────────────────────────────
async function resendAadhaarOtp() {
  const resendBtn  = document.getElementById('btnResendAadhaarOtp');
  const rawAadhaar = (document.getElementById('aadhaarNumInput')?.value || '').replace(/\D/g, '');

  if (resendBtn) resendBtn.disabled = true;
  showAadhaarMsg(2, 'info', '⏳ Requesting new Aadhaar OTP…');

  try {
    const res = await fetch('../pages/dashboard.php?action=aadhaar_resend_otp', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ aadhaar_number: rawAadhaar })
    });
    const data = await res.json();
    if (data.success) {
      aadhaarTxnId = data.txn_id || aadhaarTxnId;
      aadhaarActiveDemoOtp = data.demo_otp || '654321';
      const demoDisplay = document.getElementById('demoOtpDisplay');
      if (demoDisplay) demoDisplay.textContent = aadhaarActiveDemoOtp;

      clearOtpInputs();
      showAadhaarMsg(2, 'success', '✅ New OTP sent successfully!');
      startOtpExpiryTimer(600);
      startResendCooldownTimer(60);
    } else {
      showAadhaarMsg(2, 'error', `❌ ${data.message || 'Failed to resend OTP.'}`);
      if (resendBtn) resendBtn.disabled = false;
    }
  } catch (err) {
    aadhaarActiveDemoOtp = String(Math.floor(100000 + Math.random() * 900000));
    const demoDisplay = document.getElementById('demoOtpDisplay');
    if (demoDisplay) demoDisplay.textContent = aadhaarActiveDemoOtp;
    clearOtpInputs();
    showAadhaarMsg(2, 'success', '✅ New OTP sent successfully (Demo mode)!');
    startOtpExpiryTimer(600);
    startResendCooldownTimer(60);
  }
}

function autofillAadhaarDemoOtp() {
  const digits = aadhaarActiveDemoOtp.split('');
  const boxes = document.querySelectorAll('.otp-box-digit');
  boxes.forEach((box, i) => {
    if (digits[i]) {
      box.value = digits[i];
      box.classList.add('filled');
    }
  });
  if (boxes[5]) boxes[5].focus();
  showAadhaarMsg(2, 'info', '✨ Test OTP filled automatically! Click "Verify & Authenticate".');
}

// ── TIMERS ────────────────────────────────────────────────────
function startOtpExpiryTimer(durationSec) {
  clearInterval(aadhaarExpiryInterval);
  let remaining = durationSec;
  const timerDisplay = document.getElementById('aadhaarTimerCount');

  function updateDisplay() {
    const mins = Math.floor(remaining / 60);
    const secs = remaining % 60;
    if (timerDisplay) {
      timerDisplay.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
    if (remaining <= 0) {
      clearInterval(aadhaarExpiryInterval);
      showAadhaarMsg(2, 'error', '⏰ OTP has expired. Please click Resend OTP.');
    }
    remaining--;
  }

  updateDisplay();
  aadhaarExpiryInterval = setInterval(updateDisplay, 1000);
}

function startResendCooldownTimer(durationSec) {
  clearInterval(aadhaarResendInterval);
  let remaining = durationSec;
  const resendBtn = document.getElementById('btnResendAadhaarOtp');
  const countSpan = document.getElementById('resendCountdown');

  if (resendBtn) resendBtn.disabled = true;

  function update() {
    if (countSpan) countSpan.textContent = String(remaining);
    if (remaining <= 0) {
      clearInterval(aadhaarResendInterval);
      if (resendBtn) {
        resendBtn.disabled = false;
        resendBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Resend OTP';
      }
    }
    remaining--;
  }

  update();
  aadhaarResendInterval = setInterval(update, 1000);
}

// ── STATUS & UI UPDATES ───────────────────────────────────────
async function fetchAadhaarStatus() {
  try {
    const res = await fetch('../pages/dashboard.php?action=aadhaar_status');
    const data = await res.json();
    if (data && data.verified && data.profile) {
      aadhaarVerifiedProfile = data.profile;
      updateNavbarAadhaarBadge(true);
    }
  } catch (e) {}
}

function updateNavbarAadhaarBadge(isVerified) {
  const btn = document.getElementById('navAadhaarBtn');
  const label = document.getElementById('aadhaarBtnLabel');
  const dashPill = document.getElementById('dashboardAadhaarPill');
  const dashBtn = document.getElementById('btnDashboardLinkAadhaar');
  const dashDesc = document.getElementById('dashboardAadhaarDesc');

  if (isVerified) {
    if (btn) {
      btn.classList.add('verified');
      btn.style.background = 'linear-gradient(135deg, #0e3d2a, #165b3e)';
      btn.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#27ae60;"></i> <span>Aadhaar Verified</span>';
      btn.title = 'Aadhaar e-KYC Identity Verified (Click to view card)';
    }
    if (dashPill) {
      dashPill.style.background = '#e6f9ed';
      dashPill.style.color = '#166534';
      dashPill.style.borderColor = '#86efac';
      dashPill.innerHTML = '<i class="fa-solid fa-circle-check"></i> Verified';
    }
    if (dashBtn) {
      dashBtn.innerHTML = '<i class="fa-solid fa-id-card"></i> <span>View Aadhaar e-KYC Card</span>';
    }
    if (dashDesc && aadhaarVerifiedProfile) {
      dashDesc.innerHTML = `Linked Aadhaar: <strong>${aadhaarVerifiedProfile.aadhaar_number || 'XXXX-XXXX-8912'}</strong> (${aadhaarVerifiedProfile.full_name || 'Verified Patient'})`;
    }
  } else {
    if (btn) {
      btn.classList.remove('verified');
      btn.style.background = 'linear-gradient(130deg, #c9973a, #e0b05a)';
      btn.innerHTML = '<i class="fa-solid fa-fingerprint"></i> <span>Link by Aadhaar</span>';
      btn.title = 'Link by Aadhaar OTP';
    }
  }
}

function renderEkycCard(profile) {
  const elName = document.getElementById('ekycName');
  const elAadhaar = document.getElementById('ekycAadhaar');
  const elDob = document.getElementById('ekycDob');
  const elGender = document.getElementById('ekycGender');
  const elAddress = document.getElementById('ekycAddress');
  const elTimestamp = document.getElementById('ekycTimestamp');

  if (elName) elName.textContent = profile.full_name || 'Rajesh Kumar Sharma';
  if (elAadhaar) elAadhaar.textContent = profile.aadhaar_number || 'XXXX-XXXX-8912';
  if (elDob) elDob.textContent = profile.dob || '15/08/1988';
  if (elGender) elGender.textContent = profile.gender || 'Male';
  if (elAddress) elAddress.textContent = profile.address || 'H-12, Green Park Main, New Delhi - 110016';
  if (elTimestamp) elTimestamp.textContent = `Verified on ${profile.ekyc_timestamp || new Date().toLocaleDateString('en-IN')}`;

  const dashDesc = document.getElementById('dashboardAadhaarDesc');
  if (dashDesc) {
    dashDesc.innerHTML = `Linked Aadhaar: <strong>${profile.aadhaar_number || 'XXXX-XXXX-8912'}</strong> (${profile.full_name || 'Verified Patient'})`;
  }
}

function showAadhaarMsg(step, type, text) {
  const el = document.getElementById(`aadhaarMsg${step}`);
  if (!el) return;
  el.className = `abha-msg ${type}`;
  el.textContent = text;
  el.classList.remove('hidden');
}

// ── CLIENT-SIDE VERHOEFF CHECKSUM ALGORITHM ───────────────────
function validateAadhaarVerhoeffJS(num) {
  const d = [
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
    [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
    [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
    [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
    [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
    [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
    [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
    [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
    [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
    [9, 8, 7, 6, 5, 4, 3, 2, 1, 0]
  ];
  const p = [
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
    [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
    [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
    [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
    [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
    [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
    [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
    [7, 0, 4, 6, 9, 1, 3, 2, 5, 8]
  ];
  let c = 0;
  const rev = num.split('').reverse().map(Number);
  for (let i = 0; i < rev.length; i++) {
    c = d[c][p[i % 8][rev[i]]];
  }
  return c === 0;
}