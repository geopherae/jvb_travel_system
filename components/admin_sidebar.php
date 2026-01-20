<?php
$activePage = basename($_SERVER['PHP_SELF']);
$adminName = $_SESSION['admin']['first_name'] ?? 'Guest';

require_once '../includes/icon_map.php';

$navLinks = [
  'Dashboard'     => ['url' => '../admin/admin_dashboard.php',     'icon' => 'chart-bar', 'match' => ['admin_dashboard.php', 'view_client.php', 'admin_manual.php']],
  'Messages'      => ['url' => '../admin/messages.php?v=1.0.1',             'icon' => 'messages',  'match' => ['messages.php']],
  'Tour Packages' => ['url' => '../admin/admin_tour_packages.php', 'icon' => 'map',       'match' => ['admin_tour_packages.php']],
  'Client Reviews' => ['url' => '../admin/admin_testimonials.php', 'icon' => 'star',      'match' => ['admin_testimonials.php']],
  'Settings'      => ['url' => '../admin/admin_settings.php',      'icon' => 'settings',  'match' => ['admin_settings.php', 'audit.php']],
];


?>
<!-- Mobile Toggle -->
<button @click="sidebarOpen = !sidebarOpen"
        class="w-10 h-10 lg:hidden fixed top-4 left-4 z-50 bg-white p-2 rounded-full shadow focus:outline-none focus:ring-2 focus:ring-brand"
        aria-label="Toggle Sidebar">
  <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
  </svg>
</button>

<!-- Sidebar -->
<aside class="fixed top-0 left-0 w-64 h-screen flex flex-col bg-white border-r border-neutral-200 z-40 transform transition-transform duration-300 ease-in-out"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       @click="sidebarOpen = false">

    <!-- Logo & Admin Info -->
    <div class="p-6 border-b border-neutral-100 flex flex-col items-center">
      <img src="../images/JVB_Logo.jpg" alt="JVB Logo"
           class="w-20 h-20 object-contain mb-3 rounded-md shadow-sm" />
      <h2 class="text-lg font-semibold text-neutral-800">Admin Panel</h2>
      <span class="text-xs text-neutral-500"><?= htmlspecialchars($adminName) ?> · JV-B Travel & Tours</span>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 pt-6 space-y-4 text-[1rem]">
      <?php foreach ($navLinks as $label => $meta): ?>
        <?php $isActive = in_array($activePage, $meta['match'] ?? [$meta['url']]); ?>
        <a href="<?= $meta['url'] ?>"
           class="block px-4 py-3 rounded-lg transition-all
           <?= $isActive
               ? 'bg-sky-500 text-white font-semibold shadow'
               : 'hover:bg-sky-100 hover:text-sky-700 text-neutral-700' ?>">
          <div class="flex items-center gap-2">
            <?= getIconSvg($meta['icon']) ?>
            <span><?= htmlspecialchars($label) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </nav>

        <!-- Manual Link -->
<div class="px-4 pb-2 text-center text-sm">
  <a href="../system_manual/admin_manual.php"
     class="text-sky-600 hover:underline underline hover:text-sky-700 transition">
    Need Help?</br>View the Admin Manual
  </a>
</div>

    <!-- Footer -->
    <div class="p-4 border-t mb-6 border-neutral-100 text-sm text-center">
      <a href="../admin/admin_logout.php"
         class="inline-flex items-center gap-2 text-red-500 font-semibold hover:text-red-600 transition hover:underline focus:outline-none focus:ring-2 focus:ring-red-500">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
        </svg>
        Logout
      </a>
    </div>
  </aside>