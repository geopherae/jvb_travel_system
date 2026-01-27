<?php
// 🔐 Admin session check
if (!isset($_SESSION['admin']['id'])) {
  header("Location: admin_login.php");
  exit();
}

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/feature_flags.php';
require_once __DIR__ . '/../includes/tooltip_render.php';

$clientId = intval($editClientId ?? 0);
if (!$clientId) {
  echo "Client ID missing.";
  exit();
}

// ✅ Fetch client info (expanded to align with add_client_form fields)
$stmt = $conn->prepare("\n  SELECT id, full_name, email, phone_number, address, access_code, client_profile_photo, companions_json, status,\n         processing_type, assigned_package_id, booking_number, trip_date_start, trip_date_end, booking_date,\n         passport_number, passport_expiry, assigned_admin_id\n  FROM clients\n  WHERE id = ?\n");
$stmt->bind_param("i", $clientId);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

if (!$client) {
  die("Client not found.");
}

// Load admins and packages like add_client_form
$admins = $conn->query("SELECT id, first_name, last_name FROM admin_accounts WHERE id != 1 ORDER BY first_name ASC");
$pkg_stmt = $conn->prepare("\n  SELECT id, package_name, day_duration, night_duration, price, is_deleted\n  FROM tour_packages\n  ORDER BY package_name ASC\n");
$pkg_stmt->execute();
$packages = $pkg_stmt->get_result();

// Preview packages (non-deleted)
$all_packages_for_preview = [];
$pkg_preview_stmt = $conn->prepare("\n  SELECT id, package_name, day_duration, night_duration, price, tour_cover_image\n  FROM tour_packages\n  WHERE is_deleted = 0\n  ORDER BY package_name ASC\n");
$pkg_preview_stmt->execute();
$pkg_preview_result = $pkg_preview_stmt->get_result();
while ($pkg = $pkg_preview_result->fetch_assoc()) {
  $all_packages_for_preview[] = $pkg;
}
$pkg_preview_stmt->close();

// 🖼️ Determine profile image
$profileImage = (!empty($client['client_profile_photo']) && file_exists(__DIR__ . '/../uploads/client_profiles/' . $client['client_profile_photo']))
  ? '../uploads/client_profiles/' . rawurlencode($client['client_profile_photo'])
  : '../images/default_client_profile.png';

include_once '../components/status_alert.php';
?>

<script>
  function editClientForm() {
    return {
      tab: 'basic',
      step: 1,
      fullName: '<?= htmlspecialchars($client['full_name']) ?>',
      email: '<?= htmlspecialchars($client['email']) ?>',
      phone: '<?= htmlspecialchars($client['phone_number']) ?>',
      address: '<?= htmlspecialchars($client['address']) ?>',
      accessCode: '<?= addslashes($client['access_code'] ?? '') ?>',
      bookingNumber: '<?= htmlspecialchars($client['booking_number'] ?? '') ?>',
      assignedPackage: '<?= htmlspecialchars($client['assigned_package_id'] ?? '') ?>',
      passportNumber: '<?= htmlspecialchars($client['passport_number'] ?? '') ?>',
      passportExpiry: '<?= htmlspecialchars($client['passport_expiry'] ?? '') ?>',
      assignedAdmin: '<?= htmlspecialchars($client['assigned_admin_id'] ?? ($_SESSION['admin']['id'] ?? '')) ?>',
      tripStart: '<?= htmlspecialchars($client['trip_date_start'] ?? '') ?>',
      tripEnd: '<?= htmlspecialchars($client['trip_date_end'] ?? '') ?>',
      bookingDate: '<?= htmlspecialchars($client['booking_date'] ?? '') ?>',
      allPackages: <?= json_encode($all_packages_for_preview) ?>,
      selectedPackageDetails: {},
      getPackageBannerUrl() {
        if (this.selectedPackageDetails.tour_cover_image) {
          return `../images/tour_packages_banners/${this.selectedPackageDetails.tour_cover_image.replace(/^\/+/, '')}`;
        }
        return '';
      },
      updatePackageDetails() {
        this.selectedPackageDetails = this.allPackages.find(p => p.id == this.assignedPackage) || {};
      }
    }
  }
</script>
  <form method="POST" action="../actions/process_edit_client.php" enctype="multipart/form-data"
        class="flex flex-col h-full font-sans"
        x-data="editClientForm()"
        @submit="$el.classList.add('submitting')">

    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">

    <!-- 🧭 Tabs -->
    <div class="border-b border-slate-200 px-4 sm:px-6 pt-4">
      <nav class="flex space-x-4 text-sm font-medium">
        <button type="button" @click="tab = 'basic'"
                :class="tab === 'basic' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="py-2 border-b-2 transition">
          Client Basic Info
        </button>
        <button type="button" @click="tab = 'companions'"
                :class="tab === 'companions' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="py-2 border-b-2 transition">
          Guest Companions
        </button>
      </nav>
    </div>

    <!-- Client Basic Info -->
    <div x-show="tab === 'basic'" class="px-4 py-4 sm:p-6 space-y-3 sm:space-y-4">

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4">
        <!-- LEFT: Photo + Email + Phone -->
        <div class="space-y-3 sm:space-y-6">
          <div x-data="{ fileName: '', previewUrl: '<?= addslashes($profileImage) ?>', handleFile(e) { let file = e.target.files ? e.target.files[0] : e.dataTransfer?.files[0]; if (!file) return; if (file.size > 2 * 1024 * 1024) { alert('File must be under 2MB'); return; } this.fileName = file.name; const reader = new FileReader(); reader.onload = ev => this.previewUrl = ev.target.result; reader.readAsDataURL(file); } }"
               @dragover.prevent @drop.prevent="handleFile($event)"
               class="relative flex flex-col items-center gap-1.5 sm:gap-2 border-2 border-dashed border-sky-200 rounded-lg sm:rounded-xl py-3 sm:py-4 px-2 sm:px-3 bg-gradient-to-br from-sky-50 to-transparent hover:border-sky-400 hover:from-sky-100 transition-all cursor-pointer group">
            <div class="absolute top-0 right-0 w-8 sm:w-12 h-8 sm:h-12 bg-sky-500 opacity-5 rounded-bl-xl sm:rounded-bl-2xl"></div>
            <img :src="previewUrl" alt="Profile Preview"
                 class="w-12 sm:w-16 h-12 sm:h-16 rounded-lg sm:rounded-lg object-cover border-2 border-sky-100 shadow-sm group-hover:shadow-md transition-shadow" loading="lazy" />
            <label for="edit-client-photo" class="text-center cursor-pointer">
              <div class="flex items-center justify-center mb-1.5 sm:mb-2">
                <svg class="w-4 sm:w-5 h-4 sm:h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
              </div>
              <p class="text-xs font-semibold text-sky-600 group-hover:text-sky-700">Upload Photo</p>
              <p class="text-xs text-gray-500 mt-0.5">Max 2MB</p>
              <input id="edit-client-photo" name="client_profile_photo" type="file"
                     accept=".jpg,.jpeg,.png" class="hidden" @change="handleFile">
            </label>
          </div>

          <div class="relative">
            <label for="edit_email" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">Email <span class="text-red-500">*</span></label>
            <input id="edit_email" type="email" name="email" x-model="email" required placeholder="maria@example.com"
                   class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
          </div>

          <div class="relative">
            <label for="edit_phone" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">Phone <span class="text-red-500">*</span></label>
            <input id="edit_phone" type="tel" name="phone_number" x-model="phone" required maxlength="11" placeholder="09171234567"
                   class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
          </div>
        </div>

        <!-- RIGHT: Processing Type + Full Name + Access Code -->
        <div class="space-y-3 sm:space-y-6">
          <div class="relative">
            <label for="processing_type" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">Processing Type <span class="text-red-500">*</span></label>
            <select id="processing_type" name="processing_type" required
                    <?php if (!VISA_PROCESSING_ENABLED) echo ''; ?>
                    class="bg-white w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent <?php if (!VISA_PROCESSING_ENABLED) echo 'bg-white/100 cursor-not-allowed'; ?>">
              <option value="booking" <?= ($client['processing_type'] ?? 'booking') === 'booking' ? 'selected' : '' ?>>Booking Only</option>
              <?php if (VISA_PROCESSING_ENABLED): ?>
              <option value="visa" <?= ($client['processing_type'] ?? '') === 'visa' ? 'selected' : '' ?>>Visa Processing</option>
              <option value="both" <?= ($client['processing_type'] ?? '') === 'both' ? 'selected' : '' ?>>Both Booking & Visa</option>
              <?php endif; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1.5">
              <?php if (VISA_PROCESSING_ENABLED): ?>
              Select the type of service this client will use.
              <?php else: ?>
              Only Travel Bookings are currently accepted.
              <?php endif; ?>
            </p>
          </div>

          <div class="relative">
            <label for="edit_full_name" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">Lead Guest Full Name <span class="text-red-500">*</span></label>
            <input id="edit_full_name" type="text" name="full_name" x-model="fullName" required placeholder="Maria Reyes"
                   class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
          </div>

          <div class="pt-[0.75px]">
            <div class="relative">
              <div class="rounded z-10 absolute top-0 left-3 -translate-y-1/2 bg-white px-1 flex items-center gap-1.5">
                <label for="edit_access_code" class="z-10 text-xs font-semibold text-gray-700">Access Code</label>
                <?= renderTooltipIcon('access_code', $tooltips ?? []) ?>
              </div>
              <div class="relative">
                <input id="edit_access_code" type="text" x-model="accessCode" readonly
                       class="w-full border-2 border-sky-200 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 font-mono text-xs sm:text-sm font-bold text-sky-700 bg-sky-50 pr-10 sm:pr-12 transition hover:border-sky-300 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
                <button type="button" @click="$clipboard(accessCode)" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-sky-600 hover:text-sky-700 transition-colors" aria-label="Copy access code">
                  <svg class="w-4 sm:w-5 h-4 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                  </svg>
                </button>
              </div>
              <p class="text-xs text-gray-500 mt-1.5">Share this code with the client for quick access.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- FULL-WIDTH ADDRESS FIELD -->
      <div class="relative">
        <label for="edit_address" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">Address <span class="text-red-500">*</span></label>
        <textarea id="edit_address" name="address" x-model="address" required rows="2" placeholder="123 Rizal St, Barangay Mabini..."
                  class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm resize-none placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"></textarea>
      </div>
    </div>

    <!-- Guest Companions Section -->
    <div x-show="tab === 'companions'" class="px-4 py-4 sm:p-6 space-y-3 sm:space-y-4">
      <div x-data="{ companions: <?= !empty($client['companions_json']) ? htmlspecialchars($client['companions_json'], ENT_QUOTES, 'UTF-8') : '[]' ?>, add() { if (this.companions.length < 10) this.companions.push(''); }, remove(i) { this.companions.splice(i,1) } }">
        <div class="flex items-center justify-between">
          <p class="text-sm text-gray-500">These are the guests that will accompany the Lead Guest on the trip. <span class="font-semibold">You may add up to 10 companions.</span></p>
          <span class="font-semibold text-xs text-purple-800 bg-purple-100 px-2 py-1 rounded"><span x-text="companions.length"></span> / 10 added</span>
        </div>
        <div :class="companions.length >= 5 ? 'grid grid-cols-1 sm:grid-cols-2 gap-3' : 'space-y-3'" class="mt-3">
          <template x-for="(companion, index) in companions" :key="index">
            <div class="border rounded-lg shadow-sm bg-slate-50 px-4 py-3 flex items-center gap-2 group">
              <input type="text" :name="`companions[${index}]`" x-model="companions[index]" placeholder="Companion name" class="flex-1 border px-2 py-1 text-sm rounded placeholder:text-gray-400 focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
              <button type="button" @click="remove(index)" class="opacity-0 group-hover:opacity-100 transition shrink-0" aria-label="Remove companion">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-500 hover:text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M10 3h4a1 1 0 011 1v1H9V4a1 1 0 011-1z" /></svg>
              </button>
            </div>
          </template>
        </div>
        <button type="button" @click="add()" :disabled="companions.length >= 10" :class="companions.length >= 10 ? 'opacity-50 cursor-not-allowed' : 'text-sky-600 hover:underline'" class="pb-4 text-sm font-medium mt-2">+ Add Companion</button>
        <input type="hidden" name="companions_json" :value="JSON.stringify(companions)">
      </div>
    </div>

    <!-- Submit Buttons -->
     <div class="sticky bottom-0 flex justify-between items-center px-4 py-3 sm:px-6 sm:py-2 bg-gray-50 gap-2 sm:gap-3 z-10 mb-4 sm:mb-2 border-t border-gray-200">
    <div class="flex w-full justify-between gap-2 sm:gap-3">
      <button type="button" @click="$store.modals.editClient = false" class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">Cancel</button>
      <button type="submit" class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 disabled:opacity-50 transition-colors">Save Changes</button>
    </div>
    </div>
    
  </form>