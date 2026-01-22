<?php
include_once __DIR__ . '/../admin/admin_session_check.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 🔒 Secure session check
if (empty($_SESSION['admin']['id'])) {
  header("Location: admin_login.php");
  exit();
}

// 🚫 Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 📦 Includes
include_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../components/status_alert.php';

// ✅ Fetch tour packages with itinerary & inclusions, excluding deleted ones
$tourPackages = [];
$sql = "
  SELECT 
    tp.id, tp.tour_cover_image, tp.package_name, tp.package_description, 
    tp.price, tp.day_duration, tp.night_duration, tp.is_favorite, tp.requires_visa,
    tp.inclusions_json, tp.exclusions_json,
    tp.origin, tp.destination,
    tp.is_deleted,
    ti.itinerary_json AS package_itinerary_json
  FROM tour_packages tp
  LEFT JOIN tour_package_itinerary ti ON ti.package_id = tp.id
  WHERE tp.is_deleted = 0
  ORDER BY tp.package_name ASC;
";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $tourPackages[] = $row;
  }
}

// ✅ Transform for JS modal (only active packages)
$allToursForJS = array_map(function($tour) {
  $image = (!empty($tour['tour_cover_image']) && $tour['tour_cover_image'] !== 'NULL')
      ? '../images/tour_packages_banners/' . ltrim($tour['tour_cover_image'], '/')
      : '../images/default_trip_cover.jpg';

  $itinerary = [];
  if (!empty($tour['package_itinerary_json'])) {
    $decoded = json_decode($tour['package_itinerary_json'], true);
    if (is_array($decoded)) $itinerary = $decoded;
  }

  $inclusions = [];
  if (!empty($tour['inclusions_json'])) {
    $decoded = json_decode($tour['inclusions_json'], true);
    if (is_array($decoded)) $inclusions = $decoded;
  }

  $exclusions = [];
  if (!empty($tour['exclusions_json'])) {
    $decoded = json_decode($tour['exclusions_json'], true);
    if (is_array($decoded)) $exclusions = $decoded;
  }

  return [
    'id'          => (int) $tour['id'],
    'package_id'  => (int) $tour['id'],
    'image'       => $image,
    'name'        => $tour['package_name'] ?? 'Unnamed Package',
    'description' => $tour['package_description'] ?? '',
    'price'       => isset($tour['price']) ? (float)$tour['price'] : null,
    'days'        => (int) ($tour['day_duration'] ?? 0),
    'nights'      => (int) ($tour['night_duration'] ?? 0),
    'origin'      => $tour['origin'] ?? '',
    'destination' => $tour['destination'] ?? '',
    'requires_visa' => !empty($tour['requires_visa']) ? (bool) $tour['requires_visa'] : false,
    'itinerary'   => $itinerary,
    'inclusions'  => $inclusions,
    'exclusions'  => $exclusions,
    'is_favorite' => !empty($tour['is_favorite']) ? (bool) $tour['is_favorite'] : false
  ];
}, $tourPackages);

// ✅ Featured tours (limit to 3) from normalized data
$top3 = array_slice(
  array_filter($allToursForJS, fn($tour) => !empty($tour['is_favorite'])),
  0,
  3
);


?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tour Packages</title>
  <?php include __DIR__ . '/../components/favicon_links.php'; ?>

  <!-- 🧩 Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- ⚙️ Alpine.js v3 -->
  <script src="https://unpkg.com/alpinejs" defer></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="../includes/tour_packages_global_scope.js?v=20260118-1"></script>
  <script>
    window.tourFormData = function () {
  return {
    packageName: '',
    description: '',
    price: '',
    days: 0,
    nights: 0,
    origin: '',
    destination: '',
    checklistTemplateId: 0,
    isFavorite: false,
    requiresVisa: false,
    filename: '',
    previewUrl: '../images/default_trip_cover.jpg',
    inclusions: [],
    exclusions: [],
    itinerary: [],
    tab: 'details',
    max: 10,

    handleCoverUpload(event) {
      const file = event.target.files[0];
      if (file) {
        this.filename = file.name;
        const reader = new FileReader();
        reader.onload = e => this.previewUrl = e.target.result;
        reader.readAsDataURL(file);
      } else {
        this.filename = '';
        this.previewUrl = '../images/default_trip_cover.jpg';
      }
    },

    isValid() {
      return this.packageName.trim() &&
             this.description.trim() &&
             parseFloat(this.price) > 0 &&
             this.days > 0 &&
             this.nights >= 0 &&
             this.nights <= this.days;
    },

    add() {
      if (this.inclusions.length >= this.max) return;
      this.inclusions.push({ icon: '', title: '', desc: '' });
    },

    remove(index) {
      if (index >= 0 && index < this.inclusions.length) {
        this.inclusions.splice(index, 1);
      }
    },

    addExclusion() {
      if (this.exclusions.length >= this.max) return;
      this.exclusions.push({ icon: '', title: '', desc: '' });
    },

    removeExclusion(index) {
      if (index >= 0 && index < this.exclusions.length) {
        this.exclusions.splice(index, 1);
      }
    }
  };
};

    window.tourFilterData = function () {
      return {
        searchName: '',
        filterDestination: '',

        getFilteredTours() {
          return window.allTours.filter(tour => {
            const matchesName = tour.name.toLowerCase().includes(this.searchName.toLowerCase());
            const matchesDestination = !this.filterDestination || tour.destination === this.filterDestination;
            return matchesName && matchesDestination;
          });
        }
      };
    };
    </script>

    <!-- ✅ Make all tours available to Alpine before your JS loads -->
<script>
window.allTours = <?= json_encode($allToursForJS, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.AIRPORTS = <?php echo json_encode(require __DIR__ . '/../includes/airports.php'); ?>;
</script>


</head>

<body class="font-poppins text-gray-800 overflow-hidden" x-data="{ sidebarOpen: false }" style="background: linear-gradient(to bottom, #e0f7ff 0%, white 10%, white 100%)">

<!-- 📱 Mobile Menu Toggle -->
<button @click="sidebarOpen = !sidebarOpen" class="p-3 md:hidden absolute top-4 left-4 z-30 bg-primary text-white rounded"> ☰ </button>

  <!--🧭 Sidebar + Right Panel -->
  <div>
    <?php include '../components/admin_sidebar.php'; ?>
    <?php include '../components/right-panel.php'; ?>

    <!-- 📦 Main Content -->
    <main class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-6 relative z-0">
      <h2 class="text-xl font-bold">Manage Tour Packages</h2>

      <div class="bg-white rounded-lg p-6 space-y-4" x-data="tourFilterData()">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <button id="openAddModal"
                  class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-sky-500 rounded hover:bg-sky-600 transition w-full sm:w-auto">
            + Add Tour Package
          </button>
        </div>

        <!-- 🔍 Search & Filter Section -->
        <div class="flex flex-col lg:flex-row gap-4 lg:items-end">
          <!-- Search by Package Name -->
          <div class="flex-1 w-full">
            <label class="block text-sm font-medium text-slate-600 mb-2">Search Package Name</label>
            <input 
              type="text" 
              x-model="searchName"
              placeholder="e.g., Bali Paradise..."
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 text-sm"
            />
          </div>

          <!-- Filter by Destination -->
          <div class="flex-1 w-full">
            <label class="block text-sm font-medium text-slate-600 mb-2">Filter by Destination</label>
            <select 
              x-model="filterDestination"
              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 text-sm"
            >
              <option value="">All Destinations</option>
              <template x-for="country in Object.keys(window.AIRPORTS)" :key="country">
                <optgroup :label="country">
                  <template x-for="(airportName, code) in window.AIRPORTS[country]" :key="code">
                    <option :value="code" x-text="code + ' - ' + airportName"></option>
                  </template>
                </optgroup>
              </template>
            </select>
          </div>

          <!-- Clear Filters Button -->
          <button 
            @click="searchName = ''; filterDestination = ''"
            class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 transition text-sm font-medium w-full lg:w-auto"
          >
            Clear
          </button>
        </div>

<!-- 🌟 Popular Picks -->
<div class="max-w-7xl mx-auto">
  <h3 class="text-slate-600 text-sm font-semibold mb-2 uppercase tracking-wider">Popular Picks</h3>

  <?php if (empty($top3)): ?>
    <div class="flex flex-col items-center justify-center py-12 text-gray-500">
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#0883bc" class="mb-2 bi bi-star-fill" viewBox="0 0 16 16">
      <path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/>
    </svg>
      <h3 class="text-md italic font-semibold text-sky-700 mb-1">No Favorites Yet</h3>
      <p class="text-md text-sky-700 italic text-center max-w-lg">
        This section will highlight your <strong>top 3 favorite</strong> tour packages.</br>Mark packages as favorites to easily access them from here.
      </p>
    </div>
  <?php else: ?>
    <div x-show="getFilteredTours().filter(t => t.is_favorite).length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <template x-for="tour in getFilteredTours().filter(t => t.is_favorite).slice(0, 3)" :key="tour.id">
        <div class="h-full" @click="$store.tourModal.openModal(tour.id)">
          <div class="cursor-pointer bg-white rounded-lg shadow-sm hover:shadow-md transition overflow-hidden h-full flex flex-col">
            <img :src="tour.image" alt="Tour" class="w-full h-48 object-cover" />
            <div class="p-4 flex-1 flex flex-col">
              <h4 class="font-semibold text-slate-800 truncate" :title="tour.name" x-text="tour.name"></h4>
              <p class="text-sm text-slate-600 text-ellipsis line-clamp-2 mt-1" x-text="tour.description"></p>
              <div class="mt-auto pt-3 flex justify-between items-center text-xs text-slate-500">
                <span class="inline-block bg-sky-100 text-sky-700 font-bold px-3 py-1 rounded-full" x-text="`${tour.days}D/${tour.nights}N`"></span>
                <span class="font-bold text-sky-700" x-text="tour.price ? '₱' + Number(tour.price).toLocaleString('en-PH') : 'TBD'"></span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
    <div x-show="getFilteredTours().filter(t => t.is_favorite).length === 0" class="flex flex-col items-center justify-center py-12 text-gray-500">
      <p class="text-sm text-slate-500 italic">No favorites match your filters.</p>
    </div>
  <?php endif; ?>
</div>


<!-- 📋 Other Packages -->
<div class="max-w-7xl mx-auto mt-12">
  <h3 class="text-slate-600 text-sm font-semibold mb-2 uppercase tracking-wider">
    Other Packages
  </h3>

  <div
    x-show="getFilteredTours().filter(t => !t.is_favorite).length > 0"
    class="max-h-[28rem] overflow-y-auto divide-y divide-gray-100 border border-gray-200 rounded-md bg-white shadow-sm"
  >
    <template x-for="tour in getFilteredTours()" :key="tour.id">
      <template x-if="!tour.is_favorite">
        <!-- Row -->
        <div
          @click="$store.tourModal.openModal(tour.id)"
          class="cursor-pointer p-4 hover:bg-slate-50 transition grid grid-cols-1 sm:grid-cols-[auto,1fr,auto] gap-4 items-start"
        >
          <!-- Image -->
          <img
            :src="tour.image"
            alt="Tour"
            class="w-16 h-16 object-cover rounded shrink-0 self-start"
          />

          <!-- Text -->
          <div class="min-w-0 max-w-xl self-start">
            <h4 class="font-semibold text-slate-800" x-text="tour.name"></h4>
            <p
              class="text-sm text-slate-600 line-clamp-2"
              x-text="tour.description"
            ></p>
          </div>

          <!-- Price & Duration -->
          <div class="text-right ml-4 shrink-0 self-start">
            <p
              class="font-bold text-sky-700"
              x-text="tour.price ? '₱' + Number(tour.price).toLocaleString('en-PH') : 'TBD'"
            ></p>
            <p
              class="text-xs font-bold mt-1 text-slate-500"
              x-text="`${tour.days}D/${tour.nights}N`"
            ></p>
          </div>
        </div>
      </template>
    </template>
  </div>
</div>

<?php if (empty($allToursForJS)): ?>
  <div class="flex flex-col items-center justify-center py-12 text-gray-500">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mb-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7" />
    </svg>
    <h3 class="text-md italic font-semibold text-sky-700 mb-1">No Tour Packages Found</h3>
    <p class="text-md text-sky-700 italic text-center max-w-lg">
      You haven’t added any tour packages yet.</br>Use the <strong>Add Tour Package</strong> button above to get started!
    </p>
  </div>
<?php endif; ?>
      </div>
    </main>
  </div>
  
  
<!-- 🔲 Add Tour Modal -->
<div id="addModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-black/40 px-3 sm:px-4">
  <div class="w-full max-w-5xl overflow-hidden transition-all p-0 sm:p-6 relative">
    <!-- ✅ Scrollable Content -->
    <div id="addModalContent" class="overflow-y-auto max-h-[90vh] relative">
      <?php include '../components/add_tour_package.php'; ?>
    </div>
  </div>
</div>



<!-- 👁️ Tour View Modal (include once, Alpine will control it) -->
<?php include __DIR__ . '/../components/tour_modal.php'; ?>

</body>
</html>