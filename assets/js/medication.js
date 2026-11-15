/* =============================================
   app.js — AayuVault Medications Dashboard
   ============================================= */

// ─── DATA ────────────────────────────────────

const activeMedications = [
  { name: "Atorvastatin 20mg", dosage: "Take 1 tablet daily", priority: true },
  { name: "Metformin 25mg",    dosage: "Take 1 tablet daily", priority: true },
  { name: "Atorvastatin 20mg", dosage: "Take 1 tablet daily", priority: false },
  { name: "Cuntoontis 20mg",   dosage: "Take 1 tablet daily", priority: true },
  { name: "Atorvastatin 20mg", dosage: "Take 1 tablet daily", priority: false },
  { name: "Atorvastatin 10mg", dosage: "Take 1 tablet daily", priority: false },
  { name: "Morvastatin 5mg",   dosage: "Take 1 tablet daily", priority: false },
];

const inactiveMedications = [
  { name: "Metformiin 20mg",   dosage: "Take 1 tablet daily" },
  { name: "Atorvastatin 20mg", dosage: "Take 1 tablet daily" },
];

const upcomingRefills = [
  "Refill due for Metformin: March 22",
  "Refill due for Metformin: March 23",
  "Refill due for Metformin: March 16",
];

const wellnessInsights = [
  { icon: "🥗", text: "Suggesting a low-sodium diet and daily aerobic exercise, as prioritized by your AI analysis." },
  { icon: "🧘", text: "Suggestion for personalized stress management techniques." },
  { icon: "⚠️", text: "Priority alert for potential drug-supplement interaction (Review with Doctor)." },
];

// ─── ICON SVG HELPERS ────────────────────────

function pillIcon() {
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
    <path d="M10.5 20H4a2 2 0 01-2-2V6a2 2 0 012-2h6.5"/>
    <path d="M13.5 4H20a2 2 0 012 2v12a2 2 0 01-2 2h-6.5"/>
    <line x1="12" y1="4" x2="12" y2="20"/>
  </svg>`;
}

function pillIconInactive() {
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
    <circle cx="12" cy="12" r="9"/>
    <line x1="4.5" y1="4.5" x2="19.5" y2="19.5"/>
  </svg>`;
}

function refreshIcon() {
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
    <polyline points="23 4 23 10 17 10"/>
    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
  </svg>`;
}

function priorityStar() {
  return `<svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13">
    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
  </svg>`;
}

// ─── RENDER: MEDICATION CARD ─────────────────

function createMedCard(med, isInactive = false) {
  const card = document.createElement("div");
  card.className = `med-card${isInactive ? " inactive" : ""}`;

  const priorityHTML = (!isInactive && med.priority)
    ? `<span class="priority-badge">${priorityStar()} Priority</span>`
    : "";

  const actionsHTML = !isInactive
    ? `<div class="med-actions">
        ${priorityHTML}
        <button class="med-link">
          ${refreshIcon()} Refill
        </button>
        <button class="med-link">Reminders</button>
       </div>`
    : "";

  card.innerHTML = `
    <div class="med-card-top">
      <div class="med-icon-wrap ${isInactive ? "inactive-icon" : ""}">
        ${isInactive ? pillIconInactive() : pillIcon()}
      </div>
      <button class="star-btn" title="Favourite">★</button>
    </div>
    <div class="med-name">${med.name}</div>
    <div class="med-dosage">${med.dosage}</div>
    ${actionsHTML}
  `;

  // Toggle star favourite
  const starBtn = card.querySelector(".star-btn");
  starBtn.addEventListener("click", () => {
    starBtn.style.color = starBtn.dataset.active === "1"
      ? "var(--gold)"
      : "var(--gold-light)";
    starBtn.dataset.active = starBtn.dataset.active === "1" ? "0" : "1";
  });

  return card;
}

// ─── RENDER: MEDICATIONS ─────────────────────

function renderMedications() {
  const activeGrid   = document.getElementById("med-grid");
  const inactiveGrid = document.getElementById("inactive-grid");

  activeMedications.forEach(med => {
    activeGrid.appendChild(createMedCard(med, false));
  });

  inactiveMedications.forEach(med => {
    inactiveGrid.appendChild(createMedCard(med, true));
  });
}

// ─── RENDER: REFILLS ─────────────────────────

function renderRefills() {
  const list = document.getElementById("refills-list");
  upcomingRefills.forEach(text => {
    const item = document.createElement("div");
    item.className = "refill-item";
    item.textContent = text;
    list.appendChild(item);
  });
}

// ─── RENDER: WELLNESS INSIGHTS ───────────────

function renderWellness() {
  const ul = document.getElementById("wellness-list");
  wellnessInsights.forEach(insight => {
    const li = document.createElement("li");
    li.className = "wellness-item";
    li.innerHTML = `
      <span class="wellness-icon">${insight.icon}</span>
      <span>${insight.text}</span>
    `;
    ul.appendChild(li);
  });
}

// ─── CALENDAR ────────────────────────────────

let calDate = new Date();  // tracks the displayed month

function renderCalendar() {
  const grid  = document.getElementById("cal-grid");
  const label = document.getElementById("cal-month-label");

  const year  = calDate.getFullYear();
  const month = calDate.getMonth();

  const monthNames = [
    "January","February","March","April","May","June",
    "July","August","September","October","November","December"
  ];
  label.textContent = `${monthNames[month]} ${year}`;

  // Clear previous cells
  grid.innerHTML = "";

  // First day of month (0=Sun … 6=Sat)
  const firstDay = new Date(year, month, 1).getDay();

  // Total days in month
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  // Today for highlighting
  const today    = new Date();
  const isToday  = (d) =>
    d === today.getDate() &&
    month === today.getMonth() &&
    year  === today.getFullYear();

  // Blank cells before the 1st
  for (let i = 0; i < firstDay; i++) {
    const empty = document.createElement("div");
    empty.className = "cal-cell empty";
    grid.appendChild(empty);
  }

  // Day cells
  for (let d = 1; d <= daysInMonth; d++) {
    const cell = document.createElement("div");
    cell.className = `cal-cell${isToday(d) ? " today" : ""}`;
    cell.textContent = d;
    grid.appendChild(cell);
  }
}

function bindCalendarNav() {
  document.getElementById("prev-month").addEventListener("click", () => {
    calDate = new Date(calDate.getFullYear(), calDate.getMonth() - 1, 1);
    renderCalendar();
  });

  document.getElementById("next-month").addEventListener("click", () => {
    calDate = new Date(calDate.getFullYear(), calDate.getMonth() + 1, 1);
    renderCalendar();
  });
}

// ─── INIT ─────────────────────────────────────

document.addEventListener("DOMContentLoaded", () => {
  renderMedications();
  renderRefills();
  renderWellness();
  renderCalendar();
  bindCalendarNav();
});