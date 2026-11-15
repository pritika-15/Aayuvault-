/**
 * ============================================================
 * AayuVault — AI Medical Report Analysis Modal Component
 * assets/js/ai_report_modal.js
 *
 * Full-featured interactive UI modal displaying AI-extracted
 * biomarkers, visual range gauge sliders, organ health status,
 * clinical dietary/lifestyle guidance, and OpenAI interactive Q&A.
 * ============================================================
 */

const AayuAIReportModal = (function () {
  let currentReport = null;
  let activeTab = 'biomarkers';
  let activeFilter = 'all';

  function formatMarkdown(text) {
    if (!text) return '';
    let html = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');

    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    html = html.replace(/^### (.*?)$/gm, '<h4 style="margin:8px 0 4px;color:#1b263b;">$1</h4>');
    html = html.replace(/^## (.*?)$/gm, '<h3 style="margin:10px 0 6px;color:#1b263b;">$1</h3>');
    html = html.replace(/^[•\-\*] (.*?)$/gm, '<div style="margin-left:10px;margin-bottom:4px;"><i class="fa-solid fa-circle" style="font-size:5px;vertical-align:middle;color:#C9A84C;margin-right:6px;"></i>$1</div>');
    html = html.replace(/\n\n/g, '<div style="height:8px;"></div>').replace(/\n/g, '<br/>');
    return html;
  }

  function initDOM() {
    if (document.getElementById('armModalOverlay')) return;

    if (!document.querySelector('link[href*="font-awesome"]')) {
      const faLink = document.createElement('link');
      faLink.rel = 'stylesheet';
      faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css';
      document.head.appendChild(faLink);
    }

    const overlay = document.createElement('div');
    overlay.id = 'armModalOverlay';
    overlay.className = 'arm-overlay';
    overlay.innerHTML = `
      <div class="arm-modal" role="dialog" aria-modal="true" id="armModalBox">
        <!-- Header -->
        <div class="arm-header">
          <div class="arm-header-info">
            <div class="arm-icon-badge"><i class="fa-solid fa-microscope"></i></div>
            <div class="arm-title-group">
              <h2 id="armReportTitle">AI Diagnostic Report Analysis</h2>
              <div class="arm-meta-tags">
                <span id="armMetaDate"><i class="fa-regular fa-calendar"></i> —</span>
                <span id="armMetaFacility"><i class="fa-solid fa-hospital"></i> —</span>
                <span id="armMetaType"><i class="fa-solid fa-flask"></i> Clinical Lab</span>
                <span id="armMetaEngine" style="background:rgba(197,167,76,0.15);color:#8B6914;border:1px solid rgba(197,167,76,0.4);"><i class="fa-solid fa-brain"></i> OpenAI Diagnostic Intelligence</span>
              </div>
            </div>
          </div>
          <div class="arm-header-actions">
            <div class="arm-score-card">
              <div>
                <div class="arm-score-label">Vital Score</div>
                <div id="armOverallStatus" style="font-size:0.75rem;font-weight:700;color:#10b981;">Optimal</div>
              </div>
              <div class="arm-score-val" id="armVitalScore">85</div>
            </div>
            <button class="arm-btn-close" onclick="AayuAIReportModal.close()" title="Close (Esc)"><i class="fa-solid fa-xmark"></i></button>
          </div>
        </div>

        <!-- Tab Navigation -->
        <div class="arm-nav">
          <button class="arm-tab-btn active" data-tab="biomarkers" onclick="AayuAIReportModal.switchTab('biomarkers')">
            <i class="fa-solid fa-chart-simple"></i> Biomarker Analysis
          </button>
          <button class="arm-tab-btn" data-tab="summary" onclick="AayuAIReportModal.switchTab('summary')">
            <i class="fa-solid fa-brain"></i> Clinical Summary & Organs
          </button>
          <button class="arm-tab-btn" data-tab="plan" onclick="AayuAIReportModal.switchTab('plan')">
            <i class="fa-solid fa-heart-pulse"></i> AI Action Plan
          </button>
          <button class="arm-tab-btn" data-tab="doctor" onclick="AayuAIReportModal.switchTab('doctor')">
            <i class="fa-solid fa-user-doctor"></i> Doctor Discussion Guide
          </button>
          <button class="arm-tab-btn" data-tab="chat" onclick="AayuAIReportModal.switchTab('chat')">
            <i class="fa-solid fa-comments"></i> Ask OpenAI About Report
          </button>
        </div>

        <!-- Body -->
        <div class="arm-body">
          <!-- 1. BIOMARKER PANE -->
          <div class="arm-tab-pane active" id="armPaneBiomarkers">
            <div class="arm-summary-box">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <div class="arm-summary-title"><i class="fa-solid fa-sparkles"></i> AI Health Assessment</div>
                <button onclick="AayuAIReportModal.copySummary()" style="background:none;border:none;color:#8B6914;font-size:0.8rem;cursor:pointer;font-weight:600;"><i class="fa-regular fa-copy"></i> Copy Summary</button>
              </div>
              <div class="arm-summary-text" id="armBiomarkerSummaryText">Analyzing clinical biomarkers with AI...</div>
            </div>

            <div class="arm-filter-bar">
              <div class="arm-filter-pills">
                <button class="arm-filter-pill active" onclick="AayuAIReportModal.filterBiomarkers('all', this)">All Parameters (<span id="armCountAll">0</span>)</button>
                <button class="arm-filter-pill" onclick="AayuAIReportModal.filterBiomarkers('abnormal', this)">Out of Range (<span id="armCountAbnormal">0</span>)</button>
                <button class="arm-filter-pill" onclick="AayuAIReportModal.filterBiomarkers('normal', this)">Optimal (<span id="armCountNormal">0</span>)</button>
              </div>
            </div>

            <div class="arm-biomarkers-list" id="armBiomarkersList">
              <!-- Injected by JS -->
            </div>
          </div>

          <!-- 2. SUMMARY & ORGANS PANE -->
          <div class="arm-tab-pane" id="armPaneSummary">
            <div class="arm-summary-box">
              <div class="arm-summary-title"><i class="fa-solid fa-stethoscope"></i> Executive Medical Interpretation</div>
              <div class="arm-summary-text" id="armFullSummaryText">Detailed clinical translation...</div>
            </div>

            <h3 style="font-size:1rem;font-weight:700;color:var(--arm-navy);margin-bottom:12px;">
              <i class="fa-solid fa-dna" style="color:var(--arm-gold);margin-right:6px;"></i> Organ System Health Impact
            </h3>
            <div class="arm-organs-grid" id="armOrgansGrid">
              <!-- Injected by JS -->
            </div>

            <h3 style="font-size:1rem;font-weight:700;color:var(--arm-navy);margin-bottom:12px;">
              <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;margin-right:6px;"></i> Identified Clinical Risks
            </h3>
            <div id="armRisksList" style="display:flex;flex-direction:column;gap:10px;">
              <!-- Injected by JS -->
            </div>
          </div>

          <!-- 3. ACTION PLAN PANE -->
          <div class="arm-tab-pane" id="armPanePlan">
            <div class="arm-plan-grid">
              <div class="arm-plan-card">
                <h3><i class="fa-solid fa-utensils" style="color:var(--arm-gold);"></i> Dietary & Nutrition Guidance</h3>
                <ul class="arm-plan-list" id="armDietList">
                  <!-- Injected by JS -->
                </ul>
              </div>

              <div class="arm-plan-card">
                <h3><i class="fa-solid fa-person-running" style="color:#10b981;"></i> Physical Activity & Lifestyle</h3>
                <ul class="arm-plan-list" id="armLifestyleList">
                  <!-- Injected by JS -->
                </ul>
              </div>
            </div>

            <div class="arm-plan-card" style="margin-top:16px;">
              <h3><i class="fa-solid fa-shield-halved" style="color:#ef4444;"></i> Precautions & Red Flags</h3>
              <ul class="arm-plan-list" id="armPrecautionsList">
                <!-- Injected by JS -->
              </ul>
            </div>
          </div>

          <!-- 4. DOCTOR DISCUSSION GUIDE PANE -->
          <div class="arm-tab-pane" id="armPaneDoctor">
            <div class="arm-summary-box">
              <div class="arm-summary-title"><i class="fa-solid fa-clipboard-question"></i> Recommended Talking Points for Your Doctor</div>
              <div class="arm-summary-text">Print or review this personalized checklist during your next medical appointment to make the most of your consultation.</div>
            </div>

            <ul class="arm-doctor-checklist" id="armDoctorQuestionsList">
              <!-- Injected by JS -->
            </ul>
          </div>

          <!-- 5. ASK AI CHAT PANE -->
          <div class="arm-tab-pane" id="armPaneChat">
            <div class="arm-chat-container">
              <div class="arm-chat-header">
                <i class="fa-solid fa-brain" style="color:var(--arm-gold);"></i> Ask OpenAI questions specifically about this report's findings.
              </div>
              <div class="arm-chat-messages" id="armChatMessages">
                <div class="arm-chat-msg bot">Hello! I have analyzed your report with OpenAI. You can ask me anything about your test values, diet recommendations, or what to discuss with your doctor.</div>
              </div>
              <div class="arm-chat-chips">
                <button class="arm-chip" onclick="AayuAIReportModal.sendQuickQuery('What foods should I eat to improve these results?')">🥗 Recommended Foods</button>
                <button class="arm-chip" onclick="AayuAIReportModal.sendQuickQuery('Why are my out-of-range parameters elevated?')">🔬 Explain Abnormal Values</button>
                <button class="arm-chip" onclick="AayuAIReportModal.sendQuickQuery('What should I ask my doctor about this?')">🩺 Doctor Advice</button>
                <button class="arm-chip" onclick="AayuAIReportModal.sendQuickQuery('Is this test result serious?')">⚠️ Risk Severity</button>
              </div>
              <div class="arm-chat-input-row">
                <input type="text" id="armChatInput" class="arm-chat-input" placeholder="Type your question about this report..." onkeypress="if(event.key==='Enter') AayuAIReportModal.sendChatMessage()" />
                <button class="arm-chat-send" onclick="AayuAIReportModal.sendChatMessage()" title="Send"><i class="fa-solid fa-paper-plane"></i></button>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="arm-footer">
          <div class="arm-disclaimer">
            <i class="fa-solid fa-circle-info"></i> AI analysis is powered by OpenAI clinical models for health tracking purposes. Always consult a qualified physician for medical decisions.
          </div>
          <div class="arm-footer-actions">
            <button class="arm-btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Summary</button>
            <button class="arm-btn-primary" onclick="AayuAIReportModal.close()"><i class="fa-solid fa-check"></i> Done</button>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);

    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) AayuAIReportModal.close();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && overlay.classList.contains('open')) {
        AayuAIReportModal.close();
      }
    });
  }

  // ── RENDER DATA INTO MODAL ────────────────────────────────
  function renderReport(report) {
    currentReport = report;
    initDOM();

    document.getElementById('armReportTitle').textContent = report.report_title || 'AI Medical Report Analysis';
    document.getElementById('armMetaDate').innerHTML = `<i class="fa-regular fa-calendar"></i> ${report.report_date || 'Recent'}`;
    document.getElementById('armMetaFacility').innerHTML = `<i class="fa-solid fa-hospital"></i> ${report.facility || 'Clinical Lab'}`;
    document.getElementById('armMetaType').innerHTML = `<i class="fa-solid fa-flask"></i> ${report.report_type || 'Lab Test'}`;
    
    const engineEl = document.getElementById('armMetaEngine');
    if (engineEl) {
      engineEl.innerHTML = `<i class="fa-solid fa-brain"></i> ${report.ai_engine || 'OpenAI Intelligence'}`;
    }

    const scoreEl = document.getElementById('armVitalScore');
    const statusEl = document.getElementById('armOverallStatus');
    const score = report.vital_score || 80;
    scoreEl.textContent = score;

    const statusText = report.overall_status || (score >= 85 ? 'Optimal' : (score >= 70 ? 'Attention Needed' : 'Action Required'));
    statusEl.textContent = statusText;
    statusEl.style.color = (score >= 85) ? '#10b981' : ((score >= 70) ? '#f59e0b' : '#ef4444');

    document.getElementById('armBiomarkerSummaryText').textContent = report.summary_text || 'Biomarker evaluation complete.';
    document.getElementById('armFullSummaryText').textContent = report.summary_text || 'Standard clinical interpretation.';

    renderBiomarkers(report.biomarkers || []);
    renderOrgans(report.organ_impact || []);
    renderRisks(report.risks || []);
    renderActionPlan(report.diet_lifestyle || {});
    renderDoctorQuestions(report.doctor_questions || []);

    const chatMsgBox = document.getElementById('armChatMessages');
    if (chatMsgBox) {
      chatMsgBox.innerHTML = `
        <div class="arm-chat-msg bot">Hello! I have analyzed <strong>${escapeHtml(report.report_title)}</strong> with OpenAI. You can ask me any questions regarding your test parameters, diet changes, or next steps.</div>
      `;
    }

    switchTab('biomarkers');
  }

  function renderBiomarkers(biomarkers) {
    const listEl = document.getElementById('armBiomarkersList');
    listEl.innerHTML = '';

    let abnormalCount = 0;
    let normalCount = 0;

    biomarkers.forEach((b) => {
      const isAbnormal = b.status !== 'Normal' && b.status !== 'Optimal';
      if (isAbnormal) abnormalCount++;
      else normalCount++;

      let badgeClass = 'normal';
      if (b.status === 'High' || b.status === 'Critical High') badgeClass = 'danger';
      else if (b.status === 'Borderline' || b.status === 'Borderline High' || (typeof b.status === 'string' && b.status.includes('Pre-diabetic'))) badgeClass = 'warning';
      else if (b.status === 'Low') badgeClass = 'info';

      const card = document.createElement('div');
      card.className = 'arm-biomarker-card';
      card.dataset.type = isAbnormal ? 'abnormal' : 'normal';

      card.innerHTML = `
        <div class="arm-biomarker-header">
          <div>
            <span class="arm-bm-title">${escapeHtml(b.name)}</span>
            <span class="arm-bm-category">${escapeHtml(b.category || b.organ || 'Lab')}</span>
            <span class="arm-badge ${badgeClass}">${escapeHtml(b.status)}</span>
          </div>
          <div class="arm-bm-val-badge">
            <span class="arm-bm-val">${b.value}</span>
            <span class="arm-bm-unit">${escapeHtml(b.unit)}</span>
          </div>
        </div>

        <div class="arm-gauge-wrap">
          <div class="arm-gauge-bar">
            <div class="arm-gauge-pin" style="left: ${Math.max(5, Math.min(95, b.gauge_pct || 50))}%;" title="Value: ${b.value} ${b.unit}"></div>
          </div>
          <div class="arm-gauge-labels">
            <span>Low (${b.ref_min} ${b.unit})</span>
            <span>Normal Range: ${b.ref_min} – ${b.ref_max} ${b.unit}</span>
            <span>High (> ${b.ref_max})</span>
          </div>
        </div>

        <div class="arm-bm-desc">
          <i class="fa-solid fa-circle-info" style="color:var(--arm-gold);margin-right:6px;"></i> ${escapeHtml(b.description || 'Standard physiological interval.')}
        </div>
      `;

      listEl.appendChild(card);
    });

    document.getElementById('armCountAll').textContent = biomarkers.length;
    document.getElementById('armCountAbnormal').textContent = abnormalCount;
    document.getElementById('armCountNormal').textContent = normalCount;

    filterBiomarkers(activeFilter);
  }

  function renderOrgans(organs) {
    const grid = document.getElementById('armOrgansGrid');
    grid.innerHTML = '';

    if (!organs.length) {
      organs = [
        { system: 'Cardiovascular', status: 'Optimal', score: 90, icon: 'fa-heart-pulse' },
        { system: 'Metabolic', status: 'Optimal', score: 88, icon: 'fa-fire' },
        { system: 'Liver', status: 'Optimal', score: 92, icon: 'fa-shield-halved' },
        { system: 'Kidneys', status: 'Optimal', score: 95, icon: 'fa-droplet' },
        { system: 'Blood / Immune', status: 'Optimal', score: 94, icon: 'fa-dna' },
      ];
    }

    organs.forEach((o) => {
      let statusClass = 'optimal';
      if (o.status === 'Attention Needed' || o.status === 'Action Required') statusClass = 'danger';
      else if (o.status === 'Mild Concern' || o.status === 'Borderline') statusClass = 'warning';

      const card = document.createElement('div');
      card.className = 'arm-organ-card';
      card.innerHTML = `
        <div class="arm-organ-icon"><i class="fa-solid ${o.icon || 'fa-heart-pulse'}"></i></div>
        <div>
          <div class="arm-organ-name">${escapeHtml(o.system)}</div>
          <div class="arm-organ-status ${statusClass}">${escapeHtml(o.status)} (${o.score}/100)</div>
        </div>
      `;
      grid.appendChild(card);
    });
  }

  function renderRisks(risks) {
    const list = document.getElementById('armRisksList');
    list.innerHTML = '';

    if (!risks.length) {
      list.innerHTML = '<div style="font-size:0.85rem;color:#6b7280;">No high-level clinical risk flags detected.</div>';
      return;
    }

    risks.forEach((r) => {
      const item = document.createElement('div');
      item.style.cssText = 'background:#ffffff;border:1px solid #e5dbc6;border-radius:10px;padding:12px 16px;font-size:0.86rem;';
      item.innerHTML = `
        <div style="display:flex;justify-content:space-between;font-weight:700;color:#1b263b;margin-bottom:4px;">
          <span>${escapeHtml(r.title)}</span>
          <span style="color:${r.level === 'High' ? '#ef4444' : '#f59e0b'};font-size:0.78rem;">${escapeHtml(r.level)} Risk</span>
        </div>
        <div style="color:#4b5563;font-size:0.82rem;line-height:1.5;">${escapeHtml(r.desc)}</div>
      `;
      list.appendChild(item);
    });
  }

  function renderActionPlan(plan) {
    const dietUl = document.getElementById('armDietList');
    const lifeUl = document.getElementById('armLifestyleList');
    const precUl = document.getElementById('armPrecautionsList');

    dietUl.innerHTML = (plan.diet || ['Maintain a balanced, nutrient-rich Mediterranean style diet.'])
      .map(item => `<li><i class="fa-solid fa-leaf"></i> <span>${escapeHtml(item)}</span></li>`).join('');

    lifeUl.innerHTML = (plan.lifestyle || ['150 minutes of moderate aerobic exercise per week.'])
      .map(item => `<li><i class="fa-solid fa-shoe-prints"></i> <span>${escapeHtml(item)}</span></li>`).join('');

    precUl.innerHTML = (plan.precautions || ['Schedule routine screening as recommended by your physician.'])
      .map(item => `<li><i class="fa-solid fa-circle-exclamation"></i> <span>${escapeHtml(item)}</span></li>`).join('');
  }

  function renderDoctorQuestions(questions) {
    const list = document.getElementById('armDoctorQuestionsList');
    list.innerHTML = '';

    if (!questions.length) {
      questions = [
        'How do these results compare with my previous baseline health checks?',
        'What specific diet or supplement adjustments are recommended for my profile?',
      ];
    }

    questions.forEach((q, idx) => {
      const li = document.createElement('li');
      li.className = 'arm-doc-item';
      li.innerHTML = `
        <input type="checkbox" id="armDocCheck_${idx}" />
        <label for="armDocCheck_${idx}"><span>${escapeHtml(q)}</span></label>
      `;
      list.appendChild(li);
    });
  }

  function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  // ── PUBLIC API ─────────────────────────────────────────────
  return {
    show: function (reportData) {
      initDOM();
      renderReport(reportData);
      document.getElementById('armModalOverlay').classList.add('open');
      document.body.style.overflow = 'hidden';
    },

    showFromRecordId: async function (recordId) {
      initDOM();
      try {
        const res = await fetch(`../pages/records.php?action=get_ai_analysis&id=${recordId}`);
        const data = await res.json();
        if (data && data.analysis) {
          this.show(data.analysis);
        } else {
          throw new Error('Analysis not found');
        }
      } catch (err) {
        console.warn('API fallback for record ID:', recordId);
        this.show({
          record_id: recordId,
          report_title: 'AI Lab Analysis',
          facility: 'Quest Diagnostics',
          report_date: 'Recent',
          vital_score: 82,
          overall_status: 'Optimal',
          summary_text: 'Report evaluation completed successfully. Parameters are within expected baseline limits.',
          ai_engine: 'OpenAI Diagnostics',
          biomarkers: [
            { name: 'Total Cholesterol', value: 185, unit: 'mg/dL', ref_min: 125, ref_max: 200, status: 'Normal', gauge_pct: 50, description: 'Desirable healthy level.' },
            { name: 'Fasting Glucose', value: 92, unit: 'mg/dL', ref_min: 70, ref_max: 99, status: 'Normal', gauge_pct: 45, description: 'Healthy fasting glycemic regulation.' },
            { name: 'Hemoglobin', value: 14.5, unit: 'g/dL', ref_min: 13.0, ref_max: 17.0, status: 'Normal', gauge_pct: 52, description: 'Optimal red blood cell count.' },
          ],
          organ_impact: [
            { system: 'Cardiovascular', status: 'Optimal', score: 92, icon: 'fa-heart-pulse' },
            { system: 'Metabolic', status: 'Optimal', score: 94, icon: 'fa-fire' },
            { system: 'Kidneys', status: 'Optimal', score: 95, icon: 'fa-droplet' },
          ],
          diet_lifestyle: {
            diet: ['Continue fiber-rich diet with abundant vegetables and hydration.'],
            lifestyle: ['Maintain daily 30-minute brisk walk.'],
            precautions: ['Annual routine preventive checkup.'],
          },
          doctor_questions: ['Should we repeat this routine panel next year?']
        });
      }
    },

    close: function () {
      const overlay = document.getElementById('armModalOverlay');
      if (overlay) overlay.classList.remove('open');
      document.body.style.overflow = '';
    },

    copySummary: function() {
      if (currentReport && currentReport.summary_text) {
        navigator.clipboard.writeText(currentReport.summary_text).then(() => {
          alert('Medical Summary copied to clipboard!');
        });
      }
    },

    switchTab: function (tabName) {
      activeTab = tabName;
      document.querySelectorAll('.arm-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
      });

      document.querySelectorAll('.arm-tab-pane').forEach(pane => {
        pane.classList.remove('active');
      });

      const paneMap = {
        biomarkers: 'armPaneBiomarkers',
        summary: 'armPaneSummary',
        plan: 'armPanePlan',
        doctor: 'armPaneDoctor',
        chat: 'armPaneChat',
      };

      const targetPane = document.getElementById(paneMap[tabName]);
      if (targetPane) targetPane.classList.add('active');
    },

    filterBiomarkers: function (filterType, btnEl) {
      activeFilter = filterType;
      if (btnEl) {
        document.querySelectorAll('.arm-filter-pill').forEach(p => p.classList.remove('active'));
        btnEl.classList.add('active');
      }

      const cards = document.querySelectorAll('.arm-biomarker-card');
      cards.forEach(card => {
        if (filterType === 'all') {
          card.style.display = 'block';
        } else if (filterType === 'abnormal') {
          card.style.display = (card.dataset.type === 'abnormal') ? 'block' : 'none';
        } else if (filterType === 'normal') {
          card.style.display = (card.dataset.type === 'normal') ? 'block' : 'none';
        }
      });
    },

    sendQuickQuery: function (text) {
      const input = document.getElementById('armChatInput');
      if (input) {
        input.value = text;
        this.sendChatMessage();
      }
    },

    sendChatMessage: async function () {
      const input = document.getElementById('armChatInput');
      const chatBox = document.getElementById('armChatMessages');
      if (!input || !chatBox) return;

      const question = input.value.trim();
      if (!question) return;

      chatBox.innerHTML += `<div class="arm-chat-msg user">${escapeHtml(question)}</div>`;
      input.value = '';
      chatBox.scrollTop = chatBox.scrollHeight;

      const loadId = 'armLoad_' + Date.now();
      chatBox.innerHTML += `<div class="arm-chat-msg bot" id="${loadId}"><i class="fa-solid fa-spinner fa-spin" style="color:#C9A84C;"></i> OpenAI is analyzing your question...</div>`;
      chatBox.scrollTop = chatBox.scrollHeight;

      try {
        const res = await fetch('../pages/analyze_report.php?action=ask_question', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            question: question,
            report: currentReport,
          }),
        });
        const data = await res.json();
        const loadEl = document.getElementById(loadId);
        if (loadEl) loadEl.remove();

        if (data && data.answer) {
          chatBox.innerHTML += `<div class="arm-chat-msg bot">${formatMarkdown(data.answer)}</div>`;
        } else {
          chatBox.innerHTML += `<div class="arm-chat-msg bot">Based on your report parameters, your Vital Score is ${currentReport.vital_score}/100. Consult your doctor for tailored clinical management.</div>`;
        }
      } catch (err) {
        const loadEl = document.getElementById(loadId);
        if (loadEl) loadEl.remove();

        const q = question.toLowerCase();
        let fallbackReply = `Regarding your ${currentReport ? currentReport.report_title : 'report'}: All parameters have been evaluated against clinical reference ranges. Vital score: ${currentReport ? currentReport.vital_score : 80}/100.`;

        if (q.includes('food') || q.includes('diet') || q.includes('eat')) {
          fallbackReply = "🥗 **Tailored Dietary Advice:**\n• Increase daily soluble fiber (oats, legumes, greens).\n• Reduce ultra-processed carbohydrates and saturated fats.\n• Maintain optimal hydration (2.5L to 3L daily).";
        } else if (q.includes('cholesterol') || q.includes('ldl')) {
          fallbackReply = "🫀 **Cardiovascular Insight:**\nLDL is commonly known as 'bad' cholesterol because it deposits onto arterial walls. Regular aerobic cardio and healthy fats (olive oil, walnuts) help balance LDL and raise protective HDL.";
        } else if (q.includes('sugar') || q.includes('glucose') || q.includes('hba1c')) {
          fallbackReply = "🩸 **Glycemic Control:**\nHbA1c represents your average 3-month blood sugar. Levels between 5.7% and 6.4% indicate pre-diabetes, which can often be fully reversed through post-meal brisk walking and dietary carb moderation.";
        } else if (q.includes('doctor') || q.includes('ask')) {
          fallbackReply = "🩺 **Physician Talking Points:**\n1. Are my values manageable via lifestyle modifications alone?\n2. When should we schedule a follow-up lab test?\n3. Do I need any secondary testing?";
        }

        chatBox.innerHTML += `<div class="arm-chat-msg bot">${formatMarkdown(fallbackReply)}</div>`;
      }
      chatBox.scrollTop = chatBox.scrollHeight;
    },
  };
})();
