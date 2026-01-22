<?php
// Tour image
$imageFilename = $client['tour_cover_image'] ?? '';
$image = (!empty($imageFilename) && $imageFilename !== 'NULL')
  ? '../images/tour_packages_banners/' . rawurlencode($imageFilename)
  : '../images/default_trip_cover.jpg';

// Basic package details
$title       = $client['package_name'] ?? 'Untitled Tour';
$description = $client['package_description'] ?? 'No description available.';
$price       = (float) ($client['price'] ?? 0);
$priceDisplay = ($price > 0)
  ? '&#8369;' . number_format($price, 2)
  : 'Price TBD';

// Duration calculation
$durationText = '<span class="text-gray-400 italic">Unspecified</span>';
$start = $client['trip_date_start'] ?? null;
$end   = $client['trip_date_end'] ?? null;

if ($start && $end) {
  try {
    $startDate = new DateTime($start);
    $endDate   = new DateTime($end);
    $days      = $startDate->diff($endDate)->days + 1;
    $nights    = max(0, $days - 1);
    $durationText = "{$days} Day" . ($days !== 1 ? 's' : '') .
                    " / {$nights} Night" . ($nights !== 1 ? 's' : '');
  } catch (Exception $e) {
    // Silent fail  keep default
  }
}

// Travel inclusions
$inclusionData = json_decode($client['inclusions_json'] ?? '[]', true);

// Travel exclusions
$exclusionData = json_decode($client['exclusions_json'] ?? '[]', true);

// Itinerary passed from dashboard as $itineraryDays
$itineraryData = $itineraryDays ?? [];
?>

<!-- Backdrop -->
<div
  x-show="showAssignedPackage"
  x-transition.opacity
  x-cloak
  class="backdrop-blur-sm fixed inset-0 z-[99999] flex items-end sm:items-center justify-center bg-black/55 px-3 sm:px-4"
  @keydown.escape.window="showAssignedPackage = false"
  role="dialog"
  aria-modal="true"
>
  <!-- Modal Container -->
  <div
    class="relative bg-white rounded-t-2xl sm:rounded-lg shadow-xl w-full max-w-[100vw] sm:max-w-5xl transition-all duration-300 max-h-[calc(100vh-24px)] sm:max-h-[95vh] flex flex-col overflow-hidden"
    @click.away="showAssignedPackage = false"
    x-transition.opacity
  >
    <!-- Close Button -->
    <button
      @click="showAssignedPackage = false"
      class="absolute top-4 right-4 text-slate-500 hover:text-red-500 text-xl font-bold z-10"
      aria-label="Close"
      type="button"
    >
      
    </button>

    <div class="w-full flex-1 overflow-y-auto flex flex-col sm:flex-row gap-2 sm:gap-4 p-4 sm:p-6 pb-20 sm:pb-6">
      <!-- Left Column -->
      <div class="sm:max-w-[55%] flex-1 flex flex-col">
        <img
          src="<?= htmlspecialchars($image) ?>"
          alt="Tour Cover"
          loading="lazy"
          class="w-full h-52 sm:h-64 object-cover rounded-t-lg sm:rounded-lg sm:shadow"
        />
        <div class="p-4 pt-5 px-5 space-y-2">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800 leading-tight truncate flex-1 min-w-0"><?= htmlspecialchars($title) ?></h2>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
              <span class="inline-block bg-sky-100 text-sky-700 font-semibold px-3 py-1 rounded-full text-sm">
                <?= $priceDisplay ?>
              </span>
              <span class="inline-block bg-slate-100 text-slate-700 font-semibold px-3 py-1 rounded-full text-sm">
                <?= $durationText ?>
              </span>
            </div>
          </div>

          <?php if (!empty($client['requires_visa']) && $client['requires_visa'] == 1): ?>
          <span class="inline-block bg-green-100 text-green-800 font-medium px-3 py-1 rounded-full text-xs">
            Requires Visa
          </span>
          <?php endif; ?>

          <?php if (!empty($client['origin']) || !empty($client['destination'])): ?>
          <span class="inline-block bg-purple-100 text-purple-800 font-semibold px-3 py-1 rounded-full text-xs">
            <?= htmlspecialchars(($client['origin'] ?? 'Origin TBD') . '  ' . ($client['destination'] ?? 'Destination TBD')) ?>
          </span>
          <?php endif; ?>

          <p class="line-clamp-4 text-sm text-slate-600 leading-relaxed">
            <?= htmlspecialchars($description) ?>
          </p>
        </div>
      </div>

      <!-- Right Column -->
      <div class="sm:max-w-[45%] w-full flex flex-col border-t sm:border-t-0 sm:border-l border-slate-100 px-4 sm:px-5 py-4 sm:py-0 sm:pl-6" x-data="{ tab: 'itinerary', openDay: null }">
        <!-- Tabs -->
        <div class="flex border-b mb-4 gap-3 overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 pb-1">
          <button
            type="button"
            :class="tab === 'itinerary' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600'"
            @click="tab = 'itinerary'"
            class="px-3 py-2 text-xs sm:text-sm font-medium focus:outline-none hover:text-sky-600 shrink-0"
          >
            Itinerary
          </button>
          <button
            type="button"
            :class="tab === 'inclusions' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600'"
            @click="tab = 'inclusions'"
            class="px-3 py-2 text-xs sm:text-sm font-medium focus:outline-none hover:text-sky-600 shrink-0"
          >
            Inclusions
          </button>
          <button
            type="button"
            :class="tab === 'exclusions' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600'"
            @click="tab = 'exclusions'"
            class="px-3 py-2 text-xs sm:text-sm font-medium focus:outline-none hover:text-sky-600 shrink-0"
          >
            Exclusions
          </button>
        </div>

        <!-- Itinerary (accordion) -->
        <div x-show="tab === 'itinerary'" class="flex-1 pr-1 max-h-[260px] sm:max-h-[300px] overflow-y-auto space-y-5 text-left" x-data="{ openDay: null }">
          <?php if (!empty($itineraryData)): ?>
          <div class="space-y-4">
            <?php foreach ($itineraryData as $day): ?>
            <div class="border rounded-lg shadow-sm bg-slate-50 overflow-hidden">
              <button
                type="button"
                @click="openDay = (openDay === <?= json_encode($day['day_number'] ?? null) ?> ? null : <?= json_encode($day['day_number'] ?? null) ?>)"
                class="font-bold w-full px-4 py-3 flex items-center justify-between gap-3 hover:bg-slate-100 transition text-left focus:outline-none"
                :aria-expanded="openDay === <?= json_encode($day['day_number'] ?? null) ?>"
                :aria-controls="'day-content-<?= htmlspecialchars($day['day_number'] ?? 'x') ?>'"
              >
                <div class="flex items-center gap-3 flex-1 min-w-0">
                  <span class="text-xs font-bold bg-sky-100 text-sky-800 px-2 py-1 rounded-full shrink-0">
                    Day <?= htmlspecialchars($day['day_number'] ?? '?') ?>
                  </span>
                  <h4 class="text-sm font-semibold text-sky-800 truncate">
                    <?= htmlspecialchars($day['day_title'] ?? 'Untitled Day') ?>
                  </h4>
                </div>
                <svg class="w-5 h-5 text-slate-500 shrink-0 transition-transform duration-200"
                     :class="openDay === <?= json_encode($day['day_number'] ?? null) ?> ? 'rotate-180' : ''"
                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <div
                x-show="openDay === <?= json_encode($day['day_number'] ?? null) ?>"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 max-h-0"
                x-transition:enter-end="opacity-100 max-h-96"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 max-h-96"
                x-transition:leave-end="opacity-0 max-h-0"
                id="day-content-<?= htmlspecialchars($day['day_number'] ?? 'x') ?>"
                class="px-4 pb-4 overflow-hidden"
              >
                <?php $activities = $day['activities'] ?? []; ?>
                <?php if (!empty($activities)): ?>
                <ul class="space-y-2 ml-1">
                  <?php foreach ($activities as $activity): ?>
                  <li class="flex items-start gap-2 text-sm text-gray-700 border-l-3 border-sky-300 pt-2 pb-2">
                    <?php
                      $timeLabel = $activity['activity_time'] ?? $activity['time'] ?? '';
                    ?>
                    <span class="font-bold text-xs text-sky-700 whitespace-nowrap shrink-0 uppercase min-w-[50px]">
                      <?= htmlspecialchars($timeLabel) ?>
                    </span>
                    <span class="font-bold text-xs text-slate-600">
                      <?= htmlspecialchars($activity['title'] ?? 'Unnamed activity') ?>
                    </span>
                  </li>
                  <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-xs text-slate-500 italic ml-1 mt-2">
                  No activities planned for this day.
                </p>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p class="text-sm text-slate-500 italic text-center py-8">
            No itinerary available for this package.<br>
            <span class="text-slate-400">Your travel specialist will add one soon.</span>
          </p>
          <?php endif; ?>
        </div>

        <!-- Inclusions -->
        <div x-show="tab === 'inclusions'" class="flex-1 pr-1 max-h-[320px] sm:max-h-[500px] overflow-y-auto space-y-5 text-left">
          <?php if (!empty($inclusionData)): ?>
          <ul class="space-y-3">
            <?php foreach ($inclusionData as $item): ?>
            <li class="flex items-start gap-2">
              <span class="flex-shrink-0 mt-0.5">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                  <path d="M5 13l4 4L19 7" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
              <div>
                <p class="text-sky-800 font-semibold"><?= htmlspecialchars($item['title'] ?? '') ?></p>
                <?php if (!empty($item['desc'])): ?>
                <p class="italic text-slate-600 text-sm"><?= htmlspecialchars($item['desc']) ?></p>
                <?php endif; ?>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
          <p class="text-sm text-slate-500 italic">No inclusions listed for this package.</p>
          <?php endif; ?>
        </div>

        <!-- Exclusions -->
        <div x-show="tab === 'exclusions'" class="flex-1 pr-1 max-h-[320px] sm:max-h-[500px] overflow-y-auto space-y-5 text-left">
          <?php if (!empty($exclusionData)): ?>
          <ul class="space-y-3">
            <?php foreach ($exclusionData as $item): ?>
            <li class="flex items-start gap-2">
              <span class="text-lg flex-shrink-0"><?= $item['icon'] ?? '❌' ?></span>
              <div>
                <p class="text-red-500 font-semibold"><?= htmlspecialchars($item['title'] ?? '') ?></p>
                <?php if (!empty($item['desc'])): ?>
                <p class="italic text-slate-600 text-sm"><?= htmlspecialchars($item['desc']) ?></p>
                <?php endif; ?>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
          <p class="text-sm text-slate-500 italic">No exclusions listed for this package.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Actions (read-only, close only) -->
    <div class="pb-4 border-t border-slate-200 mt-4 sm:mt-6 pt-4 sticky bottom-0 bg-white px-4 sm:px-5">
      <div class="flex justify-end">
        <button
          type="button"
          class="text-sm text-slate-700 border border-slate-300 px-4 py-2 rounded hover:bg-slate-50 transition"
          @click="showAssignedPackage = false"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</div>
