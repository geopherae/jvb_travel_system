<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');
require_once __DIR__ . '/../actions/db.php';

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
  echo '<p class="text-red-600 text-center py-16 text-lg">Invalid user ID.</p>';
  exit;
}

$stmt = $conn->prepare("
  SELECT id, first_name, last_name, email, username, phone_number, 
         messenger_link, admin_profile, admin_photo 
  FROM admin_accounts 
  WHERE id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo '<p class="text-red-600 text-center py-16 text-lg">User not found.</p>';
  exit;
}

$user = $result->fetch_assoc();
$stmt->close();

$profileJson = json_decode($user['admin_profile'] ?? '', true) ?: [];
$userBio = $profileJson['bio'] ?? '';

$photoUrl = !empty($user['admin_photo'])
  ? "../uploads/admin_photo/" . $user['admin_photo'] . '?v=' . time()
  : '../images/default_client_profile.png';
?>

<div class="relative">
  <div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold">Edit Admin User</h2>
  </div>

  <form id="edit-admin-form"
        method="POST"
        action="../actions/process_edit_admin_user.php"
        enctype="multipart/form-data"
        @submit="handleSubmit"
        x-data="editAdminStepper"
        class="space-y-6">

    <input type="hidden" name="csrf_token_modal" value="<?= $_SESSION['csrf_token_modal'] ?>">
    <input type="hidden" name="edit_id" value="<?= $user['id'] ?>">
    <input type="hidden" name="edit_admin_profile_json">

    <!-- STEP 1: Profile Information -->
    <div x-show="step === 1" x-transition>
      <div class="bg-white border rounded-lg p-6 space-y-4 shadow-sm">
        <div class="border-b pb-2 px-2 flex items-center justify-between">
          <h3 class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Profile Information</h3>
          <p class="text-xs text-gray-400 uppercase">Step <span x-text="step"></span> of 2</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <!-- Profile Photo Preview -->
          <div x-data="addAdminPhotoPreview('<?= $photoUrl ?>')" class="flex flex-col items-center gap-2 border-2 border-dashed rounded-lg py-2 px-3 bg-gray-50 hover:border-blue-400 hover:bg-blue-50 transition">
            <img :src="previewUrl" alt="Profile Preview"
                 class="w-20 h-20 rounded-full object-cover border-2 border-blue-500 shadow"
                 loading="lazy" />
            <label for="edit-admin-photo" class="text-xs font-medium text-blue-700 hover:underline cursor-pointer">
              Click to change photo
              <input id="edit-admin-photo" name="edit_admin_photo" type="file"
                     accept=".jpg,.jpeg,.png,.webp"
                     class="hidden"
                     @change="handleFile">
            </label>
            <p class="text-[10px] text-gray-400" x-text="fileName || 'Accepted: JPG, PNG, WEBP. Max 2MB.'"></p>
          </div>

          <!-- First & Last Name -->
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
              <input type="text" name="edit_first_name" x-model="form.edit_first_name"
                     :required="step === 1"
                     placeholder="e.g., Justine"
                     class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                     :class="{ 'border-red-500': errors.edit_first_name }" />
              <p x-show="errors.edit_first_name" class="text-xs text-red-500 mt-1" x-text="errors.edit_first_name"></p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
              <input type="text" name="edit_last_name" x-model="form.edit_last_name"
                     :required="step === 1"
                     placeholder="e.g., Dela Cruz"
                     class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                     :class="{ 'border-red-500': errors.edit_last_name }" />
              <p x-show="errors.edit_last_name" class="text-xs text-red-500 mt-1" x-text="errors.edit_last_name"></p>
            </div>
          </div>
        </div>

        <!-- Email & Phone -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
            <input type="email" name="edit_email" x-model="form.edit_email"
                   :required="step === 1"
                   placeholder="e.g., justine@email.com"
                   class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                   :class="{ 'border-red-500': errors.edit_email }" />
            <p x-show="form.edit_email && !isValidEmail()" class="text-xs text-red-500 mt-1">
              Please enter a valid email address.
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
            <input type="tel" name="edit_phone_number" x-model="form.edit_phone_number"
                   maxlength="11" placeholder="e.g., 09171234567"
                   class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400" />
            <p x-show="form.edit_phone_number && !isValidPhone()" class="text-xs text-red-500 mt-1">
              Must be 11 digits starting with 09.
            </p>
          </div>
        </div>

        <!-- Messenger & Profile -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Messenger Link</label>
            <input type="text" name="edit_messenger_link" x-model="form.edit_messenger_link"
                   placeholder="https://m.me/yourname"
                   class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Admin Profile</label>
            <input type="text" name="edit_admin_profile" x-model="form.edit_admin_profile"
                   class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                   placeholder="Short description (1 line)" />
          </div>
        </div>

        <div class="text-right pt-4">
          <button type="button" @click="nextStep"
                  :disabled="!isStep1Complete()"
                  class="px-4 py-2 rounded transition font-medium"
                  :class="{
                    'bg-sky-600 text-white hover:bg-sky-700': isStep1Complete(),
                    'bg-gray-300 text-gray-500 cursor-not-allowed': !isStep1Complete()
                  }">
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- STEP 2: Login Credentials -->
    <div x-show="step === 2" x-transition>
      <div class="bg-white border rounded-lg p-6 space-y-4 shadow-sm">
        <div class="border-b pb-2 px-2 flex items-center justify-between">
          <h3 class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Login Credentials</h3>
          <p class="text-xs text-gray-400 uppercase">Step <span x-text="step"></span> of 2</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-gray-100 rounded-lg overflow-hidden shadow-sm flex items-center justify-center">
            <img src="../images/default_trip_cover.jpg" alt="Trip Cover Preview"
                 class="w-full h-auto max-h-64 object-cover rounded">
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
              <input type="text" name="edit_username" x-model="form.edit_username"
                     :required="step === 2"
                     placeholder="e.g., justine_admin"
                     class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                     :class="{ 'border-red-500': errors.edit_username }" />
              <p x-show="errors.edit_username" class="text-xs text-red-500 mt-1" x-text="errors.edit_username"></p>
              <p class="text-xs text-gray-400 mt-1">
                Use at least 12 characters, mix uppercase and symbols for stronger security.
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                New Password <em class="text-gray-500 text-xs">(leave blank to keep current)</em>
              </label>
              <input type="password" name="edit_new_password" x-model="form.edit_new_password"
                     placeholder="Leave blank to keep current"
                     class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                     @input="validatePassword(form.edit_new_password)" />
              <p class="text-xs text-gray-500 mt-1">Strength: <span x-text="passwordStrength"></span></p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
              <input type="password" name="edit_confirm_password" x-model="form.edit_confirm_password"
                     placeholder="Repeat new password"
                     class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                     :class="{ 'border-red-500': errors.edit_confirm_password }" />
              <p x-show="errors.edit_confirm_password" class="text-xs text-red-500 mt-1" x-text="errors.edit_confirm_password"></p>
              <p x-show="form.edit_confirm_password && form.edit_new_password !== form.edit_confirm_password"
                 class="text-xs text-red-500 mt-1">
                Passwords do not match.
              </p>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between pt-4">
          <button type="button" @click="step = 1"
                  class="text-sm text-gray-500 hover:underline">
            ← Previous
          </button>

          <div>
            <button type="submit"
                    :disabled="!isStep2Complete()"
                    class="px-4 py-2 rounded transition font-medium"
                    :class="{
                      'bg-green-600 text-white hover:bg-green-700': isStep2Complete(),
                      'bg-gray-300 text-gray-500 cursor-not-allowed': !isStep2Complete()
                    }">
              Save Changes
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>

  <!-- Reliable pre-fill after Alpine initializes -->
  <script>
    document.addEventListener('alpine:initialized', () => {
      const formEl = document.getElementById('edit-admin-form');
      if (!formEl) return;

      const scope = Alpine.$data(formEl);
      if (scope && scope.form) {
        scope.form.edit_first_name = <?= json_encode($user['first_name']) ?>;
        scope.form.edit_last_name = <?= json_encode($user['last_name']) ?>;
        scope.form.edit_email = <?= json_encode($user['email']) ?>;
        scope.form.edit_username = <?= json_encode($user['username']) ?>;
        scope.form.edit_phone_number = <?= json_encode($user['phone_number'] ?? '') ?>;
        scope.form.edit_messenger_link = <?= json_encode($user['messenger_link'] ?? '') ?>;
        scope.form.edit_admin_profile = <?= json_encode($userBio) ?>;
        scope.form.edit_new_password = '';
        scope.form.edit_confirm_password = '';
        scope.passwordStrength = '—';
      }
    });
  </script>
</div>