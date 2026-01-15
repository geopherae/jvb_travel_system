<?php
// Using normalized keys from $allToursForJS
$tourId      = (int) ($tour['id'] ?? 0);
$name        = $tour['name'] ?? 'Unnamed Package';
$description = $tour['description'] ?? 'No description available.';
$price       = isset($tour['price']) ? (float) $tour['price'] : 0;
$day         = (int) ($tour['days'] ?? 0);
$night       = (int) ($tour['nights'] ?? 0);
$image       = $tour['image'] ?? '../images/default_trip_cover.jpg';

$durationText = ($day && $night)
  ? "{$day} Day" . ($day > 1 ? "s" : "") . " / {$night} Night" . ($night > 1 ? "s" : "")
  : 'Duration TBD';
?>

<!-- Tour Row -->
<div
  x-data="tourRowData(<?= $tourId ?>)"
  x-transition:leave="transition-all duration-500 ease-in-out"
  x-transition:leave-start="opacity-100 translate-x-0 scale-100 max-h-[10rem] py-5 my-4"
  x-transition:leave-end="opacity-0 -translate-x-8 scale-95 max-h-0 py-0 my-0"
  class="relative overflow-visible"
>
  <!-- Row Body -->
  <div class="flex items-center justify-between px-4 py-5 transition hover:bg-sky-50 rounded-md">

    <!-- Tour Info (opens view modal) -->
    <div class="flex items-center gap-5 max-w-2xl w-full cursor-pointer"
         @click="$store.tourModal.openModal(<?= $tourId ?>)">
      <img src="<?= htmlspecialchars($image) ?>"
           alt="<?= htmlspecialchars($name) ?>"
           class="w-20 h-20 object-cover rounded-lg shadow-sm" />

      <div class="flex-1 space-y-2">
        <h4 class="text-lg font-semibold text-sky-900 leading-tight">
          <?= htmlspecialchars($name) ?>
        </h4>
        <p class="text-sm text-slate-600 leading-snug line-clamp-2">
          <?= htmlspecialchars($description) ?>
        </p>
        <div class="flex flex-wrap items-center gap-2 pt-1">
          <span class="inline-block bg-sky-100 text-sky-700 text-xs px-3 py-1 rounded-full font-medium shadow-sm">
            <?= $price > 0 ? '₱' . number_format($price, 2) : 'Price TBD' ?>
          </span>
          <span class="inline-block bg-sky-100 text-sky-700 text-xs px-3 py-1 rounded-full font-medium shadow-sm">
            <?= $durationText ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Dropdown Menu -->
    <div class="relative z-[60] shrink-0">
      <button @click.stop="toggleMenu()"
              class="text-slate-500 hover:text-slate-800 p-1.5 rounded-full hover:bg-slate-100 transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
          <path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5a1.5 1.5 0 110 3 1.5 1.5 0 010-3z" />
        </svg>
      </button>

      <div x-show="$store.dropdown.isOpen(<?= $tourId ?>)"
           @click.away="$store.dropdown.close()"
           x-transition
           data-floating-dropdown
           class="mt-2 w-32 bg-white border border-gray-200 rounded shadow">
        <button
          @click.stop.prevent="$store.dropdown.close(); $store.editTourModal.open(<?= $tourId ?>)"
          class="block w-full text-left px-4 py-2 text-sm text-slate-700 font-semibold hover:bg-slate-100"
        >
          Edit
        </button>
        <button
          @click.stop.prevent="$store.dropdown.close(); $store.deleteTourModal.open(<?= $tourId ?>)"
          class="block w-full text-left px-4 py-2 text-sm text-red-500 font-semibold hover:bg-red-50"
        >
          Archive Package
        </button>
      </div>
    </div>

  </div>
</div>