<?php
// view_client_itinerary_card.php
// Included by view_client.php — uses $parsedItinerary and $todayDay already set there
// Parent Alpine scope must have: showItineraryModal (bool)
// Triggered by clicking tour-package-card.php

// ── Safe JSON for JS ──────────────────────────────────────────────────────────
$safeTodayDay = 'null';
if (isset($todayDay)) {
  $json = json_encode($todayDay);
  if (json_last_error() === JSON_ERROR_NONE) {
    $safeTodayDay = $json;
  }
}

$safeParsedItinerary = '[]';
if (isset($parsedItinerary)) {
  $json = json_encode($parsedItinerary, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
  if (json_last_error() === JSON_ERROR_NONE) {
    $safeParsedItinerary = $json;
  }
}
?>

<script>
  function itineraryModalScope() {
    // Google Calendar-style palette — cycles per activity index
    const CARD_COLORS = [
      { bar: 'bg-sky-400',     bg: 'bg-sky-50',     badge: 'bg-sky-100 text-sky-700',      dot: 'bg-sky-400'     },
      { bar: 'bg-violet-400',  bg: 'bg-violet-50',  badge: 'bg-violet-100 text-violet-700', dot: 'bg-violet-400'  },
      { bar: 'bg-emerald-400', bg: 'bg-emerald-50', badge: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-400' },
      { bar: 'bg-amber-400',   bg: 'bg-amber-50',   badge: 'bg-amber-100 text-amber-700',   dot: 'bg-amber-400'   },
      { bar: 'bg-rose-400',    bg: 'bg-rose-50',    badge: 'bg-rose-100 text-rose-700',     dot: 'bg-rose-400'    },
      { bar: 'bg-teal-400',    bg: 'bg-teal-50',    badge: 'bg-teal-100 text-teal-700',     dot: 'bg-teal-400'    },
    ];

    return {
      compact:   true,
      activeDay: null,
      todayDay:  <?= $safeTodayDay ?>,
      itinerary: <?= $safeParsedItinerary ?>,
      colors:    CARD_COLORS,

      init() {
        if (this.itinerary && this.itinerary.length > 0) {
          this.activeDay = this.todayDay ?? this.itinerary[0].day_number;
        }
      },

      get activeData() {
        if (this.activeDay === null) return null;
        return this.itinerary.find(d => d.day_number === this.activeDay) ?? null;
      },

      colorFor(idx) {
        return this.colors[idx % this.colors.length];
      },

      isPast(dayNum) {
        return this.todayDay !== null && dayNum < this.todayDay;
      },

      isToday(dayNum) {
        return dayNum === this.todayDay;
      },
    };
  }
</script>

<!-- ═══════════════════════════════════════════════════════
     ITINERARY MODAL
═══════════════════════════════════════════════════════ -->
<div
  x-show="showItineraryModal"
  x-cloak
  class="fixed inset-0 z-50 flex items-center justify-center p-4"
  aria-labelledby="itinerary-modal-title"
  role="dialog"
  aria-modal="true"
  @keydown.escape.window="showItineraryModal = false"
>
  <!-- Backdrop -->
  <div
    class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"
    @click="showItineraryModal = false"
  ></div>

  <!-- Modal panel -->
  <div
    class="relative w-full sm:max-w-3xl bg-white rounded-2xl shadow-2xl ring-1 ring-gray-200 overflow-hidden flex flex-col"
    style="max-height: 86vh;"
    x-data="itineraryModalScope()"
  >

    <!-- ── Header ── -->
    <div class="bg-gradient-to-r from-sky-500 to-blue-600 px-5 py-3 flex-shrink-0">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <div class="pt-2 pb-2">
            <p class="text-xs uppercase tracking-widest text-sky-200 font-semibold leading-none mb-0.5">Client Itinerary</p>
            <h3 class="text-base font-bold text-white leading-tight" id="itinerary-modal-title">Day-by-Day Plan</h3>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="compact = !compact"
            class="text-sm px-3 py-1.5 rounded-lg font-semibold transition-all duration-150 bg-white/15 text-white hover:bg-white/25 border border-white/20"
          >
            <span x-show="!compact">Compact</span>
            <span x-show="compact">Comfortable</span>
          </button>
          <button
            type="button"
            @click="showItineraryModal = false"
            class="text-white/70 hover:text-white transition-colors rounded-xl p-1.5 hover:bg-white/15"
            aria-label="Close"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- ── Empty state ── -->
    <template x-if="itinerary.length === 0">
      <div class="py-16 text-center">
        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
        <p class="text-base font-semibold text-slate-500">No itinerary available</p>
        <p class="text-sm text-slate-400 mt-1">This client's package has no itinerary template yet.</p>
      </div>
    </template>

    <!-- ── Two-column layout ── -->
    <template x-if="itinerary.length > 0">
      <div class="p-2 flex flex-1 min-h-0 overflow-hidden">

        <!-- ─── LEFT: Day sidebar ─── -->
        <div class="w-[30%] flex-shrink-0 border-r border-gray-100 bg-gray-50/70 flex flex-col">
          <div class=" pb-2 pt-4 pr-4 pl-4 border-b border-gray-100">
            <p class="text-xs uppercase tracking-widest font-bold text-slate-400 px-1">Days</p>
          </div>
          <div class="flex-1 overflow-y-auto py-4 space-y-2 px-1.5">
            <template x-for="day in itinerary" :key="day.day_number">
              <button
                @click="activeDay = day.day_number"
                class="p-4 w-full text-left flex items-center gap-2 rounded-xl transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-sky-300"
                :class="[
                  compact ? 'py-1.5' : 'py-2',
                  activeDay === day.day_number
                    ? 'bg-sky-500 text-white shadow-sm shadow-sky-200'
                    : isToday(day.day_number)
                    ? 'bg-sky-50 text-sky-700 hover:bg-sky-100'
                    : isPast(day.day_number)
                    ? 'text-slate-400 hover:bg-white hover:text-slate-600'
                    : 'text-slate-600 hover:bg-white hover:text-slate-800'
                ]"
              >
                <!-- Day chip -->
                <span
                  class="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-sm font-bold tabular-nums"
                  :class="
                    activeDay === day.day_number
                      ? 'bg-white/20 text-white'
                      : isToday(day.day_number)
                      ? 'bg-sky-500 text-white'
                      : isPast(day.day_number)
                      ? 'bg-slate-200 text-slate-400'
                      : 'bg-slate-200 text-slate-600'
                  "
                  x-text="day.day_number"
                ></span>

                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold leading-tight truncate" x-text="day.day_title"></p>
                  <p class="text-xs mt-0.5 leading-none"
                     :class="activeDay === day.day_number ? 'text-sky-100' : 'text-slate-400'"
                     x-text="day.activities.length + (day.activities.length === 1 ? ' activity' : ' activities')"></p>
                </div>

                <span
                  x-show="isToday(day.day_number)"
                  class="flex-shrink-0 text-[10px] uppercase tracking-wider font-bold px-1.5 py-0.5 rounded-full leading-none"
                  :class="activeDay === day.day_number ? 'bg-white/25 text-white' : 'bg-sky-500 text-white'"
                >Now</span>
              </button>
            </template>
          </div>
        </div>
        <!-- end LEFT sidebar -->

        <!-- ─── RIGHT: Day detail panel ─── -->
        <div class="p-4 flex-1 flex flex-col min-w-0">

          <!-- Day header + prev/next nav -->
          <div class="px-5 pt-3.5 pb-3 border-b border-gray-100 flex items-center gap-3 flex-shrink-0">
            <template x-if="activeData">
              <div class="flex items-center gap-3 flex-1 min-w-0">
                <div
                  class="w-9 h-9 rounded-xl flex items-center justify-center text-base font-bold tabular-nums flex-shrink-0"
                  :class="isToday(activeData.day_number) ? 'bg-sky-500 text-white' : 'bg-sky-50 text-sky-600'"
                  x-text="activeData.day_number"
                ></div>
                <div class="min-w-0">
                  <h4 class="text-base font-medium text-slate-700 leading-tight truncate" x-text="activeData.day_title"></h4>
                  <p class="text-sm text-slate-400 mt-0.5">
                    <span x-text="activeData.activities.length"></span>
                    <span x-text="activeData.activities.length === 1 ? ' activity' : ' activities'"></span>
                    <template x-if="isToday(activeData.day_number)">
                      <span class="ml-1.5 text-sky-500 font-semibold">— Today</span>
                    </template>
                    <template x-if="isPast(activeData.day_number)">
                      <span class="ml-1.5 text-slate-400">— Completed</span>
                    </template>
                  </p>
                </div>
              </div>
            </template>
            <template x-if="!activeData">
              <p class="text-sm text-slate-400 italic flex-1">Select a day</p>
            </template>

            <!-- Prev / Next arrows -->
            <div class="flex items-center gap-1 flex-shrink-0">
              <button
                @click="activeDay = Math.max(itinerary[0].day_number, (activeDay ?? itinerary[0].day_number) - 1)"
                :disabled="!activeDay || activeDay === itinerary[0].day_number"
                class="w-7 h-7 flex items-center justify-center rounded-lg border border-gray-200 text-slate-400 hover:border-sky-300 hover:text-sky-500 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
              </button>
              <button
                @click="activeDay = Math.min(itinerary[itinerary.length - 1].day_number, (activeDay ?? itinerary[0].day_number) + 1)"
                :disabled="!activeDay || activeDay === itinerary[itinerary.length - 1].day_number"
                class="w-7 h-7 flex items-center justify-center rounded-lg border border-gray-200 text-slate-400 hover:border-sky-300 hover:text-sky-500 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Activity list -->
          <div class="flex-1 overflow-y-auto" :class="compact ? 'px-4 py-3 space-y-2' : 'px-5 py-3.5 space-y-2.5'">

            <!-- No activities -->
            <template x-if="activeData && activeData.activities.length === 0">
              <div class="flex flex-col items-center justify-center py-10">
                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center mb-3">
                  <svg class="w-5 h-5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </div>
                <p class="text-sm text-slate-400 font-medium">No activities scheduled</p>
              </div>
            </template>

            <!-- Activity cards -->
            <template x-if="activeData && activeData.activities.length > 0">
              <div :class="compact ? 'space-y-2' : 'space-y-2.5'">
                <template x-for="(act, idx) in activeData.activities" :key="idx">
                  <div
                    class="flex items-stretch rounded-xl overflow-hidden transition-all duration-150 hover:shadow-md group"
                    :class="[
                      isPast(activeData.day_number) ? 'opacity-55' : '',
                      colorFor(idx).bg
                    ]"
                  >
                    <!-- Left colour bar -->
                    <div
                      class="w-1 flex-shrink-0"
                      :class="isPast(activeData.day_number) ? 'bg-slate-300' : colorFor(idx).bar"
                    ></div>

                    <!-- Content -->
                    <div
                      class="flex items-center gap-3 flex-1 min-w-0"
                      :class="compact ? 'px-3 py-2' : 'px-4 py-2.5'"
                    >
                      <!-- Time badge -->
                      <span
                        class="font-mono font-semibold tabular-nums text-center rounded-lg leading-none flex-shrink-0"
                        :class="[
                          compact ? 'text-xs px-2 py-1 min-w-[46px]' : 'text-sm px-2.5 py-1 min-w-[52px]',
                          isPast(activeData.day_number) ? 'bg-white/60 text-slate-400' : colorFor(idx).badge
                        ]"
                        x-text="act.time || '—'"
                      ></span>

                      <!-- Dot -->
                      <div
                        class="flex-shrink-0 w-1.5 h-1.5 rounded-full"
                        :class="isPast(activeData.day_number) ? 'bg-slate-300' : colorFor(idx).dot"
                      ></div>

                      <!-- Title -->
                      <span
                        class="leading-snug flex-1 min-w-0 text-sm text-slate-700"
                        :class="[
                          compact ? 'text-sm' : 'text-base',
                          isPast(activeData.day_number)
                            ? 'text-slate-400 line-through decoration-slate-300'
                            : 'text-slate-700'
                        ]"
                        x-text="act.title"
                      ></span>

                      <!-- Completed checkmark -->
                      <template x-if="isPast(activeData.day_number)">
                        <svg class="flex-shrink-0 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                      </template>
                    </div>
                  </div>
                </template>
              </div>
            </template>

            <!-- No day selected -->
            <template x-if="!activeData">
              <div class="flex flex-col items-center justify-center py-10 text-center">
                <div class="w-10 h-10 rounded-full bg-sky-50 flex items-center justify-center mb-3">
                  <svg class="w-5 h-5 text-sky-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>
                <p class="text-sm font-medium text-slate-400">Select a day from the sidebar</p>
              </div>
            </template>
          </div>

          <!-- Footer -->
          <div class="border-t border-gray-100 px-5 py-2 flex items-center justify-between bg-gray-50/60 flex-shrink-0">
            <p class="text-sm text-gray-400">
              <span x-text="itinerary.length"></span> days total
              <template x-if="todayDay !== null">
                <span>&nbsp;&middot;&nbsp;Day <span x-text="todayDay"></span> active</span>
              </template>
            </p>
            <template x-if="todayDay !== null">
              <button
                @click="activeDay = todayDay"
                class="text-sm px-3 py-1.5 rounded-lg font-semibold text-sky-600 bg-sky-50 hover:bg-sky-100 hover:text-sky-700 transition-colors border border-sky-100"
              >
                Jump to today
              </button>
            </template>
          </div>

        </div>
        <!-- end RIGHT panel -->

      </div>
      <!-- end two-column flex -->
    </template>

  </div>
  <!-- end modal panel -->

</div>