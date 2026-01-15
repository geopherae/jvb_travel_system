// Admin Dashboard JavaScript Utilities

function showToast(message) {
  const toast = document.createElement("div");
  toast.textContent = message;
  toast.className = "fixed bottom-4 right-4 bg-sky-600 text-white px-4 py-2 rounded shadow-lg text-sm z-50";
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

function showLoadingSpinner() {
  if (document.getElementById("reload-spinner")) return;
  const spinner = document.createElement("div");
  spinner.id = "reload-spinner";
  spinner.className = "fixed bottom-4 right-4 bg-white border border-gray-300 px-4 py-2 rounded shadow text-sm text-gray-600 z-50 flex items-center gap-2";
  spinner.setAttribute("role", "status");
  spinner.setAttribute("aria-live", "polite");
  spinner.innerHTML = `
    <svg class="animate-spin h-4 w-4 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
    </svg>
    <span>Refreshing clients...</span>
  `;
  document.body.appendChild(spinner);
}

function hideLoadingSpinner() {
  const spinner = document.getElementById("reload-spinner");
  if (spinner) spinner.remove();
}

function getStatusBadgeClass(status) {
  status = status.toLowerCase().trim();
  const classes = {
    'confirmed': 'bg-green-100 text-green-800',
    'trip ongoing': 'bg-blue-100 text-blue-800',
    'trip completed': 'bg-gray-100 text-gray-800',
    'awaiting docs': 'bg-yellow-100 text-yellow-800',
    'resubmit files': 'bg-red-100 text-red-800',
    'under review': 'bg-orange-100 text-orange-800',
    'no assigned package': 'bg-gray-200 text-gray-600',
    'cancelled': 'bg-red-200 text-red-900',
    'pending': 'bg-gray-100 text-gray-600'
  };
  return classes[status] || 'bg-gray-100 text-gray-600';
}

function manualClientTableReload(options = {}) {
  const silent = options.silent || false;
  showLoadingSpinner();

  fetch("../actions/reload_clients.php?_=" + new Date().getTime())
    .then(res => {
      if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
      return res.text();
    })
    .then(html => {
      const container = document.getElementById("clients-table-container");
      if (container) {
        container.innerHTML = html;
        const rows = container.querySelectorAll("tbody tr").length;
        hideLoadingSpinner();
        if (!silent) showToast(`🔄 Refresh complete. ${rows} clients loaded.`);
      } else {
        hideLoadingSpinner();
        if (!silent) showToast("⚠️ Table container not found.");
      }
    })
    .catch(err => {
      hideLoadingSpinner();
      if (!silent) showToast("⚠️ Refresh failed: " + err.message);
    });
}

let isCheckingStatus = false;

function runStatusCheck() {
  if (isCheckingStatus) return;
  isCheckingStatus = true;

  fetch("../actions/run_status_check.php?_=" + new Date().getTime(), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(res => {
      if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
      return res.json();
    })
    .then(data => {
      if (data.cached) {
        showToast(`⏳ ${data.message}`);
        isCheckingStatus = false;
        return;
      }

      if (data.count > 0) {
        showToast(`✅ ${data.count} status update${data.count > 1 ? 's' : ''}.`);
        let needsFullReload = false;

        data.updated.forEach(update => {
          const row = document.querySelector(`tr[data-client-id="${update.clientId}"]`);
          if (row) {
            const statusCell = row.querySelector('td:nth-child(4) span');
            if (statusCell) {
              statusCell.textContent = update.to;
              statusCell.className = `px-3 py-1 text-xs font-semibold rounded-full ${getStatusBadgeClass(update.to)}`;
            } else {
              needsFullReload = true;
            }
          } else {
            needsFullReload = true;
          }
        });

        if (needsFullReload) {
          setTimeout(() => manualClientTableReload({ silent: true }), 500);
        }
      }
      isCheckingStatus = false;
    })
    .catch(err => {
      showToast("⚠️ Status check failed: " + err.message);
      isCheckingStatus = false;
    });
}

// Auto-run status check on page load
document.addEventListener("DOMContentLoaded", () => {
  runStatusCheck();
});
