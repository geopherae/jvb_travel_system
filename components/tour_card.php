<?php
// Using normalized keys from $allToursForJS
$tourId      = (int) ($tour['id'] ?? 0);
$image       = $tour['image'] ?? '../images/default_trip_cover.jpg';
$name        = $tour['name'] ?? 'Untitled Package';
$description = $tour['description'] ?? 'No description available.';
$day         = (int) ($tour['days'] ?? 0);
$night       = (int) ($tour['nights'] ?? 0);
$price       = isset($tour['price']) ? (float) $tour['price'] : 0;

$durationText = ($day && $night)
  ? "{$day} Day" . ($day > 1 ? "s" : "") . " / {$night} Night" . ($night > 1 ? "s" : "")
  : 'Duration TBD';
?>

<div 
  x-data="tourRowData(<?= $tourId ?>)"
  class="relative h-full"
>
  <div
    @click="$store.tourModal.openModal(rowId)"
    class="cursor-pointer group bg-white border border-gray-100 rounded-lg shadow-sm overflow-hidden transition hover:ring-2 hover:ring-sky-200 hover:ring-offset-1 hover:shadow-md hover:scale-[1.01] transform duration-200 h-full flex flex-col"
  >
    <img src="<?= htmlspecialchars($image) ?>"
         alt="Cover for <?= htmlspecialchars($name) ?>"
         class="w-full h-40 object-cover" />

    <div class="p-4 flex-1 flex flex-col">
      <h3 class="text-lg font-bold text-sky-900 mb-1"><?= htmlspecialchars($name) ?></h3>
      <p class="text-sm text-slate-600 line-clamp-2 flex-1">
        <?= htmlspecialchars($description) ?>
      </p>

      <!-- Duration + Price row -->
      <div class="flex flex-wrap items-center gap-2 mt-3">
        <span class="inline-block bg-sky-100 text-sky-700 text-xs font-semibold px-3 py-1 rounded-full">
          <?= $price > 0 ? '₱' . number_format($price, 2) : 'Price TBD' ?>
        </span>
        <span class="inline-block bg-sky-100 text-sky-700 text-xs font-semibold px-3 py-1 rounded-full">
          <?= $durationText ?>
        </span>
      </div>
    </div>
  </div>
</div>