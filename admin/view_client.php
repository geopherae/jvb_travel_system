<?php
session_start();

// ✅ Load dependencies
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/status-helpers.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../actions/highlight_today_itinerary.php';

// ✅ Validate client ID
$client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : null;
if (!$client_id) {
  echo "Client not specified.";
  exit();
}

// ✅ Default image paths
$default_cover  = '../images/default_trip_cover.jpg';
$default_avatar = '../images/default_client_profile.png';

// ✅ Fetch client and assigned package info
$client_stmt = $conn->prepare("
  SELECT 
    c.*, 
    t.package_name, 
    t.package_description,
    t.price,
    t.day_duration,
    t.night_duration,
    t.tour_cover_image,
    t.inclusions_json,
    t.exclusions_json,
    t.checklist_template_id,
    t.is_deleted
  FROM clients c
  LEFT JOIN tour_packages t ON c.assigned_package_id = t.id
  WHERE c.id = ?
");
$client_stmt->bind_param("i", $client_id);
$client_stmt->execute();
$client = $client_stmt->get_result()->fetch_assoc();

if (!$client) {
  echo "Client not found.";
  exit();
}

$_SESSION['last_viewed_client_id'] = $client['id'];

// ✅ Build assigned package object
$assignedPackage = null;
if (!empty($client['assigned_package_id'])) {
  $assignedPackage = [
    'id'              => $client['assigned_package_id'],
    'name'            => $client['package_name'] ?? 'Untitled Package',
    'description'     => $client['package_description'] ?? '',
    'price'           => $client['price'] ?? 0,
    'cover'           => !empty($client['tour_cover_image'])
                          ? '../uploads/tour_covers/' . rawurlencode($client['tour_cover_image'])
                          : $default_cover,
    'duration_days'   => $client['day_duration'] ?? 0,
    'duration_nights' => $client['night_duration'] ?? 0,
    'inclusions'      => json_decode($client['inclusions_json'] ?? '[]', true) ?? [],
    'exclusions'      => json_decode($client['exclusions_json'] ?? '[]', true) ?? [],
    'is_deleted'      => (int)($client['is_deleted'] ?? 0)
  ];
}

// ✅ Profile + Cover Images
$coverImage = $assignedPackage['cover'] ?? $default_cover;
$imgSrc     = !empty($client['client_profile_photo'])
                ? '../uploads/client_profiles/' . rawurlencode($client['client_profile_photo'])
                : $default_avatar;

// ✅ Itinerary JSON
$itinerary_stmt = $conn->prepare("
  SELECT itinerary_json
  FROM client_itinerary
  WHERE client_id = ?
  LIMIT 1
");
$itinerary_stmt->bind_param("i", $client_id);
$itinerary_stmt->execute();
$itinerary_row = $itinerary_stmt->get_result()->fetch_assoc();

$parsedItinerary = json_decode($itinerary_row['itinerary_json'] ?? '[]', true) ?? [];
$total_days      = count($parsedItinerary);
$total_nights    = max(0, $total_days - 1);

// ✅ Uploaded Documents
$docs_stmt = $conn->prepare("
  SELECT  
    id,
    client_id, 
    file_name, 
    file_path, 
    document_type, 
    document_status, 
    uploaded_at, 
    approved_at, 
    admin_comments, 
    status_updated_by  
  FROM uploaded_files  
  WHERE client_id = ?
");
$docs_stmt->bind_param("i", $client_id);
$docs_stmt->execute();

$documents = [];
$docResult = $docs_stmt->get_result();
while ($doc = $docResult->fetch_assoc()) {
  $file = urlencode($doc['file_path']);
  $documents[] = [
    'id'                => $doc['id'],
    'file_name'         => $doc['file_name'],
    'document_type'     => $doc['document_type'] ?? 'Unknown',
    'document_status'   => $doc['document_status'] ?? 'Pending',
    'uploaded_at'       => $doc['uploaded_at'],
    'approved_at'       => $doc['approved_at'] ?? null,
    'admin_comments'    => $doc['admin_comments'] ?? null,
    'status_updated_by' => $doc['status_updated_by'] ?? null,
    'delete_url'        => "/components/delete_document.php?file=$file",
    'approve_url'       => "/actions/approve_document.php?file=$file",
    'reject_url'        => "/actions/reject_document.php?file=$file"
  ];
}

// ✅ Itinerary Pill Stylings
$start     = $client['trip_date_start'] ?? null;
$end       = $client['trip_date_end'] ?? null;
$todayDay  = getTodayItineraryDay($start, $end);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>View Client</title>
  <?php include __DIR__ . '/../components/favicon_links.php'; ?>

<script>
  document.addEventListener('alpine:init', () => {
    Alpine.store('modals', {
      clientId: null,
      reassign: false,
      unassign: false,
      editBooking: false,
      archiveClient: false,
    });
  });
</script>

  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="../assets/js/modals.js" defer></script>
  <script src="../includes/gallery-scope.js" defer></script>
  <!--<script src="../assets/js/clientOverviewScope.js" defer></script>-->
  <style>[x-cloak] { display: none !important; }</style>
</head>

<body class="text-gray-800 font-sans" x-data="{ sidebarOpen: false, ...clientViewScope() }" x-init="initClientView()">

<!-- Includes -->
<?php $isAdmin = true; include '../components/admin_sidebar.php'; ?>
<?php $isAdmin = true; include '../components/right-panel.php'; ?>
<?php include '../components/status_alert.php'; ?>


<!-- Mobile Toggle -->
<button @click="sidebarOpen = !sidebarOpen" class="p-3 md:hidden absolute top-4 left-4 z-30 bg-primary text-white rounded">
  ☰
</button>

<main class="ml-0 lg:ml-64 lg:mr-80 min-h-screen overflow-y-auto p-4 sm:p-6 space-y-6 sm:space-y-8 relative z-0">

  <!-- 🧭 Page Title -->
  <h2 class="text-xl sm:text-2xl font-bold">Client Overview</h2>

  <div class="max-w-7xl mx-auto space-y-6 sm:space-y-10">

    <div x-data="{ tab: 'info' }" class="space-y-4 sm:space-y-6">

      <!-- 🧭 Tab Navigation -->
      <div class="border-b border-gray-200">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 pb-3">
          <!-- Tabs -->
          <div class="flex gap-3 sm:gap-6 overflow-x-auto scrollbar-hide text-sm font-semibold text-gray-600 -mb-px">
            <button @click="tab = 'info'"
                    :class="tab === 'info' ? 'text-sky-600 border-b-2 border-sky-600' : 'hover:text-sky-500'"
                    class="pb-2 transition whitespace-nowrap shrink-0">
              Client & Tour Info
            </button>
            <button @click="tab = 'itinerary'"
                    :class="tab === 'itinerary' ? 'text-sky-600 border-b-2 border-sky-600' : 'hover:text-sky-500'"
                    class="pb-2 transition whitespace-nowrap shrink-0">
              Itinerary
            </button>
            <button @click="tab = 'tripPhotos'"
                    :class="tab === 'tripPhotos' ? 'text-sky-600 border-b-2 border-sky-600' : 'hover:text-sky-500'"
                    class="pb-2 transition whitespace-nowrap shrink-0">
              Trip Photos
            </button>
          </div>
          
          <!-- Print Button -->
          <a 
            href="./print_client_details.php?client_id=<?= $client['id'] ?>"
            target="_blank"
            class="px-3 sm:px-4 py-2 bg-sky-500 hover:bg-sky-600 active:bg-sky-700 text-white text-sm font-semibold rounded-lg transition shadow-sm flex items-center justify-center gap-2 whitespace-nowrap shrink-0 touch-manipulation"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            <span class="hidden xs:inline sm:inline">Print Details</span>
            <span class="xs:hidden sm:hidden">Print</span>
          </a>
        </div>
      </div>

      <!-- 📋 Tab 1: Client Info + Tour Package -->
      <div x-show="tab === 'info'" x-transition x-cloak>
        <div class="flex flex-col lg:flex-row gap-4 lg:gap-6">
          <!-- Client Contact Details Card -->
          <div class="w-full lg:flex-1">
            <?php include '../components/client-contact-details.php'; ?>
          </div>
          <!-- 🧳 Tour Package Card -->
          <div class="w-full lg:flex-1">
            <?php include '../components/tour-package-card.php'; ?>
          </div>
        </div>
      </div>

      <!-- 🗺️ Tab 2: Itinerary -->
      <div x-show="tab === 'itinerary'" x-transition x-cloak>
        <?php include '../components/itinerary_card.php'; ?>
      </div>

      <!-- 📷 Tab 3: Client Trip Photos -->
      <div x-show="tab === 'tripPhotos'" x-transition x-cloak>
        <div class="rounded-lg border border-gray-200 p-4 sm:p-6 bg-white shadow-sm">
          <?php include '../components/trip_photos_gallery.php'; ?>
        </div>
      </div>

    </div>

    <!-- 📄 Documents Table -->
    <div class="space-y-4">
      <?php include '../components/documents-table.php'; ?>
    </div>

  </div>

</main>


  <!-- ✨ Edit Client Modal -->
  <div x-show="$store.modals.editClient" x-cloak
       class="fixed inset-0 z-50 overflow-y-auto"
       aria-labelledby="modal-title" role="dialog" aria-modal="true"
       @keydown.escape.window="$store.modals.editClient = false">

    <!-- Backdrop -->
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-4 text-center sm:p-0">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$store.modals.editClient = false"></div>

      <!-- Modal panel -->
      <div class="inline-block align-middle bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-0 sm:align-middle sm:max-w-4xl sm:w-full sm:max-h-[96vh]">
        <!-- Header -->
        <div class="bg-gradient-to-r from-sky-500 to-blue-600 px-4 py-3 sm:px-6">
          <div class="p-2 flex items-center justify-between">
            <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">
              Edit Guest | Travel Booking
            </h3>
            <button type="button" @click="$store.modals.editClient = false"
                    class="text-white hover:text-gray-200 transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>

        <?php
          $editClientId = $client['id'];
          include '../admin/edit_client.php';
        ?>
      </div>
    </div>
  </div>


<script src="https://unpkg.com/alpinejs" defer></script>
<?php include '../components/status_alert.php'; ?>
<?php include '../components/update_client_booking_modal.php'; ?>
<?php 
$isAdmin = true; 
include '../components/reassign-modal.php';
$editClientId = $client['id'];
include __DIR__ . '/../components/unassign-modal.php'; 
?>
<?php include __DIR__ . '/../components/archive_client_modal.php'; ?>

<script>
  function clientViewScope() {
    return {
      tab: 'tripPhotos',
      selectedPhoto: null,
      isAdmin: <?= json_encode($_SESSION['is_admin'] ?? false) ?>,

      updateStatus(status) {
        if (!this.selectedPhoto || this.selectedPhoto.status === status) return;

        fetch(`../actions/update_photo_status.php?id=${this.selectedPhoto.id}&status=${status}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              this.selectedPhoto.status = data.data.document_status;
              this.selectedPhoto.status_class = this.getStatusClass(data.data.document_status);
              this.selectedPhoto = null;
              this.showToast(data.message, data.toast);
            } else {
              this.showToast(data.message, 'error');
            }
          })
          .catch(err => {
            this.showToast('Network error. Please try again.', 'error');
          });
      },

      getStatusClass(status) {
        const map = {
          'Pending': 'bg-[#F5B74D]/20 text-[#D89D41]',
          'Rejected': 'bg-[#EB5757]/20 text-[#C64646]',
          'Approved': 'bg-[#27AE60]/20 text-[#1D924F]'
        };
        return map[status] || 'bg-gray-100 text-gray-600';
      },

      showToast(message, level = 'success') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-6 right-6 z-50 px-4 py-3 max-w-sm w-full rounded-lg shadow-lg ${
          level === 'error'
            ? 'bg-red-100 border border-red-300 text-red-800'
            : 'bg-green-100 border border-green-300 text-green-800'
        }`;
        toast.innerHTML = `<p class="text-sm font-medium">${message}</p>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
      },

      initClientView() {
        // Optional: preload or hydrate data
      }
    };
  }

    function archiveClientScope(clientId) {
    return {
      clientId,
      csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    };
  }

</script>

<script>
// Initialize Alpine store for review modal
document.addEventListener('alpine:init', () => {
  Alpine.store('reviewModal', {
    show: false
  });
});
</script>

</body>
<?php ob_end_flush(); ?>
</html>
