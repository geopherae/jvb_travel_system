<?php
  // ✅ Default preview image for new admin
  $defaultAdminPhoto = '../images/default_client_profile.png';
?>

<script>
  // 🧩 Stepper for Add Admin Modal
  function addAdminStepper() {
    return {
      step: 1,
      form: {
        new_first_name: '',
        new_last_name: '',
        new_email: '',
        new_username: '',
        new_password: '',
        new_confirm_password: '',
        new_phone_number: '',
        new_messenger_link: '',
        new_admin_profile: ''
      },
      errors: {},
      passwordStrength: 'Weak',

      // ✅ Validation Helpers
      isValidEmail() {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.new_email);
      },
      isValidPhone() {
        return /^09\d{9}$/.test(this.form.new_phone_number);
      },
      isStep1Complete() {
        return this.form.new_first_name.trim() &&
               this.form.new_last_name.trim() &&
               this.isValidEmail();
      },
      isStep2Complete() {
        return this.form.new_username.trim() &&
               this.form.new_password &&
               this.form.new_password === this.form.new_confirm_password;
      },

      // ✅ Password Strength Meter
      validatePassword(password) {
        if (password.length >= 12 && /[A-Z]/.test(password) && /\W/.test(password)) {
          this.passwordStrength = 'Strong';
        } else if (password.length >= 8) {
          this.passwordStrength = 'Medium';
        } else {
          this.passwordStrength = 'Weak';
        }
      },

      // ✅ Step 1 Validation
      validateStep1() {
        this.errors = {};
        if (!this.form.new_first_name.trim()) this.errors.new_first_name = 'First name is required.';
        if (!this.form.new_last_name.trim()) this.errors.new_last_name = 'Last name is required.';
        if (!this.form.new_email.trim()) {
          this.errors.new_email = 'Email is required.';
        } else if (!this.isValidEmail()) {
          this.errors.new_email = 'Invalid email format.';
        }
        if (this.form.new_phone_number && !this.isValidPhone()) {
          this.errors.new_phone_number = 'Must be 11 digits starting with 09.';
        }
        return Object.keys(this.errors).length === 0;
      },

      // ✅ Step 2 Validation
      validateStep2() {
        this.errors = {};
        if (!this.form.new_username.trim()) this.errors.new_username = 'Username is required.';
        if (!this.form.new_password) this.errors.new_password = 'Password is required.';
        if (this.form.new_password !== this.form.new_confirm_password) {
          this.errors.new_confirm_password = 'Passwords do not match.';
        }
        return Object.keys(this.errors).length === 0;
      },

      // ✅ Navigation
      nextStep() {
        if (this.step === 1 && this.validateStep1()) {
          this.step = 2;
        }
      },

      // ✅ Final Submission
      handleSubmit(event) {
        if (!this.validateStep2()) {
          event.preventDefault();
          return;
        }

        const profileJson = JSON.stringify({ bio: this.form.new_admin_profile.trim() });
        document.querySelector('input[name="new_admin_profile_json"]').value = profileJson;
      },

      // ✅ Init Lifecycle
      init() {
        this.step = 1;
        this.errors = {};
        this.passwordStrength = 'Weak';
      }
    };
  }

  // 🧩 Photo Preview Module (Scoped to Upload Box)
  function addAdminPhotoPreview(initialUrl) {
    return {
      previewUrl: initialUrl || '../images/default_admin_profile.png',
      fileName: '',
      handleFile(event) {
        const file = event.target.files[0];
        if (!file) return;

        this.fileName = file.name;

        const reader = new FileReader();
        reader.onload = ev => this.previewUrl = ev.target.result;
        reader.readAsDataURL(file);
      }
    };
  }
</script>

<form id="add-admin-form"
      method="POST"
      action="../actions/process_add_admin_user.php"
      enctype="multipart/form-data"
      @submit="handleSubmit"
      x-data="addAdminStepper()"
      x-init="init()"
      class="z-50 space-y-6">

  <!-- CSRF Token -->
  <input type="hidden" name="csrf_token_modal" value="<?= $_SESSION['csrf_token_modal'] ?>">

  <!-- Header -->
  <div class="flex items-center justify-between">
    <h2 class="text-xl font-bold">Add User</h2>
  </div>

  <!-- STEP 1: Profile Information -->
  <div x-show="step === 1" x-transition>
    <div class="bg-white border rounded-lg p-6 space-y-4 shadow-sm">
      <div class="border-b pb-2 px-2 flex items-center justify-between">
        <h3 class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Profile Information</h3>
        <p class="text-xs text-gray-400 uppercase">Step <span x-text="step"></span> of 2</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <!-- Profile Photo Preview -->
        <div x-data="addAdminPhotoPreview('<?= $defaultAdminPhoto ?>')" class="flex flex-col items-center gap-2 border-2 border-dashed rounded-lg py-2 px-3 bg-gray-50 hover:border-blue-400 hover:bg-blue-50 transition">
          <img :src="previewUrl" alt="Profile Preview"
               class="w-20 h-20 rounded-full object-cover border-2 border-blue-500 shadow"
               loading="lazy" />
          <label for="new-admin-photo" class="text-xs font-medium text-blue-700 hover:underline cursor-pointer">
            Click to upload a photo
            <input id="new-admin-photo" name="new_admin_photo" type="file"
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
            <input type="text" name="new_first_name" x-model="form.new_first_name"
                   :required="step === 1"
                   placeholder="e.g., Justine"
                   class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                   :class="{ 'border-red-500': errors.new_first_name }" />
            <p x-show="errors.new_first_name" class="text-xs text-red-500 mt-1" x-text="errors.new_first_name"></p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
            <input type="text" name="new_last_name" x-model="form.new_last_name"
                   :required="step === 1"
                   placeholder="e.g., Dela Cruz"
                   class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                   :class="{ 'border-red-500': errors.new_last_name }" />
            <p x-show="errors.new_last_name" class="text-xs text-red-500 mt-1" x-text="errors.new_last_name"></p>
          </div>
        </div>
      </div>

      <!-- Middle Row: Email & Phone -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
          <input type="email" name="new_email" x-model="form.new_email"
                 :required="step === 1"
                 placeholder="e.g., justine@email.com"
                 class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                 :class="{ 'border-red-500': errors.new_email }" />
          <p x-show="form.new_email && !isValidEmail()" class="text-xs text-red-500 mt-1">
            Please enter a valid email address.</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
          <input type="tel" name="new_phone_number" x-model="form.new_phone_number"
                 maxlength="11" placeholder="e.g., 09171234567"
                 class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                 pattern="^09\d{9}$" />
                 <p x-show="form.new_phone_number && !isValidPhone()" class="text-xs text-red-500 mt-1">
  Must be 11 digits starting with 09.
</p>
        </div>
      </div>

      <!-- Bottom Row: Messenger & Profile -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Messenger Link</label>
          <input type="text" name="new_messenger_link" x-model="form.new_messenger_link"
                 placeholder="https://m.me/yourname"
                 class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Admin Profile</label>
          <input type="text" name="new_admin_profile" x-model="form.new_admin_profile"
                 class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                 placeholder="Short description (1 line)" />
        </div>
      </div>

      <!-- Next Button -->
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
      <!-- Left Column: Visual Card -->
      <div class="bg-gray-100 rounded-lg overflow-hidden shadow-sm flex items-center justify-center">
        <img src="../images/default_trip_cover.jpg" alt="Trip Cover Preview"
             class="w-full h-auto max-h-64 object-cover rounded">
      </div>

      <!-- Right Column: Password Fields -->
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
          <input type="text" name="new_username" x-model="form.new_username"
                 :required="step === 2"
                 placeholder="e.g., justine_admin"
                 class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                 :class="{ 'border-red-500': errors.new_username }" />
          <p x-show="errors.new_username" class="text-xs text-red-500 mt-1" x-text="errors.new_username"></p>
          <p class="text-xs text-gray-400 mt-1">
  Use at least 12 characters, mix uppercase and symbols for stronger security.
</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
          <input type="password" name="new_password" x-model="form.new_password"
                 :required="step === 2"
                 placeholder="Choose a strong password"
                 class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                 :class="{ 'border-red-500': errors.new_password }"
                 @input="validatePassword(form.new_password)" />
          <p class="text-xs text-gray-500 mt-1">Strength: <span x-text="passwordStrength"></span></p>
          <p x-show="errors.new_password" class="text-xs text-red-500 mt-1" x-text="errors.new_password"></p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
          <input type="password" name="new_confirm_password" x-model="form.new_confirm_password"
                 :required="step === 2"
                 placeholder="Repeat password"
                 class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
                 :class="{ 'border-red-500': errors.new_confirm_password }" />
          <p x-show="errors.new_confirm_password" class="text-xs text-red-500 mt-1" x-text="errors.new_confirm_password"></p>
          <p x-show="form.new_confirm_password && form.new_password !== form.new_confirm_password"
   class="text-xs text-red-500 mt-1">
  Passwords do not match.
</p>
        </div>
      </div>
    </div>

    <!-- Navigation Buttons -->
    <div class="flex items-center justify-between pt-4">
      <button type="button" @click="step = 1"
              class="text-sm text-gray-500 hover:underline">
        ← Previous
      </button>

      <div>
        <input type="hidden" name="new_admin_profile_json" />
        <button type="submit"
                :disabled="!isStep2Complete()"
                class="px-4 py-2 rounded transition font-medium"
                :class="{
                  'bg-green-600 text-white hover:bg-green-700': isStep2Complete(),
                  'bg-gray-300 text-gray-500 cursor-not-allowed': !isStep2Complete()
                }">
          Create Admin Account
        </button>
      </div>
    </div>
  </div>
</div>
</form>