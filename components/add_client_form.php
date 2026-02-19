<?php
require_once __DIR__ . '/../includes/feature_flags.php';

date_default_timezone_set('Asia/Manila');

$admins = $conn->query("SELECT id, first_name, last_name FROM admin_accounts WHERE id != 1 ORDER BY first_name ASC");
$pkg_stmt = $conn->prepare("
  SELECT id, package_name, day_duration, night_duration, price, is_deleted
  FROM tour_packages
  ORDER BY package_name ASC
");
$pkg_stmt->execute();
$packages = $pkg_stmt->get_result();

$all_packages_for_preview = [];
$pkg_preview_stmt = $conn->prepare("
  SELECT id, package_name, day_duration, night_duration, price, tour_cover_image
  FROM tour_packages
  WHERE is_deleted = 0
  ORDER BY package_name ASC
");
$pkg_preview_stmt->execute();
$pkg_preview_result = $pkg_preview_stmt->get_result();
while ($pkg = $pkg_preview_result->fetch_assoc()) {
  $all_packages_for_preview[] = $pkg;
}
$pkg_preview_stmt->close();
$tooltips = require __DIR__ . '/../includes/tooltip_map.php';
require_once __DIR__ . '/../includes/tooltip_render.php';
?>

<script>
  function clientForm() {
    return {
      step: 1,
      submitting: false,

      // Field values
      fullName: '',
      email: '',
      phone: '',
      address: '',
      accessCode: '',
      copied: false,
      processingType: 'booking',
      tripStart: '',
      tripEnd: '',
      bookingDate: (function() {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
      })(),
      bookingNumber: '',
      assignedPackage: '',
      passportNumber: '',
      passportExpiry: '',
      assignedAdmin: '',
      allPackages: <?= json_encode($all_packages_for_preview) ?>,
      selectedPackageDetails: {},

      // Touched tracking — only show errors after a field has been interacted with
      touched: {
        fullName: false,
        email: false,
        phone: false,
        address: false,
        tripStart: false,
        tripEnd: false,
        passportExpiry: false,
      },

      touch(field) {
        this.touched[field] = true;
      },

      // ── Validation rules ──────────────────────────────────

      isValidEmail() {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email.trim());
      },
      isValidPhone() {
        return /^09\d{9}$/.test(this.phone.trim());
      },
      isValidDates() {
        if (!this.tripStart || !this.tripEnd) return true;
        return new Date(this.tripStart) <= new Date(this.tripEnd);
      },
      datesInFuture() {
        if (!this.tripStart) return true;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return new Date(this.tripStart) >= today;
      },
      isPassportExpiryValid() {
        if (!this.passportExpiry) return true; // optional field
        return new Date(this.passportExpiry) > new Date();
      },
      isPassportNumberValid() {
        if (!this.passportNumber) return true; // optional field
        return /^[A-Z0-9]{6,9}$/.test(this.passportNumber.trim().toUpperCase());
      },

      // ── Per-field error messages ──────────────────────────

      get fullNameError() {
        if (!this.touched.fullName) return '';
        if (!this.fullName.trim()) return 'Full name is required.';
        if (this.fullName.trim().length < 2) return 'Name must be at least 2 characters.';
        return '';
      },
      get emailError() {
        if (!this.touched.email) return '';
        if (!this.email.trim()) return 'Email address is required.';
        if (!this.isValidEmail()) return 'Enter a valid email (e.g. maria@example.com).';
        return '';
      },
      get phoneError() {
        if (!this.touched.phone) return '';
        if (!this.phone.trim()) return 'Phone number is required.';
        if (!/^09/.test(this.phone)) return 'Phone must start with 09.';
        if (this.phone.length !== 11) return `Must be 11 digits — you have ${this.phone.length}.`;
        if (!this.isValidPhone()) return 'Must be 11 digits starting with 09.';
        return '';
      },
      get addressError() {
        if (!this.touched.address) return '';
        if (!this.address.trim()) return 'Address is required.';
        if (this.address.trim().length < 5) return 'Please enter a complete address.';
        return '';
      },
      get dateError() {
        if (!this.tripStart && !this.tripEnd) return '';
        if (!this.datesInFuture()) return 'Departure date cannot be in the past.';
        if (!this.isValidDates()) return 'Return date must be on or after departure date.';
        return '';
      },
      get passportExpiryError() {
        if (!this.touched.passportExpiry || !this.passportExpiry) return '';
        if (!this.isPassportExpiryValid()) return 'Passport appears to be expired.';
        return '';
      },
      get passportNumberError() {
        if (!this.passportNumber) return '';
        if (!this.isPassportNumberValid()) return 'Use 6–9 uppercase letters/numbers (e.g. P1234567A).';
        return '';
      },

      // ── Field state helpers (for border/ring coloring) ────

      fieldState(value, errorGetter, touchedKey) {
        if (!this.touched[touchedKey]) return 'idle';
        if (errorGetter) return 'error';
        if (value && value.trim() !== '') return 'success';
        return 'idle';
      },

      // ── Step proceed guards ───────────────────────────────

      canProceedStep1() {
        return (
          this.fullName.trim().length >= 2 &&
          this.isValidEmail() &&
          this.isValidPhone() &&
          this.address.trim().length >= 5
        );
      },
      canProceedStep2() {
        return !this.dateError;
      },

      // Touch all step 1 fields to reveal errors on attempted Next
      touchStep1() {
        this.touched.fullName = true;
        this.touched.email    = true;
        this.touched.phone    = true;
        this.touched.address  = true;
      },

      // ── Access code generation ────────────────────────────

      generateAccessCode() {
        if (!this.fullName.trim()) { this.accessCode = ''; return; }
        const base   = this.fullName.trim().replace(/\s+/g, '').toUpperCase();
        const suffix = Date.now().toString().slice(-4);
        this.accessCode = base.slice(0, 4) + '-' + suffix;
      },

      // ── Package preview ───────────────────────────────────

      getPackageBannerUrl() {
        if (this.selectedPackageDetails.tour_cover_image) {
          return `../images/tour_packages_banners/${this.selectedPackageDetails.tour_cover_image.replace(/^\/+/, '')}`;
        }
        return '';
      },
      updatePackageDetails() {
        this.selectedPackageDetails = this.allPackages.find(p => p.id == this.assignedPackage) || {};
      },

      // ── Submit handler ────────────────────────────────────

      handleSubmit(e) {
        if (this.submitting) { e.preventDefault(); return; }
        this.submitting = true;
        // Let the form submit naturally; submitting=true triggers the loading UI
      },
    }
  }
</script>

<!-- ═══════════════════════════════════════════════════════
     ADD CLIENT MODAL
═══════════════════════════════════════════════════════ -->
<div x-show="showAddClientModal" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title" role="dialog" aria-modal="true"
     @keydown.escape.window="showAddClientModal = false">

  <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-4 text-center sm:p-0">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity"
         @click="showAddClientModal = false"></div>

    <!-- Modal panel -->
    <div class="relative inline-block align-middle bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-0 sm:align-middle sm:max-w-5xl sm:w-full sm:max-h-[96vh]">

      <!-- ── Header ── -->
      <div class="bg-gradient-to-r from-sky-500 to-blue-600 px-4 py-3 sm:px-6">
        <div class="p-2 flex items-center justify-between">
          <h3 class="text-lg leading-6 font-semibold text-white" id="modal-title">
            Add Guest | Travel Booking
          </h3>
          <button type="button" @click="showAddClientModal = false"
                  class="text-white/80 hover:text-white transition-colors rounded-lg p-1 hover:bg-white/10">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- ── Form ── -->
      <form method="POST" action="../actions/process_add_client.php" enctype="multipart/form-data"
            class="flex flex-col h-full font-sans"
            x-data="clientForm()"
            @submit="handleSubmit($event)">

        <div>

          <!-- ══════════════════════════════════════════
               STEP 1 — Basic Info
          ══════════════════════════════════════════ -->
          <div x-show="step === 1" class="px-4 py-4 sm:p-6 space-y-4">

            <!-- Progress header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
              <h3 class="text-sm font-semibold text-gray-900">Client Basic Info</h3>
              <div class="flex gap-1.5">
                <div class="w-6 h-1.5 rounded-full bg-sky-500"></div>
                <div class="w-6 h-1.5 rounded-full bg-gray-200"></div>
                <div class="w-6 h-1.5 rounded-full bg-gray-200"></div>
              </div>
              <span class="text-xs text-gray-400 font-medium">Step 1 of 3</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

              <!-- LEFT: Photo + Email + Phone -->
              <div class="space-y-4">

                <!-- Profile Photo -->
                <div x-data="{
                  previewUrl: '../images/default_client_profile.png',
                  handleFile(e) {
                    let file = e.target.files?.[0] ?? e.dataTransfer?.files?.[0];
                    if (!file) return;
                    if (file.size > 3 * 1024 * 1024) { alert('File must be under 3MB'); return; }
                    const reader = new FileReader();
                    reader.onload = ev => this.previewUrl = ev.target.result;
                    reader.readAsDataURL(file);
                  }
                }"
                @dragover.prevent @drop.prevent="handleFile($event)"
                class="relative flex flex-col items-center gap-2 border-2 border-dashed border-sky-200 rounded-xl py-4 px-3 bg-gradient-to-br from-sky-50 to-white hover:border-sky-400 hover:from-sky-100/70 transition-all cursor-pointer group">
                  <div class="absolute top-0 right-0 w-10 h-10 bg-sky-400/10 rounded-bl-2xl"></div>
                  <img :src="previewUrl" alt="Profile Preview"
                       class="w-14 h-14 rounded-xl object-cover border-2 border-sky-100 shadow-sm group-hover:shadow-md transition-shadow" loading="lazy" />
                  <label for="add-client-photo" class="text-center cursor-pointer">
                    <p class="text-xs font-semibold text-sky-600 group-hover:text-sky-700">
                      <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Upload Photo
                      </span>
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">JPG, PNG — Max 3MB</p>
                    <input id="add-client-photo" name="client_profile_photo" type="file"
                           accept=".jpg,.jpeg,.png" class="hidden" @change="handleFile">
                  </label>
                </div>

                <!-- Email -->
                <div class="space-y-1">
                  <label for="email" class="block text-xs font-semibold text-gray-700">
                    Email Address <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <input id="email" type="email" name="email" x-model="email" autocomplete="email"
                           placeholder="maria@example.com"
                           @blur="touch('email')"
                           @input="touched.email && touch('email')"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm placeholder:text-gray-300 transition focus:outline-none focus:ring-2"
                           :class="{
                             'border-gray-300 focus:ring-sky-500 focus:border-sky-400': !touched.email,
                             'border-red-400 ring-1 ring-red-300 focus:ring-red-400 bg-red-50/30': touched.email && emailError,
                             'border-emerald-700 ring-1 ring-emerald-200 focus:ring-emerald-700 bg-emerald-50/30': touched.email && !emailError && email
                           }" />
                    <!-- Success check -->
                    <div x-show="touched.email && !emailError && email"
                         class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                      <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                      </svg>
                    </div>
                    <!-- Error X -->
                    <div x-show="touched.email && emailError"
                         class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                      <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                      </svg>
                    </div>
                  </div>
                  <p x-show="emailError" x-text="emailError"
                     class="text-xs text-red-500 flex items-center gap-1 mt-1">
                  </p>
                </div>

                <!-- Phone -->
                <div class="space-y-1">
                  <label for="phone_number" class="block text-xs font-semibold text-gray-700">
                    Phone Number <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <input id="phone_number" type="tel" name="phone_number" x-model="phone"
                           maxlength="11" placeholder="09171234567"
                           @blur="touch('phone')"
                           @input="touched.phone && touch('phone')"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm placeholder:text-gray-300 transition focus:outline-none focus:ring-2"
                           :class="{
                             'border-gray-300 focus:ring-sky-500 focus:border-sky-400': !touched.phone,
                             'border-red-400 ring-1 ring-red-300 focus:ring-red-400 bg-red-50/30': touched.phone && phoneError,
                             'border-emerald-700 ring-1 ring-emerald-200 focus:ring-emerald-700 bg-emerald-50/30': touched.phone && !phoneError && phone
                           }" />
                    <div x-show="touched.phone && !phoneError && phone"
                         class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                      <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                      </svg>
                    </div>
                    <div x-show="touched.phone && phoneError"
                         class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                      <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                      </svg>
                    </div>
                    <!-- Live digit counter -->
                    <div class="absolute right-8 top-1/2 -translate-y-1/2 pointer-events-none"
                         x-show="phone.length > 0 && phone.length < 11">
                      <span class="text-xs text-gray-400 font-mono" x-text="`${phone.length}/11`"></span>
                    </div>
                  </div>
                  <p x-show="phoneError" x-text="phoneError" class="text-xs text-red-500 mt-1"></p>
                </div>

              </div>

              <!-- RIGHT: Processing Type + Full Name + Access Code -->
              <div class="space-y-4">

                <!-- Processing Type -->
                <div class="space-y-1">
                  <label for="processing_type" class="block text-xs font-semibold text-gray-700">
                    Processing Type <span class="text-red-500">*</span>
                  </label>
                  <select id="processing_type" name="processing_type" x-model="processingType"
                          <?php if (!VISA_PROCESSING_ENABLED) echo 'disabled'; ?>
                          class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white transition focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-400
                          <?php if (!VISA_PROCESSING_ENABLED) echo 'bg-gray-50 cursor-not-allowed opacity-70'; ?>">
                    <option value="booking">Booking Only</option>
                    <?php if (VISA_PROCESSING_ENABLED): ?>
                    <option value="both">Both Booking & Visa</option>
                    <?php endif; ?>
                  </select>
                  <?php if (!VISA_PROCESSING_ENABLED): ?>
                  <!-- Keep value submitted even when select is disabled -->
                  <input type="hidden" name="processing_type" value="booking">
                  <?php endif; ?>
                  <p class="text-xs text-gray-400">
                    <?= VISA_PROCESSING_ENABLED ? 'Select the service type for this client.' : 'Only Travel Bookings are currently accepted.' ?>
                  </p>
                </div>

                <!-- Full Name -->
                <div class="space-y-1">
                  <label for="full_name" class="block text-xs font-semibold text-gray-700">
                    Lead Guest Full Name <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <input id="full_name" type="text" name="full_name" x-model="fullName"
                           placeholder="Maria Santos Reyes"
                           @blur="touch('fullName')"
                           @input="touched.fullName && touch('fullName'); generateAccessCode()"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm placeholder:text-gray-300 transition focus:outline-none focus:ring-2"
                           :class="{
                             'border-gray-300 focus:ring-sky-500 focus:border-sky-400': !touched.fullName,
                             'border-red-400 ring-1 ring-red-300 focus:ring-red-400 bg-red-50/30': touched.fullName && fullNameError,
                             'border-emerald-700 ring-1 ring-emerald-200 focus:ring-emerald-700 bg-emerald-50/30': touched.fullName && !fullNameError && fullName
                           }" />
                    <div x-show="touched.fullName && !fullNameError && fullName"
                         class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                      <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                      </svg>
                    </div>
                  </div>
                  <p x-show="fullNameError" x-text="fullNameError" class="text-xs text-red-500 mt-1"></p>
                </div>

                <!-- Access Code -->
                <div class="space-y-1">
                  <div class="flex items-center gap-1.5">
                    <label for="access_code" class="text-xs font-semibold text-gray-700">Access Code</label>
                    <?= renderTooltipIcon('access_code', $tooltips) ?>
                  </div>
                  <div class="relative">
                    <input id="access_code" type="text" name="access_code" x-model="accessCode" readonly
                           class="w-full border-2 border-sky-200 rounded-lg px-3 py-2.5 pr-20 font-mono text-sm font-bold text-sky-700 bg-sky-50/60 transition focus:outline-none focus:ring-2 focus:ring-sky-400" />
                    <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                      <span x-show="copied" x-transition
                            class="text-xs text-emerald-600 font-semibold">Copied!</span>
                      <button type="button"
                              @click="$clipboard(accessCode); copied = true; setTimeout(() => copied = false, 2000)"
                              title="Copy access code"
                              class="text-sky-500 hover:text-sky-700 transition-colors p-1 rounded hover:bg-sky-100">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                  <p class="text-xs text-gray-400">Auto-generated. Share with client for portal access.</p>
                </div>

              </div>
            </div>

            <!-- Address (full width) -->
            <div class="space-y-1">
              <label for="address" class="block text-xs font-semibold text-gray-700">
                Address <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <textarea id="address" name="address" x-model="address" rows="2"
                          placeholder="123 Rizal St, Barangay Mabini, Quezon City..."
                          @blur="touch('address')"
                          @input="touched.address && touch('address')"
                          class="w-full border rounded-lg px-3 py-2.5 text-sm resize-none placeholder:text-gray-300 transition focus:outline-none focus:ring-2"
                          :class="{
                            'border-gray-300 focus:ring-sky-500 focus:border-sky-400': !touched.address,
                            'border-red-400 ring-1 ring-red-300 focus:ring-red-400 bg-red-50/30': touched.address && addressError,
                            'border-emerald-700 ring-1 ring-emerald-200 focus:ring-emerald-700 bg-emerald-50/30': touched.address && !addressError && address
                          }"></textarea>
              </div>
              <p x-show="addressError" x-text="addressError" class="text-xs text-red-500 mt-1"></p>
            </div>

          </div><!-- /step 1 -->

          <!-- ══════════════════════════════════════════
               STEP 2 — Travel & Booking
          ══════════════════════════════════════════ -->
          <div x-show="step === 2" class="px-4 py-4 sm:p-6 space-y-4">

            <!-- Progress header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
              <h3 class="text-sm font-semibold text-gray-900">Travel & Booking</h3>
              <div class="flex gap-1.5">
                <div class="w-6 h-1.5 rounded-full bg-sky-500"></div>
                <div class="w-6 h-1.5 rounded-full bg-sky-500"></div>
                <div class="w-6 h-1.5 rounded-full bg-gray-200"></div>
              </div>
              <span class="text-xs text-gray-400 font-medium">Step 2 of 3</span>
            </div>

            <!-- Package + Booking Number -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-1">
                <label for="assigned_package" class="block text-xs font-semibold text-gray-700">Travel Package</label>
                <select id="assigned_package" name="assigned_package_id" x-model="assignedPackage"
                        @change="updatePackageDetails()"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white transition focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-400">
                  <option value="">Select a package...</option>
                  <?php
                  $packages->data_seek(0);
                  while ($pkg = $packages->fetch_assoc()):
                    if ((int)$pkg['is_deleted'] === 1) continue; ?>
                    <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['package_name']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="space-y-1">
                <label for="booking_number" class="block text-xs font-semibold text-gray-700">Booking Number</label>
                <input id="booking_number" type="text" name="booking_number" x-model="bookingNumber"
                       placeholder="JVB-00001"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm placeholder:text-gray-300 transition focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-400" />
              </div>
            </div>

            <!-- Package Preview Card -->
            <template x-if="selectedPackageDetails.package_name">
              <div class="relative overflow-hidden rounded-2xl p-5 shadow-lg"
                   :style="`background-image: url('${getPackageBannerUrl()}'); background-size: cover; background-position: center;`"
                   x-transition:enter="transition ease-out duration-200"
                   x-transition:enter-start="opacity-0 scale-95"
                   x-transition:enter-end="opacity-100 scale-100">
                <div class="absolute inset-0 bg-gradient-to-br from-sky-900/85 via-sky-800/80 to-blue-900/85 backdrop-blur-sm"></div>
                <div class="absolute top-0 right-0 w-20 h-20 bg-white/5 rounded-full -mr-10 -mt-10"></div>
                <div class="absolute bottom-0 left-0 w-28 h-28 bg-white/5 rounded-full -ml-14 -mb-14"></div>
                <div class="relative z-10 space-y-4">
                  <div class="flex items-start justify-between gap-4">
                    <div>
                      <p class="text-xs font-semibold text-sky-200 uppercase tracking-wider">Selected Package</p>
                      <h3 class="text-xl font-bold text-white mt-1" x-text="selectedPackageDetails.package_name"></h3>
                    </div>
                    <div class="text-right flex-shrink-0">
                      <p class="text-xs text-white/70 mb-1">Booking #</p>
                      <p class="text-lg font-bold text-white font-mono" x-text="bookingNumber || '—'"></p>
                    </div>
                  </div>
                  <div class="grid grid-cols-3 gap-4 pt-4 border-t border-white/20">
                    <div>
                      <p class="text-xs text-white/60 mb-1">Duration</p>
                      <p class="text-lg font-bold text-white" x-text="`${selectedPackageDetails.day_duration}D / ${selectedPackageDetails.night_duration}N`"></p>
                    </div>
                    <div>
                      <p class="text-xs text-white/60 mb-1">Price</p>
                      <p class="text-lg font-bold text-white" x-text="`₱${Number(selectedPackageDetails.price).toLocaleString('en-US', {minimumFractionDigits: 2})}`"></p>
                    </div>
                    <div>
                      <p class="text-xs text-white/60 mb-1">Travel Dates</p>
                      <p class="text-sm font-bold text-white"
                         x-text="tripStart && tripEnd
                           ? new Date(tripStart).toLocaleDateString('en-US', {month:'short', day:'numeric'}) + ' → ' + new Date(tripEnd).toLocaleDateString('en-US', {month:'short', day:'numeric'})
                           : 'Not set'"></p>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <!-- Travel Dates -->
            <div>
              <h4 class="text-xs font-semibold text-gray-700 mb-3 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Travel Dates
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="space-y-1">
                  <label class="block text-xs font-medium text-gray-600">Booking Date</label>
                  <input type="date" name="booking_date" x-model="bookingDate"
                         class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm transition focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-400" />
                </div>
                <div class="space-y-1">
                  <label class="block text-xs font-medium text-gray-600">Departure Date</label>
                  <input type="date" name="trip_date_start" x-model="tripStart"
                         @change="touched.tripStart = true"
                         class="w-full border rounded-lg px-3 py-2.5 text-sm transition focus:outline-none focus:ring-2 focus:ring-sky-500"
                         :class="dateError && tripStart ? 'border-red-400 bg-red-50/30' : 'border-gray-300 focus:border-sky-400'" />
                </div>
                <div class="space-y-1">
                  <label class="block text-xs font-medium text-gray-600">Return Date</label>
                  <input type="date" name="trip_date_end" x-model="tripEnd"
                         @change="touched.tripEnd = true"
                         class="w-full border rounded-lg px-3 py-2.5 text-sm transition focus:outline-none focus:ring-2 focus:ring-sky-500"
                         :class="dateError && tripEnd ? 'border-red-400 bg-red-50/30' : 'border-gray-300 focus:border-sky-400'" />
                </div>
              </div>

              <!-- Date error banner -->
              <div x-show="dateError" x-transition
                   class="mt-3 flex items-start gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span x-text="dateError"></span>
              </div>
            </div>

            <!-- Hotel note -->
            <div class="bg-sky-50 border border-sky-100 rounded-lg px-4 py-3">
              <p class="text-xs text-gray-500">
                Hotel accommodation details can be added later in
                <span class="font-semibold text-sky-700">Edit Booking Details</span>.
              </p>
            </div>

          </div><!-- /step 2 -->

          <!-- ══════════════════════════════════════════
               STEP 3 — Passport & Agent
          ══════════════════════════════════════════ -->
          <div x-show="step === 3" class="px-4 py-4 sm:p-6 space-y-4">

            <!-- Progress header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
              <h3 class="text-sm font-semibold text-gray-900">Passport & Travel Agent</h3>
              <div class="flex gap-1.5">
                <div class="w-6 h-1.5 rounded-full bg-sky-500"></div>
                <div class="w-6 h-1.5 rounded-full bg-sky-500"></div>
                <div class="w-6 h-1.5 rounded-full bg-sky-500"></div>
              </div>
              <span class="text-xs text-gray-400 font-medium">Step 3 of 3</span>
            </div>

            <!-- Passport fields -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

              <!-- Passport Number -->
              <div class="space-y-1">
                <label for="passport_number" class="block text-xs font-semibold text-gray-700">
                  Passport Number <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <div class="relative">
                  <input id="passport_number" type="text" name="passport_number" x-model="passportNumber"
                         placeholder="P1234567A"
                         @input="passportNumber = passportNumber.toUpperCase()"
                         class="w-full border rounded-lg px-3 py-2.5 text-sm font-mono placeholder:text-gray-300 placeholder:font-sans transition focus:outline-none focus:ring-2 uppercase tracking-widest"
                         :class="{
                           'border-gray-300 focus:ring-sky-500 focus:border-sky-400': !passportNumber,
                           'border-red-400 ring-1 ring-red-300 focus:ring-red-400 bg-red-50/30': passportNumber && passportNumberError,
                           'border-emerald-700 ring-1 ring-emerald-200 focus:ring-emerald-700 bg-emerald-50/30': passportNumber && !passportNumberError
                         }" />
                  <div x-show="passportNumber && !passportNumberError"
                       class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                  </div>
                </div>
                <p x-show="passportNumberError" x-text="passportNumberError" class="text-xs text-red-500 mt-1"></p>
              </div>

              <!-- Passport Expiry -->
              <div class="space-y-1">
                <label for="passport_expiry" class="block text-xs font-semibold text-gray-700">
                  Expiry Date <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <div class="relative">
                  <input id="passport_expiry" type="date" name="passport_expiry" x-model="passportExpiry"
                         @blur="touch('passportExpiry')"
                         @change="touch('passportExpiry')"
                         class="w-full border rounded-lg px-3 py-2.5 text-sm transition focus:outline-none focus:ring-2"
                         :class="{
                           'border-gray-300 focus:ring-sky-500 focus:border-sky-400': !touched.passportExpiry || !passportExpiry,
                           'border-red-400 ring-1 ring-red-300 focus:ring-red-400 bg-red-50/30': touched.passportExpiry && passportExpiryError,
                           'border-emerald-700 ring-1 ring-emerald-200 focus:ring-emerald-700 bg-emerald-50/30': touched.passportExpiry && passportExpiry && !passportExpiryError
                         }" />
                </div>
                <p x-show="passportExpiryError" x-text="passportExpiryError" class="text-xs text-red-500 mt-1"></p>
              </div>
            </div>

            <!-- Travel Agent -->
            <div class="space-y-1">
              <label for="assigned_admin" class="block text-xs font-semibold text-gray-700">Travel Agent</label>
              <select id="assigned_admin" name="assigned_admin_id" x-model="assignedAdmin"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white transition focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-400">
                <option value="">Select an agent...</option>
                <?php
                $admins->data_seek(0);
                while ($admin = $admins->fetch_assoc()): ?>
                  <option value="<?= $admin['id'] ?>"
                          <?= ($admin['id'] == ($_SESSION['admin']['id'] ?? '')) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
              <p class="text-xs text-gray-400">Assign a travel agent to manage this booking.</p>
            </div>

            <!-- Summary note -->
            <div class="bg-sky-50 border border-sky-100 rounded-lg px-4 py-3">
              <p class="text-xs text-gray-600">
                <span class="font-semibold text-gray-800">Almost done!</span>
                Review the details above. You can edit everything after the client is created.
              </p>
            </div>

          </div><!-- /step 3 -->

        </div><!-- /form body -->

        <!-- ══════════════════════════════════════════
             Navigation Footer
        ══════════════════════════════════════════ -->
        <div class="sticky bottom-0 flex justify-between items-center px-4 py-3 sm:px-6 bg-gray-50 border-t border-gray-200 gap-3 z-10">

          <!-- Step 1 buttons -->
          <template x-if="step === 1">
            <div class="flex w-full justify-between gap-3">
              <button type="button" @click="showAddClientModal = false"
                      class="px-4 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300">
                Cancel
              </button>
              <button type="button"
                      @click="touchStep1(); canProceedStep1() && step++"
                      :class="canProceedStep1()
                        ? 'bg-sky-600 hover:bg-sky-700 text-white cursor-pointer'
                        : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                      class="px-4 py-2.5 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-sky-400 flex items-center gap-2">
                Next: Travel & Booking
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>
          </template>

          <!-- Step 2 buttons -->
          <template x-if="step === 2">
            <div class="flex w-full justify-between gap-3">
              <button type="button" @click="step--"
                      class="px-4 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back
              </button>
              <button type="button"
                      @click="canProceedStep2() && step++"
                      :class="canProceedStep2()
                        ? 'bg-sky-600 hover:bg-sky-700 text-white cursor-pointer'
                        : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                      class="px-4 py-2.5 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-sky-400 flex items-center gap-2">
                Next: Passport & Agent
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>
          </template>

          <!-- Step 3 buttons -->
          <template x-if="step === 3">
            <div class="flex w-full justify-between gap-3">
              <button type="button" @click="step--" :disabled="submitting"
                      class="px-4 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 disabled:opacity-50 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back
              </button>

              <!-- Submit / Loading button -->
              <button type="submit" :disabled="submitting"
                      class="px-5 py-2.5 text-sm font-semibold text-white rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-1 flex items-center gap-2 min-w-[160px] justify-center"
                      :class="submitting
                        ? 'bg-sky-400 cursor-not-allowed'
                        : 'bg-sky-600 hover:bg-sky-700 cursor-pointer shadow-sm hover:shadow-md'">

                <!-- Idle state -->
                <template x-if="!submitting">
                  <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4v16m8-8H4" />
                    </svg>
                    Create Client
                  </span>
                </template>

                <!-- Loading state -->
                <template x-if="submitting">
                  <span class="flex items-center gap-2">
                    <!-- Spinner -->
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                      <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Processing...
                  </span>
                </template>

              </button>
            </div>
          </template>

        </div><!-- /footer -->

      </form>
    </div>
  </div>
</div>