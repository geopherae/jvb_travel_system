<?php
if (!isset($conn) || !isset($client_id)) {
  echo "<p class='text-red-500 text-center text-sm'>Client context missing.</p>";
  return;
}
?>

<!-- ✅ Archive Confirmation Modal -->
<div x-show="$store.modals.archiveClient" x-cloak
     class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4 backdrop-blur-sm"
     x-data="{ clientId: <?= $editClientId ?> }"
     @click.outside="$store.modals.archiveClient = false">
  <div class="bg-white rounded-xl shadow-xl border border-gray-200 p-6 max-w-md w-full space-y-5"
      >

    <div class="flex items-center gap-3">
      <h3 class="text-lg font-semibold text-red-800">Archive Client</h3>
    </div>

    <p class="text-sm text-gray-600 leading-relaxed">
    Archiving this client will <strong>remove them from active dashboards and workflows</strong>, but their data will remain securely stored for audit and recovery purposes.
    </p>
    <ul class="list-disc pl-5 text-sm text-gray-600 space-y-1">
    <li>The client will no longer appear in active trip or onboarding lists</li>
    <li>All booking and document data will be preserved in the system</li>
    <li>Their status will be updated to <strong>"Archived"</strong></li>
    <li>Audit logs will reflect this action for transparency</li>
    </ul>
    <p class="text-sm text-gray-600">
    This action is reversible. You may restore the client later if needed. Archiving is recommended for completed trips, inactive leads, or withdrawn clients.
    </p>

    <form action="../actions/archive_client.php" method="POST" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
      <input type="hidden" name="client_id" :value="clientId">

      <div class="flex justify-end gap-3 pt-2">
        <button type="button"
                @click="$store.modals.archiveClient = false"
                class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 hover:underline transition">
          Cancel
        </button>
        <button type="submit"
                class="px-4 py-2 text-sm bg-red-500 text-white rounded-md hover:bg-red-700 transition"
                :disabled="!clientId">
          Confirm Archive Client
        </button>
      </div>
    </form>
  </div>
</div>