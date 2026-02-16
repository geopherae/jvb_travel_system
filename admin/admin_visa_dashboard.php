<?php
include_once __DIR__ . '/admin_session_check.php';
// 🔐 Auth check
if (empty($_SESSION['admin']['id'])) {
  header("Location: admin_login.php");
  exit();
}

// 📦 Includes
include_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../components/status_alert.php';
date_default_timezone_set('Asia/Manila');

// 🚫 Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 🗄️ DB connection
require_once __DIR__ . '/../actions/db.php';

// 👤 Admin info
$adminName = $_SESSION['first_name'] ?? 'Admin';
$currentAdminId = $_SESSION['admin']['id'] ?? null;
$isAdmin = true;

// 👥 Fetch visa clients (processing_type = 'visa' or 'both')
$visaClientQuery = "
  SELECT 
    c.id, 
    c.full_name, 
    c.client_profile_photo,
    vp.country AS visa_package_country,
    DATE_FORMAT(va.created_at, '%b %e, %Y') AS applied_date,
    IFNULL(va.status, 'Awaiting Docs') AS visa_status
  FROM clients c
  LEFT JOIN client_visa_applications va ON va.id = (
    SELECT id FROM client_visa_applications
    WHERE client_id = c.id
    ORDER BY created_at DESC
    LIMIT 1
  )
  LEFT JOIN visa_packages vp ON va.visa_package_id = vp.id
  WHERE c.processing_type IN ('visa', 'both')
  ORDER BY va.created_at DESC, c.full_name ASC
";
$visaClientsResult = $conn->query($visaClientQuery);
$visaClients = $visaClientsResult ? $visaClientsResult->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en" class="bg-gray-50">
<head>
  <style>[x-cloak] { display: none !important; }</style>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Visa Dashboard</title>
  <?php include __DIR__ . '/../components/favicon_links.php'; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    // Initialize archived visa applications modal store
    document.addEventListener('alpine:init', () => {
      if (!Alpine.store('archivedVisaApplicationsModal')) {
        Alpine.store('archivedVisaApplicationsModal', {
          isOpen: false,
          loading: false,
          applications: [],
          
          async open() {
            this.isOpen = true;
            this.loading = true;
            await this.fetchApplications();
            this.loading = false;
          },
          
          close() {
            this.isOpen = false;
            this.applications = [];
          },
          
          async fetchApplications() {
            try {
              const response = await fetch('../actions/get_archived_visa_applications.php');
              const text = await response.text();
              
              // Try to parse as JSON
              let data;
              try {
                data = JSON.parse(text);
              } catch (e) {
                console.error('Invalid JSON response:', text);
                this.applications = [];
                if (window.showToast) {
                  window.showToast('Server returned invalid response. Check console for details.', 'error');
                }
                return;
              }
              
              if (data.success) {
                this.applications = data.applications || [];
              } else {
                console.error('API Error:', data.message);
                this.applications = [];
                // Don't show error toast if it's just empty applications
                if (data.message && !data.message.includes('archived applications') && window.showToast) {
                  window.showToast(data.message, 'error');
                }
              }
            } catch (error) {
              console.error('Error:', error);
              this.applications = [];
              if (window.showToast) {
                window.showToast('An error occurred while loading applications.', 'error');
              }
            }
          },
          
          async unarchive(applicationId) {
            if (!confirm('Are you sure you want to unarchive this application?')) return;
            try {
              const formData = new FormData();
              formData.append('application_id', applicationId);
              const response = await fetch('../actions/unarchive_visa_application.php', {
                method: 'POST',
                body: formData
              });
              const data = await response.json();
              if (data.success) {
                if (window.showToast) {
                  window.showToast(data.message || 'Application unarchived successfully!', 'success');
                }
                await this.fetchApplications();
                setTimeout(() => window.location.reload(), 1500);
              } else {
                if (window.showToast) {
                  window.showToast(data.message || 'Failed to unarchive application.', 'error');
                }
              }
            } catch (error) {
              console.error('Error:', error);
              if (window.showToast) {
                window.showToast('An error occurred. Please try again.', 'error');
              }
            }
          },
          
          async deletePermanently(applicationId, packageName) {
            if (!confirm(`⚠️ PERMANENT DELETE\n\nAre you sure you want to permanently delete the "${packageName}" visa application?\n\nThis action cannot be undone!`)) return;
            try {
              const formData = new FormData();
              formData.append('application_id', applicationId);
              const response = await fetch('../actions/permanently_delete_visa_application.php', {
                method: 'POST',
                body: formData
              });
              const data = await response.json();
              if (data.success) {
                if (window.showToast) {
                  window.showToast(data.message || 'Application permanently deleted!', 'success');
                }
                await this.fetchApplications();
              } else {
                if (window.showToast) {
                  window.showToast(data.message || 'Failed to delete application.', 'error');
                }
              }
            } catch (error) {
              console.error('Error:', error);
              if (window.showToast) {
                window.showToast('An error occurred. Please try again.', 'error');
              }
            }
          }
        });
      }
    });
  </script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <script src="../includes/admin-dashboard.js"></script>
  <script src="../includes/global-toast.js" defer></script>
  <script src="../includes/message_received_toast_poller.js" defer></script>
</head>

<body class="font-poppins text-gray-800 overflow-hidden"
      x-data="{ 
        sidebarOpen: false, 
        showAddVisaClientModal: <?= isset($_SESSION['visa_client_added']) ? 'false' : 'false' ?>
      }"
      style="background: linear-gradient(to bottom, #e0f7ff 0%, white 10%, white 100%);">

  <!-- Mobile Toggle -->
  <button @click="sidebarOpen = !sidebarOpen" class="p-3 md:hidden absolute top-4 left-4 z-20 bg-primary text-white rounded">
    ☰
  </button>

  <!-- Sidebar -->
  <?php include '../components/admin_sidebar.php'; ?>

  <!-- Right Panel -->
  <?php include '../components/right-panel.php'; ?>

  <!-- Main Content -->
  <main class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-6 relative">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">Admin Visa Dashboard</h2>
    </div>

    <!-- Visa Clients Table -->
    <?php include '../components/visa-clients-table.php'; ?>

    <!-- Sticky View Archived Applications Button -->
    <div class="fixed bottom-0 left-0 w-full bg-white/80 backdrop-blur-sm z-40 border-t border-gray-200 pb-4 pt-2 flex justify-center">
      <button @click="$store.archivedVisaApplicationsModal.open()"
              class="inline-flex items-center gap-2 text-sm font-medium text-red-600 hover:text-red-700 transition px-4 py-2 rounded">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
        </svg>
        View Archived Applications
      </button>
    </div>

  </main>

  <!-- Add Visa Client Modal -->
  <?php 
    // Pass the current admin ID to the form for initialization
    $adminIdJson = json_encode((int)$currentAdminId, JSON_UNESCAPED_UNICODE);
    include '../components/add_visa_client.php'; 
  ?>

  <!-- Archived Visa Applications Modal -->
  <?php include '../components/archived_visa_applications_modal.php'; ?>

</body>
</html>