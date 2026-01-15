<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('client');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/status-helpers.php';
require_once __DIR__ . '/../actions/highlight_today_itinerary.php';

// 🧑‍💼 Get Client ID
$client_id = $_SESSION['client_id'] ?? null;
if (!$client_id) {
  http_response_code(403);
  exit('Unauthorized access.');
}

// 🚦 Fetch client + package info
$client_stmt = $conn->prepare("
  SELECT 
    c.full_name, c.status, c.trip_date_start, c.trip_date_end,
    c.booking_date, c.booking_number,
    t.package_name, t.package_description,
    t.origin, t.destination, t.tour_cover_image
  FROM clients c
  LEFT JOIN tour_packages t ON c.assigned_package_id = t.id
  WHERE c.id = ?
");
$client_stmt->bind_param("i", $client_id);
$client_stmt->execute();
$client = $client_stmt->get_result()->fetch_assoc();
$client_stmt->close();

$package_name = $client['package_name'] ?? '';

// 🗓 Format Dates
$start = $client['trip_date_start'] ?? null;
$end   = $client['trip_date_end'] ?? null;

$tripDateRangeDisplay = '<span class="text-gray-400 italic">Unspecified</span>';
if ($start && $end) {
  try {
    $startDate = new DateTime($start);
    $endDate   = new DateTime($end);
    $tripDateRangeDisplay = $startDate->format('M d') . " to " . $endDate->format('M d Y');
  } catch (Exception $e) {}
}

// 📦 Fetch JSON itinerary
$itinerary_stmt = $conn->prepare("
  SELECT itinerary_json 
  FROM client_itinerary 
  WHERE client_id = ? 
  LIMIT 1
");
$itinerary_stmt->bind_param("i", $client_id);
$itinerary_stmt->execute();
$itinerary_result = $itinerary_stmt->get_result()->fetch_assoc();
$itinerary_stmt->close();

$parsedDays = [];
if (!empty($itinerary_result['itinerary_json'])) {
  $decoded = json_decode($itinerary_result['itinerary_json'], true);
  if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
    $parsedDays = $decoded;
  }
}

// 🎯 Highlight today
$todayDay = getTodayItineraryDay($start, $end);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>My Travel Itinerary</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="description" content="View your travel itinerary and trip photos." />

  <!-- Tailwind / Fonts / Alpine -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
</head>

<body class="font-poppins text-gray-800" x-data="{ sidebarOpen: false }" style="background: linear-gradient(to bottom, #e0f7ff 0%, white 10%, white 100%)">

  <!--🧭 Sidebar + Right Panel -->
  <div>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/right-panel.php'; ?>

  <main class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-6 flex flex-col relative z-0">
    <div class="flex-1 overflow-y-auto space-y-6">
      <h2 class="text-xl font-bold">My Travel Itinerary</h2>

    <!-- Contact Agent Message -->
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
      <p class="text-sm text-gray-700">
        <span class="font-medium">Need to make changes?</span> If you'd like to adjust anything in your itinerary, please contact your travel agent.
      </p>
    </div>

        <?php if (!empty($parsedDays)): ?>
          <!-- Normal Itinerary View -->
          <div class="flex flex-col lg:flex-row gap-8 items-start">
            <!-- Left Panel: Package Info -->
            <?php if (file_exists('../components/client-package-info.php')) include '../components/client-package-info.php'; ?>

            <!-- Right Panel with Tabs -->
            <div x-data="{ tab: 'itinerary' }" class="w-full lg:w-2/3">
              <!-- Tabs -->
              <div class="flex gap-6 border-b border-gray-200 mb-4">
                <button @click="tab = 'itinerary'"
                        :class="tab === 'itinerary' ? 'text-sky-600 border-b-2 border-sky-600' : 'hover:text-sky-500'"
                        class="pb-1 transition">
                  Itinerary
                </button>
                <button @click="tab = 'photos'"
                        :class="tab === 'photos' ? 'text-sky-600 border-b-2 border-sky-600' : 'hover:text-sky-500'"
                        class="pb-1 transition">
                  Trip Photos
                </button>
              </div>

              <!-- Panels -->
              <div x-show="tab === 'itinerary'" class="bg-gray-50 rounded-lg border border-gray-200 p-6 min-h-[400px]">
                <?php if (file_exists('../components/client-itinerary-cards.php')) include '../components/client-itinerary-cards.php'; ?>
              </div>

              <div x-show="tab === 'photos'" class="bg-gray-50 rounded-lg border border-gray-200 p-6 min-h-[400px]">
                <?php if (file_exists('../components/client-trip-photos.php')) include '../components/client-trip-photos.php'; ?>
              </div>
            </div>
          </div>

        <?php else: ?>
          <!-- ✨ Compact Empty State -->
          <div class="max-w-md mx-auto text-center py-12">
            <!-- Icon -->
            <div class="mx-auto w-20 h-20 mb-6 bg-sky-100 rounded-xl flex items-center justify-center shadow">
              <svg class="w-10 h-10 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>

            <h2 class="text-xl font-semibold text-gray-800 mb-3">
              Your Itinerary Is Being Prepared
            </h2>

            <p class="text-sm text-gray-600 mb-6 max-w-sm mx-auto">
              Exciting adventures await! Your travel agent is crafting a personalized itinerary just for you.
            </p>

            <div class="bg-sky-50 border border-sky-200 rounded-lg p-4 mb-6">
              <h3 class="text-sm font-semibold text-sky-800 mb-2">Ready to Get Started?</h3>
              <p class="text-xs text-sky-700">Your travel agent is here to help plan your perfect trip. Reach out anytime.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-500">
              <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 rounded-lg border">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="font-medium text-gray-800 text-sm">Check Back Soon</p>
                <p class="text-xs">Your detailed itinerary will appear here once it's ready.</p>
              </div>
              <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 rounded-lg border">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <p class="font-medium text-gray-800 text-sm">Contact Your Agent</p>
                <p class="text-xs">Message them anytime to discuss your trip preferences.</p>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

</body>
</html>