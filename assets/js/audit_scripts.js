document.addEventListener('DOMContentLoaded', () => {
  // 📊 KPI Bar Chart
  const kpiCtx = document.getElementById('kpiChart');
  if (kpiCtx) {
    new Chart(kpiCtx.getContext('2d'), {
      type: 'bar',
      data: {
        labels: window.kpiLabels || [],
        datasets: [{
          label: 'Log Volume',
          data: window.kpiData || [],
          backgroundColor: 'rgba(102, 126, 234, 0.8)',
          hoverBackgroundColor: 'rgba(102, 126, 234, 1)',
          borderRadius: 8,
          borderSkipped: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: { display: false },
          tooltip: { 
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            cornerRadius: 8,
            titleFont: { size: 14, weight: 'bold' },
            bodyFont: { size: 13 },
            callbacks: {
              label: (context) => `${context.parsed.y} logs`
            }
          }
        },
        scales: {
          y: { 
            beginAtZero: true,
            grid: {
              color: 'rgba(0, 0, 0, 0.05)',
              drawBorder: false
            },
            ticks: {
              font: { size: 11 },
              color: '#6B7280'
            }
          },
          x: {
            grid: { display: false },
            ticks: {
              font: { size: 11 },
              color: '#6B7280'
            }
          }
        }
      }
    });
  }

// 🥧 Document Status Pie Chart
const statusChartEl = document.getElementById('statusPieChart');

if (statusChartEl) {
  fetch('../components/audit_chart_document_status.php')
    .then(res => res.json())
    .then(chartData => {
      const hasData = Array.isArray(chartData.data) && chartData.data.some(val => val > 0);

      if (!hasData) {
        statusChartEl.innerHTML = `
          <div class="text-center text-sm text-gray-500 py-8">
            <p class="text-2xl mb-2">📄</p>
            <p class="font-semibold text-gray-700">No document activity yet</p>
            <p class="mt-1 text-xs">Document status will appear here once clients begin uploading</p>
          </div>
        `;
        return;
      }

      new Chart(statusChartEl, {
        type: 'doughnut',
        data: {
          labels: chartData.labels,
          datasets: [{
            data: chartData.data,
            backgroundColor: [
              'rgba(251, 191, 36, 0.8)',  // Yellow - Pending
              'rgba(34, 197, 94, 0.8)',   // Green - Approved
              'rgba(239, 68, 68, 0.8)'    // Red - Rejected
            ],
            hoverBackgroundColor: [
              'rgba(251, 191, 36, 1)',
              'rgba(34, 197, 94, 1)',
              'rgba(239, 68, 68, 1)'
            ],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          cutout: '60%',
          plugins: {
            legend: { 
              position: 'bottom',
              labels: {
                padding: 15,
                font: { size: 12 },
                color: '#374151',
                usePointStyle: true,
                pointStyle: 'circle'
              }
            },
            title: {
              display: true,
              text: 'Document Status',
              font: { size: 14, weight: 'bold' },
              color: '#111827',
              padding: { bottom: 20 }
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              padding: 12,
              cornerRadius: 8,
              titleFont: { size: 13, weight: 'bold' },
              bodyFont: { size: 12 },
              callbacks: {
                label: (context) => {
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = ((context.parsed / total) * 100).toFixed(1);
                  return `${context.label}: ${context.parsed} (${percentage}%)`;
                }
              }
            }
          }
        }
      });
    })
    .catch(error => {
      statusChartEl.innerHTML = `
        <div class="text-center text-sm text-red-500 py-8">
          <p class="text-2xl mb-2">⚠️</p>
          <p class="font-semibold">Unable to load chart</p>
          <p class="mt-1 text-xs">Please try again later</p>
        </div>
      `;
      console.error('Chart fetch error:', error);
    });
}

// 📈 Onboarding Velocity Line Chart (creation → confirmed)
const velocityChartEl = document.getElementById('velocityLineChart');
if (velocityChartEl) {
  const formatHours = (hoursValue) => {
    const hours = Number(hoursValue || 0);
    if (hours < 1) return `${Math.round(hours * 60)}m`;
    if (hours >= 24) return `${(hours / 24).toFixed(hours >= 72 ? 0 : 1)}d`;
    return `${hours.toFixed(1)}h`;
  };

  const renderHelper = (container, text) => {
    if (!container) return;
    const help = document.createElement('div');
    help.className = 'mt-3 text-xs text-gray-600 bg-white/60 border border-gray-200 rounded-lg p-3 leading-relaxed';
    help.innerHTML = text;
    container.appendChild(help);
  };

  fetch('../components/audit_chart_onboarding_velocity.php')
    .then(res => res.json())
    .then(chartData => {
      const hasData = Array.isArray(chartData.data) && chartData.data.length > 0;

      // Empty state for clarity
      if (!hasData) {
        velocityChartEl.outerHTML = `
          <div class="text-center text-sm text-gray-500 py-10">
            <p class="text-2xl mb-2">📉</p>
            <p class="font-semibold text-gray-700">No onboarding data yet</p>
            <p class="mt-1 text-xs">This chart will show how long it takes clients to move from account creation to confirmed.</p>
          </div>
        `;
        return;
      }

      const chartContainer = velocityChartEl.parentElement;

      new Chart(velocityChartEl, {
        type: 'line',
        data: {
          labels: chartData.labels,
          datasets: [{
            label: 'Avg time to Confirmed',
            data: chartData.data,
            borderColor: 'rgba(102, 126, 234, 1)',
            backgroundColor: 'rgba(102, 126, 234, 0.15)',
            borderWidth: 3,
            tension: 0.35,
            fill: true,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: 'rgba(102, 126, 234, 1)',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7,
            pointHoverBackgroundColor: 'rgba(102, 126, 234, 1)',
            pointHoverBorderColor: '#fff',
            pointHoverBorderWidth: 3
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          interaction: {
            mode: 'index',
            intersect: false
          },
          scales: {
            y: {
              beginAtZero: true,
              reverse: false,
              title: {
                display: true,
                text: 'Avg Hours to Confirmed (lower is better)',
                font: { size: 12, weight: 'bold' },
                color: '#6B7280'
              },
              grid: {
                color: 'rgba(0, 0, 0, 0.05)',
                drawBorder: false
              },
              ticks: {
                callback: value => formatHours(value),
                font: { size: 11 },
                color: '#6B7280'
              }
            },
            x: {
              title: {
                display: true,
                text: 'ISO Week',
                font: { size: 12, weight: 'bold' },
                color: '#6B7280'
              },
              grid: { display: false },
              ticks: {
                font: { size: 11 },
                color: '#6B7280'
              }
            }
          },
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.85)',
              padding: 12,
              cornerRadius: 8,
              titleFont: { size: 14, weight: 'bold' },
              bodyFont: { size: 13 },
              callbacks: {
                label: context => `Avg: ${formatHours(context.parsed.y)} (≈ ${context.parsed.y.toFixed(1)}h)`
              }
            }
          }
        }
      });

      renderHelper(chartContainer, `
        <strong>How to read:</strong> Each point shows the average time from account creation to confirmed for clients created that ISO week. Hover to see exact hours; lower numbers mean smoother onboarding.
      `);
    })
    .catch(() => {
      velocityChartEl.outerHTML = `
        <div class="text-center text-sm text-red-500 py-10">
          <p class="text-2xl mb-2">⚠️</p>
          <p class="font-semibold">Unable to load onboarding velocity</p>
          <p class="mt-1 text-xs">Please try again later.</p>
        </div>
      `;
    });
}

  // 📄 Audit Table Pagination + Filters
  const tableBody = document.getElementById('audit-table-body');
  const limitSelect = document.getElementById('limitSelect');
  const filterForm = document.getElementById('auditFilters');

  function getFilterParams() {
    const params = new URLSearchParams(new FormData(filterForm));
    return params.toString();
  }

  function loadAuditPage(page = 1) {
    if (!tableBody || !limitSelect || !filterForm) return;

    const limit = parseInt(limitSelect.value) || 5;
    const filters = getFilterParams();

    fetch(`../components/audit_table_data.php?page=${page}&limit=${limit}&${filters}`)
      .then(res => res.json())
      .then(data => {
        tableBody.innerHTML = data.rows;
        renderPagination(data.page, data.totalPages);
      });
  }

  function attachPaginationHandlers() {
    const pageLinks = document.querySelectorAll('.audit-pagination a[data-page]');
    pageLinks.forEach(link => {
      link.addEventListener('click', e => {
        e.preventDefault();
        const page = parseInt(link.dataset.page);
        if (!isNaN(page)) loadAuditPage(page);
      });
    });
  }

  function renderPagination(currentPage, totalPages) {
    const container = document.getElementById('paginationLinks');
    container.innerHTML = '';

    if (currentPage > 1) {
      container.innerHTML += `<a href="#" data-page="${currentPage - 1}" class="px-3 py-2 rounded-lg bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 transition text-gray-700 font-medium">‹</a>`;
    }

    for (let i = 1; i <= totalPages; i++) {
      const active = i === currentPage ? 'bg-sky-600 text-white border-sky-600' : 'bg-white border-gray-300 hover:bg-gray-50 text-gray-700';
      container.innerHTML += `<a href="#" data-page="${i}" class="px-3 py-2 rounded-lg border ${active} hover:border-gray-400 transition font-medium">${i}</a>`;
    }

    if (currentPage < totalPages) {
      container.innerHTML += `<a href="#" data-page="${currentPage + 1}" class="px-3 py-2 rounded-lg bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 transition text-gray-700 font-medium">›</a>`;
    }

    attachPaginationHandlers();
  }

  // Initial load
  loadAuditPage();

  // Limit dropdown change
  limitSelect?.addEventListener('change', () => {
    loadAuditPage(1);
  });

  // Filter auto-submit
  filterForm?.addEventListener('input', () => {
    loadAuditPage(1);
  });

  document.getElementById('clearFiltersBtn')?.addEventListener('click', () => {
  const form = document.getElementById('auditFilters');
  form.reset(); // Reset all fields
  loadAuditPage(1); // Reload table with default filters
  });
});