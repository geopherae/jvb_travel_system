<template x-if="$store.deleteTourModal.isOpen">
  <div
    x-data
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4 backdrop-blur-sm"
  >
    <div
      class="bg-white rounded-xl shadow-xl border border-gray-200 p-6 max-w-md w-full space-y-5"
    >
      <div class="flex items-center gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4 4h16v16H4V4z" />
        </svg>
        <h3 class="text-lg font-semibold text-gray-800">Archive Tour Package</h3>
      </div>

      <p class="text-sm text-gray-600 leading-relaxed">
        This will archive the selected tour package. Assigned clients will remain linked, but the package will be hidden from active listings.
      </p>

      <p class="text-sm text-yellow-600 font-medium">
        You can restore this package later if needed. No client data will be lost.
      </p>

      <form action="../actions/delete_tour_package.php" method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <input type="hidden" name="package_id" x-model="$store.deleteTourModal.tourId">

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            @click="$store.deleteTourModal.close(); console.log('[deleteTourModal] Cancelled')"
            class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 hover:underline transition"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-4 py-2 text-sm bg-yellow-700 text-white rounded-md hover:bg-yellow-600 transition"
          >
            Confirm Archive
          </button>
        </div>
      </form>
    </div>
  </div>
</template>