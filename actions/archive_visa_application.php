<?php
if (!isset($conn)) {
  echo "<p class='text-red-500 text-center text-sm'>Database connection missing.</p>";
  return;
}

// Get application ID from context (should be set by parent page)
$applicationId = $applicationId ?? 0;
?>

<!-- ✅ Archive Visa Application Confirmation Modal -->
<div x-show="$store.modals.archiveVisaApplication" x-cloak
     class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4 backdrop-blur-sm"
     x-data="{ applicationId: <?= $applicationId ?> }"
     @click.outside="$store.modals.archiveVisaApplication = false"
     @keydown.escape.window="$store.modals.archiveVisaApplication = false">
  <div class="bg-white rounded-xl shadow-xl border border-gray-200 p-6 max-w-md w-full space-y-5">

    <div class="flex items-center gap-3">
      <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
        </svg>
      </div>
      <h3 class="text-lg font-semibold text-red-800">Archive Visa Application</h3>
    </div>

    <p class="text-sm text-gray-600 leading-relaxed">
      Archiving this visa application will <strong>remove it from active workflows and dashboards</strong>, but all application data will remain securely stored for audit and recovery purposes.
    </p>

    <ul class="list-disc pl-5 text-sm text-gray-600 space-y-1">
      <li>The application will no longer appear in active visa processing lists</li>
      <li>All client data, companions, and document submissions will be preserved</li>
      <li>The application status will be marked as <strong>"Archived"</strong></li>
      <li>Audit logs will record this action for transparency and compliance</li>
    </ul>

    <p class="text-sm text-gray-600">
      This action is <strong>reversible</strong>. You may restore the application later from the archived applications list if needed. Archiving is recommended for completed applications, withdrawn requests, or inactive cases.
    </p>

    <form action="../actions/archive_visa_application.php" method="POST" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
      <input type="hidden" name="application_id" :value="applicationId">

      <div class="flex justify-end gap-3 pt-2">
        <button type="button"
                @click="$store.modals.archiveVisaApplication = false"
                class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 hover:underline transition">
          Cancel
        </button>
        <button type="submit"
                class="px-4 py-2 text-sm bg-red-500 text-white rounded-md hover:bg-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="!applicationId">
          Confirm Archive Application
        </button>
      </div>
    </form>
  </div>
</div>