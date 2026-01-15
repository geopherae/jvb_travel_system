<?php

$client_id = $_SESSION['client_id'] ?? null;
if (!isset($conn, $client_id)) {
  echo "<p>Trip photo gallery cannot be loaded. Missing database connection or client ID.</p>";
  return;
}

// 🔹 Get assigned package ID
function getAssignedPackageId(mysqli $conn, int $client_id): ?int {
  $stmt = $conn->prepare("SELECT assigned_package_id FROM clients WHERE id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_column();
}

function parseItineraryJson(?string $json): array {
  if (empty($json)) return [];

  $decoded = json_decode($json, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Itinerary JSON decode error: " . json_last_error_msg());
    return [];
  }

  return array_map(function($day) {
    return [
      'day_number' => (int)($day['day_number'] ?? 0),
      'day_title'  => htmlspecialchars($day['day_title'] ?? ''),
      'activities' => array_map(function($activity) {
        return [
          'time'  => htmlspecialchars($activity['time'] ?? ''),
          'title' => htmlspecialchars($activity['title'] ?? '')
        ];
      }, $day['activities'] ?? [])
    ];
  }, $decoded ?? []);
}

function getItineraryMap(mysqli $conn, int $client_id): array {
  $stmt = $conn->prepare("SELECT itinerary_json FROM client_itinerary WHERE client_id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $json = $stmt->get_result()->fetch_column();
  $parsedDays = parseItineraryJson($json);

  $map = [];
  foreach ($parsedDays as $day) {
    if ($day['day_number'] > 0) {
      $map[$day['day_number']] = $day['day_title'];
    }
  }
  return $map;
}

// 🔹 Fetch and group trip photos by day
function getPhotosGroupedByDay(mysqli $conn, int $client_id, int $package_id): array {
  $stmt = $conn->prepare("
    SELECT 
      p.id, p.file_name, p.caption, p.day, p.uploaded_at, p.document_status,
      p.scope_tag, p.assigned_package_id,
      tp.package_name
    FROM client_trip_photos p
    LEFT JOIN tour_packages tp ON p.assigned_package_id = tp.id
    WHERE p.client_id = ? AND p.assigned_package_id = ?
    ORDER BY p.day ASC, p.uploaded_at DESC
  ");
  $stmt->bind_param("ii", $client_id, $package_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $grouped = [];
  while ($row = $result->fetch_assoc()) {
    $status = $row['document_status'] ?? 'Pending';
    if ($status === 'Rejected') continue;

    $day = (int)($row['day'] ?? 0);
    $grouped[$day][] = [
      'id'                  => (int) $row['id'],
      'file_name'           => $row['file_name'],
      'caption'             => $row['caption'] ?? '',
      'uploaded_at'         => date('M j, Y', strtotime($row['uploaded_at'])),
      'document_status'     => $status,
      'status_class'        => getStatusClass($status),
      'pending_overlay'     => $status === 'Pending',
      'scope_tag'           => $row['scope_tag'] ?? '',
      'day'                 => $day,
      'assigned_package_id' => (int)($row['assigned_package_id'] ?? 0),
      'package_name'        => $row['package_name'] ?? 'Unassigned',
      'url'                 => "../uploads/trip_photos/client_{$client_id}/{$package_id}/" . rawurlencode($row['file_name'])
    ];
  }

  return $grouped;
}

// 🔹 Execute
$assigned_package_id = getAssignedPackageId($conn, $client_id);
if (!$assigned_package_id) {
  echo "<p>Package not assigned. Cannot load trip photos.</p>";
  return;
}

$itineraryMap = getItineraryMap($conn, $client_id);
$photosByDay = getPhotosGroupedByDay($conn, $client_id, $assigned_package_id);

// 🔹 Prepare Alpine data
$galleryData = [];
foreach ($itineraryMap as $dayNum => $dayTitle) {
  $galleryData[] = [
    'day_number' => $dayNum,
    'day_title'  => $dayTitle,
    'photos'     => $photosByDay[$dayNum] ?? []
  ];
}
?>

<script src="../assets/js/client_tripPhotoGallery.js"></script>

<!-- ✅ Alpine Scope -->
<div 
  x-data="tripPhotoGallery"
  class="trip-photo-gallery max-h-screen overflow-y-auto"
  data-gallery='<?= json_encode($galleryData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
  data-package-id='<?= $assigned_package_id ?>'
  data-package-name='<?= htmlspecialchars($package_name ?? 'Package') ?>'
  data-client-id='<?= $client_id ?>'
>

  <input type="file" x-ref="fileInput" class="hidden" accept="image/jpeg,image/png,image/webp" @change="handleFileUpload($event)" />

  <?php include __DIR__ . '/client-photo-upload-modal.php'; ?>

  <!-- Gallery Loop -->
  <template x-if="Array.isArray(days) && days.length">
    <template x-for="day in days" :key="'day-' + day.day_number">
      <div class="rounded-2xl overflow-hidden border border-slate-200 bg-white shadow-sm mb-6">
        <!-- Header: Toggle Collapse -->
        <button type="button"
                class="w-full flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition"
                @click="day.open = !day.open">
          <div class="flex items-center gap-3 text-left">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-sky-100 text-sky-700 rounded-full text-xs font-semibold">
              Day <span x-text="day.day_number"></span>
            </span>
            <span class="text-sm font-semibold text-slate-800" x-text="day.day_title"></span>
          </div>
          <svg x-bind:class="day.open ? 'rotate-180 text-slate-700' : 'text-slate-400'" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <!-- Collapsible Content -->
        <div x-show="day.open" x-transition.duration.200ms>
          <div class="p-4">
            <!-- Ensure content never exceeds viewport height -->
            <div class="max-h-screen overflow-y-auto">
              <div class="grid grid-cols-3 gap-4">
                <template x-for="photo in day.photos" :key="'photo-' + photo.id">
                  <button type="button"
                          @click="selectedPhoto = photo"
                          class="relative group aspect-square rounded-lg overflow-hidden border border-slate-300 bg-slate-50 hover:border-sky-400 hover:shadow-md transition cursor-pointer">
                    <img :src="photo.url" :alt="photo.file_name" class="w-full h-full object-cover" />

                    <!-- 🟡 Pending Overlay -->
                    <template x-if="photo.pending_overlay">
                      <div class="absolute inset-0 bg-amber-700/50 text-white text-xs font-semibold flex items-center justify-center">
                        Awaiting Admin Approval
                      </div>
                    </template>

                    <div x-show="photo.caption"
                         class="absolute bottom-1 left-1 bg-black/60 text-white text-xs px-2 py-0.5 rounded max-w-[90%] truncate"
                         x-text="photo.caption"></div>
                  </button>
                </template>

                <template x-for="i in Math.max(0, 6 - (day.photos?.length || 0))" :key="'placeholder-' + day.day_number + '-' + i">
                  <button type="button"
                          @click="uploadDay = day.day_number"
                          class="flex flex-col items-center justify-center aspect-square rounded-lg border-2 border-dotted border-sky-300 bg-sky-50 hover:border-sky-500 hover:bg-sky-100 transition cursor-pointer">
                    <div class="w-10 h-10 bg-sky-100 rounded-full flex items-center justify-center mb-2">
                      <span class="text-sky-400 text-xl">📷</span>
                    </div>
                    <p class="text-xs text-sky-700 italic text-center">Tap to upload a memory</p>
                  </button>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </template>

  <?php include __DIR__ . '/../components/client-photo-modal.php'; ?>

  <!-- 🍞 Toast Listener -->
  <div 
    x-data="{ show: false, message: '', level: 'success' }"
    @toast.window="
      message = getToastMessage($event.detail.status);
      level = getToastLevel($event.detail.status);
      show = true;
      setTimeout(() => show = false, 3000);
    "
    x-show="show"
    x-transition
class="fixed bottom-6 right-6 z-50 px-4 py-3 max-w-sm w-full rounded-lg shadow-lg"
:class="level === 'error' 
  ? 'bg-red-100 border border-red-300 text-red-800' 
  : 'bg-green-100 border border-green-300 text-green-800'"
>
  <p class="text-sm font-medium" x-text="message"></p>
</div>

</div>