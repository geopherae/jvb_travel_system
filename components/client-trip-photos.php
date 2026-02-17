<?php
// view_trip_photos.php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('client');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/status-helpers.php';

// 🧑‍💼 Get Client ID
$client_id = $_SESSION['client_id'] ?? null;
if (!$client_id) {
  http_response_code(403);
  exit('Unauthorized access.');
}

// 🚦 Fetch client + package info
$client_stmt = $conn->prepare("
  SELECT 
    c.full_name, c.status, c.trip_date_start, c.trip_date_end,
    c.booking_date, c.booking_number,
    t.package_name, t.package_description,
    t.origin, t.destination, t.tour_cover_image
  FROM clients c
  LEFT JOIN tour_packages t ON c.assigned_package_id = t.id
  WHERE c.id = ?
");
$client_stmt->bind_param("i", $client_id);
$client_stmt->execute();
$client = $client_stmt->get_result()->fetch_assoc();
$client_stmt->close();


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


// Use the original $client array (already fetched above) for package name and details
$package_name = $client['package_name'] ?? 'Package';

// 🔹 Execute data fetching
$assigned_package_id = getAssignedPackageId($conn, $client_id);
$itineraryMap = $assigned_package_id ? getItineraryMap($conn, $client_id) : [];
$photosByDay  = $assigned_package_id ? getPhotosGroupedByDay($conn, $client_id, $assigned_package_id) : [];

// 🔹 Prepare Alpine gallery data
$galleryData = [];
foreach ($itineraryMap as $dayNum => $dayTitle) {
  $galleryData[] = [
    'day_number' => $dayNum,
    'day_title'  => $dayTitle,
    'photos'     => $photosByDay[$dayNum] ?? []
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>My Trip Photos</title>
  <?php include __DIR__ . '/../components/favicon_links.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="description" content="View and upload your trip photos." />

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <script src="../includes/global-toast.js" defer></script>
  <script src="../includes/message_received_toast_poller.js" defer></script>
  <script src="../assets/js/client_tripPhotoGallery.js"></script>
</head>

<body class="font-poppins text-gray-800 touch-manipulation" x-data="{ sidebarOpen: false }" style="background: linear-gradient(to bottom, #e0f7ff 0%, white 10%, white 100%)">

  <div>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/right-panel.php'; ?>

    <main class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-8 flex flex-col relative">
      <div class="flex-1 overflow-y-auto space-y-8">

        <?php if (!$assigned_package_id): ?>
          <p class="text-sm text-gray-500 italic">Package not assigned. Cannot load trip photos.</p>
        <?php elseif (empty($galleryData)): ?>
          <p class="text-sm text-gray-500 italic">No itinerary days found for this package.</p>
        <?php else: ?>

          <!-- Header: Captured Moments + Compact Package Info (Fixed Layout) -->
<div class="relative rounded-lg shadow-sm overflow-hidden mb-10 border border-white/30">
  <!-- Blurred background image -->
  <div 
    class="absolute inset-0 bg-cover bg-center bg-no-repeat blur-md scale-[1.08]"
    style="background-image: url('../images/login_gallery_images/48366524_10217902571938570_2199810359848599552_n.jpg');"
  ></div>
  
  <!-- Sky blue gradient overlay -->
  <div class="absolute inset-0 bg-gradient-to-br from-sky-500/65 via-sky-400/45 to-transparent"></div>
  
  <!-- Content layer -->
  <div class="relative z-0 px-6 pt-8 pb-7 flex flex-col lg:flex-row lg:items-center gap-8">
    
    <!-- LEFT: Header text -->
    <div class="flex-1 lg:max-w-[50%]">
      <h1 class="text-2xl lg:text-3xl font-bold text-white drop-shadow-md">
        Captured Moments
      </h1>
      <p class="text-sky-100 mt-2 text-base leading-relaxed max-w-md">
        Share the moments that made today unforgettable — your photos inspire fellow travelers.
      </p>
    </div>
    
    <!-- RIGHT: Package information -->
    <?php
      $galleryDir = __DIR__ . '/../images/login_gallery_images/';
      $galleryWebPath = '../images/login_gallery_images/';
      $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
      $galleryImages = array_values(array_filter(
        scandir($galleryDir),
        function($f) use ($galleryDir, $allowedExts) {
          $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
          return is_file($galleryDir . $f) && in_array($ext, $allowedExts) && strpos($f, 'default_trip_cover') === false;
        }
      ));
      // Output a JS array, not a PHP array string
      $galleryImagesJs = '[' . implode(',', array_map(function($img) { return "'" . addslashes($img) . "'"; }, $galleryImages)) . ']';
    ?>
    <div class="flex-shrink-0 max-w-[340px] w-full">
      <div x-data="{ images: <?= $galleryImagesJs ?>, idx: 0, interval: null }"
           x-init="if(images.length){interval = setInterval(() => { idx = (idx + 1) % images.length }, 2500)}"
           @mouseenter="clearInterval(interval)" @mouseleave="if(images.length){interval = setInterval(() => { idx = (idx + 1) % images.length }, 2500)}"
           class="relative w-full aspect-[16/9] rounded-2xl overflow-hidden shadow-2xl border border-white/30 bg-slate-100 flex items-center justify-center max-w-[340px]">
        <template x-if="images.length">
          <template x-for="(img, i) in images" :key="img">
            <img x-show="idx === i"
                 :src="'<?= $galleryWebPath ?>' + img"
                 class="absolute inset-0 w-full h-full object-cover object-center rounded-2xl transition-opacity duration-700"
                 :class="{ 'opacity-100': idx === i, 'opacity-0': idx !== i }"
                 style="aspect-ratio: 16/9;" loading="lazy" />
          </template>
        </template>
        <template x-if="!images.length">
          <div class="flex items-center justify-center w-full h-full text-slate-400 text-lg">No gallery images found.</div>
        </template>
        <!-- Carousel dots -->
        <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-10" x-show="images.length > 1">
          <template x-for="(img, i) in images" :key="'dot-' + i">
            <span @click="idx = i"
                  class="w-2.5 h-2.5 rounded-full border border-white bg-white/70 cursor-pointer transition-all"
                  :class="idx === i ? 'bg-sky-500 border-sky-500 scale-110' : 'bg-white/70 border-white'">
            </span>
          </template>
        </div>
      </div>
    </div>
    
  </div>
</div>

          <!-- ✅ Alpine Scope -->
          <div
            x-data="tripPhotoGallery"
            class="trip-photo-gallery"
            data-gallery='<?= json_encode($galleryData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
            data-package-id='<?= $assigned_package_id ?>'
            data-package-name='<?= htmlspecialchars($package_name) ?>'
            data-client-id='<?= $client_id ?>'
          >

            <input type="file" x-ref="fileInput" class="hidden" accept="image/jpeg,image/png,image/webp" @change="handleFileUpload($event)" />

            <?php include __DIR__ . '/../components/client-photo-upload-modal.php'; ?>

            <!-- Gallery Loop -->
            <template x-if="Array.isArray(days) && days.length">
              <template x-for="day in days" :key="'day-' + day.day_number">
                <div class="rounded-lg overflow-hidden border border-slate-200 bg-white shadow-sm mb-8">

                  <!-- Header: Toggle Collapse -->
                  <button type="button"
                          class="w-full flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition"
                          @click="day.open = !day.open">
                    <div class="flex items-center gap-4 text-left flex-wrap">
                      <!-- Day Badge -->
                      <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-sky-100 text-sky-700 rounded-2xl text-sm font-semibold">
                        Day <span x-text="day.day_number"></span>
                      </span>
                      <!-- Day Title -->
                      <span class="text-base font-semibold text-slate-800" x-text="day.day_title"></span>
                      <!-- Photo Count (only when photos exist) -->
                      <span x-show="day.photos.length > 0"
                            class="text-xs bg-white px-3 py-1 rounded-3xl border border-slate-200 text-slate-500 flex items-center gap-1 shadow-sm">
                        <span x-text="day.photos.length"></span>
                        <span class="text-orange-400">📸</span>
                      </span>
                    </div>
                    <svg x-bind:class="day.open ? 'rotate-180 text-slate-700' : 'text-slate-400'"
                         class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>

                  <!-- Collapsible Content -->
                  <div x-show="day.open" x-transition.duration.200ms>
                    <div class="p-6">
                      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4">

                        <!-- Photos -->
                        <template x-for="photo in day.photos" :key="'photo-' + photo.id">
                          <button type="button"
                                  @click="selectedPhoto = photo"
                                  class="relative group aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-50 hover:border-sky-400 hover:shadow-xl hover:-translate-y-0.5 active:scale-95 transition-all duration-300 cursor-pointer">
                            <img :src="photo.url" 
                                 :alt="photo.file_name" 
                                 class="w-full h-full object-cover"
                                 loading="lazy" />

                            <!-- 🟡 Pending Overlay -->
                            <template x-if="photo.pending_overlay">
                              <div class="absolute inset-0 bg-amber-700/60 backdrop-blur-sm text-white text-xs font-semibold flex items-center justify-center">
                                Awaiting Admin Approval
                              </div>
                            </template>

                            <div x-show="photo.caption"
                                 class="absolute bottom-2 left-2 bg-black/70 text-white text-xs px-3 py-1 rounded-2xl max-w-[85%] truncate shadow-sm"
                                 x-text="photo.caption"></div>
                          </button>
                        </template>

                        <!-- Upload Placeholders -->
                        <template x-for="i in Math.max(0, 4 - (day.photos?.length || 0))" :key="'placeholder-' + day.day_number + '-' + i">
                          <button type="button"
                                  @click="uploadDay = day.day_number"
                                  class="p-4 flex flex-col items-center justify-center aspect-square rounded-xl border-2 border-dotted border-sky-300 bg-sky-50 hover:border-sky-500 hover:bg-sky-100 hover:shadow-md active:scale-95 transition-all duration-300 cursor-pointer">
                            <div class="w-12 h-12 bg-sky-100 rounded-2xl flex items-center justify-center mb-3">
                              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
  <circle cx="12" cy="13" r="4"></circle>
</svg>
                            </div>
                            <p class="text-xs text-sky-700 font-medium text-center leading-tight">Upload a memory from this day</p>
                          </button>
                        </template>

                      </div>
                    </div>
                  </div>

                </div>
              </template>
            </template>

            <?php include __DIR__ . '/../components/client-photo-modal.php'; ?>

            <!-- 🍞 Toast -->
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
              class="fixed bottom-6 right-6 z-50 px-4 py-3 max-w-sm w-full rounded-2xl shadow-xl"
              :class="level === 'error'
                ? 'bg-red-100 border border-red-300 text-red-800'
                : 'bg-green-100 border border-green-300 text-green-800'"
            >
              <p class="text-sm font-medium" x-text="message"></p>
            </div>

          </div><!-- end Alpine scope -->

        <?php endif; ?>

      </div>
    </main>
  </div>

</body>
</html>