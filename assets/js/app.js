// ============================================================
// BARANGAY MANAGEMENT SYSTEM — app.js
// All chart data comes from PHP (dashboard.php) via window.CHART_DATA.
// Zero hardcoded sample numbers in this file.
// ============================================================
 
const page = location.pathname.split('/').pop() || 'dashboard.php';
 
// ── Active nav highlight ──────────────────────────────────────
function highlightActiveNav() {
  document.querySelectorAll('.sidebar .menu a').forEach((link) => {
    if (!link || !link.getAttribute('href')) return;
    if (link.getAttribute('href') === page) {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');
    }
  });
}
 
// ── Counter animation ─────────────────────────────────────────
function animateCounters() {
  document.querySelectorAll('[data-target]').forEach((counter) => {
    const finalValue = Number(counter.getAttribute('data-target')) || 0;
    const duration   = 1400;
    const startTime  = performance.now();
    function update(now) {
      const progress = Math.min((now - startTime) / duration, 1);
      counter.textContent = Math.floor(progress * finalValue).toLocaleString();
      if (progress < 1) requestAnimationFrame(update);
      else counter.textContent = finalValue.toLocaleString();
    }
    requestAnimationFrame(update);
  });
}
 
// ── Dashboard charts ──────────────────────────────────────────
// dashboard.php sets window.CHART_DATA before this script runs.
// If CHART_DATA is absent, charts are skipped entirely.
function initDashboardCharts() {
  const cd = window.CHART_DATA;
  if (!cd) return;
 
  const noDataPlugin = (label) => ({
    id: 'noData',
    afterDraw(chart) {
      const anyData = chart.data.datasets[0]?.data?.some(v => v > 0);
      if (anyData) return;
      const { ctx, width, height } = chart;
      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = '#9ca3af';
      ctx.font = '13px sans-serif';
      ctx.fillText(label, width / 2, height / 2);
      ctx.restore();
    },
  });
 
  // ── Age Group Bar ───────────────────────────────────────────
  const ageCtx = document.getElementById('ageChart')?.getContext('2d');
  if (ageCtx) {
    new Chart(ageCtx, {
      type: 'bar',
      plugins: [noDataPlugin('No residents registered yet')],
      data: {
        labels: ['0–12', '13–25', '26–40', '41–60', '60+'],
        datasets: [{
          label: 'Residents',
          data: cd.ageData,
          backgroundColor: ['#3b82f6','#60a5fa','#7dd3fc','#38bdf8','#0ea5e9'],
          borderRadius: 14,
          maxBarThickness: 32,
        }],
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eff6ff' } },
          x: { grid: { display: false } },
        },
      },
    });
  }
 
  // ── Monthly Requests Line ───────────────────────────────────
  const requestsCtx = document.getElementById('requestsChart')?.getContext('2d');
  if (requestsCtx) {
    const hasReqs = cd.monthLabels.length > 0;
    new Chart(requestsCtx, {
      type: 'line',
      plugins: [noDataPlugin('No document requests yet')],
      data: {
        labels: hasReqs ? cd.monthLabels : ['—'],
        datasets: [{
          label: 'Document Requests',
          data:   hasReqs ? cd.monthData  : [0],
          borderColor: '#10b981',
          backgroundColor: 'rgba(16,185,129,0.14)',
          tension: 0.35,
          fill: true,
          pointRadius: 4,
          pointBackgroundColor: '#059669',
        }],
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eff6ff' } },
          x: { grid: { display: false } },
        },
      },
    });
  }
 
  // ── Gender Pie ──────────────────────────────────────────────
  const genderCtx = document.getElementById('genderChart')?.getContext('2d');
  if (genderCtx) {
    const hasGender = cd.genderLabels.length > 0 && cd.genderData.some(v => v > 0);
    new Chart(genderCtx, {
      type: 'pie',
      plugins: [noDataPlugin('No residents registered yet')],
      data: {
        labels:   hasGender ? cd.genderLabels : ['No data'],
        datasets: [{
          data:            hasGender ? cd.genderData : [1],
          backgroundColor: hasGender
            ? ['#3b82f6','#a855f7','#f97316','#10b981']
            : ['#e5e7eb'],
        }],
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
      },
    });
  }
 
  // ── Zone Doughnut ───────────────────────────────────────────
  const zoneCtx = document.getElementById('zoneChart')?.getContext('2d');
  if (zoneCtx) {
    const hasZone = cd.zoneLabels.length > 0 && cd.zoneData.some(v => v > 0);
    new Chart(zoneCtx, {
      type: 'doughnut',
      plugins: [noDataPlugin('No zone data yet')],
      data: {
        labels:   hasZone ? cd.zoneLabels : ['No data'],
        datasets: [{
          data:            hasZone ? cd.zoneData : [1],
          backgroundColor: hasZone
            ? ['#f97316','#facc15','#34d399','#60a5fa','#a855f7','#f43f5e']
            : ['#e5e7eb'],
          hoverOffset: 10,
        }],
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
      },
    });
  }
}
 
// ── User search ───────────────────────────────────────────────
function initUserSearch() {
  const searchInput = document.getElementById('userSearch');
  const rows = document.querySelectorAll('#usersTable tbody tr');
  if (!searchInput || !rows.length) return;
  searchInput.addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase();
    rows.forEach(row => { row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none'; });
  });
}
 
// ── Report filter ─────────────────────────────────────────────
function initReportFilters() {
  const filter = document.getElementById('reportFilter');
  if (!filter) return;
  filter.addEventListener('change', () => showToast(`Report: ${filter.value}`, 'info'));
}
 
// ── Export buttons ────────────────────────────────────────────
function initExportButtons() {
  document.querySelectorAll('.btn-export').forEach(btn => {
    btn.addEventListener('click', () => showToast(`${btn.textContent.trim()} ready`, 'success'));
  });
}
 
// ── data-toast buttons ────────────────────────────────────────
function initDataToast() {
  document.querySelectorAll('[data-toast]').forEach(el => {
    el.addEventListener('click', () => showToast(el.dataset.toast || 'Action started', 'info'));
  });
}
 
// ── Quick action cards ────────────────────────────────────────
function initQuickActions() {
  document.querySelectorAll('.quick-action-card').forEach(card => {
    card.addEventListener('click', () => showToast(`${card.dataset.action || 'Action'} opened`, 'info'));
  });
}
 
// ── Toast ─────────────────────────────────────────────────────
function showToast(message, type = 'info') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(12px)';
    setTimeout(() => toast.remove(), 300);
  }, 2600);
}
 
// ── Init ──────────────────────────────────────────────────────
function initAll() {
  highlightActiveNav();
  animateCounters();
  initExportButtons();
  initDataToast();
  initQuickActions();
  initReportFilters();
  initUserSearch();
  // Charts only when CHART_DATA is provided by dashboard.php
  if (window.CHART_DATA) initDashboardCharts();
}
 
window.addEventListener('DOMContentLoaded', initAll);
 