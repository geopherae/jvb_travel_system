<?php
// ✅ Start session and enforce authentication
include_once __DIR__ . '/../admin/admin_session_check.php';
require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

// ✅ Generate CSRF tokens if missing
$_SESSION['csrf_token_settings'] ??= bin2hex(random_bytes(32));
$_SESSION['csrf_token_modal']    ??= bin2hex(random_bytes(32));

// ✅ Include layout and dependencies
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../components/status_alert.php';

// ✅ Load current admin session
$admin = $_SESSION['admin'] ?? [];

$photoFilename = $admin['admin_photo'] ?? '';
$photoPath = !empty($photoFilename)
  ? "../uploads/admin_photo/" . $photoFilename
  : '../images/default_client_profile.png';

$photoUrl = $photoPath . '?v=' . time();

// ✅ Parse admin_profile JSON safely
$profileJson = [];
if (!empty($admin['admin_profile'])) {
  $decoded = json_decode($admin['admin_profile'], true);
  if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
    $profileJson = $decoded;
  }
}
$bio = $profileJson['bio'] ?? '';

// ✅ Determine if current user is a full admin
$isFullAdmin = ($admin['role'] ?? '') === 'admin';

// ✅ Fetch all admin users (using 'id' as primary key)
$sql = "SELECT id, first_name, last_name, username, email, admin_photo, role 
        FROM admin_accounts 
        ORDER BY first_name, last_name";

$result = $conn->query($sql);
$allAdmins = [];

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $allAdmins[] = $row;
  }
}

// ✅ Handle status messages
$modalStatus = $_SESSION['modal_status'] ?? null;
unset($_SESSION['modal_status']);
?>
<!DOCTYPE html>
<html lang="en" class="bg-gray-50">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Profile & Settings</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>

  <!-- Global Alpine Components for Edit Modal -->
  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('editAdminStepper', () => ({
        step: 1,
        form: {
          edit_first_name: '',
          edit_last_name: '',
          edit_email: '',
          edit_username: '',
          edit_phone_number: '',
          edit_messenger_link: '',
          edit_admin_profile: '',
          edit_new_password: '',
          edit_confirm_password: ''
        },
        errors: {},
        passwordStrength: '—',

        isValidEmail() {
          return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.edit_email);
        },
        isValidPhone() {
          return !this.form.edit_phone_number || /^09\d{9}$/.test(this.form.edit_phone_number);
        },
        isStep1Complete() {
          return this.form.edit_first_name.trim() &&
                 this.form.edit_last_name.trim() &&
                 this.isValidEmail();
        },
        isStep2Complete() {
          if (this.form.edit_new_password || this.form.edit_confirm_password) {
            return this.form.edit_username.trim() &&
                   this.form.edit_new_password === this.form.edit_confirm_password;
          }
          return this.form.edit_username.trim();
        },

        validatePassword(pwd) {
          if (!pwd) {
            this.passwordStrength = '—';
            return;
          }
          if (pwd.length >= 12 && /[A-Z]/.test(pwd) && /\W/.test(pwd)) {
            this.passwordStrength = 'Strong';
          } else if (pwd.length >= 8) {
            this.passwordStrength = 'Medium';
          } else {
            this.passwordStrength = 'Weak';
          }
        },

        nextStep() {
          if (this.step === 1 && this.isStep1Complete()) {
            this.step = 2;
          }
        },

        handleSubmit(event) {
          if (!this.isStep2Complete()) {
            event.preventDefault();
            return;
          }

          const profileJson = JSON.stringify({ bio: this.form.edit_admin_profile.trim() });
          document.querySelector('input[name="edit_admin_profile_json"]').value = profileJson;
        },

        init() {
          this.step = 1;
          this.errors = {};
          this.passwordStrength = '—';
        }
      }));

      Alpine.data('addAdminPhotoPreview', (initialUrl) => ({
        previewUrl: initialUrl,
        fileName: '',
        handleFile(e) {
          const file = e.target.files[0];
          if (!file) return;
          this.fileName = file.name;
          const reader = new FileReader();
          reader.onload = ev => this.previewUrl = ev.target.result;
          reader.readAsDataURL(file);
        }
      }));
    });
  </script>

  <!-- Main Controller -->
  <script>
    function adminSettingsController() {
      return {
        sidebarOpen: false,
        showAddUserModal: false,
        showEditUserModal: false,
        editModalContent: '',
        showUserActions: {},

        toastMessage: '',
        showToast: false,
        triggerToast(message) {
          this.toastMessage = message;
          this.showToast = true;
          setTimeout(() => this.showToast = false, 3000);
        },

        openAddUserModal() {
          this.showAddUserModal = true;
        },
        closeAddUserModal() {
          this.showAddUserModal = false;
        },

        openEditModal(userId) {
          this.showEditUserModal = true;
          this.editModalContent = '<div class="text-center py-16"><div class="animate-spin rounded-full h-12 w-12 border-t-4 border-b-4 border-sky-600 inline-block"></div><p class="mt-6 text-gray-600 text-lg">Loading edit form...</p></div>';

          fetch(`../actions/edit_admin_user.php?id=${userId}`)
            .then(response => {
              if (!response.ok) throw new Error('Failed to load');
              return response.text();
            })
            .then(html => {
              this.editModalContent = html;
            })
            .catch(() => {
              this.editModalContent = '<p class="text-red-600 text-center py-16 text-lg">Failed to load edit form.</p>';
            });

          this.hideAllUserActions();
        },
        closeEditModal() {
          this.showEditUserModal = false;
          this.editModalContent = '';
        },

        toggleUserActions(userId, event) {
          event.stopPropagation();
          this.showUserActions[userId] = !this.showUserActions[userId];
        },
        hideAllUserActions() {
          this.showUserActions = {};
        },

        init() {
          document.addEventListener('click', () => this.hideAllUserActions());

          <?php if ($modalStatus): ?>
            <?php
              $messages = [
                'add_admin_success'     => 'New admin user added successfully!',
                'edit_admin_success'    => 'Admin user updated successfully!',
                'duplicate_email'       => 'Email already in use.',
                'duplicate_username'    => 'Username already taken.',
                'password_mismatch'     => 'Passwords do not match.',
                'weak_password'         => 'Password is too weak.',
                'csrf_error'            => 'Security error.',
                'update_failed'         => 'Failed to update user.'
              ];
              $toastMsg = $messages[$modalStatus] ?? 'Operation completed.';
            ?>
            this.triggerToast(<?= json_encode($toastMsg) ?>);
          <?php endif; ?>
        }
      };
    }
  </script>
</head>

<body x-data="adminSettingsController()" x-init="init()">

  <button @click="sidebarOpen = !sidebarOpen"
          class="p-3 md:hidden absolute top-4 left-4 z-30 bg-sky-600 text-white rounded">
    ☰
  </button>

  <?php include '../components/admin_sidebar.php'; ?>
  <?php include '../components/right-panel.php'; ?>

  <form method="POST" action="../actions/process_admin_settings.php"
        enctype="multipart/form-data"
        class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-6 relative z-0">

    <input type="hidden" name="csrf_token_settings" value="<?= $_SESSION['csrf_token_settings'] ?>">

    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">Admin Profile & Settings</h2>
    </div>

    <?php include '../components/admin_profile_form.php'; ?>
    <?php include '../components/admin_login_form.php'; ?>

    <!-- Manage Admin Users -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
      <div class="flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-800">Manage Admin Users</h3>
        <?php if ($isFullAdmin): ?>
          <button type="button" @click="openAddUserModal"
                  class="px-4 py-2 bg-sky-600 text-white rounded hover:bg-sky-700 text-sm font-medium">
            + Add Admin User
          </button>
        <?php endif; ?>
      </div>

      <p class="text-sm text-gray-600">
        <?php echo $isFullAdmin ? 'Add, edit, or remove admin accounts.' : 'View admin accounts.'; ?>
      </p>

      <div class="space-y-3">
        <?php if (!empty($allAdmins)): ?>
          <?php foreach ($allAdmins as $user): ?>
            <?php
              $userPhoto = !empty($user['admin_photo'])
                ? "../uploads/admin_photo/" . $user['admin_photo'] . '?v=' . time()
                : '../images/default_client_profile.png';
            ?>
            <div class="flex items-center justify-between py-3 px-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
              <div class="flex items-center gap-4">
                <img src="<?= htmlspecialchars($userPhoto) ?>"
                     alt="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>"
                     class="w-10 h-10 rounded-full object-cover border border-gray-200">

                <div>
                  <p class="font-medium text-gray-900">
                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                    <?php if ($user['id'] === $admin['id']): ?>
                      <span class="text-xs text-sky-600 font-normal">(You)</span>
                    <?php endif; ?>
                  </p>
                  <p class="text-xs text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                  <p class="text-xs text-gray-500">@<?= htmlspecialchars($user['username']) ?></p>
                </div>
              </div>

              <div class="flex items-center gap-3">
                <span class="px-3 py-1 text-xs font-medium rounded-full <?= $user['role'] === 'admin' ? 'bg-sky-100 text-sky-700' : 'bg-gray-100 text-gray-700' ?>">
                  <?= ucfirst(htmlspecialchars($user['role'])) ?>
                </span>

                <?php if ($isFullAdmin && $user['id'] !== $admin['id']): ?>
                  <div class="relative" x-data>
                    <button type="button"
                            @click.stop="toggleUserActions(<?= $user['id'] ?>, $event)"
                            class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-200 rounded-full transition">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                      </svg>
                    </button>

                    <div x-show="showUserActions[<?= $user['id'] ?>]"
                         x-cloak x-transition
                         class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-20">
                      <button type="button"
                              @click="openEditModal(<?= $user['id'] ?>)"
                              class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        Edit User
                      </button>
                      <button type="button"
                              onclick="if(confirm('Are you sure you want to delete this admin user? This action cannot be undone.')) {
                                window.location.href='../actions/delete_admin_user.php?id=<?= $user['id'] ?>&csrf=<?= $_SESSION['csrf_token_settings'] ?>';
                              }"
                              class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                        Delete User
                      </button>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-sm text-gray-500 text-center py-6">No admin users found.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Session & Permissions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="text-base font-semibold text-gray-800">Session Settings</h3>
        <label class="block text-sm text-gray-500 mb-1">Auto Logout After (minutes)</label>
        <input type="number" name="session_timeout" value="30" min="5" max="120"
               class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-400">
        <p class="text-xs text-gray-500 mt-1">You’ll be prompted before timeout.</p>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-semibold text-gray-800">Role & Permissions</h3>
          <span class="px-3 py-1 text-xs font-medium bg-sky-100 text-sky-700 rounded-full">
            <?= ucfirst(htmlspecialchars($admin['role'] ?? 'admin')) ?>
          </span>
        </div>
        <p class="text-xs text-gray-500">
          <?= $isFullAdmin ? 'Full access to system and user management.' : 'Limited admin access.' ?>
        </p>
      </div>
    </div>

    <div class="sticky bottom-0 right-0 z-10 px-6 py-4 text-right backdrop-blur-sm bg-gradient-to-t from-[rgba(14,165,233,0.1)] to-transparent">
      <button type="submit"
              class="px-6 py-2.5 bg-sky-600 text-white rounded font-medium hover:bg-sky-700 transition">
        Save All Changes
      </button>
    </div>
  </form>

  <!-- Toast -->
  <div x-show="showToast" x-transition
       class="fixed bottom-4 right-4 max-w-sm bg-green-600 text-white px-6 py-4 rounded-lg shadow-2xl z-50"
       x-text="toastMessage">
  </div>

  <!-- Add Admin Modal -->
  <div x-show="showAddUserModal" x-cloak @keydown.escape.window="closeAddUserModal"
       class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[95vh] overflow-y-auto relative p-8">
      <button @click="closeAddUserModal"
              class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-3xl z-10 bg-white rounded-full w-10 h-10 flex items-center justify-center shadow">&times;</button>
      <?php include '../components/add_admin_modal.php'; ?>
    </div>
  </div>

  <!-- Edit Admin Modal (AJAX) -->
  <div x-show="showEditUserModal" x-cloak @keydown.escape.window="closeEditModal"
       class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[95vh] overflow-y-auto relative">
      <button @click="closeEditModal"
              class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-3xl z-10 bg-white rounded-full w-10 h-10 flex items-center justify-center shadow">&times;</button>
      <div class="p-8" x-html="editModalContent"></div>
    </div>
  </div>

</body>
</html>