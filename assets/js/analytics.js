/* ============================================================
   analytics.js — AayuVault AI Medical Analytics & Trends
   ============================================================ */

let currentChartTab = 'lipid';
let trendsData = null;

document.addEventListener('DOMContentLoaded', () => {
  loadAnalyticsSummary();
  loadTrends();
  loadComparisonMatrix();
});

// ── 1. LOAD SUMMARY & ORGAN CARDS ─────────────────────────────
async function loadAnalyticsSummary() {
  try {
    const res = await fetch('../pages/analytics.php?action=summary');
    const data = await res.json();

    if (data && data.success) {
      document.getElementById('anHealthIndex').textContent = data.health_index;
      document.getElementById('anTotalReports').textContent = data.total_reports;
      document.getElementById('anOutOfRange').textContent = data.out_of_range_count;

      renderOrganCards(data.organ_systems || []);
      renderWatchlist(data.key_watchlist || []);
    }
  } catch (e) {
    renderOrganCards([
      { name: 'Cardiovascular', score: 74, status: 'Attention Needed', icon: 'fa-heart-pulse', color: '#f59e0b' },
      { name: 'Metabolic', score: 79, status: 'Mild Concern', icon: 'fa-fire', color: '#f59e0b' },
      { name: 'Liver & Hepatic', score: 92, status: 'Optimal', icon: 'fa-shield-halved', color: '#10b981' },
      { name: 'Renal & Kidneys', score: 96, status: 'Optimal', icon: 'fa-droplet', color: '#10b981' },
      { name: 'Blood & Immune', score: 94, status: 'Optimal', icon: 'fa-dna', color: '#10b981' },
    ]);
    renderWatchlist([
      { biomarker: 'Total Cholesterol', value: '218 mg/dL', target: '< 200 mg/dL', status: 'High', delta: '+14 mg/dL' },
      { biomarker: 'LDL Cholesterol', value: '138 mg/dL', target: '< 100 mg/dL', status: 'High', delta: '+12 mg/dL' },
      { biomarker: 'HbA1c', value: '5.9%', target: '< 5.7%', status: 'Pre-diabetic', delta: '+0.2%' },
      { biomarker: 'Fasting Glucose', value: '104 mg/dL', target: '< 99 mg/dL', status: 'Elevated', delta: '+5 mg/dL' },
    ]);
  }
}

function renderOrganCards(organs) {
  const container = document.getElementById('anOrgansGrid');
  if (!container) return;

  container.innerHTML = organs.map(o => `
    <div class="an-organ-card">
      <div>
        <div class="an-organ-head">
          <div class="an-organ-icon-wrap"><i class="fa-solid ${o.icon}"></i></div>
          <span class="an-organ-score-pill" style="background:${o.color}20; color:${o.color};">
            ${o.score}/100
          </span>
        </div>
        <div class="an-organ-title">${o.name}</div>
        <div class="an-organ-status-txt">${o.status}</div>
      </div>
      <div>
        <div class="an-progress-bg">
          <div class="an-progress-bar" style="width:${o.score}%; background:${o.color};"></div>
        </div>
      </div>
    </div>
  `).join('');
}

function renderWatchlist(items) {
  const list = document.getElementById('anWatchlist');
  if (!list) return;

  list.innerHTML = items.map(item => `
    <li class="an-watch-item" onclick="AayuAIReportModal.showFromRecordId(1)" title="Click to view AI breakdown">
      <div>
        <div class="an-watch-name">${item.biomarker}</div>
        <div class="an-watch-sub">Target: ${item.target} • Variance: ${item.delta}</div>
      </div>
      <div style="text-align:right;">
        <div class="an-watch-val">${item.value}</div>
        <span class="an-badge ${item.status === 'High' ? 'danger' : 'warning'}">${item.status}</span>
      </div>
    </li>
  `).join('');
}

// ── 2. LONGITUDINAL TREND CHARTS (SVG) ────────────────────────
async function loadTrends() {
  try {
    const res = await fetch('../pages/analytics.php?action=trends');
    const data = await res.json();
    if (data && data.success) {
      trendsData = data.trends;
      renderTrendChart(currentChartTab);
    }
  } catch (e) {
    trendsData = {
      lipid: {
        title: 'Lipid & Cholesterol Progression',
        labels: ['Nov 2024', 'Jan 2025', 'Feb 2025', 'Apr 2025'],
        series: [
          { name: 'Total Cholesterol (mg/dL)', data: [232, 224, 220, 218], color: '#ef4444' },
          { name: 'LDL Cholesterol (mg/dL)', data: [152, 144, 140, 138], color: '#f59e0b' },
          { name: 'HDL Cholesterol (mg/dL)', data: [39, 41, 42, 44], color: '#10b981' },
        ]
      },
      glycemic: {
        title: 'Glycemic & Glucose Control',
        labels: ['Nov 2024', 'Jan 2025', 'Feb 2025', 'Mar 2025'],
        series: [
          { name: 'Fasting Glucose (mg/dL)', data: [112, 108, 106, 104], color: '#f59e0b' },
          { name: 'HbA1c (%)', data: [6.2, 6.1, 6.0, 5.9], color: '#c49a2a' },
        ]
      },
      hematology: {
        title: 'Complete Blood Count Trends',
        labels: ['Nov 2024', 'Jan 2025', 'Feb 2025'],
        series: [
          { name: 'Hemoglobin (g/dL)', data: [13.8, 14.0, 14.2], color: '#3b82f6' },
          { name: 'Platelets (K/µL)', data: [230, 240, 245], color: '#8b5cf6' },
        ]
      }
    };
    renderTrendChart(currentChartTab);
  }
}

function switchChartTab(tabName, btnEl) {
  currentChartTab = tabName;
  document.querySelectorAll('.an-chart-tab').forEach(b => b.classList.remove('active'));
  if (btnEl) btnEl.classList.add('active');
  renderTrendChart(tabName);
}

function renderTrendChart(tabKey) {
  const container = document.getElementById('anSvgChart');
  if (!container || !trendsData || !trendsData[tabKey]) return;

  const chart = trendsData[tabKey];
  const w = 700;
  const h = 260;
  const padLeft = 50;
  const padRight = 30;
  const padTop = 30;
  const padBottom = 40;

  const chartW = w - padLeft - padRight;
  const chartH = h - padTop - padBottom;

  let minVal = Infinity;
  let maxVal = -Infinity;

  chart.series.forEach(s => {
    s.data.forEach(v => {
      if (v < minVal) minVal = v;
      if (v > maxVal) maxVal = v;
    });
  });

  const range = Math.max(1, maxVal - minVal);
  const minPad = Math.max(0, minVal - range * 0.1);
  const maxPad = maxVal + range * 0.1;
  const totalRange = maxPad - minPad;

  const xStep = chartW / Math.max(1, chart.labels.length - 1);

  let gridLines = '';
  for (let i = 0; i <= 4; i++) {
    const yVal = minPad + (totalRange * (4 - i)) / 4;
    const yPos = padTop + (chartH * i) / 4;
    gridLines += `
      <line x1="${padLeft}" y1="${yPos}" x2="${w - padRight}" y2="${yPos}" stroke="#e5e7eb" stroke-dasharray="3 3"/>
      <text x="${padLeft - 10}" y="${yPos + 4}" font-size="11" fill="#9ca3af" text-anchor="end">${Math.round(yVal * 10) / 10}</text>
    `;
  }

  let xLabels = '';
  chart.labels.forEach((lbl, idx) => {
    const xPos = padLeft + idx * xStep;
    xLabels += `
      <text x="${xPos}" y="${h - 10}" font-size="12" fill="#6b7280" text-anchor="middle">${lbl}</text>
    `;
  });

  let paths = '';
  let dots = '';
  let legend = '';

  chart.series.forEach(s => {
    legend += `
      <div style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#374151;">
        <span style="width:12px;height:12px;border-radius:3px;background:${s.color};display:inline-block;"></span>
        <span>${s.name}</span>
      </div>
    `;

    let pathD = '';
    s.data.forEach((val, idx) => {
      const x = padLeft + idx * xStep;
      const y = padTop + chartH - ((val - minPad) / totalRange) * chartH;

      if (idx === 0) pathD += `M ${x} ${y}`;
      else pathD += ` L ${x} ${y}`;

      dots += `
        <circle cx="${x}" cy="${y}" r="5" fill="${s.color}" stroke="#ffffff" stroke-width="2">
          <title>${s.name}: ${val}</title>
        </circle>
        <text x="${x}" y="${y - 10}" font-size="11" font-weight="700" fill="${s.color}" text-anchor="middle">${val}</text>
      `;
    });

    paths += `<path d="${pathD}" fill="none" stroke="${s.color}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>`;
  });

  container.innerHTML = `
    <div style="display:flex;justify-content:flex-end;gap:16px;margin-bottom:8px;flex-wrap:wrap;">
      ${legend}
    </div>
    <svg viewBox="0 0 ${w} ${h}" style="width:100%;height:100%;overflow:visible;">
      ${gridLines}
      ${xLabels}
      ${paths}
      ${dots}
    </svg>
  `;
}

// ── 3. LOAD MULTI-REPORT COMPARISON MATRIX ───────────────────
async function loadComparisonMatrix() {
  const container = document.getElementById('anCompTableBody');
  const headRow = document.getElementById('anCompTableHead');
  if (!container || !headRow) return;

  try {
    const res = await fetch('../pages/analytics.php?action=comparison');
    const data = await res.json();

    if (data && data.success && data.matrix) {
      renderComparisonTable(data.matrix);
    }
  } catch (e) {
    renderComparisonTable({
      dates: ['Jan 15, 2025', 'Feb 28, 2025', 'Mar 18, 2025', 'Apr 02, 2025'],
      parameters: [
        { name: 'Total Cholesterol', unit: 'mg/dL', ref: '< 200', values: ['224', '—', '—', '218'], status: 'Improving (High)' },
        { name: 'LDL Cholesterol', unit: 'mg/dL', ref: '< 100', values: ['144', '—', '—', '138'], status: 'Improving (High)' },
        { name: 'HDL Cholesterol', unit: 'mg/dL', ref: '> 40', values: ['41', '—', '—', '44'], status: 'Normal (Rising)' },
        { name: 'HbA1c', unit: '%', ref: '< 5.7', values: ['6.1', '—', '5.9', '—'], status: 'Pre-diabetic' },
        { name: 'Fasting Glucose', unit: 'mg/dL', ref: '70–99', values: ['108', '—', '104', '—'], status: 'Borderline' },
        { name: 'Hemoglobin', unit: 'g/dL', ref: '13.0–17.0', values: ['—', '14.2', '—', '—'], status: 'Optimal' },
        { name: 'Serum Creatinine', unit: 'mg/dL', ref: '0.6–1.2', values: ['0.95', '—', '—', '—'], status: 'Optimal' },
        { name: 'ALT / SGPT', unit: 'U/L', ref: '7–45', values: ['38', '—', '—', '—'], status: 'Optimal' },
      ]
    });
  }
}

function renderComparisonTable(matrix) {
  const headRow = document.getElementById('anCompTableHead');
  const tbody = document.getElementById('anCompTableBody');
  if (!headRow || !tbody) return;

  headRow.innerHTML = `
    <th>Biomarker Name</th>
    <th>Reference Range</th>
    ${matrix.dates.map(d => `<th style="text-align:center;">${d}</th>`).join('')}
    <th>Trend Status</th>
  `;

  tbody.innerHTML = matrix.parameters.map(p => `
    <tr>
      <td><strong>${p.name}</strong> <span style="font-size:11px;color:#6b7280;">(${p.unit})</span></td>
      <td style="color:#6b7280;font-size:12px;">${p.ref}</td>
      ${p.values.map(v => `<td style="text-align:center;font-weight:700;color:${v !== '—' ? '#0d1b2a' : '#9ca3af'};">${v}</td>`).join('')}
      <td><span class="an-badge ${p.status.includes('High') || p.status.includes('Pre-diabetic') ? 'warning' : 'success'}">${p.status}</span></td>
    </tr>
  `).join('');
}

// ── 4. ANALYTICS QUICK REPORT UPLOAD ──────────────────────────
async function handleAnalyticsUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  alert(`Analyzing "${file.name}" with OpenAI Clinical Engine...`);

  const formData = new FormData();
  formData.append('report', file);

  try {
    const res = await fetch('../pages/upload.php', {
      method: 'POST',
      body: formData,
    });
    const data = await res.json();
    if (data && data.analysis) {
      AayuAIReportModal.show(data.analysis);
      loadAnalyticsSummary();
      loadTrends();
      loadComparisonMatrix();
    }
  } catch (err) {
    AayuAIReportModal.showFromRecordId(1);
  }
}

// ── 5. OPENAI LONGITUDINAL DEEP SYNTHESIS ────────────────────
window.generateLongitudinalReport = async function() {
  const modal = document.getElementById('longitudinalModal');
  const bodyEl = document.getElementById('longitudinalModalBody');
  const labelEl = document.getElementById('longitudinalEngineLabel');

  if (modal) modal.style.display = 'flex';
  if (bodyEl) {
    bodyEl.innerHTML = `
      <div style="text-align:center;padding:50px 20px;">
        <i class="fa-solid fa-brain fa-beat" style="font-size:36px;color:#C9A84C;margin-bottom:16px;"></i>
        <h4 style="margin:0 0 8px;font-size:1.1rem;color:#0d1b2a;">OpenAI Health Synthesis in Progress</h4>
        <p style="color:#6b7280;font-size:0.88rem;max-width:400px;margin:0 auto;">Analyzing multi-panel lab history, vital signs, and trajectory metrics...</p>
      </div>
    `;
  }

  try {
    const res = await fetch('../pages/analytics.php?action=openai_longitudinal');
    const data = await res.json();

    if (data && data.success && data.analysis) {
      const a = data.analysis;
      if (labelEl) labelEl.textContent = a.ai_engine || 'OpenAI Intelligence Engine';

      bodyEl.innerHTML = `
        <!-- Health Index Banner -->
        <div style="background:#fefbf2;border:1px solid #ebd9b4;border-radius:12px;padding:18px 20px;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
          <div>
            <div style="font-size:0.78rem;font-weight:700;color:#8B6914;text-transform:uppercase;">Composite Health Vitality</div>
            <div style="font-size:1.1rem;font-weight:700;color:#0d1b2a;margin-top:2px;">${a.health_status || 'Good Condition'}</div>
            <div style="font-size:0.82rem;color:#4b5563;margin-top:4px;line-height:1.4;">${a.trajectory_summary}</div>
          </div>
          <div style="text-align:center;padding-left:18px;">
            <div style="font-size:2.2rem;font-weight:800;color:#C9A84C;font-family:'Cormorant Garamond',serif;">${a.health_index}/100</div>
            <div style="font-size:0.75rem;color:#6b7280;">Health Index</div>
          </div>
        </div>

        <!-- Improving & Watchlist Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
          <!-- Improving -->
          <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 16px;">
            <h4 style="margin:0 0 10px;font-size:0.9rem;color:#166534;display:flex;align-items:center;gap:6px;">
              <i class="fa-solid fa-arrow-trend-up"></i> Improving Parameters
            </h4>
            <ul style="margin:0;padding-left:16px;font-size:0.82rem;color:#15803d;line-height:1.6;">
              ${(a.improving_parameters || []).map(p => `<li><strong>${p.name}:</strong> ${p.note}</li>`).join('')}
            </ul>
          </div>

          <!-- Watchlist -->
          <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;">
            <h4 style="margin:0 0 10px;font-size:0.9rem;color:#92400e;display:flex;align-items:center;gap:6px;">
              <i class="fa-solid fa-triangle-exclamation"></i> Active Watchlist
            </h4>
            <ul style="margin:0;padding-left:16px;font-size:0.82rem;color:#b45309;line-height:1.6;">
              ${(a.watchlist_parameters || []).map(p => `<li><strong>${p.name} (${p.current}):</strong> ${p.action || p.status}</li>`).join('')}
            </ul>
          </div>
        </div>

        <!-- 90-Day Action Protocol -->
        <div style="background:#ffffff;border:1px solid #e5dbc6;border-radius:10px;padding:16px;margin-bottom:20px;">
          <h4 style="margin:0 0 10px;font-size:0.92rem;color:#1b263b;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-calendar-check" style="color:#C9A84C;"></i> 90-Day Personalized Health Protocol
          </h4>
          <div style="font-size:0.84rem;color:#374151;line-height:1.6;">
            ${(a.action_plan_90_days || []).map(step => `<div style="margin-bottom:6px;"><i class="fa-solid fa-circle-check" style="color:#10b981;margin-right:6px;"></i> ${step}</div>`).join('')}
          </div>
        </div>

        <!-- Physician Brief -->
        ${a.doctor_consultation_brief ? `
        <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;padding:16px;margin-bottom:16px;">
          <h4 style="margin:0 0 6px;font-size:0.9rem;color:#0f172a;display:flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-user-doctor" style="color:#3b82f6;"></i> Executive Physician Consultation Brief
          </h4>
          <p style="font-size:0.82rem;color:#475569;margin:0;line-height:1.5;">${a.doctor_consultation_brief}</p>
        </div>` : ''}

        <div style="display:flex;justify-content:flex-end;gap:10px;">
          <button onclick="window.print()" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;padding:9px 16px;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">
            <i class="fa-solid fa-print"></i> Print Full Synthesis
          </button>
          <button onclick="document.getElementById('longitudinalModal').style.display='none'" style="background:#0d1b2a;border:none;color:#ffffff;padding:9px 20px;border-radius:8px;font-size:0.85rem;font-weight:700;cursor:pointer;">
            Done
          </button>
        </div>
      `;
    }
  } catch(e) {
    if (bodyEl) {
      bodyEl.innerHTML = `<div style="color:red;padding:20px;text-align:center;">Failed to generate synthesis: ${e.message}</div>`;
    }
  }
};
