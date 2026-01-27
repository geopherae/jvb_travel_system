<?php
// Auth check
require_once __DIR__ . '../admin_session_check.php';
require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../includes/feature_flags.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../actions/db.php';

date_default_timezone_set('Asia/Manila');

$visaPackages = [];
$decodeJson = function ($json) {
    $decoded = json_decode($json ?? '[]', true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
};

try {
    $stmt = $conn->prepare("SELECT id, visa_cover_image, country, processing_days, visa_package_description, inclusions_json, requirements_json, visa_types_json, is_active, created_at, updated_at FROM visa_packages WHERE is_active <> 0 ORDER BY country ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $inclusions    = $decodeJson($row['inclusions_json'] ?? '[]');
        $requirements  = $decodeJson($row['requirements_json'] ?? '[]');
        $visaTypes     = $decodeJson($row['visa_types_json'] ?? '[]');

        $visaPackages[] = [
          'id'                => (int) ($row['id'] ?? 0),
          'visa_cover_image'  => $row['visa_cover_image'] ?? '',
          'country'           => $row['country'] ?? 'Unknown Country',
          'processing_days'   => (int) ($row['processing_days'] ?? 0),
          'description'       => $row['visa_package_description'] ?? 'No description available.',
          'inclusion_count'   => is_array($inclusions) ? count($inclusions) : 0,
          'requirement_count' => is_array($requirements) ? count($requirements) : 0,
          'visa_type_count'   => is_array($visaTypes) ? count($visaTypes) : 0,
          'updated_at'        => $row['updated_at'] ?? null,
          'created_at'        => $row['created_at'] ?? null,
          'is_active'         => (int) ($row['is_active'] ?? 0),
        ];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log('Error fetching visa packages: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en" class="bg-gray-50">
<head>
  <style>[x-cloak] { display: none !important; }</style>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Visa Packages</title>
  <?php include __DIR__ . '/../components/favicon_links.php'; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <script src="../includes/admin-dashboard.js"></script>
</head>

<body class="font-poppins text-gray-800 overflow-hidden"
      x-data="{ sidebarOpen: false }"
      style="background: linear-gradient(to bottom, #e0f7ff 0%, white 10%, white 100%);">

  <!-- Mobile Toggle -->
  <button @click="sidebarOpen = !sidebarOpen"
          class="p-3 md:hidden absolute top-4 left-4 z-30 bg-primary text-white rounded">
    Menu
  </button>

  <!-- Sidebar -->
  <?php include '../components/admin_sidebar.php'; ?>

  <!-- Right Panel -->
  <?php include '../components/right-panel.php'; ?>

  <!-- Main Content -->
    <main class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-6 relative z-0">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Visa Packages</h1>
        <p class="text-gray-600 text-sm mt-1">Listing all active visa packages.</p>
      </div>
      <div class="flex flex-wrap gap-2 items-center">
        <a href="#"
           class="disabled inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition">
          Add New Visa Package
        </a>
      </div>
    </div>

            <!-- 🔍 Search & Filter Section -->
        <div class="flex flex-col lg:flex-row gap-4 lg:items-end">
          <!-- Search by Package Name -->
          <div class="flex-1 w-full">
            <label class="block text-sm font-medium text-slate-600 mb-2">Search Package Name</label>
            <input 
              type="text" 
              x-model="searchName"
              placeholder="e.g., Bali Paradise..."
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 text-sm"
            />
          </div>

          <!-- Filter by Destination -->
          <div class="flex-1 w-full">
            <label class="block text-sm font-medium text-slate-600 mb-2">Filter by Destination</label>
            <select 
              x-model="filterDestination"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 text-sm"
            >
              <option value="">All Destinations</option>
              <template x-for="country in Object.keys(window.AIRPORTS)" :key="country">
                <optgroup :label="country">
                  <template x-for="(airportName, code) in window.AIRPORTS[country]" :key="code">
                    <option :value="code" x-text="code + ' - ' + airportName"></option>
                  </template>
                </optgroup>
              </template>
            </select>
          </div>

          <!-- Clear Filters Button -->
          <button 
            @click="searchName = ''; filterDestination = ''"
            class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 transition text-sm font-medium w-full lg:w-auto"
          >
            Clear
          </button>
        </div>

    <?php if (!defined('VISA_PROCESSING_ENABLED') || VISA_PROCESSING_ENABLED !== true): ?>
      <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-4">
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86A2.07 2.07 0 0021 16.93L12 4 3 16.93A2.07 2.07 0 005.07 19z" />
          </svg>
          <p class="text-sm font-medium">Visa processing is disabled via feature flag. Enable it to view packages.</p>
        </div>
      </div>
    <?php elseif (empty($visaPackages)): ?>
      <div class="bg-white border border-gray-200 rounded-lg p-8 flex flex-col items-center justify-center text-center shadow-sm">
        <div class="w-12 h-12 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center mb-3">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M4.93 19h14.14A2.07 2.07 0 0021 16.93L12 4 3 16.93A2.07 2.07 0 004.93 19z" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">No Active Visa Packages</h3>
        <p class="text-sm text-gray-600 mt-1">Add a visa package and mark it active to have it appear here.</p>
      </div>
    <?php else: ?>
      <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden divide-y divide-gray-100">
        <?php foreach ($visaPackages as $visaPackage): ?>
          <?php
            $coverFile = trim($visaPackage['visa_cover_image'] ?? '');
            $coverUrl  = $coverFile !== ''
              ? '../images/visa_packages_banners/' . ltrim($coverFile, '/\\')
              : '';
          ?>
          <div class="flex items-start gap-4 p-4 hover:bg-sky-50 transition">
            <div class="w-16 h-16 rounded-lg bg-slate-100 overflow-hidden flex items-center justify-center border border-gray-100">
              <?php if ($coverUrl !== ''): ?>
                <img src="<?= htmlspecialchars($coverUrl) ?>"
                     alt="Cover for <?= htmlspecialchars($visaPackage['country']) ?>"
                     class="w-full h-full object-cover" />
              <?php else: ?>
                <svg class="w-8 h-8 text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              <?php endif; ?>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-xs text-slate-500">ID #<?= $visaPackage['id'] ?></div>
                  <h3 class="text-base font-semibold text-sky-900 truncate"><?= htmlspecialchars($visaPackage['country']) ?></h3>
                  <p class="text-sm text-slate-600 line-clamp-2 mt-1">
                    <?= htmlspecialchars($visaPackage['description']) ?>
                  </p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700">
                  <?= ((int)($visaPackage['is_active'] ?? 0)) ? 'Active' : 'Inactive' ?>
                </span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

</body>
</html>
