<?php if (!empty($parsedDays)): ?>
  <?php foreach ($parsedDays as $day): ?>
    <?php
      $isToday = ($todayDay && $day['day_number'] == $todayDay);
      $cardId  = $isToday ? 'today-card' : '';
      $cardRing = $isToday ? 'ring-4 ring-emerald-300 animate-pulse' : '';
      $cardBg   = $isToday ? 'bg-emerald-50 ring-2 ring-emerald-300' : 'bg-white border border-slate-200';
    ?>

    <div id="<?= $cardId ?>" class="relative flex flex-col gap-3 mb-5 <?= $cardRing ?>">
      <article class="rounded-xl p-4 transition-all duration-200 <?= $cardBg ?>
        shadow-sm hover:bg-sky-50 hover:border-sky-400 hover:shadow-md"
        aria-label="Day <?= $day['day_number'] ?>: <?= htmlspecialchars($day['day_title'] ?? '') ?>">

        <!-- 🗓️ Day Header -->
        <div class="flex items-center justify-between mb-2">
          <span class="inline-block px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide
                       rounded-full border border-sky-300 text-sky-700 bg-sky-50">
            Day <?= $day['day_number'] ?>
          </span>
          <?php if ($isToday): ?>
            <span class="text-[11px] bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded font-semibold uppercase tracking-wide">
              Today
            </span>
          <?php endif; ?>
        </div>

        <!-- 📍 Title -->
        <h3 class="text-base font-bold text-slate-800 leading-tight mb-2">
          <?= htmlspecialchars(html_entity_decode($day['day_title'] ?? '')) ?>
        </h3>

        <!-- 📌 Activities -->
        <ul class="space-y-2 text-sm" aria-label="Activities for Day <?= $day['day_number'] ?>">
          <?php foreach ($day['activities'] ?? [] as $act): ?>
            <?php
              $safeTitle = htmlspecialchars(html_entity_decode($act['title'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
              $safeTime  = htmlspecialchars(html_entity_decode($act['time'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            ?>
            <li class="flex items-start gap-2 flex-wrap">
              <?php if (!empty($safeTime)): ?>
                <span class="inline-block font-mono text-xs bg-sky-100 text-sky-700 px-2 py-0.5 rounded w-fit flex-shrink-0">
                  <?= $safeTime ?>
                </span>
              <?php endif; ?>
              <span class="font-medium text-slate-700 break-words"><?= $safeTitle ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </article>
    </div>
  <?php endforeach; ?>

  <!-- 🔍 Scroll to Today's Card -->
  <script>
    window.addEventListener('DOMContentLoaded', () => {
      const todayCard = document.getElementById('today-card');
      if (todayCard) {
        todayCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  </script>

<?php else: ?>
  <!-- 🧭 Empty State: No Itinerary -->
  <div class="bg-white border border-dashed border-sky-200 rounded-xl p-6 text-center text-slate-600 flex flex-col items-center justify-center gap-3">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-sky-400 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h4m0 0l-4-4m4 4l-4 4" />
    </svg>
    <h4 class="text-sm font-semibold text-sky-800">No Itinerary Available</h4>
    <p class="text-xs italic">Once a tour package is assigned, your day-by-day itinerary will appear here.</p>
  </div>
<?php endif; ?>