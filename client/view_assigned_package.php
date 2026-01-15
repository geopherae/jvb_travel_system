<?php
// 🖼️ Tour image
$imageFilename = $client['tour_cover_image'] ?? '';
$image = (!empty($imageFilename) && $imageFilename !== 'NULL')
  ? '../images/tour_packages_banners/' . rawurlencode($imageFilename)
  : '../images/default_trip_cover.jpg';

// 📋 Basic package details
$title         = $client['package_name'] ?? 'Untitled Tour';
$description   = $client['package_description'] ?? 'No description available.';
$price         = (float) ($client['price'] ?? 0);
$priceDisplay  = ($price > 0)
  ? '₱' . number_format($price, 2)
  : 'Price TBD';

// ⏳ Duration calculation
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
    // Silent fail — keep default
  }
}

// 🎒 Travel inclusions
$inclusionData = json_decode($client['inclusions_json'] ?? '[]', true);

// 🗂️ Itinerary passed from dashboard as $itineraryDays
$itineraryData = $itineraryDays ?? [];
?>

<!-- Backdrop -->
<div x-show="showAssignedPackage" x-transition.opacity x-cloak
     class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center px-4 backdrop-blur-sm">

  <!-- Modal Container -->
  <div @click.away="showAssignedPackage = false"
       class="relative bg-white rounded-lg text-center shadow-xl max-w-5xl w-full overflow-hidden transition-all duration-300 max-h-[95vh] overflow-y-auto flex flex-col sm:flex-row gap-0 sm:gap-4 sm:p-6 p-0">

    <!-- X Close Button -->
    <button
      @click="showAssignedPackage = false"
      class="absolute top-4 right-4 z-20 text-gray-400 hover:text-gray-700 bg-white/80 rounded-full p-2 shadow transition"
      aria-label="Close"
      type="button"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>

    <!-- 🖼️ Left Column -->
    <div class="sm:max-w-[55%] flex-1 flex flex-col">
      <img src="<?= htmlspecialchars($image) ?>"
           alt="Tour Cover"
           loading="lazy"
           class="w-full h-52 sm:h-64 object-cover rounded-t-lg sm:rounded-lg sm:shadow" />

      <div class="px-5 pt-5 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-xl font-semibold text-slate-800 leading-tight"><?= htmlspecialchars($title) ?></h2>
          <div class="flex gap-2 text-sm">
            <span class="inline-block bg-sky-100 text-sky-700 font-semibold px-3 py-1 rounded-full">
              <?= $priceDisplay ?>
            </span>
            <span class="inline-block bg-slate-100 text-slate-700 font-semibold px-3 py-1 rounded-full">
              <?= $durationText ?>
            </span>
          </div>
        </div>
        
        <!-- Requires Visa Pill -->
        <?php if (!empty($client['requires_visa']) && $client['requires_visa'] == 1): ?>
        <span class="inline-block bg-green-100 text-green-800 font-medium px-3 py-1 rounded-full text-xs">
          Requires Visa
        </span>
        <?php endif; ?>
        
        <p class="text-sm text-slate-600 text-left line-clamp-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
          <?= htmlspecialchars($description) ?>
        </p>
      </div>
    </div>

    <!-- 📋 Right Column with Tabs -->
    <div x-data="{ tab: 'itinerary' }"
         class="sm:max-w-[45%] w-full flex flex-col border-t sm:border-t-0 sm:border-l border-slate-100 px-5 py-5 sm:py-0 sm:pl-6">

      <!-- Tabs -->
      <div class="flex border-b mb-4">
        <button type="button"
                :class="tab === 'itinerary' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600'"
                @click="tab = 'itinerary'"
                class="px-3 py-2 text-sm font-medium focus:outline-none hover:text-sky-600">
          Travel Itinerary
        </button>
        <button type="button"
                :class="tab === 'inclusions' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600'"
                @click="tab = 'inclusions'"
                class="px-3 py-2 text-sm font-medium focus:outline-none hover:text-sky-600">
          Travel Inclusions
        </button>
      </div>

      <!-- ✈️ Itinerary -->
      <div x-show="tab === 'itinerary'" class="flex-1 pr-1 max-h-[370px] overflow-y-auto space-y-5">
        <?php if (!empty($itineraryData)): ?>
        <div class="space-y-6">
          <?php foreach ($itineraryData as $day): ?>
          <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
              <div class="inline-block bg-sky-100 text-sky-700 font-semibold text-xs px-3 py-1 rounded-full uppercase">
                Day <?= htmlspecialchars($day['day_number'] ?? '?') ?>
              </div>
              <h4 class="text-sky-800 font-bold text-base"><?= htmlspecialchars($day['day_title'] ?? 'Untitled Day') ?></h4>
            </div>
            <ul class="text-left text-sm text-gray-700 space-y-2">
              <?php foreach ($day['activities'] ?? [] as $activity): ?>
              <li class="flex gap-2 items-start">
                <?php if (!empty($activity['activity_time'])): ?>
                <span class="font-medium text-gray-900"><?= htmlspecialchars($activity['time']) ?>:</span>
                <?php endif; ?>
                <span><?= htmlspecialchars($activity['title'] ?? 'Unnamed activity') ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-sm text-slate-500 italic">No itinerary data available for this package.</p>
        <?php endif; ?>
      </div>

      <!-- 🧳 Inclusions -->
      <div x-show="tab === 'inclusions'" class="flex-1 pr-1 max-h-[500px] overflow-y-auto space-y-5 text-left">
        <?php if (!empty($inclusionData)): ?>
        <ul class="space-y-3">
          <?php foreach ($inclusionData as $item): ?>
          <li class="flex items-start gap-2">
            <span class="text-lg"><?= htmlspecialchars($item['icon'] ?? '🔹') ?></span>
            <div>
              <p class="text-sky-800 font-semibold"><?= htmlspecialchars($item['title'] ?? '—') ?></p>
              <?php if (!empty($item['desc'])): ?>
              <p class="text-slate-600 text-sm"><?= htmlspecialchars($item['desc']) ?></p>
              <?php endif; ?>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-sm text-slate-500 italic">No inclusions listed for this package.</p>
        <?php endif; ?>
      </div>

      <!-- 👋 Close -->
      <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
        <button @click="showAssignedPackage = false"
                class="text-gray-600 hover:text-gray-800 text-sm font-medium underline">Close</button>
      </div>
    </div>

  </div>
</div>