<?php
include_once __DIR__ . '/../admin/admin_session_check.php';
// 🔐 Auth check
if (empty($_SESSION['admin']['id'])) {
  header("Location: admin_login.php");
  exit();
}

// 📦 Includes
include_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/status-helpers.php';
require_once __DIR__ . '/../actions/client_status_checker.php';
require_once __DIR__ . '/../components/status_alert.php';


// 🚫 Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 🛠 DB connection
require_once __DIR__ . '/../actions/db.php';

$adminId = $_SESSION['admin']['id'] ?? null;

// 🧠 Check if there's a pending weekly survey that is eligible to show
$pendingCheck = $conn->prepare("
  SELECT COUNT(*) FROM user_survey_status 
  WHERE user_id = ? AND user_role = 'admin' 
  AND survey_type = 'admin_weekly_survey' 
  AND is_completed = 0 AND created_at <= NOW()
");
$pendingCheck->bind_param("i", $adminId);
$pendingCheck->execute();
$pendingCheck->bind_result($pendingCount);
$pendingCheck->fetch();
$pendingCheck->close();

if ($pendingCount > 0) {
  $_SESSION['show_weekly_survey_modal'] = true;
  $_SESSION['survey_type'] = 'admin_weekly_survey';
} else {
  $lastCompletedCheck = $conn->prepare("
    SELECT completed_at FROM user_survey_status 
    WHERE user_id = ? AND user_role = 'admin' 
    AND survey_type = 'admin_weekly_survey' AND is_completed = 1 
    ORDER BY completed_at DESC LIMIT 1
  ");
  $lastCompletedCheck->bind_param("i", $adminId);
  $lastCompletedCheck->execute();
  $lastCompletedCheck->bind_result($lastCompletedAt);
  $lastCompletedCheck->fetch();
  $lastCompletedCheck->close();

  $now = new DateTime();
  $lastCompleted = $lastCompletedAt ? new DateTime($lastCompletedAt) : null;
  $intervalDays = $lastCompleted ? $lastCompleted->diff($now)->days : 999;

  $futureCheck = $conn->prepare("
    SELECT COUNT(*) FROM user_survey_status 
    WHERE user_id = ? AND user_role = 'admin' 
    AND survey_type = 'admin_weekly_survey' 
    AND is_completed = 0 AND created_at > NOW()
  ");
  $futureCheck->bind_param("i", $adminId);
  $futureCheck->execute();
  $futureCheck->bind_result($futureCount);
  $futureCheck->fetch();
  $futureCheck->close();

  if ($intervalDays >= 7 && $futureCount == 0) {
    $createdAt = $now->format('Y-m-d H:i:s');
    $insertSurvey = $conn->prepare("
      INSERT INTO user_survey_status 
      (user_id, user_role, survey_type, is_completed, created_at) 
      VALUES (?, 'admin', 'admin_weekly_survey', 0, ?)
    ");
    $insertSurvey->bind_param("is", $adminId, $createdAt);
    $insertSurvey->execute();
    $insertSurvey->close();

    $_SESSION['show_weekly_survey_modal'] = true;
    $_SESSION['survey_type'] = 'admin_weekly_survey';
  }
}

// 👥 Fetch clients
$clientQuery = "
  SELECT 
    c.id, 
    c.full_name, 
    c.booking_number,
    c.client_profile_photo, 
    CASE
      WHEN c.trip_date_start IS NOT NULL AND c.trip_date_end IS NOT NULL THEN
        CONCAT(DATEDIFF(c.trip_date_end, c.trip_date_start) + 1, ' Days / ',
               DATEDIFF(c.trip_date_end, c.trip_date_start), ' Nights')
      ELSE '—'
    END AS duration,
    CASE
      WHEN c.trip_date_start IS NOT NULL AND c.trip_date_end IS NOT NULL THEN
        CONCAT(
          DATE_FORMAT(c.trip_date_start, '%b %e'), ' to ',
          DATE_FORMAT(c.trip_date_end, '%b %e'), ', ',
          DATE_FORMAT(c.trip_date_end, '%Y')
        )
      ELSE '—'
    END AS trip_date_range,
    IFNULL(c.status, '—') AS status
  FROM clients c
  LEFT JOIN tour_packages t ON c.assigned_package_id = t.id
  ORDER BY c.full_name
";
$clients = $conn->query($clientQuery)->fetch_all(MYSQLI_ASSOC);

// 🧳 Fetch packages
$packageQuery = "SELECT id, package_name, package_description FROM tour_packages ORDER BY package_name ASC";
$packageResult = $conn->query($packageQuery);
$packages = $packageResult ? $packageResult->fetch_all(MYSQLI_ASSOC) : [];

// 👤 Admin info
$isAdmin = true;
$adminName = $_SESSION['first_name'] ?? 'Admin';

// 🔐 Access code generator
function generateAccessCode($fullName = 'Guest User') {
  $parts = explode(' ', strtoupper(trim($fullName)));
  $first = substr($parts[0] ?? 'XX', 0, 2);
  $last = substr($parts[1] ?? $parts[0] ?? 'YY', 0, 2);
  $date = date('md');
  return "{$first}{$last}-{$date}";
}

$preGeneratedAccessCode = generateAccessCode('New Client');
?>

<!DOCTYPE html>
<html lang="en" class="bg-gray-50">
<head>
  <style>[x-cloak] { display: none !important; }</style>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <script src="../includes/admin-dashboard.js"></script>
</head>

<body class="font-poppins text-gray-800 overflow-hidden"
      x-data="{  
        sidebarOpen: false,
        editClientModal: false,
        showAddClientModal: false,
        step: 1,
        fullName: '',
        email: '',
        phone: '',
        accessCode: 'XXYY-0000',
        copied: false,
        updateAccessCode() {
          const parts = this.fullName.trim().split(' ').filter(Boolean);
          if (parts.length === 0) {
            this.accessCode = 'XXYY-0000';
            return;
          }
          const first = (parts[0] || 'XX').substring(0, 2).toUpperCase();
          const last = (parts[1] || '').substring(0, 2).toUpperCase() || first;
          const date = new Date();
          const month = (date.getMonth() + 1).toString().padStart(2, '0');
          const day = date.getDate().toString().padStart(2, '0');
          this.accessCode = `${first}${last}-${month}${day}`;
        },
        isValidEmail() {
          return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email);
        },
        isValidPhone() {
          return /^09\d{9}$/.test(this.phone);
        },
        canProceed() {
          return this.fullName.trim() !== '' &&
                 this.isValidEmail() &&
                 this.isValidPhone();
        }
      }"
      x-init="
        $watch('fullName', () => updateAccessCode());
        $watch('showAddClientModal', val => {
          if (val) {
            step = 1;
            fullName = '';
            email = '';
            phone = '';
            accessCode = 'XXYY-0000';
            copied = false;
          }
        });
        window.addEventListener('keydown', e => {
          if (e.key === 'Escape') showAddClientModal = false;
        });
      " style="background: linear-gradient(to bottom, #e0f7ff 0%, white 10%, white 100%);">




  <!-- Mobile Toggle -->
  <button @click="sidebarOpen = !sidebarOpen" class="p-3 md:hidden absolute top-4 left-4 z-30 bg-primary text-white rounded">
    ☰
  </button>

  <!-- Sidebar -->
  <?php include '../components/admin_sidebar.php'; ?>

  <!-- Right Panel -->
  <?php include '../components/right-panel.php'; ?>

  <!-- Main Content -->
  <main class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-6 relative z-0">

    <h2 class="text-xl font-bold">Admin Dashboard</h2>

    <!-- Welcome Card -->
    <?php include '../components/welcome-card.php'; ?>

    <!-- Clients Table -->
    <?php include '../components/clients-table.php'; ?>

  </main>

<!-- Add Client Modal -->
<div x-show="showAddClientModal"
     x-cloak
     class="backdrop-blur-sm fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
     x-transition>
  
  <div class="bg-white p-6 rounded-lg shadow max-w-2xl w-full max-h-[90vh] overflow-y-auto relative"
       @click.away="showAddClientModal = false"
       x-transition>

    <!-- Close Button -->
    <button type="button"
            @click="showAddClientModal = false"
            class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 text-xl font-bold">
      &times;
    </button>

    <!-- Modal Header -->
    <div class="bacspace-y-1 mb-4 border-b border-gray-100 pb-2">
      <h2 class="text-xl font-semibold text-gray-800">Add New Client</h2>
    </div>

    <!-- Add Client Form -->
    <?php include '../components/add_client_form.php'; ?>
    <?php include '../components/status_alert.php'; ?>

  </div>
</div>


<!-- Alpine.js toggle -->
<script>
  document.addEventListener('alpine:init', () => {
    Alpine.data('uploadModal', () => ({
      showUploadModal: false
    }));
  });

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

    fetch("../actions/reload_clients.php?_=" + new Date().getTime() + "&debug=true")
      .then(res => {
        if (!res.ok) {
          throw new Error(`HTTP error! Status: ${res.status}`);
        }
        return res.text();
      })
      .then(html => {
        const container = document.getElementById("clients-table-container");
        if (container) {
          container.innerHTML = html;
          const rows = container.querySelectorAll("tbody tr").length;
          hideLoadingSpinner();
          if (!silent) {
            showToast(`🔄 Manual refresh complete. ${rows} clients loaded.`);
          }
        } else {
          hideLoadingSpinner();
          if (!silent) {
            showToast("⚠️ Table container not found.");
          }
        }
      })
      .catch(err => {
        hideLoadingSpinner();
        if (!silent) {
          showToast("⚠️ Manual reload failed: " + err.message);
        }
      });
  }

  let isCheckingStatus = false;

  function runStatusCheck() {
    if (isCheckingStatus) {
      return;
    }
    isCheckingStatus = true;

    fetch("../actions/run_status_check.php?_=" + new Date().getTime(), {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(res => {
        if (!res.ok) {
          throw new Error(`HTTP error! Status: ${res.status}`);
        }
        return res.json();
      })
      .then(data => {
        if (data.cached) {
          showToast(`⏳ ${data.message}`);
          return;
        }

        if (data.count > 0) {
          showToast(`✅ ${data.count} status update${data.count > 1 ? 's' : ''}. Updating table...`);
          let needsFullReload = false;

          data.updated.forEach(update => {
            const row = document.querySelector(`tr[data-client-id="${update.clientId}"]`);
            if (row) {
              const statusCell = row.querySelector('td:nth-child(4) span');
              if (statusCell) {
                const newStatus = update.to;
                statusCell.textContent = newStatus;
                statusCell.className = `px-3 py-1 text-xs font-semibold rounded-full ${getStatusBadgeClass(newStatus)}`;
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
        } else {
          showToast("✓ Status check completed - all statuses up to date.");
        }
      })
      .catch(err => {
        showToast("⚠️ Status check failed: " + err.message);
      })
      .finally(() => {
        isCheckingStatus = false;
      });
  }

  document.addEventListener("DOMContentLoaded", () => {
    runStatusCheck();
  });
</script>


<?php
// Disclaimer modal (shows first)
if (!empty($_SESSION['show_disclaimer'])) {
  echo '<div id="disclaimerWrapper">';
  include '../components/disclaimer_popup.php';
  echo '</div>';
  unset($_SESSION['show_disclaimer']);
}
?>

<?php if (!empty($_SESSION['show_survey_modal'])): ?>
  <div id="surveyWrapper" style="display: none;">
    <?php include '../components/admin_first_time_survey_modal.php'; ?>
  </div>
<?php endif; ?>

<?php if (!empty($_SESSION['show_weekly_survey_modal'])): ?>
  <div id="weeklySurveyWrapper" style="display: none;">
    <?php include '../components/admin_weekly_survey_modal.php'; ?>
  </div>
<?php endif; ?>

<script src="../includes/survey-modals.js"></script>

</body>
</html>