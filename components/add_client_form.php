<?php
require_once __DIR__ . '/../includes/feature_flags.php';

$admins = $conn->query("SELECT id, first_name, last_name FROM admin_accounts WHERE id != 1 ORDER BY first_name ASC");
$pkg_stmt = $conn->prepare("
  SELECT id, package_name, day_duration, night_duration, price, is_deleted
  FROM tour_packages
  ORDER BY package_name ASC
");
$pkg_stmt->execute();
$packages = $pkg_stmt->get_result();

// 📦 Fetch all packages for preview (including deleted for comparison)
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
      fullName: '',
      email: '',
      phone: '',
      address: '',
      accessCode: '',
      copied: false,
      processingType: 'booking',
      tripStart: '',
      tripEnd: '',
      bookingDate: '',
      bookingNumber: '',
      assignedPackage: '',
      passportNumber: '',
      passportExpiry: '',
      assignedAdmin: '',
      allPackages: <?= json_encode($all_packages_for_preview) ?>,
      selectedPackageDetails: {},

      getPackageBannerUrl() {
        if (this.selectedPackageDetails.tour_cover_image) {
          return `../images/tour_packages_banners/${this.selectedPackageDetails.tour_cover_image.replace(/^\/+/, '')}`;
        }
        return '';
      },

      // Validation
      isValidEmail() {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email);
      },
      isValidPhone() {
        return /^09\d{9}$/.test(this.phone);
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
      canProceedStep1() {
        return this.fullName.trim() !== '' && this.isValidEmail() && this.isValidPhone() && this.address.trim() !== '';
      },
      canProceedStep2() {
        return !this.showDateWarning();
      },

      // Access code generation
      generateAccessCode() {
        if (!this.fullName.trim()) {
          this.accessCode = '';
          return;
        }
        const base = this.fullName.trim().replace(/\s+/g, '').toUpperCase();
        const suffix = Date.now().toString().slice(-4);
        this.accessCode = base.slice(0, 4) + '-' + suffix;
      },

      // Update selected package details
      updatePackageDetails() {
        this.selectedPackageDetails = this.allPackages.find(p => p.id == this.assignedPackage) || {};
      },

      // Date warning
      showDateWarning() {
        return !this.isValidDates() || !this.datesInFuture();
      }
    }
  }
</script>

<!-- Add Client Modal -->
<div x-show="showAddClientModal" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title" role="dialog" aria-modal="true"
     @keydown.escape.window="showAddClientModal = false">

  <!-- Backdrop -->
  <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-4 text-center sm:p-0">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showAddClientModal = false"></div>

    <!-- Modal panel -->
    <div class="inline-block align-middle bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-0 sm:align-middle sm:max-w-4xl sm:w-full sm:max-h-[96vh]">
      <!-- Header -->
      <div class="bg-gradient-to-r from-sky-500 to-blue-600 px-4 py-3 sm:px-6">
        <div class="p-2 flex items-center justify-between">
          <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">
            Add Guest | Travel Booking
          </h3>
          <button type="button" @click="showAddClientModal = false"
                  class="text-white hover:text-gray-200 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>

      <form method="POST" action="../actions/process_add_client.php" enctype="multipart/form-data"
            class="flex flex-col h-full font-sans"
            x-data="clientForm()" 
            @submit="$el.classList.add('submitting')">

        <!-- Form content wrapped with padding - scrollable -->
        <div>

  <!-- STEP 1: Basic Info -->
  <div x-show="step === 1" class="px-4 py-4 sm:p-6 space-y-3 sm:space-y-4">

    <!-- Progress Header -->
    <div class="flex items-center justify-between mb-3 sm:mb-4 pb-2 sm:pb-3 border-b border-gray-200">
      <h3 class="text-sm sm:text-base font-semibold text-gray-900">Client Basic Info</h3>
      <div class="flex gap-1.5 sm:gap-2">
        <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
        <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
        <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
      </div>
      <div class="text-xs sm:text-sm text-gray-500">Step 1 of 3</div>
    </div>

    <!-- Two-Column Layout (Responsive) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4">

      <!-- LEFT COLUMN: Profile Photo + Email + Phone Number -->
      <div class="space-y-3 sm:space-y-6">

<!-- Profile Photo - Modern -->
<div x-data="{
  fileName: '',
  previewUrl: '../images/default_client_profile.png',
  handleFile(e) {
    let file = e.target.files ? e.target.files[0] : e.dataTransfer?.files[0];
    if (!file) return;
    if (file.size > 3 * 1024 * 1024) {
      alert('File must be under 3MB');
      return;
    }
    this.fileName = file.name;
    const reader = new FileReader();
    reader.onload = ev => this.previewUrl = ev.target.result;
    reader.readAsDataURL(file);
  }
}"
@dragover.prevent @drop.prevent="handleFile($event)"
class="relative flex flex-col items-center gap-1.5 sm:gap-2 border-2 border-dashed border-sky-200 rounded-lg sm:rounded-xl py-3 sm:py-4 px-2 sm:px-3 bg-gradient-to-br from-sky-50 to-transparent hover:border-sky-400 hover:from-sky-100 transition-all cursor-pointer group">

  <!-- Decorative corner accent -->
  <div class="absolute top-0 right-0 w-8 sm:w-12 h-8 sm:h-12 bg-sky-500 opacity-5 rounded-bl-xl sm:rounded-bl-2xl"></div>

  <!-- Image with better styling -->
  <img :src="previewUrl" alt="Profile Preview"
       class="w-12 sm:w-16 h-12 sm:h-16 rounded-lg sm:rounded-lg object-cover border-2 border-sky-100 shadow-sm group-hover:shadow-md transition-shadow" loading="lazy" />

  <!-- Upload label with icon -->
  <label for="add-client-photo" class="text-center cursor-pointer">
    <div class="flex items-center justify-center mb-1.5 sm:mb-2">
      <svg class="w-4 sm:w-5 h-4 sm:h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
      </svg>
    </div>
    <p class="text-xs font-semibold text-sky-600 group-hover:text-sky-700">Upload Photo</p>
    <p class="text-xs text-gray-500 mt-0.5">Max 3MB</p>
    <input id="add-client-photo" name="client_profile_photo" type="file"
           accept=".jpg,.jpeg,.png" class="hidden" @change="handleFile">
  </label>

</div>

        <!-- Email -->
        <div class="relative">
          <label for="email" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
            Email <span class="text-red-500">*</span>
          </label>
          <input id="email" type="email" name="email" x-model="email" required placeholder="maria@example.com"
                 class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                 :class="{ 'border-red-500 ring-red-500': email && !isValidEmail(), 'border-green-500 ring-green-500': isValidEmail() }" />
          <p x-show="email && !isValidEmail()" class="text-xs text-red-500 mt-1.5 flex items-center gap-1">
            <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            Invalid email format.
          </p>
        </div>

        <!-- Phone Number -->
        <div class="relative">
          <label for="phone_number" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
            Phone <span class="text-red-500">*</span>
          </label>
          <input id="phone_number" type="tel" name="phone_number" x-model="phone" required maxlength="11" placeholder="09171234567"
                 class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                 :class="{ 'border-red-500 ring-red-500': phone && !isValidPhone(), 'border-green-500 ring-green-500': isValidPhone() }" />
          <p x-show="phone && !isValidPhone()" class="text-xs text-red-500 mt-1.5 flex items-center gap-1">
            <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            Must be 11 digits (09xxxxxxxxx).
          </p>
        </div>

      </div>

      <!-- RIGHT COLUMN: Full Name + Access Code -->
      <div class="space-y-3 sm:space-y-6">

        <!-- Processing Type -->
        <div class="relative">
          <label for="processing_type" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
            Processing Type <span class="text-red-500">*</span>
          </label>
          <select id="processing_type" name="processing_type" x-model="processingType" required
                  <?php if (!VISA_PROCESSING_ENABLED) echo 'readonly onclick="return false;"'; ?>
                  class="bg-white w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent <?php if (!VISA_PROCESSING_ENABLED) echo 'bg-gray-50 cursor-not-allowed pointer-events-none'; ?>">
            <option value="booking">Booking Only</option>
            <?php if (VISA_PROCESSING_ENABLED): ?>
            <option value="visa">Visa Processing</option>
            <option value="both">Both Booking & Visa</option>
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

        <!-- Full Name -->
        <div class="relative">
          <label for="full_name" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
            Lead Guest Full Name <span class="text-red-500">*</span>
          </label>
          <input id="full_name" type="text" name="full_name" x-model="fullName" required
                 placeholder="Maria Reyes"
                 @input.debounce.500="generateAccessCode()"
                 class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
          <p x-show="fullName.trim() === ''" class="text-xs text-red-500 mt-1.5 flex items-center gap-1">
            <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            This field is required.
          </p>
        </div>

              <!-- Access Code -->
              <div class="pt-[0.75px]">
              <div class="relative">
                <div class="rounded z-10 absolute top-0 left-3 -translate-y-1/2 bg-white px-1 flex items-center gap-1.5">
                  <label for="access_code" class="z-10 text-xs font-semibold text-gray-700">
                    Access Code
                  </label>
                  <?= renderTooltipIcon('access_code', $tooltips) ?>
                </div>
                <div class="relative">
                  <input id="access_code" type="text" name="access_code" x-model="accessCode" readonly
                         class="w-full border-2 border-sky-200 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 font-mono text-xs sm:text-sm font-bold text-sky-700 bg-sky-50 pr-10 sm:pr-12 transition hover:border-sky-300 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
                  <button type="button"
                          @click="$clipboard(accessCode); copied = true; setTimeout(() => copied = false, 2000)"
                          class="absolute right-2 top-1/2 transform -translate-y-1/2 text-sky-600 hover:text-sky-700 transition-colors">
                    <svg class="w-4 sm:w-5 h-4 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                  </button>
                  <span x-show="copied" x-transition x-cloak
                        class="absolute right-8 top-1/2 transform -translate-y-1/2 text-xs text-green-600 font-medium">
                    Copied!
                  </span>
                </div>
                <p class="text-xs text-gray-500 mt-1.5">Share this code with the client for quick access.</p>
              </div></div>

            </div>
          </div>

    <!-- FULL-WIDTH ADDRESS FIELD -->
    <div class="relative">
      <label for="address" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
        Address <span class="text-red-500">*</span>
      </label>
      <textarea id="address" name="address" x-model="address" required rows="2" placeholder="123 Rizal St, Barangay Mabini..."
                class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm resize-none placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"></textarea>
      <p x-show="address.trim() === ''" class="text-xs text-red-500 mt-1.5 flex items-center gap-1">
        <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
        This field is required.
      </p>
        </div>
      </div>

      <!-- STEP 2: Travel & Booking -->
  <div x-show="step === 2" class="px-4 py-4 sm:p-6 space-y-3 sm:space-y-4">

    <!-- Progress Header -->
    <div class="flex items-center justify-between mb-3 sm:mb-4 pb-2 sm:pb-3 border-b border-gray-200">
      <h3 class="text-sm sm:text-base font-semibold text-gray-900">Travel & Booking</h3>
      <div class="flex gap-1.5 sm:gap-2 flex-shrink-0">
        <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
        <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
        <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
      </div>
      <div class="text-xs sm:text-sm text-gray-500">Step 2 of 3</div>
    </div>

    <!-- Booking Information -->
    <div class="space-y-3 sm:space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
        <div class="relative">
          <label for="assigned_package" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
            Travel Package
          </label>
          <select id="assigned_package" name="assigned_package_id" x-model="assignedPackage" @change="updatePackageDetails()" class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm bg-white transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
            <option value="" selected>Select a package...</option>
            <?php 
            $packages->data_seek(0);
            while ($pkg = $packages->fetch_assoc()): ?>
              <?php if ((int)$pkg['is_deleted'] !== 1): ?>
                <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['package_name']) ?></option>
              <?php endif; ?>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="relative">
          <label for="booking_number" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
            Booking Number
          </label>
          <input id="booking_number" type="text" name="booking_number" x-model="bookingNumber" placeholder="JVB-00001"
                 class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
        </div>
      </div>

      <!-- 🎉 Package Preview Card - WOW Factor (Responsive) -->
      <template x-if="selectedPackageDetails.package_name" class="p-4">
        <div class="relative overflow-hidden rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg transform transition-all duration-300 hover:shadow-2xl hover:scale-105" 
             :style="`background-image: url('${getPackageBannerUrl()}'); background-size: cover; background-position: center; filter: blur(0px);`"
             x-transition>
          <!-- Background overlay with semi-transparent dark gradient -->
          <div class="absolute inset-0 bg-gradient-to-br from-sky-900/85 via-sky-800/80 to-blue-900/85 backdrop-blur-sm"></div>
          
          <!-- Decorative elements -->
          <div class="absolute top-0 right-0 w-16 sm:w-24 h-16 sm:h-24 bg-white/5 rounded-full -mr-8 sm:-mr-12 -mt-8 sm:-mt-12"></div>
          <div class="absolute bottom-0 left-0 w-20 sm:w-32 h-20 sm:h-32 bg-white/5 rounded-full -ml-10 sm:-ml-16 -mb-10 sm:-mb-16"></div>

          <!-- Content -->
          <div class="relative z-10 space-y-3 sm:space-y-4">
            <!-- Package Name & Booking Number -->
            <div class="flex items-start sm:items-center justify-between gap-2 sm:gap-4">
              <div class="min-w-0">
                <p class="text-xs font-semibold text-sky-100 uppercase tracking-wider">Selected Package</p>
                <h3 class="text-lg sm:text-2xl font-bold text-white mt-0.5 sm:mt-1 break-words" x-text="selectedPackageDetails.package_name"></h3>
              </div>
              <div class="text-right flex-shrink-0">
                <p class="text-xs font-medium text-white/80 mb-0.5 sm:mb-1">Booking #</p>
                <p class="text-base sm:text-lg font-bold text-white font-mono" x-text="bookingNumber || '—'"></p>
              </div>
            </div>

            <!-- Duration, Price & Travel Dates Grid (Responsive) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4 pt-3 sm:pt-4 border-t border-white/30">
              <!-- Duration -->
              <div class="space-y-1 sm:space-y-2">
                <div class="flex items-center gap-1.5 text-white/80">
                  <svg class="w-3 sm:w-4 h-3 sm:h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                  </svg>
                  <span class="text-xs font-medium">Duration</span>
                </div>
                <p class="text-base sm:text-xl font-bold text-white" x-text="`${selectedPackageDetails.day_duration}D / ${selectedPackageDetails.night_duration}N`"></p>
              </div>

              <!-- Price -->
              <div class="space-y-1 sm:space-y-2">
                <div class="flex items-center gap-1.5 text-white/80">
                  <svg class="w-3 sm:w-4 h-3 sm:h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                  </svg>
                  <span class="text-xs font-medium">Price</span>
                </div>
                <p class="text-base sm:text-xl font-bold text-white" x-text="`₱${Number(selectedPackageDetails.price).toLocaleString('en-US', {minimumFractionDigits: 2})}`"></p>
              </div>

                <!-- Travel Dates -->
                <div class="space-y-1 sm:space-y-2">
                <div class="flex items-center gap-1.5 text-white/80">
                  <svg class="w-3 sm:w-4 h-3 sm:h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                  </svg>
                  <span class="text-xs font-medium">Travel Dates</span>
                </div>
                <p class="text-base sm:text-xl font-bold text-white" x-text="tripStart && tripEnd ? new Date(tripStart).toLocaleDateString('en-US', {month: 'short', day: 'numeric'}) + ' to ' + new Date(tripEnd).toLocaleDateString('en-US', {month: 'short', day: 'numeric'}) : 'Not set'"></p>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Travel Dates -->
      <div>
        <h4 class="text-xs sm:text-sm font-semibold text-gray-700 mb-2.5 sm:mb-3 flex items-center gap-1.5 sm:gap-2">
          <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
          </svg>
          Travel Dates
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-5">
          <div>
            <label for="trip_start" class="text-xs sm:text-sm text-gray-700 mb-1.5 sm:mb-2 block font-medium">Departure Date</label>
            <input id="trip_start" type="date" name="trip_date_start" x-model="tripStart"
                   class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
          </div>
          <div>
            <label for="trip_end" class="text-xs sm:text-sm text-gray-700 mb-1.5 sm:mb-2 block font-medium">Return Date</label>
            <input id="trip_end" type="date" name="trip_date_end" x-model="tripEnd"
                   class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
          </div>
          <div>
            <label for="booking_date" class="text-xs sm:text-sm text-gray-700 mb-1.5 sm:mb-2 block font-medium">Booking Date</label>
            <input id="booking_date" type="date" name="booking_date" x-model="bookingDate"
                   class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
          </div>
        </div>
      </div>

      <!-- Hotel Accommodation -->
      <div>
        <h4 class="text-xs sm:text-sm font-semibold text-gray-700 mb-2.5 sm:mb-3 flex items-center gap-1.5 sm:gap-2">
          <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
          </svg>
          Hotel Accommodation
        </h4>
        <!-- Hotel Accommodation Info -->
        <div class="bg-sky-50 border border-sky-100 rounded-lg p-3 sm:p-4">
          <p class="text-xs sm:text-sm text-gray-600">
            <span class="text-gray-900">Hotel Accommodation Details can be updated in <span class="font-semibold text-sky-800">Edit Booking Details</span></span>
          </p>
        </div>

      </div>

    </div>



    <!-- Date Warnings -->
    <div x-show="showDateWarning()" x-transition class="text-xs sm:text-sm text-amber-700 bg-amber-50 px-3 sm:px-4 py-2.5 sm:py-3 rounded-lg border border-amber-200 flex gap-2 sm:gap-3">
      <svg class="w-4 sm:w-5 h-4 sm:h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
      </svg>
      <div class="min-w-0">
        <p x-show="!isValidDates()">Return date must be on or after departure date.</p>
        <p x-show="!datesInFuture()">Departure date cannot be in the past.</p>
      </div>
        </div>
      </div>

      <!-- STEP 3: Passport & Agent -->
  <div x-show="step === 3" class="px-4 py-4 sm:p-6 space-y-3 sm:space-y-4">

    <!-- Progress Header -->
    <div class="flex items-center justify-between mb-3 sm:mb-4 pb-2 sm:pb-3 border-b border-gray-200">
      <h3 class="text-sm sm:text-base font-semibold text-gray-900">Passport & Travel Agent</h3>
      <div class="flex gap-1.5 sm:gap-2 flex-shrink-0">
        <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
        <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
        <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
      </div>
      <div class="text-xs sm:text-sm text-gray-500">Step 3 of 3</div>
    </div>

    <!-- Passport Information -->
    <div class="space-y-3 sm:space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
        <div class="relative">
          <label for="passport_number" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
            Passport Number
          </label>
          <input id="passport_number" type="text" name="passport_number" x-model="passportNumber" placeholder="P1234567A"
                 class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
        </div>

        <div class="relative">
          <label for="passport_expiry" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
            Expiry Date
          </label>
          <input id="passport_expiry" type="date" name="passport_expiry" x-model="passportExpiry"
                 class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
        </div>
      </div>

      <!-- Travel Agent -->
      <div class="relative">
        <label for="assigned_admin" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
          Travel Agent
        </label>
        <select id="assigned_admin" name="assigned_admin_id" x-model="assignedAdmin" class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm bg-white transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
          <option value="" selected>Select an agent...</option>
          <?php 
          $admins->data_seek(0);
          while ($admin = $admins->fetch_assoc()): ?>
            <option value="<?= $admin['id'] ?>" <?= ($admin['id'] == ($_SESSION['admin']['id'] ?? '')) ? 'selected' : '' ?>>
              <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
        <p class="text-xs text-gray-500 mt-1.5">Assign a travel agent to manage this booking.</p>
      </div>
    </div>

    <!-- Summary Section -->
    <div class="bg-sky-50 border border-sky-100 rounded-lg p-3 sm:p-4">
      <p class="text-xs sm:text-sm text-gray-600">
        <span class="font-semibold text-gray-900">Ready to create?</span> Make sure all information is accurate. You can edit it later if needed.
      </p>
    </div>
  </div>

  <!-- Navigation Buttons -->
  <div class="sticky bottom-0 flex justify-between items-center px-4 py-3 sm:px-6 sm:py-2 bg-gray-50 gap-2 sm:gap-3 z-10 mb-4 sm:mb-0 border-t border-gray-200">
    <template x-if="step === 1">
      <div class="flex w-full justify-between gap-2 sm:gap-3">
        <button type="button" @click="showAddClientModal = false"
                class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
          Cancel
        </button>
        <button type="button" @click="step++" :disabled="!canProceedStep1()"
                class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
          Next: Travel & Booking
        </button>
      </div>
    </template>

    <template x-if="step === 2">
      <div class="flex w-full justify-between gap-2 sm:gap-3">
        <button type="button" @click="step--"
                class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
          Back
        </button>
        <button type="button" @click="step++" :disabled="!canProceedStep2()"
                class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
          Next: Passport & Agent
        </button>
      </div>
    </template>

    <template x-if="step === 3">
      <div class="flex w-full justify-between gap-2 sm:gap-3">
        <button type="button" @click="step--"
                class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
          Back
        </button>
        <button type="submit" :disabled="$el.closest('form').classList.contains('submitting')"
                class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 disabled:opacity-50 transition-colors">
          <span x-show="!$el.closest('form').classList.contains('submitting')">Create Client</span>
          <span x-show="$el.closest('form').classList.contains('submitting')">Creating...</span>
        </button>
      </div>
    </template>
  </div>
        </div>
      </form>
    </div>
  </div>
</div>