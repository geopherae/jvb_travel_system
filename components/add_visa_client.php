<?php
// Fetch visa packages for dropdown
$visaPackagesStmt = $conn->prepare("SELECT id, country, processing_days FROM visa_packages WHERE is_active = 1 ORDER BY country ASC");
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
        x-data="visaClientForm(<?= $isAddingToGroup ? htmlspecialchars(json_encode($groupData), ENT_QUOTES, 'UTF-8') : 'null' ?>)" 
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
          <div class="flex items-center justify-between mb-3 sm:mb-4 pb-2 sm:pb-3 border-b border-gray-200">
            <div>
              <h3 class="text-sm sm:text-base font-semibold text-gray-900">Client Basic Info</h3>
              <p x-show="isAddingToGroup" class="text-xs text-sky-600 font-medium mt-0.5 flex items-center gap-1">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path></svg>
                Adding to group
              </p>
            </div>
            <div class="flex gap-1.5 sm:gap-2">
              <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
              <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
              <div x-show="applicationMode === 'group'" class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
            </div>
            <div class="text-xs sm:text-sm text-gray-500" x-text="'Step 1 of ' + getTotalSteps()"></div>
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
                <div class="relative">
                  <label for="processing_type" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                    Processing Type <span class="text-red-500">*</span>
                  </label>
                  <select id="processing_type" name="processing_type" x-model="processingType" required
                          class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                    <option value="visa">Visa Processing</option>
                <!--<option value="booking">Booking Only</option> -->
                    <option value="both">Both Booking & Visa</option>
                  </select>
                  <p class="text-xs text-gray-500 mt-1.5">Select the type of service this client will use.</p>
                </div>

                <!-- Application Mode -->
                <div class="relative">
                  <label for="application_mode" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                    Application Mode <span class="text-red-500">*</span>
                  </label>
                  <select id="application_mode" x-model="applicationMode" required
                          class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                    <option value="individual">Individual Application</option>
                    <option value="group">Group Application</option>
                  </select>
                  <p class="text-xs text-gray-500 mt-1.5">Choose Individual for single client or Group for family/group.</p>
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
          <div class="flex items-center justify-between mb-3 sm:mb-4 pb-2 sm:pb-3 border-b border-gray-200">
            <div>
              <h3 class="text-sm sm:text-base font-semibold text-gray-900">Passport & Visa Status</h3>
              <p class="text-xs text-gray-500 mt-0.5">Add passport details and applicant status for requirement matching.</p>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2">
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
            <div class="text-xs sm:text-sm text-gray-500" x-text="'Step 2 of ' + getTotalSteps()"></div>
          </div>

          <!-- Passport Details (Two Column Layout) -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 pb-4 sm:pb-6 border-b border-gray-200">
            
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

          <!-- Applicant Status -->
          <div>
            <label for="applicant_status" class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">
              <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
              </svg>
              Applicant Status (Optional)
              <span class="text-gray-400 text-xs font-normal">For requirement matching</span>
            </label>
            <select id="applicant_status" name="applicant_status" x-model="applicantStatus"
                    class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
              <option value="">Not specified</option>
              <option value="employed">Employed</option>
              <option value="self_employed">Self-Employed</option>
              <option value="business_owner">Business Owner</option>
              <option value="student">Student</option>
              <option value="retired">Retired</option>
              <option value="senior_citizen">Senior Citizen</option>
              <option value="unemployed">Unemployed</option>
            </select>
            <p class="text-xs text-gray-500 mt-1.5 sm:mt-2">This determines which conditional requirements apply in visa processing.</p>
          </div>

          <!-- Visa Package Selection -->
          <div class="border-t border-gray-200 pt-4 sm:pt-6 mt-4 sm:mt-6">
            <label for="visa_package_id" class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">
              <svg class="w-4 sm:w-5 h-4 sm:h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              Visa Package (Optional)
            </label>
            <select id="visa_package_id" name="visa_package_id" x-model.number="selectedVisaPackage"
                    class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
              <option value="">Select a visa package...</option>
              <?php foreach ($visaPackages as $pkg): ?>
                <option value="<?= $pkg['id'] ?>" data-processing-days="<?= $pkg['processing_days'] ?>">
                  <?= htmlspecialchars($pkg['country']) ?> (<?= $pkg['processing_days'] ?> days)
                </option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1.5 sm:mt-2">Choose a visa package if known, or leave blank for later assignment.</p>
          </div>

        </div>

        <!-- STEP 3: Group Members (Only if Group Application) -->
        <div x-show="step === 3 && applicationMode === 'group'" class="px-4 py-5 sm:p-6 space-y-4 sm:space-y-6 max-h-[60vh] overflow-y-auto">

          <!-- Progress Header -->
          <div class="flex items-start sm:items-center justify-between mb-4 sm:mb-6 pb-3 sm:pb-4 border-b border-gray-200 gap-3">
            <div class="min-w-0">
              <h3 class="text-sm sm:text-base font-semibold text-gray-900">Group Members</h3>
              <p class="text-xs sm:text-sm text-gray-500 mt-0.5 sm:mt-1">Step 3 of 3 - Add group members (Optional)</p>
            </div>
            <div class="flex gap-1.5 sm:gap-2 flex-shrink-0">
              <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
              <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
              <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
            </div>
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
          <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
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

                  <div class="relative">
                    <label :for="'companion_relationship_' + index" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                      Relationship with Lead Guest<span class="text-red-500">*</span>
                    </label>
                    <select :id="'companion_relationship_' + index" x-model="member.relationship"
                           :name="'companion_relationship_' + index" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-3 pt-5 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                      <option value="">Select relationship</option>
                      <option value="spouse">Spouse</option>
                      <option value="child">Child</option>
                      <option value="parent">Parent</option>
                      <option value="sibling">Sibling</option>
                      <option value="relative">Other Relative</option>
                      <option value="friend">Friend</option>
                    </select>
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
                    <label :for="'companion_status_' + index" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700 z-10">
                      Applicant Status
                    </label>
                    <select :id="'companion_status_' + index" x-model="member.applicantStatus"
                           :name="'companion_status_' + index"
                           class="w-full border border-gray-300 rounded-lg px-3 py-3 pt-5 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                      <option value="">Not specified</option>
                      <option value="employed">Employed</option>
                      <option value="self_employed">Self-Employed</option>
                      <option value="business_owner">Business Owner</option>
                      <option value="student">Student</option>
                      <option value="retired">Retired</option>
                      <option value="senior_citizen">Senior Citizen</option>
                      <option value="unemployed">Unemployed</option>
                    </select>
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
              <button type="button" @click="step = 2"
                      class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
                Next: Passport Details
              </button>
            </div>
          </template>

          <template x-if="step === 2">
            <div class="flex w-full justify-between gap-2 sm:gap-3">
              <button type="button" @click="step = 1"
                      class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
                Back
              </button>
              <template x-if="applicationMode === 'individual'">
                <button type="submit" :disabled="$el.closest('form').classList.contains('submitting')"
                        class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                  <span x-show="!$el.closest('form').classList.contains('submitting')">
                    <span x-show="isAddingToGroup">Add Companion</span>
                    <span x-show="!isAddingToGroup">Create Visa Application</span>
                  </span>
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
  function visaClientForm(groupData = null) {
    return {
      step: 1,
      applicationMode: 'individual', // 'individual' or 'group'
      isAddingToGroup: !!groupData,
      groupCode: groupData?.group_code || '',
      processingType: groupData?.processing_type || 'visa',
      assignedAdminId: groupData?.assigned_admin_id || '',
      fullName: '',
      email: '',
      phone: '',
      address: '',
      accessCode: '',
      passportNumber: '',
      passportExpiry: '',
      applicantStatus: '',
      copied: false,
      selectedVisaPackage: groupData?.visa_package_id || '',
      
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
        return this.fullName.trim() !== '' && this.isValidEmail() && this.isValidPhone() && this.address.trim() !== '';
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
        const base = this.fullName.trim().replace(/\s+/g, '').toUpperCase();
        const suffix = Date.now().toString().slice(-4);
        this.accessCode = base.slice(0, 4) + '-' + suffix;
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
          applicantStatus: ''
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