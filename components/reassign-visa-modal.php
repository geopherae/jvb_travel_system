<?php

date_default_timezone_set('Asia/Manila');
// 📦 Fetch available visa packages
$visaPkgQuery = $conn->query("
  SELECT id, visa_package_name, country, processing_days, visa_cover_image
  FROM visa_packages 
  WHERE is_active = 1
  ORDER BY visa_package_name ASC
");

$visaPackageOptions = [];
while ($vPkg = $visaPkgQuery->fetch_assoc()) {
  $visaPackageOptions[] = $vPkg;
}

$hasVisaPackages = !empty($visaPackageOptions);

// Get current visa package assignment (if any)
$currentVisaPackageId = null;
if (!empty($client['id'])) {
  $visaAppQuery = $conn->prepare("
    SELECT visa_package_id 
    FROM client_visa_applications 
    WHERE client_id = ? 
    LIMIT 1
  ");
  $visaAppQuery->bind_param("i", $client['id']);
  $visaAppQuery->execute();
  $visaAppResult = $visaAppQuery->get_result();
  if ($visaAppRow = $visaAppResult->fetch_assoc()) {
    $currentVisaPackageId = $visaAppRow['visa_package_id'];
  }
  $visaAppQuery->close();
}
?>

<script>
  document.addEventListener('alpine:init', () => {
    const modals = Alpine.store('modals');
    if (!modals) {
      Alpine.store('modals', {
        clientId: null,
        reassignVisa: false
      });
      return;
    }

    if (typeof modals.clientId === 'undefined') modals.clientId = null;
    if (typeof modals.reassignVisa === 'undefined') modals.reassignVisa = false;
  });
</script>

<!-- ✅ Alpine Component Logic -->
<script>
  function reassignVisaModal() {
    return {
      selectedVisaPackageId: <?= json_encode($currentVisaPackageId) ?>,
      selectedVisaPackage: {},
      visaPackageOptions: <?= json_encode($visaPackageOptions) ?>,
      hasVisaPackages: <?= json_encode($hasVisaPackages) ?>,
      currentVisaPackageId: <?= json_encode($currentVisaPackageId) ?>,
      showList: false,

      updateSelected() {
        this.selectedVisaPackage = this.visaPackageOptions.find(p => p.id == this.selectedVisaPackageId) || {};
      },

      isSameAsCurrent() {
        return this.selectedVisaPackageId && this.selectedVisaPackageId == this.currentVisaPackageId;
      },

      getButtonText() {
        if (!this.hasVisaPackages) return 'No Visa Packages Available';
        if (this.isSameAsCurrent()) return 'No Changes to Apply';
        return 'Confirm Reassignment';
      },

      isConfirmDisabled() {
        return !this.hasVisaPackages || this.isSameAsCurrent();
      }
    };
  }
</script>

<!-- ✅ Reassign Visa Package Modal -->
<div 
  x-data="reassignVisaModal()" 
  x-init="updateSelected()"
  x-show="$store.modals.reassignVisa" 
  x-cloak 
  x-transition
  class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
>
  <div class="bg-white rounded-xl shadow-2xl p-6 sm:p-8 max-w-md w-full mx-4 border border-gray-200">
    <h3 class="text-xl font-bold text-sky-800 mb-4" x-text="hasVisaPackages ? 'Reassign Visa Package' : 'Assign Visa Package'"></h3>
    
    <p class="text-sm text-gray-600 mb-6 leading-relaxed">
      <template x-if="hasVisaPackages">
        <span>Select a new visa package below. This will replace the client's current visa application. This applies to <strong>all</strong> applicants in group applications.</span>
      </template>
      <template x-if="!hasVisaPackages">
        <span>No visa packages are available yet. Please create one first.</span>
      </template>
    </p>

    <form action="../actions/reassign_visa_package.php" method="POST" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
      <input type="hidden" name="client_id" :value="$store.modals.clientId">
      <input type="hidden" name="visa_package_id" :value="selectedVisaPackageId">
      <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/jvb_travel_system/admin/view_client_visa.php') ?>">

      <!-- 🔽 Visa Package Dropdown -->
      <div class="space-y-2">
        <label class="block text-sm font-semibold text-gray-700">Choose Visa Package:</label>

        <template x-if="hasVisaPackages">
          <div class="relative">
            <button 
              type="button" 
              @click="showList = !showList"
              class="w-full px-4 py-3 text-left bg-white border rounded-lg shadow-sm flex justify-between items-center focus:outline-none focus:ring-2 focus:ring-sky-500 transition
                     <?= $currentVisaPackageId ? 'border-sky-400' : 'border-gray-300' ?>"
            >
              <span class="font-medium text-slate-800" x-text="selectedVisaPackage.visa_package_name || 'Select a visa package...'"></span>
              <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Dropdown List -->
            <div 
              x-show="showList" 
              @click.outside="showList = false"
              x-transition
              x-cloak
              class="absolute z-10 mt-2 w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-64 overflow-y-auto"
            >
              <template x-for="vPkg in visaPackageOptions" :key="vPkg.id">
                <button
                  type="button"
                  @click="selectedVisaPackageId = vPkg.id; updateSelected(); showList = false"
                  :class="{
                    'bg-sky-50 text-sky-800 font-semibold': vPkg.id == selectedVisaPackageId,
                    'text-gray-400 cursor-not-allowed opacity-60': vPkg.id == currentVisaPackageId && vPkg.id != selectedVisaPackageId,
                    'hover:bg-sky-100': vPkg.id != currentVisaPackageId
                  }"
                  class="w-full text-left px-4 py-3 transition flex justify-between items-center"
                  :disabled="vPkg.id == currentVisaPackageId"
                  :title="vPkg.id == currentVisaPackageId ? 'Currently assigned' : ''"
                >
                  <span x-text="vPkg.visa_package_name"></span>
                </button>
              </template>
            </div>
          </div>
        </template>

        <!-- Empty State -->
        <template x-if="!hasVisaPackages">
          <div class="p-6 text-center bg-sky-50 border-2 border-dashed border-sky-300 rounded-lg">
            <p class="text-sky-700 font-medium">No visa packages available</p>
            <p class="text-xs text-sky-600 mt-2">Add visa packages in the <strong>Visa Packages</strong> section first.</p>
          </div>
        </template>
      </div>

      <!-- 🛂 Selected Visa Package Preview -->
      <template x-if="selectedVisaPackage.visa_package_name">
        <div class="bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 rounded-lg p-4 space-y-2">
          <div class="flex items-start justify-between">
            <div>
              <p class="font-semibold text-emerald-900" x-text="selectedVisaPackage.visa_package_name"></p>
              <p class="text-xs text-emerald-700 mt-1" x-text="'🌍 ' + selectedVisaPackage.country"></p>
            </div>
            <span class="inline-block bg-emerald-100 text-emerald-700 text-xs font-semibold px-2.5 py-1 rounded-full" x-text="selectedVisaPackage.processing_days + ' days'"></span>
          </div>
          <template x-if="isSameAsCurrent()">
            <p class="text-xs italic text-amber-700 mt-3">⚠ This is the client's current visa package — no changes will be made.</p>
          </template>
        </div>
      </template>

      <!-- 🆗 Action Buttons -->
      <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
        <button 
          type="button"
          @click="$store.modals.reassignVisa = false"
          class="px-5 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800 transition"
        >
          Cancel
        </button>

        <button 
          type="submit"
          x-text="getButtonText()"
          :disabled="isConfirmDisabled()"
          class="px-6 py-2.5 text-sm font-semibold rounded-lg shadow transition"
          :class="{
            'bg-sky-600 text-white hover:bg-sky-700 cursor-pointer': hasVisaPackages && !isSameAsCurrent(),
            'bg-gray-300 text-gray-500 cursor-not-allowed': isConfirmDisabled()
          }"
        >
          Confirm Reassignment
        </button>
      </div>
    </form>
  </div>
</div>
