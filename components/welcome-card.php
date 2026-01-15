<?php
$isAdmin = $isAdmin ?? false;
$client = $client ?? null;
$status = $client['status'] ?? 'Pending';

require_once __DIR__ . '/../includes/icon_map.php';
require_once __DIR__ . '/dashboard_widget.php'; // Sets $currentTime, $location, $iconUrl, $temp, $condition

date_default_timezone_set('Asia/Manila');
$hour = (int) date('G');

// 🌤 Greeting + Styling
switch (true) {
  case ($hour >= 5 && $hour < 12):
    $greeting = 'Good morning';
    $gradient = 'from-[#BFECFF] via-[#E8FEFA] to-[#E7FDE7]';
    $greetingGradient = 'from-sky-600 via-teal-600 to-emerald-600';
    break;
  case ($hour >= 12 && $hour < 17):
    $greeting = 'Good afternoon';
    $gradient = 'from-[#BEE3DB] via-[#FFF1B6] to-[#FFE5EC]';
    $greetingGradient = 'from-amber-600 via-orange-500 to-rose-500';
    break;
  default:
    $greeting = 'Good evening';
    $gradient = 'from-[#223E5B] via-[#3A5A80] to-[#5A7CA8]';
    $greetingGradient = 'from-blue-400 via-cyan-300 to-sky-200';
    break;
}

$isNight      = $hour >= 17 || $hour < 5;
$textColor    = $isNight ? 'text-white' : 'text-sky-950';
$textMuted    = $isNight ? 'text-white/90' : 'text-sky-950';
$cardText     = $isNight ? 'text-gray-800' : 'text-sky-950';
$widgetText   = $isNight ? 'text-white' : 'text-sky-950';
$widgetIcon   = $isNight ? 'text-white' : 'text-sky-950';

// 🧮 Compute Trip Duration
$tripStart = isset($client['trip_date_start']) && $client ? new DateTime($client['trip_date_start']) : null;
$tripEnd   = isset($client['trip_date_end']) && $client ? new DateTime($client['trip_date_end']) : null;
$tripDurationDisplay = '—';

if ($tripStart && $tripEnd) {
  $interval = $tripStart->diff($tripEnd);
  $days     = (int) $interval->days;
  $nights   = max(0, $days - 1);
  $tripDurationDisplay = "{$days} Days / {$nights} Nights";
}
?>

<style>
@keyframes gradientShift {
  0%, 100% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
}
.animate-gradient {
  background-size: 200% 200%;
  animation: gradientShift 15s ease infinite;
}
</style>

<section
  x-data="{ collapsed: false }"
  x-init="window.addEventListener('scroll', () => collapsed = window.scrollY > 1)"
  :class="collapsed ? 'p-4' : 'p-6 lg:p-8'"
  class="w-full rounded-xl bg-gradient-to-r <?= $gradient ?> <?= $textColor ?> shadow-lg mb-6 transition-all duration-300 ease-in-out animate-gradient overflow-hidden relative"
>
  <div class="flex flex-col lg:flex-row justify-between gap-8 items-start">

    <!-- 🌅 Greeting + Widgets -->
    <?php if ($isAdmin): ?>
    <div class="w-full flex flex-col lg:flex-row justify-between items-start gap-6">
      <div class="lg:max-w-[50%] flex flex-col gap-4">
        <h1 class="text-3xl md:text-4xl font-bold leading-tight tracking-tight">
          <?= $greeting ?>, <span class="bg-gradient-to-r <?= $greetingGradient ?> bg-clip-text text-transparent"><?= htmlspecialchars($_SESSION['admin']['first_name'] ?? $_SESSION['first_name'] ?? 'Admin') ?></span>!
        </h1>
        <p class="text-base md:text-lg mt-1 <?= $textMuted ?> leading-relaxed">
          Here’s what’s happening across your clients and packages today.
        </p>
      </div>

      <div x-show="!collapsed" x-transition
           class="bg-white/10 backdrop-blur-md rounded-xl p-5 text-sm shadow-lg border border-white/20 <?= $widgetText ?> min-w-[200px]">
        <div class="space-y-3">
          <div class="flex items-center gap-3 hover:gap-4 transition-all duration-200">
            <span class="<?= $widgetIcon ?> w-5 h-5 flex-shrink-0"><?= getIconSvg('clock') ?></span>
            <span class="font-medium"><?= $currentTime ?></span>
          </div>
          <div class="flex items-center gap-3 hover:gap-4 transition-all duration-200">
            <span class="<?= $widgetIcon ?> w-5 h-5 flex-shrink-0"><?= getIconSvg('location') ?></span>
            <span class="font-medium"><?= htmlspecialchars($location) ?></span>
          </div>
          <div class="flex items-center gap-3 hover:gap-4 transition-all duration-200">
            <span class="<?= $widgetIcon ?> w-5 h-5 flex-shrink-0"><?= getIconSvg('temperature') ?></span>
            <span class="font-medium"><?= $temp ?>°C, <?= htmlspecialchars($condition) ?></span>
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="w-full lg:w-1/2 flex flex-col gap-4">
      <h1 class="text-3xl md:text-4xl font-bold leading-tight tracking-tight">
        <?= $greeting ?>, <span class="bg-gradient-to-r <?= $greetingGradient ?> bg-clip-text text-transparent"><?= htmlspecialchars($client ? ($client['full_name'] ?? 'Traveler') : ($_SESSION['admin']['first_name'] ?? $_SESSION['first_name'] ?? 'Admin')) ?></span>!
      </h1>
      <p class="text-base md:text-lg mt-1 <?= $textMuted ?> leading-relaxed">
        <?php if ($client && ($client['status'] ?? '') === 'Trip Completed'): ?>
          Thank you for booking with us. If you enjoyed your trip, kindly consider leaving a review.
        <?php elseif ($client): ?>
          Your next adventure awaits — here's your latest trip update and documents.
        <?php else: ?>
          Here’s what’s happening across your clients and packages today.
        <?php endif; ?>
      </p>
      <?php if ($client && ($client['status'] ?? '') === 'Trip Completed'): ?>
        <?php if ((int)($client['left_review'] ?? 0) === 1): ?>
          <button disabled class="bg-gray-400 text-gray-200 px-5 py-3 rounded-lg cursor-not-allowed font-medium text-sm flex items-center gap-2" title="You have already reviewed your trip">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
            Thank you for your review!
          </button>
        <?php else: ?>
          <button @click="$store.reviewModal.show = true" class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-6 py-3 rounded-lg hover:from-emerald-500 hover:to-teal-500 transition-all duration-300 font-semibold shadow-lg hover:shadow-xl hover:scale-105 active:scale-95 flex items-center gap-2 w-fit">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
            Rate my trip
          </button>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ✈️ Trip Summary -->
    <div
      class="relative rounded-xl transition-all duration-300 flex flex-col justify-between h-full ring-2 ring-sky-400/20 hover:shadow-2xl hover:shadow-sky-200/50 hover:ring-sky-400/40 hover:ring-4 <?= $client && isset($client['assigned_package_id']) ? 'hover:scale-[1.02] hover:cursor-pointer' : 'cursor-default' ?> group"
      style="will-change: transform;"
      <?= $client && isset($client['assigned_package_id']) ? '@click="showAssignedPackage = true"' : '' ?>
    >
      <?php if ($client && isset($client['assigned_package_id'])): ?>
      <div class="absolute -top-3 -right-3 bg-gradient-to-r from-sky-600 to-blue-600 text-white text-xs px-3 py-1.5 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition-all duration-300 pointer-events-none z-50 font-medium flex items-center gap-1">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
        View Details
      </div>
      <?php endif; ?>

      <?php if (!$isAdmin && !empty($client)): ?>
      <div class="flex-1 bg-white/90 backdrop-blur-sm rounded-lg p-5 space-y-4 text-sm <?= $cardText ?>"
           x-show="!collapsed" x-transition>
        <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
          <div class="w-full sm:max-w-[60%]">
            <h2 class="text-lg font-semibold text-slate-700 leading-snug">Your Trip Details</h2>
            <p class="text-xs text-slate-500 mt-1">Here’s what’s happening with your trip. We hope you enjoy!</p>
          </div>
          <span class="px-4 py-1 text-xs font-medium rounded-full <?= getStatusBadgeClass($status) ?>">
            <?= htmlspecialchars($status); ?>
          </span>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
          <div>
            <p class="font-medium text-gray-500 uppercase">Assigned Package</p>
            <p class="text-sm font-medium">
              <?= htmlspecialchars($client['package_name'] ?? 'No Package Assigned'); ?>
            </p>
          </div>
          <div>
            <p class="font-medium text-gray-500 uppercase">Trip Dates</p>
            <p class="text-sm font-medium">
              <?php
                if (!empty($client['trip_date_start']) && !empty($client['trip_date_end'])) {
                  $start = date('M d', strtotime($client['trip_date_start']));
                  $end   = date('M d', strtotime($client['trip_date_end']));
                  $year  = date('Y', strtotime($client['trip_date_end']));
                  echo "{$start} to {$end} {$year}";
                } else {
                  echo '—';
                }
              ?>
            </p>
          </div>
          <div>
            <p class="font-medium text-gray-500 uppercase">Trip Duration</p>
            <p class="text-sm font-medium"><?= $tripDurationDisplay ?></p>
          </div>
          <div>
            <p class="font-medium text-gray-500 uppercase">Origin & Destination</p>
            <p class="text-sm font-medium">
              <?= htmlspecialchars($client['origin'] ?? 'N/A'); ?> →
              <?= htmlspecialchars($client['destination'] ?? 'N/A'); ?>
            </p>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php
$clientId = $client ? ($client['id'] ?? null) : null;
$packageName = $assignedPackage['name'] ?? 'Unknown Package';
$assignedPackageId = $assignedPackage['id'] ?? null;
$returnUrl = $isAdmin ? 'admin/view_client.php?client_id=' . ($client ? $client['id'] : '') : 'client/client_dashboard.php';
if ($client) {
  include 'review_modal.php';
}
?>