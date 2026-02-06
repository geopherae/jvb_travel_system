<?php
require_once '../includes/icon_map.php';
require_once '../actions/db.php';
require_once '../includes/feature_flags.php';

$activePage = basename($_SERVER['PHP_SELF']);
$clientName = 'Guest';
$processingType = $_SESSION['processing_type'] ?? 'booking'; // Default to booking
$isCompanion = $_SESSION['is_companion'] ?? false;

if (isset($_SESSION['client_id'])) {
  $clientQuery = $conn->prepare("SELECT full_name FROM clients WHERE id = ? LIMIT 1");
  $clientQuery->bind_param("i", $_SESSION['client_id']);
  $clientQuery->execute();
  $clientResult = $clientQuery->get_result();
  if ($clientRow = $clientResult->fetch_assoc()) {
    $clientName = $clientRow['full_name'] ?? 'Guest';
  }
  $clientResult->free();
  $clientQuery->close();
}

// Define all possible navigation links
$allNavLinks = [
  'Dashboard'       => [
    'url' => '../client/client_dashboard.php', 
    'icon' => 'chart-bar', 
    'match' => ['client_dashboard.php', 'client_manual.php'],
    'show_for' => ['booking', 'both'], // Travel Booking tab
    'category' => 'Travel Booking'
  ],
  'My Itinerary'    => [
    'url' => '../client/view_client_itinerary.php', 
    'icon' => 'briefcase', 
    'match' => ['view_client_itinerary.php'],
    'show_for' => ['booking', 'both'], // Travel Booking tab
    'category' => 'Travel Booking'
  ],
  'Visa Processing' => [
    'url' => '../client/client_visa_dashboard.php', 
    'icon' => 'itinerary', 
    'match' => ['client_visa_dashboard.php'],
    'show_for' => ['visa', 'both'], // Visa Processing tab
    'category' => 'Visa Processing',
    'requires_feature' => 'VISA_PROCESSING_ENABLED'
  ],
  'Messages'        => [
    'url' => '../client/messages_client.php', 
    'icon' => 'chat-alt', 
    'match' => ['messages_client.php'],
    'show_for' => ['booking', 'visa', 'both'], // Shows for all types
    'category' => 'Messages'
  ],
];

// Filter nav links based on processing type
$navLinks = [];
foreach ($allNavLinks as $label => $meta) {
  // Check processing type match
  if (!in_array($processingType, $meta['show_for'])) {
    continue;
  }
  
  // Check feature flag if required
  if (isset($meta['requires_feature'])) {
    $featureName = $meta['requires_feature'];
    if (!defined($featureName) || !constant($featureName)) {
      continue; // Skip if feature flag not enabled
    }
  }
  
  $navLinks[$label] = $meta;
}
?>

<div>
  <!-- Mobile Toggle -->
  <button @click="sidebarOpen = !sidebarOpen"
          class="w-10 h-10 lg:hidden fixed top-4 left-4 z-41 bg-white p-2 rounded-full shadow focus:outline-none focus:ring-2 focus:ring-brand"
          aria-label="Toggle Sidebar">
    <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
  </button>

  <!-- Sidebar -->
  <aside class="fixed top-0 left-0 w-64 h-screen flex flex-col bg-white border-r border-neutral-200 z-40 transform transition-transform duration-300 ease-in-out"
         :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

    <!-- Header -->
    <div class="p-6 border-b border-neutral-100 flex flex-col items-center">
      <img src="../images/JVB_Logo.jpg" alt="JVB Logo"
           class="w-20 h-20 object-contain mb-3 rounded-[6px] shadow-sm" />
      <h2 class="text-lg font-semibold text-neutral-800 tracking-wide">Client Portal</h2>
      <span class="text-xs text-neutral-500 line-clamp-1"><?= htmlspecialchars($clientName) ?> · JV-B Travel & Tours</span>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-4 pt-5 space-y-3 text-[1rem]">
      <?php foreach ($navLinks as $label => $meta): ?>
        <?php $isActive = in_array($activePage, $meta['match'] ?? [$meta['url']]); ?>
        <a href="<?= $meta['url'] ?>"
           class="block px-3 py-2.5 rounded-[8px] transition-all
           <?= $isActive
               ? 'bg-sky-500 text-white font-semibold shadow'
               : 'hover:bg-sky-100 hover:text-brand text-neutral-700' ?>">
          <div class="flex items-center space-x-1.5">
            <?= getIconSvg($meta['icon']) ?>
            <span><?= htmlspecialchars($label) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </nav>

    <!-- Manual Link -->
<div class="px-4 pb-2 text-center text-sm">
  <a href="../system_manual/client_manual.php"
     class="text-sky-600 hover:underline hover:text-sky-700 transition">
    Need Help?</br>View the Client Manual
  </a>
</div>

    <!-- Footer / Logout -->
    <div class="p-4 border-t mb-6 border-neutral-100 text-sm text-center">
      <form action="../client/logout.php" method="POST">
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 text-red-500 font-semibold hover:text-red-600 transition hover:underline focus:outline-none focus:ring-2 focus:ring-red-500">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
               viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
          </svg>
          Logout
        </button>
      </form>
    </div>

  </aside>
</div>

<!-- Sidebar Unread Indicator Script -->
<script src="../includes/sidebar_unread_indicator.js?v=1.0.5"></script>