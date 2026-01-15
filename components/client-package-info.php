<!-- 🧳 Left Panel: Package Info -->
<div class="w-full lg:w-1/3 flex flex-col gap-0 rounded-2xl overflow-hidden bg-white shadow-lg border border-slate-100">

  <!-- 📸 Cover Image -->
  <div class="relative h-56 overflow-hidden">
    <img src="<?= !empty($client['tour_cover_image']) ? '../images/tour_packages_banners/' . htmlspecialchars($client['tour_cover_image']) : '../images/default_trip_cover.jpg' ?>"
         alt="Trip Cover"
         class="object-cover w-full h-full transition-transform duration-300 hover:scale-105" />
    <!-- Gradient Overlay -->
    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
    <!-- Package Badge -->
    <div class="absolute top-4 left-4">
      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white/90 backdrop-blur-sm rounded-full text-xs font-semibold text-sky-700 shadow-lg">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
        </svg>
        Your Package
      </span>
    </div>
    <!-- Package Name -->
    <div class="absolute bottom-4 left-4 right-4">
      <h3 class="text-xl font-bold text-white drop-shadow-lg leading-tight">
        <?= htmlspecialchars($client['package_name'] ?? 'Untitled Tour') ?>
      </h3>

          <!-- Description -->
    <div>
      <p class="text-sm text-white leading-relaxed line-clamp-2">
        <?= nl2br(htmlspecialchars($client['package_description'] ?? '')) ?>
      </p>
    </div>
    </div>
  </div>

  <!-- 📋 Tour Package Details -->
  <?php if (!empty($client['package_name'])): ?>
  <div class="p-6 space-y-5">

    <!-- Route -->
    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-sky-50 to-blue-50 rounded-lg border border-sky-100">
      <div class="flex-shrink-0 w-9 h-9 bg-sky-500 rounded-full flex items-center justify-center shadow-sm">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-xs font-medium text-sky-700 mb-0.5">Travel Route</p>
        <p class="text-sm font-semibold text-slate-800 truncate">
          <?= htmlspecialchars($client['origin'] ?? '—') ?> <span class="text-sky-500 mx-1">→</span> <?= htmlspecialchars($client['destination'] ?? '—') ?>
        </p>
      </div>
    </div>

    <!-- Divider -->
    <div class="border-t border-slate-100"></div>

    <!-- Meta Information -->
    <div class="space-y-4">
      <!-- Booking Number -->
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0 w-5 h-5 text-slate-400 mt-0.5">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
          </svg>
        </div>
        <div class="flex-1">
          <p class="text-xs font-medium text-slate-500 mb-0.5">Booking Number</p>
          <p class="text-sm font-semibold text-slate-800">
            <?= htmlspecialchars($client['booking_number'] ?? '—') ?>
          </p>
        </div>
      </div>

      <!-- Trip Dates -->
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0 w-5 h-5 text-slate-400 mt-0.5">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <div class="flex-1">
          <p class="text-xs font-medium text-slate-500 mb-0.5">Trip Schedule</p>
          <p class="text-sm font-semibold text-slate-800">
            <?= $tripDateRangeDisplay ?>
          </p>
        </div>
      </div>
    </div>

  </div>
  <?php else: ?>
  <!-- 🧭 Enhanced Empty State -->
  <div class="px-6 py-12 text-center flex flex-col items-center justify-center gap-4">
    <div class="w-16 h-16 bg-gradient-to-br from-sky-100 to-blue-100 rounded-2xl flex items-center justify-center shadow-sm">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
      </svg>
    </div>
    <div class="space-y-2">
      <h3 class="text-base font-bold text-slate-800">No Package Assigned</h3>
      <p class="text-sm text-slate-600 max-w-xs mx-auto leading-relaxed">
        Your travel agent will assign a package to your booking soon. You'll see all the details here.
      </p>
    </div>
    <div class="mt-2 px-4 py-2 bg-sky-50 rounded-lg border border-sky-100">
      <p class="text-xs text-sky-700">Contact your agent for assistance</p>
    </div>
  </div>
  <?php endif; ?>
</div>