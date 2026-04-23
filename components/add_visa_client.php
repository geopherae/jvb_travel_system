<?php
// Fetch visa packages for dropdown (including visa_types_json)
$visaPackagesStmt = $conn->prepare("SELECT id, visa_package_name, country, applicant_status_options, processing_days, visa_types_json FROM visa_packages WHERE is_active = 1 ORDER BY visa_package_name ASC");
$visaPackagesStmt->execute();
$visaPackagesResult = $visaPackagesStmt->get_result();
$visaPackages = [];
while ($pkg = $visaPackagesResult->fetch_assoc()) {
  $visaPackages[] = $pkg;
}
$visaPackagesStmt->close();

// Check for group member addition (pre-fill data from session)
$groupData = $_SESSION['visa_client_added'] ?? null;
$isAddingToGroup = !empty($groupData);

// Include tooltips if needed
$tooltips = require __DIR__ . '/../includes/tooltip_map.php';
require_once __DIR__ . '/../includes/tooltip_render.php';
?>

<!-- Add Visa Client Modal -->
<div x-show="showAddVisaClientModal" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title" role="dialog" aria-modal="true"
     @keydown.escape.window="showAddVisaClientModal = false">

  <!-- Backdrop -->
  <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-4 text-center sm:p-0">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showAddVisaClientModal = false"></div>

    <!-- Modal panel -->
    <div class="inline-block align-middle bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-0 sm:align-middle sm:max-w-4xl sm:w-full sm:max-h-[96vh]">
      <form method="POST" action="../actions/process_add_visa_client.php" enctype="multipart/form-data"
        class="flex flex-col h-full font-sans"
        x-data="visaClientForm(<?= $isAddingToGroup ? htmlspecialchars(json_encode($groupData), ENT_QUOTES, 'UTF-8') : 'null' ?>, <?= isset($currentAdminId) ? (int)$currentAdminId : 'null' ?>)" 
        @submit="$el.classList.add('submitting')">

    <!-- Hidden field for group_code -->
    <input type="hidden" name="group_code" x-model="groupCode" />
    <input type="hidden" name="assigned_admin_id" x-model="assignedAdminId" />
  <input type="hidden" name="application_mode" x-model="applicationMode" />

        <!-- Header -->
        <div class="bg-gradient-to-r from-sky-500 to-blue-600 px-4 py-3 sm:px-6">
          <div class="p-2 flex items-center justify-between">
            <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">
              Add Client | Visa Processing
            </h3>
            <button type="button" @click="showAddVisaClientModal = false"
                    class="text-white hover:text-gray-200 transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>

        <!-- STEP 1: Basic Info -->
        <div x-show="step === 1" class="px-4 py-4 sm:p-6 space-y-3 sm:space-y-4">

          <!-- Progress Header with Group Indicator -->
          <div class="grid grid-cols-3 items-center gap-4 mb-3 sm:mb-4 pb-2 sm:pb-3 border-b border-gray-200">
            <div>
              <h3 class="text-sm sm:text-base font-semibold text-gray-900">Client Basic Info</h3>
              <p x-show="isAddingToGroup" class="text-xs text-sky-600 font-medium mt-0.5 flex items-center gap-1">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path></svg>
                Adding to group
              </p>
            </div>
            <div class="flex items-center justify-center gap-1.5 sm:gap-2">
              <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
              <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
              <div x-show="applicationMode === 'group'" class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
            </div>
            <div class="text-xs sm:text-sm text-gray-500 text-right" x-text="'Step 1 of ' + getTotalSteps()"></div>
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
              class="relative flex flex-col items-center gap-1.5 sm:gap-2 border-2 border-dashed border-sky-200 rounded-lg sm:rounded-xl py-3 sm:py-4 px-2 sm:px-3 bg-gradient-to-br from-sky-50 to-transparent hover:border-sky-400 hover:from-sky-100 transition-all cursor-pointer group">

                <!-- Decorative corner accent -->
                <div class="absolute top-0 right-0 w-8 sm:w-12 h-8 sm:h-12 bg-sky-500 opacity-5 rounded-bl-xl sm:rounded-bl-2xl"></div>

                <!-- Image with better styling -->
                <img :src="previewUrl" alt="Profile Preview"
                     class="w-12 sm:w-16 h-12 sm:h-16 rounded-lg sm:rounded-lg object-cover border-2 border-sky-100 shadow-sm group-hover:shadow-md transition-shadow" loading="lazy" />

                <!-- Upload label with icon -->
                <label for="add-visa-client-photo" class="text-center cursor-pointer">
                  <div class="flex items-center justify-center mb-1.5 sm:mb-2">
                    <svg class="w-4 sm:w-5 h-4 sm:h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                  </div>
                  <p class="text-xs font-semibold text-sky-600 group-hover:text-sky-700">Upload Photo</p>
                  <p class="text-xs text-gray-500 mt-0.5">Max 2MB</p>
                  <input id="add-visa-client-photo" name="client_profile_photo" type="file"
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
                       :class="{ 'border-red-500 ring-red-500': email && (!isValidEmail() || emailExists), 'border-green-500 ring-green-500': isValidEmail() && !emailExists }" />
                <p x-show="email && !isValidEmail()" class="text-xs text-red-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                  Invalid email format.
                </p>
                <p x-show="emailExists" class="text-xs text-red-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                  Email already exists in the database.
                </p>
                <p x-show="checkingEmail" class="text-xs text-gray-500 mt-1.5">Checking email…</p>
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

              <!-- Address -->
              <div class="relative">
                <label for="address" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                  Address <span class="text-red-500">*</span>
                </label>
                <input id="address" type="text" name="address" x-model="address" required placeholder="Street, City"
                       class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
              </div>

            </div>

            <!-- RIGHT COLUMN: Processing Type + Application Mode + Name + Access Code -->
            <div class="space-y-4 sm:space-y-5">

              <!-- Group: Processing + Application Mode -->
              <div class="space-y-3 sm:space-y-4">

              
                <!-- Processing Type -->
<!-- Processing Type -->
<div x-data="{ 
  open: false,
  options: [
    { value: 'visa', label: 'Visa Processing' },
    { value: 'both', label: 'Both Booking & Visa' }
  ],
  getDisplayText() {
    const found = this.options.find(o => o.value === processingType);
    return found ? found.label : 'Select processing type';
  }
}" 
@click.away="open = false"
class="relative">
  <label for="processing_type" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700 z-10">
    Processing Type <span class="text-red-500">*</span>
  </label>
  
  <!-- Dropdown Button -->
  <button 
    type="button"
    @click="open = !open"
    class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent flex items-center justify-between bg-white"
    :class="{ 'text-gray-500': !processingType, 'text-gray-900': processingType }">
    <span x-text="getDisplayText()"></span>
    <svg class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
  </button>

  <!-- Dropdown Options -->
  <div 
    x-show="open"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
    
    <template x-for="option in options" :key="option.value">
      <label class="flex items-center px-3 sm:px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition"
             @click.stop="processingType = option.value; open = false">
        <input 
          type="radio"
          name="processing_type_display"
          :value="option.value"
          :checked="processingType === option.value"
          class="w-4 h-4 text-sky-600 border-gray-300 focus:ring-sky-500 focus:ring-2 cursor-pointer">
        <span class="ml-3 text-sm text-gray-700" x-text="option.label"></span>
      </label>
    </template>
  </div>

  <!-- Hidden input for form submission -->
  <input type="hidden" name="processing_type" :value="processingType">

  <p class="text-xs text-gray-500 mt-1.5">Select the type of service this client will use.</p>
</div>

<!-- Application Mode -->
<div x-data="{ 
  open: false,
  options: [
    { value: 'individual', label: 'Individual Application' },
    { value: 'group', label: 'Group Application' }
  ],
  getDisplayText() {
    const found = this.options.find(o => o.value === applicationMode);
    return found ? found.label : 'Select application mode';
  }
}" 
@click.away="open = false"
class="relative">
  <label for="application_mode" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700 z-10">
    Application Mode <span class="text-red-500">*</span>
  </label>
  
  <!-- Dropdown Button -->
  <button 
    type="button"
    @click="open = !open"
    class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent flex items-center justify-between bg-white"
    :class="{ 'text-gray-500': !applicationMode, 'text-gray-900': applicationMode }">
    <span x-text="getDisplayText()"></span>
    <svg class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
  </button>

  <!-- Dropdown Options -->
  <div 
    x-show="open"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
    
    <template x-for="option in options" :key="option.value">
      <label class="flex items-center px-3 sm:px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition"
             @click.stop="applicationMode = option.value; open = false">
        <input 
          type="radio"
          name="application_mode_display"
          :value="option.value"
          :checked="applicationMode === option.value"
          class="w-4 h-4 text-sky-600 border-gray-300 focus:ring-sky-500 focus:ring-2 cursor-pointer">
        <span class="ml-3 text-sm text-gray-700" x-text="option.label"></span>
      </label>
    </template>
  </div>

  <p class="text-xs text-gray-500 mt-1.5">Choose Individual for single client or Group for family/group.</p>
</div>

<!-- Financial Source -->
<div x-data="{ 
  open: false,
  options: [
    { value: 'self_funded', label: 'Self-Funded' },
    { value: 'sponsor', label: 'Sponsor' }
  ],
  getDisplayText() {
    const found = this.options.find(o => o.value === financialSource);
    return found ? found.label : 'Select financial source';
  }
}" 
@click.away="open = false"
class="relative top-4">
  <label for="financial_source" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700 z-10">
    Financial Source <span class="text-red-500">*</span>
  </label>
  
  <!-- Dropdown Button -->
  <button 
    type="button"
    @click="open = !open"
    class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent flex items-center justify-between bg-white"
    :class="{ 'text-gray-500': !financialSource, 'text-gray-900': financialSource }">
    <span x-text="getDisplayText()"></span>
    <svg class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
  </button>

  <!-- Dropdown Options -->
  <div 
    x-show="open"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
    
    <template x-for="option in options" :key="option.value">
      <label class="flex items-center px-3 sm:px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition"
             @click.stop="financialSource = option.value; open = false">
        <input 
          type="radio"
          name="financial_source_display"
          :value="option.value"
          :checked="financialSource === option.value"
          class="w-4 h-4 text-sky-600 border-gray-300 focus:ring-sky-500 focus:ring-2 cursor-pointer">
        <span class="ml-3 text-sm text-gray-700" x-text="option.label"></span>
      </label>
    </template>
  </div>

  <!-- Hidden input for form submission -->
  <input type="hidden" name="financial_source" :value="financialSource">
</div>
              </div>

              <!-- Group: Full Name + Access Code -->
              <div class="space-y-3 sm:space-y-4">
                <!-- Full Name -->
                <div class="pt-[20px]">
                  <div class="relative">
                    <label for="full_name" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                      Lead Guest Full Name <span class="text-red-500">*</span>
                    </label>
                    <input id="full_name" type="text" name="full_name" x-model="fullName" required
                           placeholder="Maria Reyes"
                           @input.debounce.500="generateAccessCode()"
                           class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
                    <p x-show="fullName.trim() === ''" class="text-xs text-red-500 mt-1.5 flex items_center gap-1">
                      <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414z" clip-rule="evenodd"></path></svg>
                      This field is required.
                    </p>
                  </div>
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
                  </div>
                </div>
              </div>

            </div>
          </div>

        </div>

<!-- STEP 2: Passport & Visa Status -->
        <div x-show="step === 2" class="px-4 py-4 sm:p-6 space-y-4 sm:space-y-6">

<!-- Progress Header -->
          <div class="grid grid-cols-3 items-center gap-4 mb-3 sm:mb-4 pb-2 sm:pb-3 border-b border-gray-200">
            <div>
              <h3 class="text-sm sm:text-base font-semibold text-gray-900">Passport & Visa Status</h3>
              <p class="text-xs text-gray-500 mt-0.5">Add passport details and applicant status.</p>
            </div>
            <div class="flex items-center justify-center gap-1.5 sm:gap-2">
              <template x-if="getTotalSteps() === 2">
                <div class="flex gap-1.5 sm:gap-2">
                  <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
                  <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
                </div>
              </template>
              <template x-if="getTotalSteps() === 3">
                <div class="flex gap-1.5 sm:gap-2">
                  <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
                  <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
                  <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
                </div>
              </template>
            </div>
            <div class="text-xs sm:text-sm text-gray-500 text-right" x-text="'Step 2 of ' + getTotalSteps()"></div>
          </div>

          <!-- Visa Package Selection -->
          <div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
              
<!-- Visa Package Dropdown -->
<div x-data="{ 
  open: false,
  packages: <?= htmlspecialchars(json_encode(array_map(function($pkg) {
    return [
      'id' => $pkg['id'],
      'label' => $pkg['visa_package_name'] ?: ($pkg['country'] . ' (' . $pkg['processing_days'] . ' days)'),
      'processingDays' => $pkg['processing_days'],
      'visaTypes' => $pkg['visa_types_json'] ? json_decode($pkg['visa_types_json'], true) : [],
      'applicantStatusOptions' => $pkg['applicant_status_options'] ?? '[]'
    ];
  }, $visaPackages)), ENT_QUOTES, 'UTF-8') ?>,
  getDisplayText() {
    if (!selectedVisaPackage) return 'Select a visa package...';
    const found = this.packages.find(p => p.id == selectedVisaPackage);
    return found ? found.label : 'Select a visa package...';
  },
  selectPackage(pkg) {
    selectedVisaPackage = pkg.id;
    this.open = false;
    // Manually trigger the package change
    const selectElement = document.getElementById('visa_package_id');
    if (selectElement) {
      selectElement.value = pkg.id;
      selectElement.dispatchEvent(new Event('change'));
    }
    onVisaPackageChange();
  }
}"
@click.away="open = false">
  <label for="visa_package_id" class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">
    <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>
    Visa Package <span class="text-red-500">*</span>
  </label>
  
  <!-- Dropdown Button -->
  <button 
    type="button"
    @click="open = !open"
    class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent flex items-center justify-between bg-white"
    :class="{ 'text-gray-500': !selectedVisaPackage, 'text-gray-900': selectedVisaPackage }">
    <span class="block truncate" :title="getDisplayText()" x-text="getDisplayText()"></span>
    <svg class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
  </button>

  <!-- Dropdown Options -->
  <div 
    x-show="open"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="absolute z-50 w-full max-w-[845px] mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
    
    <template x-for="pkg in packages" :key="pkg.id">
      <label class="flex items-center px-3 sm:px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition"
             @click.stop="selectPackage(pkg)">
        <input 
          type="radio"
          name="visa_package_display"
          :value="pkg.id"
          :checked="selectedVisaPackage == pkg.id"
          class="w-4 h-4 text-sky-600 border-gray-300 focus:ring-sky-500 focus:ring-2 cursor-pointer">
        <span class="ml-3 text-sm text-gray-700 truncate" :title="pkg.label" x-text="pkg.label"></span>
      </label>
    </template>
  </div>

  <!-- Hidden select for form submission and change detection -->
  <select id="visa_package_id" name="visa_package_id" x-model.number="selectedVisaPackage" 
          @change="onVisaPackageChange()" required class="hidden">
    <option value="">Select a visa package...</option>
    <?php foreach ($visaPackages as $pkg):
      // Safe decode for visa_types_json
      $visaTypesArr = [];
      if (!empty($pkg['visa_types_json'])) {
        $decoded = json_decode($pkg['visa_types_json'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
          $visaTypesArr = $decoded;
        }
      }
      // Safe decode for applicant_status_options
      $appStatusArr = [];
      if (!empty($pkg['applicant_status_options'])) {
        $decoded = json_decode($pkg['applicant_status_options'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
          $appStatusArr = $decoded;
        }
      }
    ?>
      <option value="<?= $pkg['id'] ?>" 
              data-processing-days="<?= $pkg['processing_days'] ?>"
              data-visa-types="<?= htmlspecialchars(json_encode($visaTypesArr, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
              data-applicant-status-options="<?= htmlspecialchars(json_encode($appStatusArr, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($pkg['visa_package_name'] ?: ($pkg['country'] . ' (' . $pkg['processing_days'] . ' days)')) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <p class="text-xs text-gray-500 mt-1.5 sm:mt-2">Choose a visa package. This will be applied if group application.</p>
</div>

<!-- Visa Type Dropdown (Enabled only after package selection) -->
<div x-data="{ 
  open: false,
  getDisplayText() {
    return visaTypeSelected || 'Select Visa Type';
  }
}" 
@click.away="open = false">
  <label for="visa_type_selected" class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">
    <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
    </svg>
    Visa Type <span class="text-red-500">*</span>
  </label>
  
  <!-- Dropdown Button -->
  <button 
    type="button"
    @click="open = !open"
    :disabled="!selectedVisaPackage"
    class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-500 flex items-center justify-between bg-white"
    :class="{ 'text-gray-500': !visaTypeSelected, 'text-gray-900': visaTypeSelected }">
    <span x-text="getDisplayText()"></span>
    <svg class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
  </button>

  <!-- Dropdown Options -->
  <div 
    x-show="open"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
    
    <template x-for="visaType in availableVisaTypes" :key="visaType">
      <label class="flex items-center px-3 sm:px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition"
             @click.stop="visaTypeSelected = visaType; open = false">
        <input 
          type="radio"
          name="visa_type_display"
          :value="visaType"
          :checked="visaTypeSelected === visaType"
          class="w-4 h-4 text-sky-600 border-gray-300 focus:ring-sky-500 focus:ring-2 cursor-pointer">
        <span class="ml-3 text-sm text-gray-700" x-text="visaType"></span>
      </label>
    </template>
  </div>

  <!-- Hidden input for form submission -->
  <input type="hidden" id="visa_type_selected" name="visa_type_selected" :value="visaTypeSelected" required>

  <p class="text-xs text-gray-500 mt-1.5 sm:mt-2" x-show="!selectedVisaPackage">
    Select a visa package above to choose a visa type.
  </p>
  <p class="text-xs text-gray-500 mt-1.5 sm:mt-2" x-show="selectedVisaPackage && availableVisaTypes.length === 0">
    No visa types available for this package.
  </p>
</div>

            </div>
          </div>

<!-- Applicant Status and Sponsor Status (Side by Side) -->
<div class="border-t border-gray-200 pt-4 sm:pt-6">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
    
    <!-- Applicant Status -->
    <div x-data="{ 
      open: false,
      selectedStatuses: [],
      toggleStatus(status) {
        const index = this.selectedStatuses.indexOf(status);
        if (index === -1) {
          this.selectedStatuses.push(status);
        } else {
          this.selectedStatuses.splice(index, 1);
        }
      },
      isSelected(status) {
        return this.selectedStatuses.includes(status);
      },
      getDisplayText() {
        if (this.selectedStatuses.length === 0) return 'Select applicant status';
        if (this.selectedStatuses.length === 1) {
          const found = applicantStatusOptions.find(o => o.option === this.selectedStatuses[0]);
          return found ? found.label : this.selectedStatuses[0];
        }
        return `${this.selectedStatuses.length} statuses selected`;
      }
    }" 
    @click.away="open = false"
    class="relative">
      <label for="applicant_status" class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">
        <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
        </svg>
        Applicant Status <span class="text-red-500">*</span>
      </label>
      
      <!-- Dropdown Button -->
      <button 
        type="button"
        @click="open = !open"
        :disabled="!selectedVisaPackage"
        class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-500 flex items-center justify-between"
        :class="{ 'text-gray-500': selectedStatuses.length === 0, 'text-gray-900': selectedStatuses.length > 0 }">
        <span x-text="getDisplayText()"></span>
        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
      </button>

      <!-- Dropdown Options -->
      <div 
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
        
        <div class="flex items-center justify-between px-3 sm:px-4 py-2 border-b border-gray-200 bg-gray-50">
          <button type="button" @click="selectedStatuses = applicantStatusOptions.map(o => o.option)" 
                  class="text-xs text-sky-600 hover:text-sky-700 font-medium">
            Select All
          </button>
          <button type="button" @click="selectedStatuses = []" 
                  class="text-xs text-gray-600 hover:text-gray-700 font-medium">
            Clear All
          </button>
        </div>

        <template x-for="statusOption in applicantStatusOptions" :key="statusOption.option">
          <label 
            class="flex items-center px-3 sm:px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition">
            <input 
              type="checkbox"
              :value="statusOption.option"
              @change="toggleStatus(statusOption.option)"
              :checked="isSelected(statusOption.option)"
              class="w-4 h-4 text-sky-600 border-gray-300 rounded focus:ring-sky-500 focus:ring-2 cursor-pointer">
            <span class="ml-3 text-sm text-gray-700" x-text="statusOption.label"></span>
          </label>
        </template>
      </div>

      <!-- Hidden inputs for form submission and validation -->
      <template x-for="(status, index) in selectedStatuses" :key="index">
        <input type="hidden" name="applicant_status[]" :value="status">
      </template>
      <!-- Hidden field to send status options for backend mapping -->
      <input type="hidden" name="applicant_status_options" :value="JSON.stringify(applicantStatusOptions)">
      
      <!-- Hidden required field for validation -->
      <input type="hidden" name="applicant_status_required" :value="selectedStatuses.length > 0 ? 'valid' : ''" required>

      <p class="text-xs text-gray-500 mt-1.5 sm:mt-2" x-show="!selectedVisaPackage">
        Select a visa package above to choose applicant status.
      </p>
      <p class="text-xs text-gray-500 mt-1.5 sm:mt-2" x-show="selectedVisaPackage">
        This determines which conditional requirements apply.
      </p>
    </div>

    <!-- Sponsor Status (only if Financial Source is Sponsor) -->
    <div x-show="financialSource === 'sponsor'" x-transition>
      <label for="sponsor_status" class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">
        <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
        Sponsor Status <span class="text-red-500">*</span>
      </label>
      <div x-data="{ open: false, options: [
        { value: 'employed', label: 'Employed' },
        { value: 'self_employed_business_owner_corporation', label: 'Self-Employed/Business Owner/Corporation' }
      ], getDisplayText() {
        const found = this.options.find(o => o.value === sponsorStatus);
        return found ? found.label : 'Select sponsor status';
      }}" @click.away="open = false">
        <button type="button" @click="open = !open"
          class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent flex items-center justify-between bg-white"
          :class="{ 'text-gray-500': !sponsorStatus, 'text-gray-900': sponsorStatus }">
          <span x-text="getDisplayText()"></span>
          <svg class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </button>
        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
          <template x-for="option in options" :key="option.value">
            <label class="flex items-center px-3 sm:px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition" @click.stop="sponsorStatus = option.value; open = false">
              <input type="radio" name="sponsor_status_display" :value="option.value" :checked="sponsorStatus === option.value" class="w-4 h-4 text-sky-600 border-gray-300 focus:ring-sky-500 focus:ring-2 cursor-pointer">
              <span class="ml-3 text-sm text-gray-700" x-text="option.label"></span>
            </label>
          </template>
        </div>
        <input type="hidden" name="sponsor_status" :value="sponsorStatus" :required="financialSource === 'sponsor'">
      </div>
      <p class="text-xs text-gray-500 mt-1.5">Required when financial source is Sponsor.</p>
    </div>

  </div>
</div>

          <!-- Passport Details (Two Column Layout) -->
           <div class="border-t border-gray-200 pt-4 sm:pt-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 pb-4 sm:pb-6">
            
            <!-- Passport Number -->
            <div class="relative">
              <label for="passport_number" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                Passport Number <span class="text-red-500">*</span>
              </label>
              <input id="passport_number" type="text" name="passport_number" x-model="passportNumber" required
                     placeholder="e.g., AA-1234567"
                     class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
              <p class="text-xs text-gray-500 mt-1.5">From passport bio page</p>
            </div>

            <!-- Passport Expiry Date -->
            <div class="relative">
              <label for="passport_expiry" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                Passport Expiry <span class="text-red-500">*</span>
              </label>
              <input id="passport_expiry" type="date" name="passport_expiry" x-model="passportExpiry" required
                     class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                     :min="getTodayDate()"
                     @change="checkPassportValidity()" />
              <p x-show="passportExpiry && !isPassportValid()" class="text-xs text-red-500 mt-1.5 flex items-center gap-1">
                <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                Passport must be valid for at least 6 months.
              </p>
            </div>
          </div>
          </div>

        </div>

        <!-- STEP 3: Group Members (Only if Group Application) -->
        <div x-show="step === 3 && applicationMode === 'group'" class="px-4 py-5 sm:p-6 space-y-3 sm:space-y-5 max-h-[60vh] overflow-y-auto">

<!-- Progress Header -->
          <div class="grid grid-cols-3 items-center gap-4 mb-3 sm:mb-4 pb-2 sm:pb-3 border-b border-gray-200">
            <div class="min-w-0">
              <h3 class="text-sm sm:text-base font-semibold text-gray-900">Group Members</h3>
              <p class="text-xs text-gray-500 mt-0.5">Add group members (Optional)</p>
            </div>
            <div class="flex items-center justify-center gap-1.5 sm:gap-2">
              <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
              <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
              <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
            </div>
            <div class="text-xs sm:text-sm text-gray-500 text-right">Step 3 of 3</div>
          </div>

          <div class="bg-blue-50 border border-blue-100 rounded-lg p-3">
            <p class="text-xs text-gray-700">
              <strong>Lead Guest:</strong> <span x-text="fullName"></span> will be created first. Add additional group members below (they will share the same visa package and group code).
            </p>
          </div>

          <!-- Add Member Button -->
          <button type="button" @click="addGroupMember()" :disabled="groupMembers.length >= maxGroupMembers"
                  class="w-full px-4 py-3 border-2 border-dashed rounded-lg text-sm font-medium flex items-center justify-center gap-2 transition-colors"
                  :class="groupMembers.length >= maxGroupMembers ? 'border-gray-200 text-gray-400 cursor-not-allowed' : 'border-sky-300 text-sky-600 hover:bg-sky-50 hover:border-sky-400'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            <span x-show="groupMembers.length < maxGroupMembers">Add Companion</span>
            <span x-show="groupMembers.length >= maxGroupMembers">Companion limit reached</span>
          </button>
          <p class="text-xs text-gray-600">Up to {{ maxGroupMembers }} additional companions per application. Added: <span x-text="groupMembers.length"></span>.</p>

          <!-- Group Members List -->
          <div class="space-y-3 max-h-[75vh] overflow-y-auto pr-1">
            <template x-for="(member, index) in groupMembers" :key="member.id">
              <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <div class="flex items-center justify-between mb-3">
                  <h4 class="text-sm font-semibold text-gray-700" x-text="'Companion ' + (index + 1)"></h4>
                  <button type="button" @click="removeGroupMember(member.id)"
                          class="text-red-500 hover:text-red-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                  </button>
                </div>

<div x-data="{ 
  open: false,
  getDisplayText() {
    return member.visaType || 'Select visa type';
  }
}" 
@click.away="open = false"
class="relative mb-4">
  <label :for="'companion_visa_type_' + index" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700 z-10">
    Visa Type <span class="text-red-500">*</span>
  </label>
  
  <!-- Dropdown Button -->
  <button 
    type="button"
    @click="open = !open"
    class="w-full border border-gray-300 rounded-lg px-3 py-3 pt-5 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent flex items-center justify-between bg-white"
    :class="{ 'text-gray-500': !member.visaType, 'text-gray-900': member.visaType }">
    <span x-text="getDisplayText()"></span>
    <svg class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
  </button>

  <!-- Dropdown Options -->
  <div 
    x-show="open"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
    
    <template x-for="vtype in availableVisaTypes" :key="vtype">
      <label class="flex items-center px-3 py-2.5 hover:bg-gray-50 cursor-pointer transition"
             @click.stop="member.visaType = vtype; open = false">
        <input 
          type="radio"
          :name="'companion_visa_type_display_' + index"
          :value="vtype"
          :checked="member.visaType === vtype"
          class="w-4 h-4 text-sky-600 border-gray-300 focus:ring-sky-500 focus:ring-2 cursor-pointer">
        <span class="ml-3 text-sm text-gray-700" x-text="vtype"></span>
      </label>
    </template>
  </div>

  <!-- Hidden input for form submission -->
  <input type="hidden" :name="'companion_visa_type_' + index" :value="member.visaType" required>

  <p class="text-xs text-gray-500 mt-1">Same as lead applicant or select different</p>
</div>

                <!-- Basic Info Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                  <div class="relative">
                    <label :for="'companion_name_' + index" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                      Full Name <span class="text-red-500">*</span>
                    </label>
                    <input :id="'companion_name_' + index" type="text" x-model="member.fullName" 
                           :name="'companion_name_' + index" required
                           placeholder="Full Name"
                           class="w-full border border-gray-300 rounded-lg px-3 py-3 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
                  </div>

<div x-data="{ 
  open: false,
  options: [
    { value: 'spouse', label: 'Spouse' },
    { value: 'child', label: 'Child' },
    { value: 'parent', label: 'Parent' },
    { value: 'sibling', label: 'Sibling' },
    { value: 'relative', label: 'Other Relative' },
    { value: 'friend', label: 'Friend' }
  ],
  getDisplayText() {
    if (!member.relationship) return 'Select relationship';
    const found = this.options.find(o => o.value === member.relationship);
    return found ? found.label : member.relationship;
  }
}" 
@click.away="open = false"
class="relative">
  <label :for="'companion_relationship_' + index" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700 z-10">
    Relationship with Lead Guest<span class="text-red-500">*</span>
  </label>
  
  <!-- Dropdown Button -->
  <button 
    type="button"
    @click="open = !open"
    class="w-full border border-gray-300 rounded-lg px-3 py-3 pt-5 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent flex items-center justify-between bg-white"
    :class="{ 'text-gray-500': !member.relationship, 'text-gray-900': member.relationship }">
    <span x-text="getDisplayText()"></span>
    <svg class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
  </button>

  <!-- Dropdown Options -->
  <div 
    x-show="open"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
    
    <template x-for="option in options" :key="option.value">
      <label class="flex items-center px-3 py-2.5 hover:bg-gray-50 cursor-pointer transition"
             @click.stop="member.relationship = option.value; open = false">
        <input 
          type="radio"
          :name="'companion_relationship_display_' + index"
          :value="option.value"
          :checked="member.relationship === option.value"
          class="w-4 h-4 text-sky-600 border-gray-300 focus:ring-sky-500 focus:ring-2 cursor-pointer">
        <span class="ml-3 text-sm text-gray-700" x-text="option.label"></span>
      </label>
    </template>
  </div>

  <!-- Hidden input for form submission -->
  <input type="hidden" :name="'companion_relationship_' + index" :value="member.relationship" required>
</div>
                </div>

                <!-- Contact & Passport Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                  <div class="relative">
                    <label :for="'companion_email_' + index" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                      Email <span class="text-red-500">*</span>
                    </label>
                    <input :id="'companion_email_' + index" type="email" x-model="member.email" 
                           :name="'companion_email_' + index" required
                           placeholder="Email"
                           class="w-full border border-gray-300 rounded-lg px-3 py-3 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                           :class="{ 'border-red-500': member.email && !isValidMemberEmail(member.email) }" />
                  </div>

                  <div class="relative">
                    <label :for="'companion_passport_' + index" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                      Passport Number <span class="text-red-500">*</span>
                    </label>
                    <input :id="'companion_passport_' + index" type="text" x-model="member.passportNumber"
                           :name="'companion_passport_' + index" required
                           placeholder="e.g., AA-1234567"
                           class="w-full border border-gray-300 rounded-lg px-3 py-3 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
                  </div>
                </div>

                <!-- Phone & Passport Expiry Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                  <div class="relative">
                    <label :for="'companion_phone_' + index" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                      Phone <span class="text-red-500">*</span>
                    </label>
                    <input :id="'companion_phone_' + index" type="tel" x-model="member.phone" 
                           :name="'companion_phone_' + index" required maxlength="11"
                           placeholder="09xxxxxxxxx"
                           class="w-full border border-gray-300 rounded-lg px-3 py-3 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                           :class="{ 'border-red-500': member.phone && !isValidMemberPhone(member.phone) }" />
                  </div>

                  <div class="relative">
                    <label :for="'companion_passport_expiry_' + index" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                      Passport Expiry <span class="text-red-500">*</span>
                    </label>
                    <input :id="'companion_passport_expiry_' + index" type="date" x-model="member.passportExpiry"
                           :name="'companion_passport_expiry_' + index" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-3 pt-5 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                           :min="getTodayDate()" />
                  </div>
                </div>

                <!-- Address & Applicant Status Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div class="relative">
                    <label :for="'companion_address_' + index" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                      Address <span class="text-red-500">*</span>
                    </label>
                    <input :id="'companion_address_' + index" type="text" x-model="member.address" 
                           :name="'companion_address_' + index" required
                           placeholder="Street, City"
                           class="w-full border border-gray-300 rounded-lg px-3 py-3 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
                  </div>

<div class="relative">
  <!-- Applicant Status Multi-Select Dropdown (Companion) -->
  <div x-data="{
    open: false,
    toggleStatus(status) {
      const idx = member.applicantStatus?.indexOf(status) ?? -1;
      if (idx === -1) {
        if (!member.applicantStatus) member.applicantStatus = [];
        member.applicantStatus.push(status);
      } else {
        member.applicantStatus.splice(idx, 1);
      }
    },
    isSelected(status) {
      return Array.isArray(member.applicantStatus) && member.applicantStatus.includes(status);
    },
    getDisplayText() {
      if (!member.applicantStatus || member.applicantStatus.length === 0) return 'Select applicant status';
      if (member.applicantStatus.length === 1) {
        const found = applicantStatusOptions.find(o => o.option === member.applicantStatus[0]);
        return found ? found.label : member.applicantStatus[0];
      }
      return `${member.applicantStatus.length} statuses selected`;
    }
  }" 
  @click.away="open = false"
  class="relative">
    
    <label :for="'companion_status_' + index" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700 z-10">
      Applicant Status
    </label>
    
    <!-- Dropdown Button -->
    <button 
      type="button"
      @click="open = !open"
      :id="'companion_status_' + index"
      class="w-full border border-gray-300 rounded-lg px-3 py-3 pt-5 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent flex items-center justify-between"
      :class="{ 'text-gray-500': !member.applicantStatus || member.applicantStatus.length === 0, 'text-gray-900': member.applicantStatus && member.applicantStatus.length > 0 }">
      <span x-text="getDisplayText()"></span>
      <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
      </svg>
    </button>

    <!-- Dropdown Options -->
    <div 
      x-show="open"
      x-transition:enter="transition ease-out duration-100"
      x-transition:enter-start="opacity-0 scale-95"
      x-transition:enter-end="opacity-100 scale-100"
      x-transition:leave="transition ease-in duration-75"
      x-transition:leave-start="opacity-100 scale-100"
      x-transition:leave-end="opacity-0 scale-95"
      class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
      
      <!-- Select All / Clear All -->
      <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 bg-gray-50">
        <button 
          type="button" 
          @click="member.applicantStatus = applicantStatusOptions.map(o => o.option)"
          class="text-xs text-sky-600 hover:text-sky-700 font-medium">
          Select All
        </button>
        <button 
          type="button" 
          @click="member.applicantStatus = []"
          class="text-xs text-gray-600 hover:text-gray-700 font-medium">
          Clear All
        </button>
      </div>

      <!-- Checkbox Options -->
      <template x-for="statusOption in applicantStatusOptions" :key="statusOption.option">
        <label class="flex items-center px-3 py-2.5 hover:bg-gray-50 cursor-pointer transition">
          <input 
            type="checkbox"
            :value="statusOption.option"
            @change="toggleStatus(statusOption.option)"
            :checked="isSelected(statusOption.option)"
            class="w-4 h-4 text-sky-600 border-gray-300 rounded focus:ring-sky-500 focus:ring-2 cursor-pointer">
          <span class="ml-3 text-sm text-gray-700" x-text="statusOption.label"></span>
        </label>
      </template>
    </div>

    <!-- Hidden inputs for form submission -->
    <template x-if="Array.isArray(member.applicantStatus)">
      <template x-for="(status, statusIdx) in member.applicantStatus" :key="statusIdx">
        <input type="hidden" :name="'companion_status_' + index + '[]'" :value="status">
      </template>
    </template>
    <!-- Hidden field to send status options for backend mapping (per companion) -->
    <input type="hidden" :name="'companion_applicant_status_options_' + index" :value="JSON.stringify(applicantStatusOptions)">
  </div>
</div>
                </div>
              </div>
            </template>

            <p x-show="groupMembers.length === 0" class="text-center text-sm text-gray-500 py-4">
              No companions added yet. Click "Add Companion" to include more people.
            </p>
          </div>

          <!-- Hidden field to pass group members as JSON -->
          <input type="hidden" name="group_members_json" :value="JSON.stringify(groupMembers)" />
        </div>

        <!-- Navigation Buttons -->
        <div class="sticky bottom-0 flex justify-between items-center px-4 py-3 sm:px-6 sm:py-2 bg-gray-50 gap-2 sm:gap-3 z-10 mb-4 sm:mb-0 border-t border-gray-200">
          <template x-if="step === 1">
            <div class="flex w-full justify-between gap-2 sm:gap-3">
              <button type="button" @click="showAddVisaClientModal = false"
                      class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
                Cancel
              </button>
              <button type="button" @click="proceedStep1()" :disabled="checkingEmail"
                      class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
                Next: Passport Details
              </button>
            </div>
          </template>

<!-- Step 2 Navigation Buttons -->
<template x-if="step === 2">
  <div class="flex w-full justify-between gap-2 sm:gap-3">
    <button type="button" @click="step = 1"
            class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
      Back
    </button>
    <template x-if="applicationMode === 'individual'">
      <button type="submit" :disabled="$el.closest('form').classList.contains('submitting')"
              class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
        <span x-show="!$el.closest('form').classList.contains('submitting')">Create Visa Application</span>
        <span x-show="$el.closest('form').classList.contains('submitting')">Creating...</span>
      </button>
    </template>
    <template x-if="applicationMode === 'group'">
      <button type="button" @click="step = 3"
              class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
        Next: Add Companions
      </button>
    </template>
  </div>
</template>

          <template x-if="step === 3">
            <div class="flex w-full justify-between gap-2 sm:gap-3">
              <button type="button" @click="step = 2"
                      class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
                Back
              </button>
              <button type="submit" :disabled="$el.closest('form').classList.contains('submitting')"
                      class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                <span x-show="!$el.closest('form').classList.contains('submitting')">Create Group Application</span>
                <span x-show="$el.closest('form').classList.contains('submitting')">Creating...</span>
              </button>
            </div>
          </template>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function visaClientForm(groupData = null, currentAdminId = null) {
  return {
    step: 1,
    applicationMode: 'individual', // 'individual' or 'group'
    isAddingToGroup: !!groupData,
    groupCode: groupData?.group_code || '',
    processingType: groupData?.processing_type || 'visa',
    assignedAdminId: groupData?.assigned_admin_id || (currentAdminId ? String(currentAdminId) : ''),
    financialSource: 'self_funded',
    fullName: '',
    email: '',
    emailExists: false,
    checkingEmail: false,
    phone: '',
    address: '',
    accessCode: '',
    passportNumber: '',
    passportExpiry: '',
    applicantStatus: '',
    applicantStatusOptions: [],
    copied: false,
    selectedVisaPackage: groupData?.visa_package_id || '',
    visaTypeSelected: '',
    availableVisaTypes: [],

    sponsorStatus: '',

    // Group members (companions)
    groupMembers: [],
    maxGroupMembers: 10,

    // Validation
    isValidEmail() {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email);
    },
    isValidPhone() {
      return /^09\d{9}$/.test(this.phone);
    },
    isValidPassportValidity() {
      if (!this.passportExpiry) return true;
      const today = new Date();
      const expiry = new Date(this.passportExpiry);
      const sixMonthsLater = new Date();
      sixMonthsLater.setMonth(sixMonthsLater.getMonth() + 6);
      return expiry >= sixMonthsLater;
    },
    isPassportValid() {
      return this.isValidPassportValidity();
    },
    checkPassportValidity() {
      // Validation happens on blur, checked by isPassportValid()
    },
    canProceedStep1() {
      return this.fullName.trim() !== '' && this.isValidEmail() && !this.emailExists && this.isValidPhone() && this.address.trim() !== '';
    },
    async checkEmailExists() {
      this.emailExists = false;
      if (!this.email || !this.isValidEmail()) return false;
      this.checkingEmail = true;
      try {
        const response = await fetch('../actions/check_client_email.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ email: this.email })
        });
        const data = await response.json();
        this.emailExists = !!data.exists;
        return !this.emailExists;
      } catch (e) {
        return false;
      } finally {
        this.checkingEmail = false;
      }
    },
    async proceedStep1() {
      const ok = await this.checkEmailExists();
      if (!ok) return;
      if (this.canProceedStep1()) {
        this.step = 2;
      }
    },

    // Get today's date in YYYY-MM-DD format
    getTodayDate() {
      const today = new Date();
      return today.toISOString().split('T')[0];
    },

    // Get total steps based on application mode
    getTotalSteps() {
      return this.applicationMode === 'group' ? 3 : 2;
    },

    // Access code generation
    generateAccessCode() {
      if (!this.fullName.trim()) return;
      const nameParts = this.fullName.trim().split(/\s+/);
      // First 2 letters of first name + first 2 letters of second/last name + dash + 4 random numbers
      let prefix = '';
      if (nameParts.length >= 2) {
        prefix = (nameParts[0].substring(0, 2) + nameParts[nameParts.length - 1].substring(0, 2)).toUpperCase();
      } else {
        prefix = nameParts[0].substring(0, 4).toUpperCase().padEnd(4, 'X');
      }
      const randomNum = Math.floor(1000 + Math.random() * 9000);
      this.accessCode = prefix + '-' + randomNum;
    },

    // Handle visa package selection and load visa types and applicant status options
    onVisaPackageChange() {
      this.availableVisaTypes = [];
      this.visaTypeSelected = '';
      this.applicantStatusOptions = [];
      this.applicantStatus = '';

      if (!this.selectedVisaPackage) {
        console.log('[onVisaPackageChange] No package selected');
        return;
      }
      // Find the selected package option
      const selectElement = document.getElementById('visa_package_id');
      const selectedOption = selectElement.querySelector(`option[value="${this.selectedVisaPackage}"]`);

      if (!selectedOption) {
        console.error('[onVisaPackageChange] Selected option not found');
        return;
      }

      // Get the visa_types_json from data attribute
      const visaTypesJson = selectedOption.getAttribute('data-visa-types') || '[]';
      console.log('[onVisaPackageChange] visa_types_json:', visaTypesJson);
      
      try {
        const visaTypesData = JSON.parse(visaTypesJson);
        console.log('[onVisaPackageChange] Parsed visa types data:', visaTypesData);
        
        if (Array.isArray(visaTypesData) && visaTypesData.length > 0) {
          // Extract the "type" field from each object
          this.availableVisaTypes = visaTypesData.map(item => {
            if (typeof item === 'object' && item !== null && item.type) {
              return item.type.trim(); // Trim whitespace
            }
            return item;
          }).filter(Boolean);
          
          console.log('[onVisaPackageChange] Available visa types:', this.availableVisaTypes);
          
          if (this.availableVisaTypes.length > 0) {
            this.visaTypeSelected = this.availableVisaTypes[0];
            console.log('[onVisaPackageChange] Auto-selected visa type:', this.visaTypeSelected);
          }
        } else {
          console.log('[onVisaPackageChange] No visa types in array');
          this.availableVisaTypes = [];
        }
      } catch (e) {
        console.error('[onVisaPackageChange] Error parsing visa types:', e);
        this.availableVisaTypes = [];
      }

      // Get applicant status options from data attribute
      const statusOptionsJson = selectedOption.getAttribute('data-applicant-status-options') || '[]';
      try {
        const statusOptionsData = JSON.parse(statusOptionsJson);
        if (Array.isArray(statusOptionsData) && statusOptionsData.length > 0) {
          this.applicantStatusOptions = statusOptionsData.map(item => {
            // Handle both string and object formats
            if (typeof item === 'object' && item !== null) {
              return {
                option: item.option || item.value || item,
                label: item.label || item.option || item.value || item
              };
            }
            return {
              option: item,
              label: item
            };
          });
        } else {
          this.applicantStatusOptions = [];
        }
      } catch (e) {
        console.error('[onVisaPackageChange] Error parsing applicant status options:', e);
        this.applicantStatusOptions = [];
      }
    },

    // Group member management
    addGroupMember() {
      if (this.groupMembers.length >= this.maxGroupMembers) {
        alert('You can add up to 10 additional companions per application. Please submit another application for more.');
        return;
      }
      this.groupMembers.push({
        id: Date.now(),
        fullName: '',
        email: '',
        phone: '',
        address: this.address, // Pre-fill with lead address
        relationship: '',
        passportNumber: '',
        passportExpiry: '',
        applicantStatus: [],
        visaType: this.visaTypeSelected, // Inherit lead applicant's visa type
        financialSource: 'self_funded'
      });
    },

    removeGroupMember(id) {
      this.groupMembers = this.groupMembers.filter(m => m.id !== id);
    },

    isValidMemberEmail(email) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },

    isValidMemberPhone(phone) {
      return /^09\d{9}$/.test(phone);
    }
  }
}
</script>