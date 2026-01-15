<?php
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/status-helpers.php';

$client_id = $_GET['client_id'] ?? $_SESSION['client_id'] ?? null;
if (!$conn || !$client_id) {
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

// 🔹 Get trip start date
function getTripStartDate(mysqli $conn, int $client_id): ?string {
  $stmt = $conn->prepare("SELECT trip_date_start FROM clients WHERE id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_column();
}

// 🔹 Get itinerary map
function getItineraryMap(mysqli $conn, int $client_id): array {
  $stmt = $conn->prepare("SELECT itinerary_json FROM client_itinerary WHERE client_id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $json = $stmt->get_result()->fetch_column();
  $map = [];

  foreach (json_decode($json, true) ?? [] as $day) {
    $map[(int)($day['day_number'] ?? 0)] = $day['day_title'] ?? '';
  }

  return $map;
}

// 🔹 Fetch and group trip photos by day
function getPhotosGroupedByDay(mysqli $conn, int $client_id, int $package_id): array {
  $stmt = $conn->prepare("
    SELECT 
      p.id, p.file_name, p.caption, p.day, p.uploaded_at, p.document_status,
      p.scope_tag, p.location_tag, p.status_updated_by, p.approved_at,
      p.assigned_package_id,
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
    //if ($status === 'Rejected') continue;

    $day = (int)($row['day'] ?? 0);
    $grouped[$day][] = [
      'id'                  => (int) $row['id'],
      'file_name'           => $row['file_name'],
      'caption'             => $row['caption'] ?? '',
      'uploaded_at'         => $row['uploaded_at'],
      'document_status'     => $status,
      'status_class'        => getStatusClass($status),
      'pending_overlay'     => $status === 'Pending',
      'rejected_overlay'     => $status === 'Rejected',
      'scope_tag'           => $row['scope_tag'] ?? '',
      'location_tag'        => $row['location_tag'] ?? '',
      'day'                 => $day,
      'approved_at'         => $row['approved_at'] ? date('M j, Y', strtotime($row['approved_at'])) : null,
      'status_updated_by'   => $row['status_updated_by'] ?? '—',
      'assigned_package_id' => (int)($row['assigned_package_id'] ?? 0),
      'package_name'        => $row['package_name'] ?? 'Unassigned',
      'url'                 => "../uploads/trip_photos/client_{$client_id}/{$package_id}/" . rawurlencode($row['file_name'])
    ];
  }

  return $grouped;
}

// 🔹 Execute
$assigned_package_id = getAssignedPackageId($conn, $client_id);
$trip_start_date     = getTripStartDate($conn, $client_id);
$itineraryMap        = getItineraryMap($conn, $client_id);
$photosByDay         = $assigned_package_id ? getPhotosGroupedByDay($conn, $client_id, $assigned_package_id) : [];

// 🔹 Prepare Alpine data
$galleryData = [];
foreach ($itineraryMap as $dayNum => $dayTitle) {
  $galleryData[] = [
    'day_number' => $dayNum,
    'day_title'  => $dayTitle,
    'photos'     => $photosByDay[$dayNum] ?? []
  ];
}

// 🔹 Determine if trip has started
$tripStarted = $trip_start_date && strtotime($trip_start_date) <= time();

// 🔹 Fallback empty state
$hasPhotos = array_reduce($galleryData, fn($carry, $day) => $carry || !empty($day['photos']), false);
if (!$tripStarted && !$hasPhotos) {
  $galleryData = [];
}
?>

<!-- ✅ Alpine Scope -->
<div 
  x-data="tripPhotoGallery(<?= htmlspecialchars(json_encode($galleryData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>, <?= json_encode($_SESSION['is_admin'] ?? false) ?>)"
  class="trip-photo-gallery"
>

  <!-- Header + Toggle -->
  <div class="flex items-center justify-between mb-3">
    <div class="flex items-center gap-2 text-slate-800">
      <div class="w-9 h-9 rounded-lg bg-sky-100 text-sky-600 flex items-center justify-center shadow-sm">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01" />
        </svg>
      </div>
      <div>
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-semibold">Client Trip Photos</p>
        <h3 class="text-xl font-bold">Client Photos Gallery</h3>
      </div>
    </div>
    <button 
      @click="open = !open" 
      class="text-sm px-3 py-1.5 bg-sky-100 text-sky-700 rounded-lg hover:bg-sky-200 transition border border-sky-200">
      <span x-show="open">Hide</span>
      <span x-show="!open">Show</span>
    </button>
  </div>

  <div x-show="open" x-transition class="max-h-[420px] snap-mandatory overflow-y-auto snap-y scroll-smooth">
    <!-- 📭 Global Empty State -->
    <template x-if="days.length === 0">
      <div class="border-2 border-dotted border-sky-300 rounded-lg bg-sky-30 flex flex-col items-center justify-center text-center p-6 mb-4">
        <div class="w-12 h-12 bg-sky-100 rounded-full mb-2 flex items-center justify-center">
          <span class="text-sky-400 text-xl">📁</span>
        </div>
        <p class="text-sm text-sky-700 font-medium mb-1">No photos uploaded yet.</p>
        <p class="text-xs italic text-sky-600">Once the trip begins, this gallery will fill with memories.</p>
      </div>
    </template>

  <!-- 📅 Day Loop -->
  <template x-for="day in days" :key="day.day_number">
    <div :class="day.photos.length > 0 ? 'snap-start min-h-[420px]' : 'snap-start'" class="p-3 mb-8 rounded-2xl bg-white shadow-sm border border-gray-100">
      
      <!-- 🗓️ Day Header -->
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
          <p class="text-xs uppercase tracking-wider text-sky-600 font-semibold">Day <span x-text="day.day_number"></span></p>
          <h4 class="text-lg font-bold text-slate-800" x-text="day.day_title"></h4>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-full bg-sky-50 text-sky-700 border border-sky-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/></svg>
            <span x-text="day.photos.length + ' Photo' + (day.photos.length === 1 ? '' : 's')"></span>
          </span>
        </div>
      </div>

      <!-- 📭 Empty Day -->
      <div x-show="day.photos.length === 0" class="border-2 border-dotted border-sky-300 rounded-xl bg-sky-30 flex flex-col items-center justify-center text-center p-6 mb-4">
        <div class="w-12 h-12 bg-sky-100 rounded-full mb-2 flex items-center justify-center">
          <span class="text-sky-400 text-xl">📁</span>
        </div>
        <p class="text-sm text-sky-700 font-medium mb-1">No photos uploaded for this day yet.</p>
        <p class="text-xs italic text-sky-600">Encourage your client to share their travel memories!</p>
      </div>

      <!-- 🖼️ Photo Grid -->
      <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 auto-rows-fr" x-show="day.photos.length > 0">
        <template x-for="photo in day.photos" :key="photo.id">
          <div 
            @click="selectedPhoto = photo" 
            class="aspect-[4/3] rounded-xl overflow-hidden bg-slate-900 relative shadow-md transition-all duration-200 hover:scale-[1.02] hover:shadow-[0_10px_30px_rgba(56,189,248,0.25)] cursor-pointer"
          >
            <img :src="photo.url" :alt="photo.file_name" class="w-full h-full object-cover" />

            <!-- Status Pill -->
            <div class="absolute top-3 left-3">
              <span :class="photo.status_class" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full bg-white/90 backdrop-blur border">
                <span class="w-2 h-2 rounded-full" :class="{
                  'bg-yellow-500': photo.document_status === 'Pending',
                  'bg-emerald-500': photo.document_status === 'Approved',
                  'bg-red-500': photo.document_status === 'Rejected'
                }"></span>
                <span x-text="photo.document_status"></span>
              </span>
            </div>

            <!-- Overlays -->
            <template x-if="photo.rejected_overlay">
              <div class="absolute inset-0 bg-red-800/60 text-white text-sm font-semibold flex items-center justify-center">
                Rejected by Admin
              </div>
            </template>
            <template x-if="photo.pending_overlay">
              <div class="absolute inset-0 bg-amber-700/50 text-white text-sm font-semibold flex items-center justify-center">
                Awaiting Admin Approval
              </div>
            </template>

            <!-- Bottom Info Bar -->
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 via-black/40 to-transparent p-3 space-y-2">
              <div class="flex items-center gap-2 text-white text-xs">
                <svg class="w-4 h-4 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span class="font-semibold" x-text="'Day ' + photo.day"></span>
                <span class="text-white/80" x-text="photo.uploaded_at"></span>
              </div>
              <div class="flex items-center gap-2 text-white text-xs" x-show="photo.scope_tag || photo.location_tag">
                <svg class="w-4 h-4 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c1.657 0 3-1.567 3-3.5S13.657 4 12 4 9 5.567 9 7.5 10.343 11 12 11z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c-4 0-6 2.5-6 5.5V19h12v-2.5c0-3-2-5.5-6-5.5z"/></svg>
                <span class="font-semibold" x-text="photo.scope_tag"></span>
                <span class="text-white/70" x-show="photo.location_tag">•</span>
                <span class="text-white/80" x-text="photo.location_tag"></span>
              </div>
              <div x-show="photo.caption" class="text-white text-sm font-semibold leading-tight line-clamp-2" x-text="photo.caption"></div>
            </div>
          </div>
        </template>

        <!-- ➕ Placeholder Cards -->
        <template x-for="i in Math.max(0, 6 - day.photos.length)" :key="'placeholder-' + day.day_number + '-' + i">
          <div class="aspect-[4/3] border-2 border-dotted border-sky-300 rounded-xl bg-sky-30 flex flex-col items-center justify-center text-center p-4">
            <div class="w-12 h-12 bg-sky-100 rounded-full mb-2 flex items-center justify-center">
              <span class="text-sky-400 text-xl">📷</span>
            </div>
            <p class="text-xs italic text-sky-700">This space is waiting to hold a memory. Ask your client to post more photos!</p>
          </div>
        </template>
      </div>
    </div>
  </template>

  <!-- 📋 Photo Modal -->
  <?php include __DIR__ . '/photo-details-modal.php'; ?>
</div>

<script>
function tripPhotoGallery(galleryData, isAdmin) {
  return {
    open: false,
    days: galleryData,
    selectedPhoto: null,
    confirmDeletePhoto: false,
    isAdmin: isAdmin,

    updateStatus(status) {
      if (!this.selectedPhoto || this.selectedPhoto.document_status === status) return;

      fetch(`../actions/update_photo_status.php?id=${this.selectedPhoto.id}&status=${status}`)
        .then(res => {
          if (!res.ok) throw new Error("Failed to update status");
          return res.json();
        })
        .then(data => {
          if (data.success) {
            // Update the selected photo object
            this.selectedPhoto.document_status = data.data.document_status;
            this.selectedPhoto.status_class = this.getStatusClass(data.data.document_status);
            this.selectedPhoto.status_updated_by = data.data.status_updated_by;
            this.selectedPhoto.approved_at = data.data.approved_at;

            // Also update the photo in the days array
            const day = this.days.find(d => d.day_number === this.selectedPhoto.day);
            if (day) {
              const photo = day.photos.find(p => p.id === this.selectedPhoto.id);
              if (photo) {
                photo.document_status = data.data.document_status;
                photo.status_class = this.getStatusClass(data.data.document_status);
                photo.pending_overlay = data.data.document_status === 'Pending';
                photo.rejected_overlay = data.data.document_status === 'Rejected';
                photo.status_updated_by = data.data.status_updated_by;
                photo.approved_at = data.data.approved_at;
              }
            }

            window.dispatchEvent(new CustomEvent("toast", {
              detail: { status: "photo_status_updated" }
            }));

            this.selectedPhoto = null;
            
            // Soft refresh: Re-fetch the gallery data to ensure consistency
            this.refreshGallery();
          } else {
            throw new Error(data.message || "Unknown error");
          }
        })
        .catch(err => {
          console.error("Photo status update failed:", err);
          window.dispatchEvent(new CustomEvent("toast", {
            detail: { status: "photo_status_failed" }
          }));
        });
    },

    refreshGallery() {
      const clientId = new URLSearchParams(window.location.search).get('client_id') || <?= json_encode($_SESSION['client_id'] ?? null) ?>;
      if (!clientId) return;

      fetch(`../actions/refresh_trip_gallery.php?client_id=${clientId}`)
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            this.days = data.data || [];
          }
        })
        .catch(err => console.error("Gallery refresh failed:", err));
    },

    deletePhoto(photoId) {
      fetch(`../actions/update_photo_status.php?id=${photoId}&action=delete`)
        .then(res => {
          if (!res.ok) throw new Error("Failed to delete photo");
          return res.json();
        })
        .then(data => {
          if (data.success) {
            // Remove the photo from the days array
            for (let day of this.days) {
              const photoIndex = day.photos.findIndex(p => p.id === photoId);
              if (photoIndex > -1) {
                day.photos.splice(photoIndex, 1);
                break;
              }
            }

            this.selectedPhoto = null;

            window.dispatchEvent(new CustomEvent("toast", {
              detail: { status: "photo_deleted" }
            }));
          } else {
            throw new Error(data.message || "Unknown error");
          }
        })
        .catch(err => {
          console.error("Photo deletion failed:", err);
          window.dispatchEvent(new CustomEvent("toast", {
            detail: { status: "photo_delete_failed" }
          }));
        });
    },

    getStatusClass(status) {
      const map = {
        "Pending": "bg-yellow-100 text-yellow-700 border border-yellow-300",
        "Rejected": "bg-red-100 text-red-700 border border-red-300",
        "Approved": "bg-emerald-100 text-emerald-700 border border-emerald-300"
      };
      return map[status] || "bg-gray-100 text-gray-600 border border-gray-300";
    }
  };
}
</script>