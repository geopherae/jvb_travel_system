<?php
// 🔐 Admin session check
if (!isset($_SESSION['admin']['id'])) {
  header("Location: admin_login.php");
  exit();
}

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/tooltip_render.php';

$clientId = intval($editClientId ?? 0);
if (!$clientId) {
  echo "Client ID missing.";
  exit();
}

// ✅ Fetch client info
$stmt = $conn->prepare("
  SELECT id, full_name, email, phone_number, address, access_code, client_profile_photo, companions_json, status 
  FROM clients 
  WHERE id = ?
");
$stmt->bind_param("i", $clientId);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

if (!$client) {
  die("Client not found.");
}

// 🖼️ Determine profile image
$profileImage = (!empty($client['client_profile_photo']) && file_exists(__DIR__ . '/../uploads/client_profiles/' . $client['client_profile_photo']))
  ? '../uploads/client_profiles/' . rawurlencode($client['client_profile_photo'])
  : '../images/default_client_profile.png';

include_once '../components/status_alert.php';
?>

<div x-cloak class="font-sans" x-data="{ activeTab: 'lead' }">
  <h2 class="text-lg font-semibold text-slate-800 mb-1">Edit Guest Profile</h2>
      <p class="text-sm text-slate-600 mb-4">
      Update your guests' details and manage their profile information here.
    </p>

  <!-- Tab Navigation -->
  <div class="flex border-b mb-6">
    <button type="button"
            @click="activeTab = 'lead'"
            :class="activeTab === 'lead' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'"
            class="px-5 py-3 text-sm font-medium transition">
      Lead Guest
    </button>
    <button type="button"
            @click="activeTab = 'companions'"
            :class="activeTab === 'companions' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'"
            class="px-5 py-3 text-sm font-medium transition">
      Guest Companions
    </button>
  </div>

<form method="POST" action="../actions/process_edit_client.php" enctype="multipart/form-data"
      class="space-y-8"
      x-data="{
        previewUrl: '<?= addslashes($profileImage) ?>',
        companions: <?= !empty($client['companions_json']) ? htmlspecialchars($client['companions_json'], ENT_QUOTES, 'UTF-8') : '[]' ?>,
        copied: false,
        handleFile(e) {
          let file = e.target.files ? e.target.files[0] : e.dataTransfer?.files[0];
          if (!file) return;
          if (file.size > 2 * 1024 * 1024) {
            alert('File must be under 2MB');
            return;
          }
          const reader = new FileReader();
          reader.onload = ev => this.previewUrl = ev.target.result;
          reader.readAsDataURL(file);
        },
        addCompanion() {
          if (this.companions.length < 10) {
            this.companions.push('');
          }
        },
        removeCompanion(index) {
          this.companions.splice(index, 1);
        }
      }"
      @dragover.prevent @drop.prevent="handleFile($event)">



    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">

<!-- Lead Guest Tab -->
<div x-show="activeTab === 'lead'" x-transition>
  <!-- Two-Column Layout (Responsive) -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
    
    <!-- LEFT COLUMN: Profile Photo + Email + Phone Number -->
    <div class="space-y-4 sm:space-y-5">

      <!-- Profile Photo - Modern -->
      <div x-data="{
        fileName: '',
        previewUrl: '<?= addslashes($profileImage) ?>',
        handleFile(e) {
          let file = e.target.files ? e.target.files[0] : e.dataTransfer?.files[0];
          if (!file) return;
          if (file.size > 2 * 1024 * 1024) {
            alert('File must be under 2MB');
            return;
          }
          this.fileName = file.name;
          const reader = new FileReader();
          reader.onload = ev => this.previewUrl = ev.target.result;
          reader.readAsDataURL(file);
        }
      }"
      @dragover.prevent @drop.prevent="handleFile($event)"
      class="relative flex flex-col items-center gap-2 sm:gap-3 border-2 border-dashed border-sky-200 rounded-xl sm:rounded-2xl py-4 sm:py-5 px-3 sm:px-4 bg-gradient-to-br from-sky-50 to-transparent hover:border-sky-400 hover:from-sky-100 transition-all cursor-pointer group">

        <!-- Decorative corner accent -->
        <div class="absolute top-0 right-0 w-8 sm:w-12 h-8 sm:h-12 bg-sky-500 opacity-5 rounded-bl-xl sm:rounded-bl-2xl"></div>

        <!-- Image -->
        <img :src="previewUrl" alt="Profile Preview"
             class="w-16 sm:w-20 h-16 sm:h-20 rounded-lg sm:rounded-xl object-cover border-2 border-sky-100 shadow-sm group-hover:shadow-md transition-shadow" loading="lazy" />

        <!-- Upload label -->
        <label for="edit-client-photo" class="text-center cursor-pointer">
          <div class="flex items-center justify-center mb-1.5 sm:mb-2">
            <svg class="w-4 sm:w-5 h-4 sm:h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
          </div>
          <p class="text-xs sm:text-sm font-semibold text-sky-600 group-hover:text-sky-700">Upload Photo</p>
          <p class="text-xs text-gray-500 mt-0.5 sm:mt-1">JPG, PNG • Max 2MB</p>
          <input id="edit-client-photo" name="client_profile_photo" type="file"
                 accept=".jpg,.jpeg,.png" class="hidden" @change="handleFile">
        </label>
      </div>

      <!-- Email -->
      <div>
        <label for="edit_email"
               class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">
          <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
          </svg>
          Email <span class="text-red-500">*</span>
        </label>
        <input id="edit_email" type="email" name="email" value="<?= htmlspecialchars($client['email']) ?>" required
               placeholder="maria@example.com"
               class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
      </div>

      <!-- Phone Number -->
      <div>
        <label for="edit_phone"
               class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">
          <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
          </svg>
          Phone Number <span class="text-red-500">*</span>
        </label>
        <input id="edit_phone" type="tel" name="phone_number" value="<?= htmlspecialchars($client['phone_number']) ?>" required maxlength="11"
               placeholder="09171234567"
               class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
      </div>

    </div>

    <!-- RIGHT COLUMN: Full Name + Access Code + Address -->
    <div class="space-y-6">
      <!-- Full Name -->
      <div>
        <label for="edit_full_name"
               class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">
          <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          Full Name <span class="text-red-500">*</span>
        </label>
        <input id="edit_full_name" type="text" name="full_name"
               value="<?= htmlspecialchars($client['full_name']) ?>" required
               placeholder="e.g., Maria Reyes"
               class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
      </div>

      <!-- Access Code -->
      <div>
        <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5 sm:mb-2">
          <label for="edit_access_code"
                 class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-gray-700">
            <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox
                  d="M12 11c0-3.866-3.582-7-8-7m16 0c-4.418 0-8 3.134-8 7m0 0v10m0-10c0 1.657 1.343 3 3 3h5a3 3 0 003-3m-11 10a3 3 0 01-3-3m6 3a3 3 0 003-3m-6 3v2m6-5v2m-6 1h6"></path>
          </svg>
          </label>
          <?= renderTooltipIcon('access_code', $tooltips ?? []) ?>
        </div>
        <div class="relative">
          <input id="edit_access_code" type="text"
                 value="<?= htmlspecialchars($client['access_code'] ?? '') ?>" readonly
                 class="w-full border-2 border-sky-200 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 font-mono text-xs sm:text-sm font-bold text-sky-700 bg-sky-50 pr-10 sm:pr-12 transition hover:border-sky-300 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
          <button type="button"
                  @click="navigator.clipboard.writeText('<?= addslashes($client['access_code'] ?? '') ?>'); copied = true; setTimeout(() => copied = false, 1500)"
                  class="absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-sky-600 transition hover:scale-125 p-1"
                  aria-label="Copy access code">
            <svg class="w-4 sm:w-5 h-4 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
            </svg>
          </button>
          <span x-show="copied" x-transition x-cloak
                class="absolute -top-8 right-0 text-xs text-white font-medium bg-sky-600 px-2.5 py-1 rounded shadow whitespace-nowrap flex items-center gap-1">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd"
                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                    clip-rule="evenodd"></path>
            </svg>
            Copied!
          </span>
        </div>
        <p class="text-xs text-gray-500 mt-1.5 sm:mt-2">Share this code with the client for quick access.</p>
      </div>

      <!-- Address -->
      <div>
        <label for="edit_address"
               class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">
          <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
          </svg>
          Address <span class="text-red-500">*</span>
        </label>
        <textarea id="edit_address" name="address" required rows="5"
                  placeholder="e.g., 123 Rizal St, Barangay Mabini, Olongapo City"
                  class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm resize-none placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"><?= htmlspecialchars($client['address']) ?></textarea>
      </div>
    </div>
  </div>
</div>

<!-- 👥 Guest Companions Tab -->
<div x-show="activeTab === 'companions'" x-cloak class="space-y-4 max-h-[500px] overflow-y-auto text-sm">

  <!-- Disclaimer + Counter -->
  <div class="flex items-center justify-between">
    <p class="text-sm text-gray-500">
      These are the guests that will accompany the Lead Guest on the trip.<br>
      <span class="font-semibold">You may add up to 10 companions.</span>
    </p>
    <span class="font-semibold text-xs text-purple-800 bg-purple-100 px-2 py-1 rounded">
      <span x-text="companions.length"></span> / 10 added
    </span>
  </div>

  <!-- Companions List -->
  <div :class="companions.length >= 5 ? 'grid grid-cols-1 sm:grid-cols-2 gap-3' : 'space-y-3'">
    <template x-for="(companion, index) in companions" :key="index">
      <div class="border rounded-lg shadow-sm bg-slate-50 px-4 py-3 flex items-center gap-2 group">
        <input type="text"
               :name="`companions[${index}]`"
               x-model="companions[index]"
               placeholder="Companion name"
               class="flex-1 border px-2 py-1 text-sm rounded placeholder:text-gray-400 focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
        <button type="button" @click="removeCompanion(index)"
                class="opacity-0 group-hover:opacity-100 transition shrink-0"
                aria-label="Remove companion">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-500 hover:text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M10 3h4a1 1 0 011 1v1H9V4a1 1 0 011-1z" />
          </svg>
        </button>
      </div>
    </template>
  </div>

  <!-- Add Companion Button -->
  <button type="button"
          @click="addCompanion()"
          :disabled="companions.length >= 10"
          :class="companions.length >= 10 ? 'opacity-50 cursor-not-allowed' : 'text-sky-600 hover:underline'"
          class="pb-4 text-sm font-medium">
    + Add Companion
  </button>

  <!-- Hidden field to submit JSON -->
  <input type="hidden" name="companions_json" :value="JSON.stringify(companions)">
</div>
    <!-- Submit Buttons -->
    <div class="flex justify-end items-center gap-4 pt-6 border-t border-gray-200">
      <button type="button"
              @click="$store.modals.editClient = false"
              class="text-slate-500 hover:underline text-sm">
        Cancel
      </button>
      <button type="submit"
              class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-700 transition font-medium">
        Save Changes
      </button>
    </div>
  </form>
</div>