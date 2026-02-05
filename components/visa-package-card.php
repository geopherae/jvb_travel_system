<?php
require_once __DIR__ . '/../actions/db.php';

if ($conn->connect_error) {
  http_response_code(500);
  echo "⚠️ Database connection failed: " . $conn->connect_error;
  exit();
}

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
if (!$client_id) {
  echo "Client not specified.";
  exit();
}

// 🧭 Fetch client + latest visa application + visa package details
$query = $conn->prepare("
  SELECT 
    c.id, c.visa_application_id,
    cva.id AS app_id, cva.visa_package_id, cva.application_mode, cva.status, cva.group_access_code,
    vp.country, vp.visa_package_name, vp.processing_days, vp.visa_cover_image, vp.requirements_json
  FROM clients c
  LEFT JOIN client_visa_applications cva ON cva.client_id = c.id
  LEFT JOIN visa_packages vp ON cva.visa_package_id = vp.id
  WHERE c.id = ?
  ORDER BY cva.created_at DESC
  LIMIT 1
");
$query->bind_param("i", $client_id);
$query->execute();
$client = $query->get_result()->fetch_assoc();

$hasVisaPackage = !empty($client['visa_package_id']);
$visaApplicationId = $client['app_id'] ?? null;

// 🔑 Fetch group access code from client_visa_applications table
$groupAccessCode = '—';
if ($visaApplicationId) {
  // Get the group access code directly from the visa application
  $groupAccessCode = $client['group_access_code'] ?? '—';
}

// 🖼️ Cover Image
$coverImage = $hasVisaPackage && !empty($client['visa_cover_image'])
  ? '../images/visa_packages_banners/' . rawurlencode($client['visa_cover_image'])
  : '../images/default_visa_cover.jpg';

// 📋 Requirements Progress
$totalRequirements = 0;

if ($hasVisaPackage) {
  // Get total requirements from JSON in visa_packages table
  if (!empty($client['requirements_json'])) {
    $requirements = json_decode($client['requirements_json'], true);
    if (is_array($requirements)) {
      $totalRequirements = count($requirements);
    }
  }
}

// 📊 Application Status
$statusColors = [
  'Awaiting Docs' => 'text-amber-100 border-amber-200/40',
  'Rejected' => 'text-red-100 border-red-200/40',
  'Complete' => 'text-emerald-100 border-emerald-200/40',
];
$appStatus = $client['status'] ?? 'Awaiting Docs';
$statusClass = $statusColors[$appStatus] ?? 'bg-gray-500/90 text-white';
$statusDisplay = htmlspecialchars($appStatus);
?>

<!-- 🛂 Visa Package Card - WOW Factor Design -->
<div class="relative overflow-hidden rounded-xl sm:rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 sm:hover:scale-[1.02] h-full flex flex-col">
  <!-- Background Image -->
  <img 
    src="<?= $coverImage ?>" 
    alt="Visa Package Cover"
    class="absolute inset-0 w-full h-full object-cover"
    loading="lazy"
  />

  <!-- Background overlay with semi-transparent dark gradient -->
  <div class="absolute inset-0 bg-gradient-to-br from-slate-900/85 via-slate-700/80 to-slate-900/85 backdrop-blur-sm"></div>
  
  <!-- Decorative elements -->
  <div class="absolute top-0 right-0 w-16 h-16 sm:w-24 sm:h-24 bg-white/5 rounded-full -mr-8 -mt-8 sm:-mr-12 sm:-mt-12"></div>
  <div class="absolute bottom-0 left-0 w-20 h-20 sm:w-32 sm:h-32 bg-white/5 rounded-full -ml-10 -mb-10 sm:-ml-16 sm:-mb-16"></div>

  <!-- Content -->
  <div class="relative z-10 p-4 sm:p-6 space-y-4 flex-1 flex flex-col">
    <!-- Header: Package Name -->
    <div>
      <p class="text-[0.65rem] font-semibold text-sky-100 uppercase tracking-wider mb-1">Visa Package</p>
      <h3 class="line-clamp-1 pb-2 text-lg sm:text-xl font-bold text-white break-words max-w-[85%] sm:max-w-[80%]">
        <?= $hasVisaPackage ? htmlspecialchars($client['visa_package_name']) : '<span class="italic opacity-80">No Visa Package Assigned</span>' ?>
      </h3>
      <?php if ($hasVisaPackage): ?>
        <p class="text-xs sm:text-sm text-sky-200 font-medium">
          <?= htmlspecialchars($client['country']) ?> • ~<?= intval($client['processing_days']) ?> days processing
        </p>
      <?php endif; ?>
    </div>

    <!-- Dropdown Menu -->
    <div x-data="{ open: false }" class="absolute top-3 right-3 sm:top-4 sm:right-4 z-50" @click.outside="open = false">
      <button 
        @click="open = !open"
        class="p-2 bg-white/90 hover:bg-white active:bg-white rounded-full shadow-lg transition backdrop-blur-sm border border-gray-200 touch-manipulation"
        title="Visa Package Options"
        aria-label="Visa package options"
      >
        <svg class="w-5 h-5 text-gray-700" fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z" />
        </svg>
      </button>

      <div x-show="open" x-transition x-cloak
           class="absolute right-0 mt-2 w-60 sm:w-64 bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden z-50">
        <button type="button"
                class="w-full text-left px-4 sm:px-5 py-3 sm:py-3.5 text-sm font-medium text-gray-800 hover:bg-sky-50 active:bg-sky-100 transition touch-manipulation"
                @click="open = false; $store.modals.reassignVisa = true; $store.modals.clientId = <?= $client_id ?>">
          <?= $hasVisaPackage ? 'Reassign Visa Package' : 'Assign Visa Package' ?>
        </button>

        <div x-data="{ showUnassignTip: false }" class="relative">
          <button type="button"
                  class="w-full text-left px-4 sm:px-5 py-3 sm:py-3.5 text-sm font-medium <?= $hasVisaPackage ? 'text-red-600 hover:bg-red-50 active:bg-red-100' : 'text-gray-400 cursor-not-allowed' ?> transition touch-manipulation"
                  <?= $hasVisaPackage ? '' : 'disabled' ?>
                  @mouseenter="showUnassignTip = <?= $hasVisaPackage ? 'false' : 'true' ?>"
                  @mouseleave="showUnassignTip = false">
            Unassign Visa Package
          </button>
          <div x-show="showUnassignTip" x-cloak x-transition
               class="absolute right-full top-1/2 -translate-y-1/2 mr-3 px-3 py-2 text-xs text-white bg-gray-800 rounded-lg shadow-lg whitespace-nowrap">
            No visa package currently assigned
          </div>
        </div>
      </div>
    </div>

    <!-- Package Details Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 pt-4 border-t border-white/30 mt-auto">
      <!-- Requirements Progress -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd" />
          </svg>
          <span class="text-xs font-medium">Requirements</span>
        </div>
        <?php if ($hasVisaPackage): ?>
          <div class="space-y-1">
            <p class="text-sm sm:text-base font-bold text-white">
              <?= $totalRequirements ?> requirement<?= $totalRequirements !== 1 ? 's' : '' ?>
            </p>
            <p class="text-xs text-sky-200">
              See requirements table below
            </p>
          </div>
        <?php else: ?>
          <p class="text-sm sm:text-base font-bold text-white/50 italic">—</p>
        <?php endif; ?>
      </div>

      <!-- Application Status -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <span class="text-xs font-medium">Status</span>
        </div>
        <?php if ($hasVisaPackage): ?>
          <span class="inline-flex items-center px-3 py-1 rounded-full border bg-white/10 backdrop-blur-md text-xs sm:text-sm font-semibold shadow-sm ring-1 ring-white/10 <?= $statusClass ?>">
            <?= htmlspecialchars($statusDisplay) ?>
          </span>
        <?php else: ?>
          <p class="text-sm sm:text-base font-bold text-white/50 italic">—</p>
        <?php endif; ?>
      </div>

      <!-- Application Mode -->
      <?php if ($hasVisaPackage && !empty($client['application_mode'])): ?>
        <div class="space-y-2 min-w-0">
          <div class="flex items-center gap-2 text-white/80">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
            </svg>
            <span class="text-xs font-medium">Application Mode</span>
          </div>
          <p class="text-sm sm:text-base font-bold text-white capitalize">
            <?= htmlspecialchars($client['application_mode']) ?>
          </p>
        </div>
      <?php endif; ?>

      <!-- Group Access Code -->
      <?php if ($hasVisaPackage && $client['application_mode'] === 'group'): ?>
        <div class="space-y-2 min-w-0">
          <div class="flex items-center gap-2 text-white/80">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
            </svg>
            <span class="text-xs font-medium">Group Access Code</span>
          </div>
          <p class="text-sm sm:text-base font-bold text-white font-mono tracking-wide">
            <?= htmlspecialchars($groupAccessCode) ?>
          </p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
