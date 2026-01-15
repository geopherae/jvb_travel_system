<?php
// review_modal.php - Modal for client to leave a review
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../components/status_alert.php';

$clientId = $clientId ?? $_SESSION['client_id'] ?? $_SESSION['client']['id'] ?? null;
$packageName = 'Unknown Package';
$assignedPackageId = null;
$debugInfo = [];

if ($clientId) {
    $stmt = $conn->prepare("
        SELECT c.assigned_package_id, tp.package_name
        FROM clients c
        JOIN tour_packages tp ON c.assigned_package_id = tp.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $assignedPackageId = $row['assigned_package_id'];
        $packageName = $row['package_name'];
        $debugInfo = $row;
    } else {
        $debugInfo = ['error' => "No row returned for client_id={$clientId}"];
    }
    $stmt->close();
} else {
    $debugInfo = ['error' => 'clientId is null'];
}
?>

<div x-show="$store.reviewModal.show" x-cloak
     class="fixed inset-0 z-10 bg-black bg-opacity-50 flex items-center justify-center backdrop-blur-sm"
     x-transition
     role="dialog" aria-modal="true"
     @keydown.escape.window="$store.reviewModal.show = false"
     @click.outside="$store.reviewModal.show = false">

  <div class="max-w-md w-full bg-white p-6 rounded-lg shadow-xl max-h-[90vh] overflow-y-auto relative"
       tabindex="-1"
       x-init="$el.focus()"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-90"
       x-data="{ rating: 0 }">

    <!-- Close Button -->
    <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 text-xl"
            @click="$store.reviewModal.show = false">&times;</button>

    <!-- Modal Header -->
    <div class="mb-6">
      <h2 class="text-xl font-semibold text-gray-800">Leave a Review</h2>
      <p class="text-sm text-gray-600 mt-1">Share your experience with us!</p>
    </div>

    <!-- Review Form -->
    <form action="../actions/process_client_review.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="client_id" value="<?= $clientId ?>">
      <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">

      <!-- Tour Package (Non-editable) -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Tour Package</label>
        <input type="text" value="<?= htmlspecialchars($packageName) ?>" readonly
               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 text-gray-500">
      </div>

      <!-- Star Rating -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Rating</label>
        <div class="flex space-x-1 mt-1">
          <template x-for="star in 5" :key="star">
            <button type="button" @click="rating = star"
                    class="text-2xl transition-colors"
                    :class="star <= rating ? 'text-yellow-400' : 'text-gray-300'">
              ★
            </button>
          </template>
        </div>
        <input type="hidden" name="rating" x-model="rating">
      </div>

      <!-- Review Text -->
      <div class="mb-4">
        <label for="review" class="block text-sm font-medium text-gray-700">Your Review</label>
        <textarea id="review" name="review" rows="4"
                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500"
                  placeholder="Tell us about your experience..." required></textarea>
      </div>

      <!-- Photo Upload -->
      <div class="mb-4">
        <label for="photo" class="block text-sm font-medium text-gray-700">Upload a Photo (Optional)</label>
        <input type="file" id="photo" name="photo" accept="image/*"
               class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
      </div>

      <!-- Buttons -->
      <div class="flex justify-end space-x-3 pt-4">
        <button type="button" @click="$store.reviewModal.show = false"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
          Cancel
        </button>
        <button type="submit"
                class="px-4 py-2 text-sm font-medium text-white bg-sky-600 border border-transparent rounded-md hover:bg-sky-700">
          Submit Review
        </button>
      </div>
    </form>

  </div>
</div>