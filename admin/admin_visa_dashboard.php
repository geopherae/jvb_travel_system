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
$isAdmin = true;

// 👥 Fetch visa clients (processing_type = 'visa' or 'both')
$visaClientQuery = "
  SELECT 
    c.id, 
    c.full_name, 
    c.client_profile_photo,
    vp.country AS visa_package_country,
    DATE_FORMAT(va.created_at, '%b %e, %Y') AS applied_date,
    IFNULL(va.status, 'draft') AS visa_status
  FROM clients c
  LEFT JOIN client_visa_applications va ON c.visa_application_id = va.id
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
  <script src="https://unpkg.com/alpinejs" defer></script>
  <script src="../includes/admin-dashboard.js"></script>
</head>

<body class="font-poppins text-gray-800 overflow-hidden"
      x-data="{ 
        sidebarOpen: false, 
        showAddVisaClientModal: <?= isset($_SESSION['visa_client_added']) ? 'false' : 'false' ?>
      }"
      style="background: linear-gradient(to bottom, #e0f7ff 0%, white 10%, white 100%);">

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
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">Admin Visa Dashboard</h2>
    </div>

    <!-- Visa Clients Table -->
    <?php include '../components/visa-clients-table.php'; ?>

  </main>

  <!-- Add Visa Client Modal -->
  <?php include '../components/add_visa_client.php'; ?>

  <!-- Add Another Group Member Toast Action -->
  <?php if (isset($_GET['visa_added']) && isset($_SESSION['visa_client_added'])): ?>
  <div x-data="{ showGroupPrompt: true }" x-show="showGroupPrompt" x-cloak
       class="fixed bottom-20 right-4 z-40 bg-white rounded-lg shadow-lg border border-gray-200 p-4 max-w-sm">
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0">
        <svg class="w-6 h-6 text-sky-600" fill="currentColor" viewBox="0 0 20 20">
          <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path>
        </svg>
      </div>
      <div class="flex-1">
        <p class="text-sm font-medium text-gray-900">Add more members to this group?</p>
        <p class="text-xs text-gray-500 mt-1">Group Code: <code class="font-mono text-sky-600"><?= htmlspecialchars($_SESSION['visa_client_added']['group_code'] ?? '') ?></code></p>
        <div class="mt-3 flex gap-2">
          <button @click="showAddVisaClientModal = true; showGroupPrompt = false"
                  class="text-xs px-3 py-1.5 bg-sky-600 text-white rounded hover:bg-sky-700 transition-colors">
            Add Member
          </button>
          <a href="admin_visa_dashboard.php"
             class="text-xs px-3 py-1.5 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors">
            Done
          </a>
        </div>
      </div>
      <button @click="showGroupPrompt = false; window.location.href='admin_visa_dashboard.php'"
              class="flex-shrink-0 text-gray-400 hover:text-gray-500">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
        </svg>
      </button>
    </div>
  </div>
  <?php endif; ?>

  <?php 
  // Clear session data if not adding another member
  if (!isset($_GET['visa_added']) && isset($_SESSION['visa_client_added'])) {
    unset($_SESSION['visa_client_added']);
  }
  ?>

</body>
</html>
