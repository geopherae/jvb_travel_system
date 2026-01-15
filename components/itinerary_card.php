<div 
  x-data="{ open: false, compact: false }" 
  class="bg-white p-5 shadow rounded-2xl col-span-1 lg:col-span-2 xl:col-span-3 border border-gray-200"
>
  <!-- Header with toggle button -->
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="flex items-center gap-2 text-slate-800">
      <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center shadow-sm">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
      </div>
      <div>
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-semibold">Client Itinerary</p>
        <h3 class="text-xl font-bold">Day-by-Day Plan</h3>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <button 
        @click="open = !open" 
        class="text-sm px-3 py-1.5 bg-sky-100 text-sky-700 rounded-lg hover:bg-sky-200 transition border border-sky-200"
      >
        <span x-show="open">Hide</span>
        <span x-show="!open">Show</span>
      </button>
      <button 
        @click="compact = !compact" 
        class="text-sm px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition border border-gray-200"
      >
        <span x-show="!compact">Compact</span>
        <span x-show="compact">Comfort</span>
      </button>
    </div>
  </div>

  <!-- Collapsible Content -->
  <div x-show="open" x-transition>
    <?php if (!empty($parsedItinerary)): ?>
      <div class="space-y-3 overflow-y-auto max-h-[420px] pr-1">
        <?php foreach ($parsedItinerary as $day): ?>
          <?php $isToday = ($todayDay && $day['day_number'] == $todayDay); ?>
          <div class="relative rounded-2xl border border-gray-200 bg-gradient-to-br from-white via-slate-50 to-slate-100 shadow-sm transition-all hover:shadow-md"
            :class="compact ? 'p-3' : 'p-4'">
            <!-- Timeline marker -->
            <div class="absolute left-3 top-4 bottom-4 w-px bg-sky-200"></div>
            <div class="relative pl-6">
              <!-- Day Header -->
              <div class="flex flex-wrap items-center gap-2 mb-3">
                <span class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold uppercase tracking-wide rounded-full border border-sky-200 text-sky-800 bg-sky-50">
                  <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                  Day <?= $day['day_number'] ?>
                </span>
                <span class="font-semibold text-slate-900 text-sm md:text-base"><?= htmlspecialchars($day['day_title'] ?? '') ?></span>
                <?php if ($isToday): ?>
                  <span class="text-[11px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-semibold uppercase tracking-wide">Today</span>
                <?php endif; ?>
              </div>

              <!-- Activities -->
              <?php if (!empty($day['activities'])): ?>
                <ul class="space-y-2">
                  <?php foreach ($day['activities'] as $act): ?>
                    <li class="flex flex-col md:flex-row md:items-center bg-white/70 border border-gray-200 rounded-xl"
                        :class="compact ? 'md:gap-2 text-xs px-3 py-2' : 'md:gap-3 text-sm px-4 py-3'">
                      <?php if (!empty($act['time'])): ?>
                        <span class="inline-flex items-center font-mono bg-sky-100 text-sky-800 rounded-md min-w-[64px] justify-center border border-sky-200"
                              :class="compact ? 'text-[10px] px-2 py-1' : 'text-[11px] md:text-xs px-2.5 py-1.5'">
                          <?= htmlspecialchars($act['time']) ?>
                        </span>
                      <?php endif; ?>
                      <span class="font-semibold text-slate-900 leading-snug"
                            :class="compact ? 'text-[13px]' : 'text-sm'">
                        <?= htmlspecialchars($act['title'] ?? '') ?>
                      </span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p class="text-xs text-gray-500 italic">No activities listed.</p>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
    <?php
    require_once '../includes/empty_state_map.php';
    ?>
    <div class="flex flex-col items-center justify-center py-12 text-center">
      <?php echo getEmptyStateSvg('no-itineraries-found'); ?>
      <p class="text-md italic font-semibold text-sky-700 mb-1">No itinerary found.</p>
      <p class="text-md text-sky-700 italic">Assign a tour package from the <strong>Client & Tour Info Tab</strong> to get started!</p>
    </div>
          <?php endif; ?>
  </div>
</div>