<?php
require_once __DIR__ . '/../actions/db.php';

if ($conn->connect_error) {
  http_response_code(500);
  echo "⚠️ Database connection failed: " . $conn->connect_error;
  exit();
}

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
if (!$client_id) {
  echo "Client not specified.";
  exit();
}

// 🧭 Fetch client + package + agent details
$query = $conn->prepare("
  SELECT 
    c.id, c.assigned_admin_id, c.assigned_package_id, c.booking_number,
    c.trip_date_start, c.trip_date_end, c.booking_date, c.status, c.created_at,
    t.package_name, t.package_description, t.price, t.day_duration, t.night_duration,
    t.tour_cover_image, t.inclusions_json,
    a.first_name AS agent_first_name, a.last_name AS agent_last_name
  FROM clients c
  LEFT JOIN tour_packages t ON c.assigned_package_id = t.id
  LEFT JOIN admin_accounts a ON c.assigned_admin_id = a.id
  WHERE c.id = ?
");
$query->bind_param("i", $client_id);
$query->execute();
$client = $query->get_result()->fetch_assoc();

$hasPackage = !empty($client['assigned_package_id']);

// 🧑 Assigned Agent
$assignedAgent = trim(($client['agent_first_name'] ?? '') . ' ' . ($client['agent_last_name'] ?? '')) ?: '—';

// 🖼️ Cover Image
$coverImage = $hasPackage && !empty($client['tour_cover_image'])
  ? '../images/tour_packages_banners/' . rawurlencode($client['tour_cover_image'])
  : '../images/default_trip_cover.jpg';

// 📅 Trip Duration & Dates
$durationDisplay = '<span class="text-gray-500 italic">Unspecified</span>';
$tripDateRangeDisplay = '<span class="text-gray-500 italic">Unspecified</span>';

if ($client['trip_date_start'] && $client['trip_date_end']) {
  try {
    $startDate = new DateTime($client['trip_date_start']);
    $endDate   = new DateTime($client['trip_date_end']);
    $interval  = $startDate->diff($endDate);
    $days      = $interval->days + 1;
    $nights    = max(0, $days - 1);
    $durationDisplay = "<span class='font-semibold text-slate-f00'>{$days} Days / {$nights} Nights</span>";
    $tripDateRangeDisplay = $startDate->format('M j') . ' – ' . $endDate->format('M j, Y');
  } catch (Exception $e) {
    $durationDisplay = '<span class="text-red-600 italic">Invalid dates</span>';
    $tripDateRangeDisplay = '<span class="text-red-600 italic">Invalid dates</span>';
  }
}

// 📦 Inclusions
$inclusions = [];
$parsed = [];
if ($hasPackage && !empty($client['inclusions_json'])) {
  $parsed = json_decode($client['inclusions_json'], true);
  if (is_array($parsed)) {
    $inclusions = array_slice($parsed, 0, 4);
  }
}
?>

<!-- 🧳 Tour Package Card - WOW Factor Design -->
<div class="relative overflow-hidden rounded-xl sm:rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 sm:hover:scale-[1.02] h-full flex flex-col">
  <!-- Background Image -->
  <img 
    src="<?= $coverImage ?>" 
    alt="Tour Package Cover"
    class="absolute inset-0 w-full h-full object-cover"
    loading="lazy"
  />

  <!-- Background overlay with semi-transparent dark gradient -->
  <div class="absolute inset-0 bg-gradient-to-br from-sky-900/85 via-sky-800/80 to-blue-900/85 backdrop-blur-sm"></div>
  
  <!-- Decorative elements -->
  <div class="absolute top-0 right-0 w-16 h-16 sm:w-24 sm:h-24 bg-white/5 rounded-full -mr-8 -mt-8 sm:-mr-12 sm:-mt-12"></div>
  <div class="absolute bottom-0 left-0 w-20 h-20 sm:w-32 sm:h-32 bg-white/5 rounded-full -ml-10 -mb-10 sm:-ml-16 sm:-mb-16"></div>

  <!-- Content -->
  <div class="relative z-10 p-4 sm:p-6 space-y-4 flex-1 flex flex-col">
    <!-- Header: Package Name Only -->
    <div>
      <p class="text-[0.65rem] font-semibold text-sky-100 uppercase tracking-wider mb-1">Assigned Package</p>
      <h3 class="pb-3 text-lg sm:text-2xl font-bold text-white break-words max-w-[85%] sm:max-w-[80%]">
        <?= $hasPackage ? htmlspecialchars($client['package_name']) : '<span class="italic opacity-80">No Package Assigned</span>' ?>
      </h3>
      <?php 
        $priceVal = $client['price'] ?? null;
        $priceDisplay = ($priceVal !== null && $priceVal !== '') ? '₱' . number_format((float)$priceVal, 2) : '—';
      ?>
      <!--
      <div class="mt-2">
        <span class="inline-block px-3 py-1 rounded-full bg-white/10 border border-white/20 text-white text-xs font-semibold">
          <?= $priceDisplay ?>
        </span>
      </div> -->
    </div>

    <!-- Dropdown Menu -->
    <div x-data="{ open: false }" class="absolute top-3 right-3 sm:top-4 sm:right-4 z-50" @click.outside="open = false">
      <button 
        @click="open = !open"
        class="p-2 bg-white/90 hover:bg-white active:bg-white rounded-full shadow-lg transition backdrop-blur-sm border border-gray-200 touch-manipulation"
        title="Package Options"
        aria-label="Package options"
      >
        <svg class="w-5 h-5 text-gray-700" fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z" />
        </svg>
      </button>

      <div x-show="open" x-transition x-cloak
           class="absolute right-0 mt-2 w-60 sm:w-64 bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden z-50">
        <div x-data="{ showTip: false }" class="relative">
          <button type="button"
                  <?= $hasPackage ? "@click=\"\$store.modals.clientId = $client_id; \$store.modals.editBooking = true; open = false\"" : '' ?>
                  class="w-full text-left px-4 sm:px-5 py-3 sm:py-3.5 text-sm font-medium <?= $hasPackage ? 'text-gray-800 hover:bg-sky-50 active:bg-sky-100' : 'text-gray-400 cursor-not-allowed' ?> transition touch-manipulation"
                  <?= $hasPackage ? '' : 'disabled' ?>
                  @mouseenter="showTip = <?= $hasPackage ? 'false' : 'true' ?>"
                  @mouseleave="showTip = false">
            Edit Booking Details
          </button>
          <div x-show="showTip" x-cloak x-transition
               class="absolute right-full top-1/2 -translate-y-1/2 mr-3 px-3 py-2 text-xs text-white bg-gray-800 rounded-lg shadow-lg whitespace-nowrap">
            Assign a package first to edit booking details
          </div>
        </div>

        <button type="button"
                @click="$store.modals.clientId = <?= $client_id ?>; $store.modals.reassign = true; open = false"
                class="w-full text-left px-4 sm:px-5 py-3 sm:py-3.5 text-sm font-medium text-gray-800 hover:bg-sky-50 active:bg-sky-100 transition touch-manipulation">
          <?= $hasPackage ? 'Reassign Package' : 'Assign Package' ?>
        </button>

        <div x-data="{ showUnassignTip: false }" class="relative">
          <button type="button"
                  <?= $hasPackage ? "@click=\"\$store.modals.unassign = true; open = false\"" : '' ?>
                  class="w-full text-left px-4 sm:px-5 py-3 sm:py-3.5 text-sm font-medium <?= $hasPackage ? 'text-red-600 hover:bg-red-50 active:bg-red-100' : 'text-gray-400 cursor-not-allowed' ?> transition touch-manipulation"
                  <?= $hasPackage ? '' : 'disabled' ?>
                  @mouseenter="showUnassignTip = <?= $hasPackage ? 'false' : 'true' ?>"
                  @mouseleave="showUnassignTip = false">
            Unassign Package
          </button>
          <div x-show="showUnassignTip" x-cloak x-transition
               class="absolute right-full top-1/2 -translate-y-1/2 mr-3 px-3 py-2 text-xs text-white bg-gray-800 rounded-lg shadow-lg whitespace-nowrap">
            No package currently assigned
          </div>
        </div>
      </div>
    </div>

    <!-- Package Details Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 pt-4 border-t border-white/30 mt-auto">
      <!-- Trip Dates -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
          </svg>
          <span class="text-xs font-medium">Trip Dates</span>
        </div>
        <p class="text-sm sm:text-base font-bold text-white break-words"><?= $tripDateRangeDisplay ?></p>
      </div>

      <!-- Booking # -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2h-6a2 2 0 00-2-2H4z" clip-rule="evenodd" />
          </svg>
          <span class="text-xs font-medium">Booking #</span>
        </div>
        <p class="text-sm sm:text-base font-bold text-white font-mono <?= empty($client['booking_number']) ? 'italic opacity-70' : '' ?> break-all">
          <?= htmlspecialchars($client['booking_number'] ?? '—') ?>
        </p>
      </div>

      <!-- Duration -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
          </svg>
          <span class="text-xs font-medium">Duration</span>
        </div>
        <p class="text-sm sm:text-base font-bold text-white"><?= $durationDisplay ?></p>
      </div>

      <!-- Travel Agent -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
          </svg>
          <span class="text-xs font-medium">Travel Agent</span>
        </div>
        <p class="text-sm sm:text-base font-bold text-white truncate" title="<?= htmlspecialchars($assignedAgent) ?>"><?= htmlspecialchars($assignedAgent) ?></p>
      </div>
    </div>
  </div>
</div>

