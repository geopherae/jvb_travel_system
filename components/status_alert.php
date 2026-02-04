<?php
$status = $_SESSION['modal_status'] ?? $_GET['status'] ?? null;
$message = '';
$type = 'toast'; // default fallback
$toastLevel = 'success';

// 🧭 Message & Type Map
$statusMap = [
  'add_client_success'   => ['Client added successfully.', 'toast'],
  'edit_client_success'  => ['Client updated successfully.', 'toast'],
  'edit_client_failed'   => ['Failed to update client details. Please try again.', 'toast', 'error'],
  'created'              => ['New tour package added.', 'toast'],
  'success'              => ['Package updated successfully.', 'toast'],
  'reassigned'           => ['Package reassigned successfully.', 'toast'],
  'reassign_failed'      => ['Failed to reassign package. Please try again or contact support.', 'toast', 'error'],
  'unassigned'           => ['Package unassigned. Itinerary removed.', 'toast'],
  'deleted'              => ['Tour package deleted successfully.', 'toast'],
  'delete_failed'        => ['Failed to delete the tour package.', 'toast', 'error'],
  'invalid_id'           => ['Invalid tour package ID.', 'toast', 'error'],
  'db_error'             => ['Database connection failed.', 'toast', 'error'],
  'error'                => ['Something went wrong. Please check your inputs.', 'toast', 'error'],
  'invalid_file'         => ['Only JPG, JPEG, PNG, or WebP files are allowed.', 'toast', 'error'],
  'too_large'            => ['Image must be under 5MB.', 'toast', 'error'],
  'updated'              => ['Tour package updated.', 'toast'],
  'partial_success'      => ['Package saved, but itinerary failed.', 'toast', 'error'],
  'invalid_airport'      => ['Invalid airport code selected.', 'toast', 'error'],
  'add_admin_success'    => ['New admin user created successfully.', 'toast'],
  'add_admin_failed'     => ['Failed to create admin user. Please check required fields or try again.', 'toast', 'error'],
  'admin_update_success' => ['Admin profile updated successfully.', 'toast'],
  'admin_update_failed'  => ['Failed to update admin profile. Please try again or check your inputs.', 'toast', 'error'],
  'duplicate_email'      => ['Email address already exists.', 'toast', 'error'],
  'client_status_updated' => ['Client statuses updated successfully.', 'toast'],
  'no_status_changes'     => ['No client status changes detected.', 'toast'],
  'status_check_failed'   => ['Status check failed. Please try again.', 'toast', 'error'],
  'package_soft_deleted'  => ['Package is no longer visible in active listings.', 'toast', 'error'],
  'survey_submitted'      => ['Survey submitted successfully. Thank you for your feedback!', 'toast'],
  'survey_skipped'       => ['Survey skipped. You can submit feedback later from your profile.', 'toast'],
  'survey_failed'        => ['Failed to submit survey. Please try again or contact support.', 'toast', 'error'],
  'survey_invalid'       => ['Invalid survey data. Please check your inputs and try again.', 'toast', 'error'],
  'survey_already_completed' => ['Survey already completed. No further action needed.', 'toast'],
  'gallery_refresh_failed' => ['Photo uploaded, but gallery failed to refresh.', 'toast', 'error'],
  'upload_success' => ['Document uploaded successfully.', 'toast', 'success'],
  'upload_failed'  => ['Failed to upload document. Please try again or contact support.', 'toast', 'error'],
  'document_deleted' => ['Document deleted successfully.', 'toast'],
  'document_delete_failed' => ['Failed to delete the document.', 'toast', 'error'],
  'review_success' => ['Review submitted successfully.', 'toast'],
  'review_failed' => ['Failed to submit review.', 'toast', 'error'],
  'review_public' => ['Review is now public and visible on homepage.', 'toast'],
  'review_hidden' => ['Review is now hidden from homepage.', 'toast'],
  'review_toggle_failed' => ['Failed to update review visibility. Please try again.', 'toast', 'error'],
  'review_deleted' => ['Review deleted successfully.', 'toast'],
  'review_delete_failed' => ['Failed to delete review. Please try again.', 'toast', 'error'],
];

// 🔍 Resolve Status
if (isset($statusMap[$status])) {
  [$message, $typeOverride, $levelOverride] = array_pad($statusMap[$status], 3, null);
  $type = $typeOverride ?? $type;
  $toastLevel = $levelOverride ?? $toastLevel;
}
?>

<?php if ($type === 'modal' && $message): ?>
  <!-- 🍞 Modal Toast Style -->
  <div
    x-data="{ show: true }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4"
    x-init="setTimeout(() => show = false, 3000)"
    class="fixed bottom-4 right-4 z-50 bg-white border border-slate-200 shadow-lg rounded-lg px-4 py-3 max-w-sm w-full"
  >
    <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($message) ?></p>
  </div>
<?php endif; ?>

<?php if ($type === 'toast' && $message): ?>
  <!-- 🍞 Toast Notification -->
  <div
    x-data="{ show: true }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4"
    x-init="setTimeout(() => show = false, 3000)"
        class="fixed bottom-4 right-4 z-50 px-4 py-3 max-w-sm w-full rounded-lg shadow-lg
          <?= $toastLevel === 'error' ? 'bg-red-50 border border-red-300 text-red-800' : 'bg-emerald-50 border border-emerald-300 text-emerald-800' ?>"
  >
    <p class="text-sm font-medium"><?= htmlspecialchars($message) ?></p>
  </div>
<?php endif; ?>

<?php unset($_SESSION['modal_status']); ?>

<!-- 🍞 Client-Side Toast Listener -->
<script>
  // Define toast messages and levels
  function getToastMessage(status) {
    return {
      photo_uploaded: "Photo uploaded successfully.",
      photo_upload_failed: "Failed to upload photo. Please try again.",
      gallery_refresh_failed: "Photo uploaded, but gallery failed to refresh.",
      photo_deleted: "Photo deleted successfully.",
      photo_delete_failed: "Failed to delete photo. Please try again.",
      review_success: "Review submitted successfully.",
      review_failed: "Failed to submit review.",
      review_public: "Review is now public and visible on homepage.",
      review_hidden: "Review is now hidden from homepage.",
      review_toggle_failed: "Failed to update review visibility. Please try again.",
      review_deleted: "Review deleted successfully.",
      review_delete_failed: "Failed to delete review. Please try again.",
      visa_document_approved: "Document approved successfully.",
      visa_document_rejected: "Document rejected.",
      visa_document_updated: "Document status updated successfully."
    }[status] || "Action completed.";
  }

  function getToastLevel(status) {
    return {
      photo_uploaded: "success",
      photo_upload_failed: "error",
      gallery_refresh_failed: "error",
      photo_deleted: "success",
      photo_delete_failed: "error",
      review_success: "success",
      review_failed: "error",
      review_public: "success",
      review_hidden: "success",
      review_toggle_failed: "error",
      review_deleted: "success",
      review_delete_failed: "error",
      visa_document_approved: "success",
      visa_document_rejected: "success",
      visa_document_updated: "success"
    }[status] || "success";
  }

  // Listen for custom toast events
  window.addEventListener('toast', (event) => {
    const { status } = event.detail;
    const message = getToastMessage(status);
    const level = getToastLevel(status);

    if (typeof window.showToast === 'function') {
      window.showToast(message, level);
      return;
    }

    // Create toast element (fallback)
    const toast = document.createElement('div');
    toast.setAttribute('x-data', '{ show: true }');
    toast.setAttribute('x-show', 'show');
    toast.setAttribute('x-transition:enter', 'transition ease-out duration-300');
    toast.setAttribute('x-transition:enter-start', 'opacity-0 translate-y-4');
    toast.setAttribute('x-transition:enter-end', 'opacity-100 translate-y-0');
    toast.setAttribute('x-transition:leave', 'transition ease-in duration-300');
    toast.setAttribute('x-transition:leave-start', 'opacity-100 translate-y-0');
    toast.setAttribute('x-transition:leave-end', 'opacity-0 translate-y-4');
    toast.setAttribute('x-init', 'setTimeout(() => show = false, 3000)');
    toast.className = `fixed bottom-4 right-4 z-50 px-4 py-3 max-w-sm w-full rounded-lg shadow-lg ${
      level === 'error' ? 'bg-red-50 border border-red-300 text-red-800' :
      'bg-emerald-50 border border-emerald-300 text-emerald-800'
    }`;

    const messageEl = document.createElement('p');
    messageEl.className = 'text-sm font-medium';
    messageEl.textContent = message;
    toast.appendChild(messageEl);

    document.body.appendChild(toast);

    // Ensure Alpine.js processes the new element
    Alpine.nextTick(() => {
      Alpine.initTree(toast);
    });
  });
</script>