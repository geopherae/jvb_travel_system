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

<body class="text-gray-800 font-sans overflow-hidden" x-data="{ sidebarOpen: false, ...clientViewScope() }" x-init="initClientView()">

<!-- Includes -->
<?php $isAdmin = true; include '../components/admin_sidebar.php'; ?>
<?php $isAdmin = true; include '../components/right-panel.php'; ?>
<?php include '../components/status_alert.php'; ?>

<!-- Mobile Toggle -->
<button @click="sidebarOpen = !sidebarOpen" class="p-3 md:hidden absolute top-4 left-4 z-30 bg-primary text-white rounded">
  ☰
</button>

<main class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-4 sm:p-6 space-y-8 relative z-0">

  <!-- 🧭 Page Title -->
  <h2 class="text-xl font-bold">Client Overview</h2>

<div class="max-w-7xl mx-auto space-y-10">

  <div x-data="{ tab: 'info' }" class="space-y-6">

    <!-- 🧭 Tab Navigation -->
    <div class="border-b pb-2">
      <div class="flex items-center justify-between gap-4">
        <!-- Tabs -->
        <div class="flex gap-4 sm:gap-6 overflow-x-auto text-sm font-semibold text-gray-600 min-w-0">
          <button @click="tab = 'info'"
                  :class="tab === 'info' ? 'text-sky-600 border-b-2 border-sky-600' : 'hover:text-sky-500'"
                  class="pb-1 transition whitespace-nowrap shrink-0">
            Client & Tour Info
          </button>
          <button @click="tab = 'itinerary'"
                  :class="tab === 'itinerary' ? 'text-sky-600 border-b-2 border-sky-600' : 'hover:text-sky-500'"
                  class="pb-1 transition whitespace-nowrap shrink-0">
            Itinerary
          </button>
          <button @click="tab = 'tripPhotos'"
                  :class="tab === 'tripPhotos' ? 'text-sky-600 border-b-2 border-sky-600' : 'hover:text-sky-500'"
                  class="pb-1 transition whitespace-nowrap shrink-0">
            Client Trip Photos
          </button>
        </div>
        
        <!-- Print Button -->
        <a 
          href="./print_client_details.php?client_id=<?= $client['id'] ?>"
          target="_blank"
          class="px-4 py-2 bg-sky-500 hover:bg-sky-700 text-white text-sm font-semibold rounded-lg transition shadow-sm flex items-center gap-2 whitespace-nowrap shrink-0"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
          </svg>
          <span class="hidden sm:inline">Print Client Details</span>
          <span class="sm:hidden">Print</span>
        </a>
      </div>
    </div>

      <!-- 📋 Tab 1: Client Info + Tour Package -->
      <div x-show="tab === 'info'" x-transition>
        <div class="flex flex-col sm: flex-row lg:flex-row gap-4 lg:gap-6 min-w-0">
          <!-- Client Contact Details Card -->
          <div class="min-w-0 flex-1">
            <?php include '../components/client-contact-details.php'; ?>
          </div>
          <!-- 🧳 Tour Package Card -->
          <div class="min-w-0 flex-1">
            <?php include '../components/tour-package-card.php'; ?>
          </div>
        </div>
      </div>

      <!-- 🗺️ Tab 2: Itinerary -->
      <div x-show="tab === 'itinerary'" x-transition x-cloak>
        <?php include '../components/itinerary_card.php'; ?>
      </div>

      <!-- 📷 Tab 3: Client Trip Photos -->
      <div x-show="tab === 'tripPhotos'" class="rounded-lg border border-gray-200 p-6 mb-8 bg-white shadow-sm" x-transition x-cloak>
        <?php include '../components/trip_photos_gallery.php'; ?>
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
       class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-end sm:items-center justify-center backdrop-blur-sm px-3 sm:px-4"
       x-transition
       role="dialog" aria-modal="true"
       @keydown.escape.window="$store.modals.editClient = false"
       @click.outside="$store.modals.editClient = false">

    <div class="max-w-xl w-full bg-white p-4 sm:p-6 rounded-t-2xl sm:rounded-lg shadow-xl max-h-[90vh] overflow-y-auto relative"
         tabindex="-1"
         x-init="$el.focus()"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-90">

      <!-- ❌ Close Button -->
      <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 text-xl"
              @click="$store.modals.editClient = false">&times;</button>

      <?php
        $editClientId = $client['id'];
        include '../admin/edit_client.php';
      ?>
    </div>
  </div>
<script src="https://unpkg.com/alpinejs" defer></script>
<?php include '../components/status_alert.php'; ?>
<?php include '../components/update_client_booking_modal.php'; ?>

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
