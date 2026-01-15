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
  SELECT id, full_name, email, phone_number, address, access_code, client_profile_photo 
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

<div x-cloak class="font-sans">
  <h2 class="text-xl font-semibold text-gray-800 mb-6">Edit Client Profile</h2>

  <form method="POST" action="../actions/process_edit_client.php" enctype="multipart/form-data"
        class="space-y-8"
        x-data="{
          previewUrl: '<?= addslashes($profileImage) ?>',
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
          }
        }"
        @dragover.prevent @drop.prevent="handleFile($event)">

    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">

    <!-- Two-Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">

      <!-- LEFT COLUMN: Profile Photo + Email + Phone Number -->
      <div class="space-y-6">

        <!-- Profile Photo Upload -->
        <div class="flex flex-col items-center gap-3 border-2 border-dashed border-gray-300 rounded-xl py-4 px-6 bg-gray-50 hover:border-sky-500 hover:bg-sky-50 transition-all cursor-pointer">
          <img :src="previewUrl" alt="Profile Preview"
               class="w-24 h-24 rounded-full object-cover border-4 border-sky-100 shadow-sm" loading="lazy" />

          <label for="edit-client-photo" class="text-xs font-medium text-sky-600 hover:text-sky-800 cursor-pointer">
            Upload Photo
            <input id="edit-client-photo" name="client_profile_photo" type="file"
                   accept=".jpg,.jpeg,.png" class="hidden" @change="handleFile">
          </label>

          <p class="text-xs text-gray-500">JPG/PNG, max 2MB</p>
        </div>

        <!-- Email -->
        <div>
          <label for="edit_email" class="block text-sm font-semibold text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
          <input id="edit_email" type="email" name="email" value="<?= htmlspecialchars($client['email']) ?>" required
                 placeholder="e.g., maria@example.com"
                 class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition" />
        </div>

        <!-- Phone Number -->
        <div>
          <label for="edit_phone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number <span class="text-red-500">*</span></label>
          <input id="edit_phone" type="tel" name="phone_number" value="<?= htmlspecialchars($client['phone_number']) ?>" required maxlength="11"
                 placeholder="e.g., 09171234567"
                 class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition" />
        </div>

      </div>

      <!-- RIGHT COLUMN: Full Name + Access Code + Address -->
      <div class="space-y-6">

        <!-- Full Name -->
        <div>
          <label for="edit_full_name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name <span class="text-red-500">*</span></label>
          <input id="edit_full_name" type="text" name="full_name" value="<?= htmlspecialchars($client['full_name']) ?>" required
                 placeholder="e.g., Maria Reyes"
                 class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition" />
        </div>

        <!-- Access Code (readonly with copy button) -->
        <div class="relative">
          <div class="flex items-center gap-2 mb-2">
            <label class="text-sm font-semibold text-gray-700">Access Code</label>
            <?= renderTooltipIcon('access_code', $tooltips ?? []) ?>
          </div>
          <div class="relative">
            <input type="text" value="<?= htmlspecialchars($client['access_code'] ?? '') ?>" readonly
                   class="w-full border border-gray-300 rounded-lg px-4 py-3 font-mono tracking-wider bg-gray-100 text-gray-700 pr-12 text-sm focus:ring-2 focus:ring-sky-500" />
            <button type="button"
                    @click="navigator.clipboard.writeText('<?= addslashes($client['access_code'] ?? '') ?>'); $el.nextElementSibling.style.display = 'block'; setTimeout(() => $el.nextElementSibling.style.display = 'none', 2000)"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-sky-600 transition hover:scale-110"
                    aria-label="Copy access code">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
              </svg>
            </button>
            <span class="hidden absolute -top-8 right-0 text-xs text-white font-medium bg-sky-600 px-3 py-1.5 rounded shadow">
              Copied!
            </span>
          </div>
        </div>

        <!-- Address -->
        <div>
          <label for="edit_address" class="block text-sm font-semibold text-gray-700 mb-2">Address <span class="text-red-500">*</span></label>
          <textarea id="edit_address" name="address" required rows="5" placeholder="e.g., 123 Rizal St, Barangay Mabini, Olongapo City"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm resize-none placeholder:text-gray-400 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition">
<?= htmlspecialchars($client['address']) ?></textarea>
        </div>

      </div>
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