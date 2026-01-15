<?php
if (!isset($conn) || !isset($client_id)) {
  echo "<p class='text-red-500 text-center text-sm'>Client context missing.</p>";
  return;
}
?>

<!-- ✅ Unassign Confirmation Modal -->
<div x-show="$store.modals.unassign" x-cloak
     class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4 backdrop-blur-sm"
     x-data="{ clientId: <?= $editClientId ?> }"
     @click.outside="$store.modals.unassign = false">
  <div class="bg-white rounded-xl shadow-xl border border-gray-200 p-6 max-w-md w-full space-y-5"
      >

    <div class="flex items-center gap-3">
      <h3 class="text-lg font-semibold text-gray-800">Unassign Package</h3>
    </div>

    <p class="text-sm text-gray-600 leading-relaxed">
      Unassigning this tour package will <strong>permanently remove the client's current itinerary</strong> and reset all booking-related fields. This includes:
    </p>
    <ul class="list-disc pl-5 text-sm text-gray-600 space-y-1">
      <li>Clearing travel dates (departure and return)</li>
      <li>Removing booking confirmation status</li>
      <li>Detaching the assigned tour package</li>
      <li>Deleting any saved itinerary linked to this package</li>
      <li>Updating the client's status to <strong>"No Assigned Package"</strong></li>
    </ul>
    <p class="text-sm text-gray-600">
      This action is irreversible and will return the client to an unassigned state. You may reassign a new package afterward, but all previous booking data will be lost.
    </p>

    <form action="../actions/unassign_package.php" method="POST" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
      <input type="hidden" name="client_id" :value="clientId">

      <div class="flex justify-end gap-3 pt-2">
        <button type="button"
                @click="$store.modals.unassign = false"
                class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 hover:underline transition">
          Cancel
        </button>
        <button type="submit"
                class="px-4 py-2 text-sm bg-red-500 text-white rounded-md hover:bg-red-900 transition"
                :disabled="!clientId">
          Confirm Unassign
        </button>
      </div>
    </form>
  </div>
</div>