<?php
// 🔐 Auth check
require_once __DIR__ . '/admin_session_check.php';
require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/header.php';

// Get counts for both packages
$tourCount = 0;
$visaCount = 0;

try {
    // Get tour packages count
    $tourResult = $conn->query("SELECT COUNT(*) as count FROM tour_packages WHERE is_active = 1");
    if ($tourResult) {
        $tourRow = $tourResult->fetch_assoc();
        $tourCount = (int)$tourRow['count'];
    }
    
    // Get visa packages count
    $visaResult = $conn->query("SELECT COUNT(*) as count FROM visa_packages WHERE is_active = 1");
    if ($visaResult) {
        $visaRow = $visaResult->fetch_assoc();
        $visaCount = (int)$visaRow['count'];
    }
} catch (Exception $e) {
    error_log("Error fetching package counts: " . $e->getMessage());
}

$adminName = $_SESSION['first_name'] ?? 'Admin';
$isAdmin = true;
?>

<!DOCTYPE html>
<html lang="en" class="bg-gray-50">
<head>
  <style>[x-cloak] { display: none !important; }</style>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Packages Management</title>
  <?php include __DIR__ . '/../components/favicon_links.php'; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs" defer></script>
  <script src="../includes/admin-dashboard.js"></script>
</head>

<body class="font-poppins text-gray-800 overflow-hidden"
      x-data="{ sidebarOpen: false }"
      style="background: linear-gradient(to bottom, #e0f7ff 0%, white 10%, white 100%);">

  <!-- Mobile Toggle -->
  <button @click="sidebarOpen = !sidebarOpen" 
          class="p-3 md:hidden absolute top-4 left-4 z-30 bg-primary text-white rounded">
    ☰
  </button>

  <!-- Sidebar -->
  <?php include '../components/admin_sidebar.php'; ?>

  <!-- Right Panel -->
  <?php include '../components/right-panel.php'; ?>

  <!-- Main Content -->
  <main class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-6 relative z-0">
    
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-xl font-bold">Packages Management</h1>
      <p class="text-gray-600 mt-2">Manage tour and visa packages for your clients</p>
    </div>

    <!-- Packages Grid -->
    <div class="grid md:grid-cols-2 gap-8 max-w-6xl">
      
      <!-- 🎫 Tour Packages Card -->
      <a href="admin_tour_packages.php"
         class="group relative block rounded-lg overflow-hidden border-2 border-sky-200 bg-transparent text-sky-600 shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 cursor-pointer">
        
        <!-- Card Content -->
        <div class="p-8 flex flex-col items-center text-center space-y-4">
          
          <!-- Icon -->
          <div class="w-20 h-20 rounded-full bg-sky-100 flex items-center justify-center group-hover:scale-110 transition-transform">
            <svg class="w-10 h-10 text-sky-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
            </svg>
          </div>
          
          <!-- Title & Description -->
          <h2 class="text-2xl font-bold text-sky-600">Tour Packages</h2>
          <p class="text-sm text-gray-600">Create and manage custom travel experiences</p>
          
          <!-- Call to Action -->
          <div class="mt-4 px-6 py-2 bg-sky-100 text-sky-600 font-semibold rounded-full group-hover:bg-sky-200 transition">
            Manage Tours
          </div>
        </div>
        
        <!-- Hover Overlay -->
        <div class="absolute inset-0 bg-sky-500/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
      </a>

      <!-- 🛂 Visa Packages Card -->
      <a href="../admin/admin_visa_packages.php"
         class="group relative block rounded-lg overflow-hidden border-2 border-purple-200 bg-transparent text-sky-600 shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 cursor-pointer">
        
        <!-- Card Content -->
        <div class="p-8 flex flex-col items-center text-center space-y-4">
          
          <!-- Icon -->
          <div class="w-20 h-20 rounded-full bg-purple-100 flex items-center justify-center group-hover:scale-110 transition-transform">
            <svg class="w-10 h-10 text-purple-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
            </svg>
          </div>
          
          <!-- Title & Description -->
          <h2 class="text-2xl font-bold text-purple-600">Visa Packages</h2>
          <p class="text-sm text-gray-600">Handle visa applications and requirements</p>
          
          <!-- Call to Action -->
          <div class="mt-4 px-6 py-2 bg-purple-100 text-purple-600 font-semibold rounded-full group-hover:bg-purple-200 transition">
            Manage Visas
          </div>
        </div>
        
        <!-- Hover Overlay -->
        <div class="absolute inset-0 bg-purple-500/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
      </a>

    </div>

  </main>

</body>
</html>
