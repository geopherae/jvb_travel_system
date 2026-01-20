<!-- tour_modal.php -->
<script>
document.addEventListener('alpine:init', () => {
  if (!Alpine.store('deleteTourModal')) {
    Alpine.store('deleteTourModal', {
      isOpen: false,
      tourId: null,
      loading: false,
      open(tourId) {
        console.log('[deleteTourModal] Opening with tourId:', tourId);
        this.tourId = tourId;
        this.isOpen = true;
      },
      close() {
        console.log('[deleteTourModal] Closing');
        this.isOpen = false;
        this.tourId = null;
      },
      reset() {
        console.log('[deleteTourModal] Resetting');
        this.isOpen = false;
        this.tourId = null;
        this.loading = false;
      }
    });
    console.log('deleteTourModal defined in tour_modal.php:', Alpine.store('deleteTourModal'));
  }
});
</script>

<!-- 👁️ Tour View Modal -->
<div
  x-show="$store.tourModal.isOpen"
  x-transition.opacity
  x-cloak
  class="backdrop-blur-sm fixed inset-0 z-[50] flex items-end sm:items-center justify-center bg-black/55 px-3 sm:px-4"
  @keydown.escape.window="$store.tourModal.closeModal()"
  role="dialog"
  aria-modal="true"
>
  <!-- Modal Container -->
  <div
    class="relative bg-white rounded-t-2xl sm:rounded-lg shadow-xl w-full max-w-[100vw] sm:max-w-5xl transition-all duration-300 max-h-[calc(100vh-24px)] sm:max-h-[95vh] flex flex-col overflow-hidden"
    @click.away="$store.tourModal.closeModal()"
    x-transition.opacity
  >
    <!-- Close Button -->
    <button
      @click="$store.tourModal.closeModal()"
      class="absolute top-4 right-4 text-slate-500 hover:text-red-500 text-xl font-bold z-10"
      aria-label="Close Tour Modal"
    >
      ×
    </button>

    <div class="w-full flex-1 overflow-y-auto flex flex-col sm:flex-row gap-2 sm:gap-4 p-4 sm:p-6 pb-20 sm:pb-6">
      <!-- Left Column -->
<div class="sm:max-w-[55%] flex-1 flex flex-col">
  <img
    :src="$store.tourModal.activeTour.image || '../images/default_trip_cover.jpg'"
    alt="Tour Cover"
    class="w-full h-52 sm:h-64 object-cover rounded-t-lg sm:rounded-lg sm:shadow"
  />
  <div class="p-4 pt-5 px-5 space-y-2">
    <!-- Tour Name + Price & Duration on the same line -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h2 class="text-xl font-semibold text-slate-800 leading-tight truncate flex-1 min-w-0"
          x-text="$store.tourModal.activeTour.name || 'Unnamed Package'"></h2>

      <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
        <span class="inline-block bg-sky-100 text-sky-700 font-semibold px-3 py-1 rounded-full text-sm">
          <span x-text="$store.tourModal.activeTour.price ? '₱' + Number($store.tourModal.activeTour.price).toLocaleString('en-PH') : 'Price TBD'"></span>
        </span>
        <span class="inline-block bg-slate-100 text-slate-700 font-semibold px-3 py-1 rounded-full text-sm">
          <span x-text="`${$store.tourModal.activeTour.days || 0} Day${$store.tourModal.activeTour.days != 1 ? 's' : ''} / ${$store.tourModal.activeTour.nights || 0} Night${$store.tourModal.activeTour.nights != 1 ? 's' : ''}`"></span>
        </span>
      </div>
    </div>

    <!-- Requires Visa Pill -->
    <template x-if="$store.tourModal.activeTour.requires_visa == 1">
      <span class="inline-block bg-green-100 text-green-800 font-medium px-3 py-1 rounded-full text-xs">
        Requires Visa
      </span>
    </template>

    <!-- Origin & Destination Pill -->
    <span class="inline-block bg-purple-100 text-purple-800 font-semibold px-3 py-1 rounded-full text-xs">
      <span x-text="`${$store.tourModal.activeTour.origin || 'Origin TBD'} → ${$store.tourModal.activeTour.destination || 'Destination TBD'}`"></span>
    </span>

    <!-- Description -->
    <p class="line-clamp-4 text-sm text-slate-600 leading-relaxed"
       x-text="$store.tourModal.activeTour.description || 'No description available.'"></p>
  </div>
</div>

      <!-- Right Column -->
      <div class="sm:max-w-[45%] w-full flex flex-col border-t sm:border-t-0 sm:border-l border-slate-100 px-4 sm:px-5 py-4 sm:py-0 sm:pl-6">
        <!-- Tabs -->
        <div class="flex border-b mb-4 gap-3 overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 pb-1">
          <button
            type="button"
            :class="$store.tourModal.tab === 'itinerary' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600'"
            @click="$store.tourModal.tab = 'itinerary'"
            class="px-3 py-2 text-xs sm:text-sm font-medium focus:outline-none hover:text-sky-600 shrink-0"
          >
            Itinerary
          </button>
          <button
            type="button"
            :class="$store.tourModal.tab === 'inclusions' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600'"
            @click="$store.tourModal.tab = 'inclusions'"
            class="px-3 py-2 text-xs sm:text-sm font-medium focus:outline-none hover:text-sky-600 shrink-0"
          >
            Inclusions
          </button>
          <button
            type="button"
            :class="$store.tourModal.tab === 'exclusions' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600'"
            @click="$store.tourModal.tab = 'exclusions'"
            class="px-3 py-2 text-xs sm:text-sm font-medium focus:outline-none hover:text-sky-600 shrink-0"
          >
            Exclusions
          </button>
        </div>

<!-- Itinerary Viewer (Read-Only - Collapsible Accordion) -->
<div x-show="$store.tourModal.tab === 'itinerary'" 
  class="flex-1 pr-1 max-h-[260px] sm:max-h-[300px] overflow-y-auto space-y-5 text-left"
     x-data="{ openDay: null }">

  <!-- No Itinerary Message -->
  <template x-if="!$store.tourModal.activeTour.itinerary || !$store.tourModal.activeTour.itinerary.length">
    <p class="text-sm text-slate-500 italic text-center py-8">
      No itinerary available for this package.<br>
      <span class="text-slate-400">Create one in the admin panel to display it here.</span>
    </p>
  </template>

  <!-- Itinerary Days (Accordion) -->
  <template x-if="$store.tourModal.activeTour.itinerary && $store.tourModal.activeTour.itinerary.length">
    <div class="space-y-4">
      <template x-for="day in $store.tourModal.activeTour.itinerary" :key="day.day_number">
        <div class="border rounded-lg shadow-sm bg-slate-50 overflow-hidden">

          <!-- Day Header (Clickable to Toggle) -->
            <button
            type="button"
            @click="openDay = (openDay === day.day_number ? null : day.day_number)"
            class="font-bold w-full px-4 py-3 flex items-center justify-between gap-3 hover:bg-slate-100 transition text-left focus:outline-none"
            :aria-expanded="openDay === day.day_number"
            :aria-controls="'day-content-' + day.day_number"
            >
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <span class="text-xs font-bold bg-sky-100 text-sky-800 px-2 py-1 rounded-full shrink-0"
                x-text="'Day ' + day.day_number">
              </span>
              <h4 class="text-sm font-semibold text-sky-800 truncate"
                x-text="day.day_title || 'Untitled Day'">
              </h4>
            </div>

            <!-- Chevron Indicator -->
            <svg class="w-5 h-5 text-slate-500 shrink-0 transition-transform duration-200"
                 :class="openDay === day.day_number ? 'rotate-180' : ''"
                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Day Content (Collapsible) -->
          <div x-show="openDay === day.day_number"
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="opacity-0 max-h-0"
               x-transition:enter-end="opacity-100 max-h-96"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="opacity-100 max-h-96"
               x-transition:leave-end="opacity-0 max-h-0"
               :id="'day-content-' + day.day_number"
               class="px-4 pb-4 overflow-hidden">

            <!-- Activities List -->
            <template x-if="day.activities && day.activities.length">
              <ul class="space-y-2 ml-1">
                <template x-for="activity in day.activities" :key="activity.title + activity.time">
                  <li class="flex items-start gap-2 text-sm text-gray-700 border-l-3 border-sky-300 pt-2 pb-2">
                    <!-- Time Column (aligned with min-width for consistent spacing) -->
                    <span class="font-bold text-xs text-sky-700 whitespace-nowrap shrink-0 uppercase min-w-[50px]"
                        x-text="activity.time ? activity.time : ''">
                    </span>

                    <!-- Activity Title -->
                    <span class="font-bold text-xs text-slate-600"
                          x-text="activity.title || 'Unnamed activity'">
                    </span>
                  </li>
                </template>
              </ul>
            </template>

            <!-- No Activities -->
            <template x-if="!day.activities || !day.activities.length">
              <p class="text-xs text-slate-500 italic ml-1 mt-2">
                No activities planned for this day.
              </p>
            </template>

          </div>
        </div>
      </template>
    </div>
  </template>
</div>

<!-- Inclusions -->
<div x-show="$store.tourModal.tab === 'inclusions'" class="flex-1 pr-1 max-h-[320px] sm:max-h-[500px] overflow-y-auto space-y-5 text-left">
  <template x-if="$store.tourModal.activeTour.inclusions && $store.tourModal.activeTour.inclusions.length">
    <ul class="space-y-3">
      <template x-for="(item, index) in $store.tourModal.activeTour.inclusions" :key="'inclusion-' + index">
        <li class="flex items-start gap-2">
          <span class="flex-shrink-0 mt-0.5">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M5 13l4 4L19 7" stroke="#10893c" 
                    stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <div>
            <p class="text-sky-800 font-semibold" x-text="item.title || '—'"></p>
            <p class="italic text-slate-600 text-sm" x-show="item.desc" x-text="item.desc"></p>
          </div>
        </li>
      </template>
    </ul>
  </template>
  <template x-if="!$store.tourModal.activeTour.inclusions || !$store.tourModal.activeTour.inclusions.length">
    <p class="text-sm text-slate-500 italic">No inclusions listed for this package.<br>Create one in the Inclusions tab to get started!</p>
  </template>
</div>

        <!-- Exclusions -->
        <div x-show="$store.tourModal.tab === 'exclusions'" class="flex-1 pr-1 max-h-[320px] sm:max-h-[500px] overflow-y-auto space-y-5 text-left">
          <template x-if="$store.tourModal.activeTour.exclusions && $store.tourModal.activeTour.exclusions.length">
            <ul class="space-y-3">
              <template x-for="(item, index) in $store.tourModal.activeTour.exclusions" :key="'exclusion-' + index">
                <li class="flex items-start gap-2">
                  <span class="flex-shrink-0 mt-0.5">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                      <path d="M18 6L6 18M6 6l12 12" stroke="#891313" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                  </span>
                  <div>
                    <p class="text-sky-800 font-semibold" x-text="item.title || '—'"></p>
                    <p class="italic text-slate-600 text-sm" x-show="item.desc" x-text="item.desc"></p>
                  </div>
                </li>
              </template>
            </ul>
          </template>
          <template x-if="!$store.tourModal.activeTour.exclusions || !$store.tourModal.activeTour.exclusions.length">
            <p class="text-sm text-slate-500 italic">No exclusions listed for this package.<br>Create one in the Exclusions tab to get started!</p>
          </template>
        </div>

    <!-- Divider + Action Buttons (sticky on mobile) -->
    <div class="pb-4 border-t border-slate-200 mt-4 sm:mt-6 pt-4 sticky bottom-0 bg-white px-4 sm:px-5">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 sm:gap-6 pb-2 sm:pb-0">
      <button
        type="button"
        class="bg-sky-600 hover:bg-sky-700 text-white text-sm px-4 py-2 rounded transition w-full sm:w-auto"
        @click="
          $store.editTourModal.open($store.tourModal.activeTour.id);
          $store.tourModal.closeModal();
        "
      >
        Edit Package
      </button>

      <button
        type="button"
        class="text-sm text-red-600 border border-red-600 px-4 py-2 rounded hover:bg-red-600 hover:text-white transition w-full sm:w-auto"
        @click="$store.deleteTourModal.open($store.tourModal.activeTour.id)"
      >
        Archive Package
      </button>
      </div>
    </div>
  </div>
</div>

<!-- ✏️ Edit Tour Package Modal -->
<div x-data="tourFormData($store.editTourModal.tourData)" x-show="$store.editTourModal.isOpen" x-cloak>
  <?php include '../components/edit_tour_package.php'; ?>
</div>

<!-- 🗑️ Delete Tour Package Modal -->
<?php include '../components/delete_tour_package.php'; ?>