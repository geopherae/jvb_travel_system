<?php
// 📦 Fetch available packages
$pkgQuery = $conn->query("
  SELECT id, package_name, day_duration, night_duration, price 
  FROM tour_packages 
  ORDER BY package_name ASC
");

$packageOptions = [];
while ($pkg = $pkgQuery->fetch_assoc()) {
  $packageOptions[] = $pkg;
}

$hasPackages = !empty($packageOptions);
$currentPackageId = $client['assigned_package_id'] ?? null;
?>

<!-- ✅ Alpine Component Logic -->
<script>
  function reassignModal() {
    return {
      selectedPackageId: <?= json_encode($currentPackageId) ?>,
      selectedPackage: {},
      packageOptions: <?= json_encode($packageOptions) ?>,
      hasPackages: <?= json_encode($hasPackages) ?>,
      currentPackageId: <?= json_encode($currentPackageId) ?>,
      showList: false,

      updateSelected() {
        this.selectedPackage = this.packageOptions.find(p => p.id == this.selectedPackageId) || {};
      },

      isSameAsCurrent() {
        return this.selectedPackageId && this.selectedPackageId == this.currentPackageId;
      },

      getButtonText() {
        if (!this.hasPackages) return 'No Packages Available';
        if (this.isSameAsCurrent()) return 'No Changes to Apply';
        return 'Confirm Reassignment';
      },

      isConfirmDisabled() {
        return !this.hasPackages || this.isSameAsCurrent();
      }
    };
  }
</script>

<!-- ✅ Reassign Package Modal -->
<div 
  x-data="reassignModal()" 
  x-init="updateSelected()"
  x-show="$store.modals.reassign" 
  x-cloak 
  x-transition
  class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm"
>
  <div class="bg-white rounded-xl shadow-2xl p-6 sm:p-8 max-w-md w-full mx-4 border border-gray-200">
    <h3 class="text-xl font-bold text-sky-800 mb-4" x-text="hasPackages ? 'Reassign Tour Package' : 'Assign Tour Package'"></h3>
    
    <p class="text-sm text-gray-600 mb-6 leading-relaxed">
      <template x-if="hasPackages">
        <span>Select a new package below. This will replace the client's current itinerary.</span>
      </template>
      <template x-if="!hasPackages">
        <span>No tour packages are available yet. Please create one first.</span>
      </template>
    </p>

    <form action="../actions/reassign_package.php" method="POST" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
      <input type="hidden" name="client_id" :value="$store.modals.clientId">
      <input type="hidden" name="package_id" :value="selectedPackageId">

      <!-- 🔽 Package Dropdown -->
      <div class="space-y-2">
        <label class="block text-sm font-semibold text-gray-700">Choose Package:</label>

        <template x-if="hasPackages">
          <div class="relative">
            <button 
              type="button" 
              @click="showList = !showList"
              class="w-full px-4 py-3 text-left bg-white border rounded-lg shadow-sm flex justify-between items-center focus:outline-none focus:ring-2 focus:ring-sky-500 transition
                     <?= $currentPackageId ? 'border-sky-400' : 'border-gray-300' ?>"
            >
              <span class="font-medium text-slate-800" x-text="selectedPackage.package_name || 'Select a package...'"></span>
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
              <template x-for="pkg in packageOptions" :key="pkg.id">
                <button
                  type="button"
                  @click="selectedPackageId = pkg.id; updateSelected(); showList = false"
                  :class="{
                    'bg-sky-50 text-sky-800 font-semibold': pkg.id == selectedPackageId,
                    'text-gray-400 cursor-not-allowed opacity-60': pkg.id == currentPackageId && pkg.id != selectedPackageId,
                    'hover:bg-sky-100': pkg.id != currentPackageId
                  }"
                  class="w-full text-left px-4 py-3 transition flex justify-between items-center"
                  :disabled="pkg.id == currentPackageId"
                  :title="pkg.id == currentPackageId ? 'Currently assigned' : ''"
                >
                  <span x-text="pkg.package_name"></span>
                  <span class="text-xs text-gray-500" x-text="`${pkg.day_duration}D/${pkg.night_duration}N • ₱${Number(pkg.price).toLocaleString()}`"></span>
                </button>
              </template>
            </div>
          </div>
        </template>

        <!-- Empty State -->
        <template x-if="!hasPackages">
          <div class="p-6 text-center bg-sky-50 border-2 border-dashed border-sky-300 rounded-lg">
            <p class="text-sky-700 font-medium">No tour packages available</p>
            <p class="text-xs text-sky-600 mt-2">Add packages in the <strong>Tour Packages</strong> section first.</p>
          </div>
        </template>
      </div>

      <!-- 📦 Selected Package Preview -->
      <template x-if="selectedPackage.package_name">
        <div class="bg-gradient-to-r from-sky-50 to-blue-50 border border-sky-200 rounded-lg p-4 space-y-2">
          <p class="font-semibold text-sky-900" x-text="selectedPackage.package_name"></p>
          <div class="text-sm text-sky-700 space-y-1">
            <p><strong>Duration:</strong> <span x-text="`${selectedPackage.day_duration} Days / ${selectedPackage.night_duration} Nights`"></span></p>
            <p><strong>Price:</strong> ₱<span x-text="Number(selectedPackage.price).toLocaleString('en-US', {minimumFractionDigits: 2})"></span></p>
          </div>
          <template x-if="isSameAsCurrent()">
            <p class="text-xs italic text-amber-700 mt-3">⚠ This is the client's current package — no changes will be made.</p>
          </template>
        </div>
      </template>

      <!-- 🆗 Action Buttons -->
      <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
        <button 
          type="button"
          @click="$store.modals.reassign = false"
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
            'bg-sky-600 text-white hover:bg-sky-700 cursor-pointer': hasPackages && !isSameAsCurrent(),
            'bg-gray-300 text-gray-500 cursor-not-allowed': isConfirmDisabled()
          }"
        >
          Confirm Reassignment
        </button>
      </div>
    </form>
  </div>
</div>