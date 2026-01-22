<?php
include_once __DIR__ . '/../admin/admin_session_check.php';
// 🔐 Auth check
if (empty($_SESSION['admin']['id'])) {
  header("Location: admin_login.php");
  exit();
}

// 📦 Includes
include_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../components/status_alert.php';

// 🚫 Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 👤 Admin info
$adminName = $_SESSION['first_name'] ?? 'Admin';
$isAdmin = true;
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
      x-data="{ sidebarOpen: false }"
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
    <h2 class="text-xl font-bold">Admin Visa Dashboard</h2>
    <!-- No welcome card, rest of page empty for now -->
  </main>

</body>
</html>
