<?php
// Visa Package Card Component
// Expects normalized keys from visa_packages array:
// - id, country, processing_days, description, visa_cover_image

$packageId      = (int) ($visaPackage['id'] ?? 0);
$country        = $visaPackage['country'] ?? 'Unknown Country';
$processingDays = (int) ($visaPackage['processing_days'] ?? 0);
$description    = $visaPackage['description'] ?? 'No description available.';
$coverImage     = $visaPackage['visa_cover_image'] ?? '';

// Build image path - use absolute path from web root
$imageUrl = '../images/visa_packages_banners/' . urlencode($coverImage);
$imagePath = __DIR__ . '/../images/visa_packages_banners/' . basename($coverImage);
$hasImage = !empty($coverImage) && file_exists($imagePath);
?>

<div 
  x-data="visaPackageRowData(<?= $packageId ?>)"
  class="relative h-full"
>
  <div
    @click="$store.visaModal.openModal(packageId)"
    class="cursor-pointer group bg-white border border-gray-100 rounded-lg shadow-sm overflow-hidden transition hover:ring-2 hover:ring-sky-200 hover:ring-offset-1 hover:shadow-md hover:scale-[1.01] transform duration-200 h-full flex flex-col"
  >
    <!-- Image Section -->
    <div class="w-full h-40 bg-gradient-to-br from-sky-100 to-sky-50 flex items-center justify-center border-b border-gray-100 overflow-hidden">
      <?php if ($hasImage): ?>
        <img 
          src="<?= htmlspecialchars($imageUrl) ?>" 
          alt="<?= htmlspecialchars($country) ?> visa package"
          class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
          loading="lazy"
        />
      <?php else: ?>
        <svg class="w-16 h-16 text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      <?php endif; ?>
    </div>

    <!-- Content Section -->
    <div class="p-4 flex-1 flex flex-col">
      <h3 class="text-lg font-bold text-sky-900 mb-1"><?= htmlspecialchars($country) ?></h3>
      <p class="text-sm text-slate-600 line-clamp-2 flex-1">
        <?= htmlspecialchars($description) ?>
      </p>

      <!-- Country + Processing Days Badges -->
      <div class="flex flex-wrap items-center gap-2 mt-3">
        <span class="inline-block bg-sky-100 text-sky-700 text-xs font-semibold px-3 py-1 rounded-full">
          <?= htmlspecialchars($country) ?>
        </span>
        <span class="inline-block bg-emerald-100 text-emerald-700 text-xs font-semibold px-3 py-1 rounded-full">
          ~<?= max($processingDays, 0) ?> days
        </span>
      </div>
    </div>
  </div>
</div>
