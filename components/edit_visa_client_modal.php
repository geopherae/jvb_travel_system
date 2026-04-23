<?php
/**
 * Edit Visa Client Modal Component
 * 
 * Edits individual visa applicant information
 * Handles both lead applicants (clients table) and companions (client_visa_companions table)
 */

$tooltips = require __DIR__ . '/../includes/tooltip_map.php';
require_once __DIR__ . '/../includes/tooltip_render.php';
?>

<!-- Edit Visa Client Modal -->
<div x-show="$store.modals.editVisaClient" 
     x-cloak
     x-transition
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title" role="dialog" aria-modal="true"
     @keydown.escape.window="$store.modals.editVisaClient = false; $store.modals.editVisaClientData = null"
     @click.self="$store.modals.editVisaClient = false; $store.modals.editVisaClientData = null">

  <!-- Backdrop -->
  <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-4 text-center sm:p-0">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$store.modals.editVisaClient = false; $store.modals.editVisaClientData = null"></div>

    <!-- Modal panel -->
    <div class="inline-block align-middle bg-white rounded-lg text-left overflow-visible shadow-xl transform transition-all sm:my-0 sm:align-middle sm:max-w-4xl sm:w-full sm:max-h-[96vh]">
      <form method="POST" action="../actions/process_edit_visa_client.php" enctype="multipart/form-data"
        class="flex flex-col h-full font-sans"
        x-data="editVisaClientForm()"
        @submit.prevent="submitEditVisaClient($el)">

        <!-- Hidden fields -->
        <input type="hidden" name="applicant_type" x-model="applicantType" />
        <input type="hidden" name="applicant_id" x-model="applicantId" />
        <input type="hidden" name="client_id" x-model="clientId" />
        <!-- Hidden field for existing photo (if editing) -->
        <input type="hidden" name="existing_photo" x-model="existingPhoto" />

        <!-- Header -->
        <div class="bg-gradient-to-r from-sky-500 to-blue-600 px-4 py-3 sm:px-6">
          <div class="p-2 flex items-center justify-between">
            <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">
              Edit Visa Applicant Info
            </h3>
            <button type="button" @click="$store.modals.editVisaClient = false; $store.modals.editVisaClientData = null"
                    class="text-white hover:text-gray-200 transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>

        <!-- Form content wrapped with scrolling -->
        <div class="overflow-y-auto flex-1">

<!-- STEP 1: Basic Info -->
<div x-show="step === 1" class="px-4 py-4 sm:p-6 space-y-4 sm:space-y-5">

  <!-- Progress Header -->
  <div class="flex items-center justify-between mb-3 sm:mb-4 pb-2 sm:pb-3 border-b border-gray-200">
    <h3 class="text-sm sm:text-base font-semibold text-gray-900">Personal Information</h3>
    <div class="flex gap-1.5 sm:gap-2">
      <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
      <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
    </div>
    <div class="text-xs sm:text-sm text-gray-500">Step 1 of 2</div>
  </div>

  <!-- Two Column Layout: Photo Upload + All Basic Info -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
    
    <!-- Left Column: Profile Photo Upload -->
    <div class="sm:col-span-1">
      <div x-data="{
        fileName: '',
        handleFile(e) {
          let file = e.target.files ? e.target.files[0] : e.dataTransfer?.files[0];
          if (!file) return;
          if (file.size > 2 * 1024 * 1024) {
            if (typeof window.showToast === 'function') {
              window.showToast('File must be under 2MB', 'error');
            } else {
              alert('File must be under 2MB');
            }
            return;
          }
          this.fileName = file.name;
          const reader = new FileReader();
          reader.onload = ev => {
            previewUrl = ev.target.result;
          };
          reader.readAsDataURL(file);
        }
      }"
      @dragover.prevent @drop.prevent="handleFile($event)"
      class="relative flex flex-col items-center justify-center gap-2 sm:gap-3 border-2 border-dashed border-sky-200 rounded-lg sm:rounded-xl py-6 sm:py-8 px-3 sm:px-4 bg-gradient-to-br from-sky-50 to-transparent hover:border-sky-400 hover:from-sky-100 transition-all cursor-pointer group h-full">

        <!-- Decorative corner accent -->
        <div class="absolute top-0 right-0 w-10 sm:w-14 h-10 sm:h-14 bg-sky-500 opacity-5 rounded-bl-xl sm:rounded-bl-2xl"></div>

        <!-- Image with better styling -->
        <img :src="previewUrl" alt="Profile Preview"
             class="w-20 sm:w-24 h-20 sm:h-24 rounded-xl object-cover border-2 border-sky-100 shadow-sm group-hover:shadow-md transition-shadow" loading="lazy" />

        <!-- Upload label with icon -->
        <label for="edit-visa-client-photo" class="text-center cursor-pointer">
          <div class="flex items-center justify-center mb-2">
            <svg class="w-5 sm:w-6 h-5 sm:h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
          </div>
          <p class="text-sm font-semibold text-sky-600 group-hover:text-sky-700">Upload Photo</p>
          <p class="text-xs text-gray-500 mt-1">Max 2MB</p>
          <input id="edit-visa-client-photo" name="client_profile_photo" type="file"
                 accept=".jpg,.jpeg,.png" class="hidden" @change="handleFile">
        </label>

      </div>
    </div>

    <!-- Right Column: All Form Fields -->
    <div class="sm:col-span-2 space-y-4 sm:space-y-5">
      
      <!-- Full Name -->
      <div class="relative">
        <label for="full_name" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1.5 text-xs font-semibold text-gray-700">
          Full Name <span class="text-red-500">*</span>
        </label>
        <input id="full_name" type="text" name="full_name" x-model="fullName" required placeholder="Maria Reyes"
               class="w-full border border-gray-300 rounded-lg px-4 py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
        <p x-show="fullName.trim() === ''" class="text-xs text-red-500 mt-1.5 flex items-center gap-1">
          <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
          This field is required.
        </p>
      </div>

      <!-- Email and Phone (Two Column) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Email -->
        <div class="relative">
          <label for="email" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1.5 text-xs font-semibold text-gray-700">
            Email <span class="text-red-500">*</span>
          </label>
          <input id="email" type="email" name="email" x-model="email" required placeholder="email@example.com"
                 class="w-full border border-gray-300 rounded-lg px-4 py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                 :class="{ 'border-red-500 ring-red-500': email && !isValidEmail(), 'border-green-500 ring-green-500': isValidEmail() }" />
          <p x-show="email && !isValidEmail()" class="text-xs text-red-500 mt-1.5 flex items-center gap-1">
            <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            Invalid email format.
          </p>
        </div>

        <!-- Phone Number -->
        <div class="relative">
          <label for="phone_number" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1.5 text-xs font-semibold text-gray-700">
            Phone <span class="text-red-500">*</span>
          </label>
          <input id="phone_number" type="tel" name="phone_number" x-model="phone" required maxlength="11" placeholder="09171234567"
                 class="w-full border border-gray-300 rounded-lg px-4 py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                 :class="{ 'border-red-500 ring-red-500': phone && !isValidPhone(), 'border-green-500 ring-green-500': isValidPhone() }" />
          <p x-show="phone && !isValidPhone()" class="text-xs text-red-500 mt-1.5 flex items-center gap-1">
            <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            Must be 11 digits (09xxxxxxxxx).
          </p>
        </div>
      </div>

      <!-- Address -->
      <div class="relative">
        <label for="address" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1.5 text-xs font-semibold text-gray-700">
          Address <span class="text-red-500">*</span>
        </label>
        <input id="address" type="text" name="address" x-model="address" required placeholder="Street, City, Province"
               class="w-full border border-gray-300 rounded-lg px-4 py-3.5 pt-5 text-sm placeholder:text-gray-400 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent" />
      </div>

<!-- Financial Source and Sponsor Status (Two Column when sponsored) -->
<div class="grid grid-cols-1 gap-4" :class="{ 'sm:grid-cols-2': financialSource === 'sponsored' }">
  
  <!-- Financial Source -->
  <div class="relative">
    <label for="financial_source" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1.5 text-xs font-semibold text-gray-700">
      Financial Source <span class="text-red-500">*</span>
    </label>
    <select id="financial_source" name="financial_source" x-model="financialSource" required
            class="w-full border border-gray-300 rounded-lg px-4 py-3.5 pt-5 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent appearance-none bg-white bg-[url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e')] bg-[length:1.25rem] bg-[right_0.75rem_center] bg-no-repeat">
      <option value="">Select financial source</option>
      <option value="self_funded">Self-Funded</option>
      <option value="sponsored">Sponsored</option>
    </select>
    <p class="text-xs text-gray-500 mt-1.5">How will the trip expenses be covered?</p>
  </div>

  <!-- Sponsor Status (Only shown when Sponsored is selected) -->
  <div x-show="financialSource === 'sponsored'" 
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0 transform scale-95"
       x-transition:enter-end="opacity-100 transform scale-100"
       class="relative">
    <label for="sponsor_status" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1.5 text-xs font-semibold text-gray-700">
      Sponsor Status <span class="text-red-500">*</span>
    </label>
    <select id="sponsor_status" name="sponsor_status" x-model="sponsorStatus" 
            :required="financialSource === 'sponsored'"
            class="w-full border border-gray-300 rounded-lg px-4 py-3.5 pt-5 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent appearance-none bg-white bg-[url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e')] bg-[length:1.25rem] bg-[right_0.75rem_center] bg-no-repeat">
      <option value="">Select sponsor status</optio`n>
      <option value="employed">Employed</option>
      <option value="self_employed_business_owner">Self-Employed/Business Owner</option>
      <option value="company">Company/Corporation</option>
    </select>
    <p class="text-xs text-gray-500 mt-1.5">Employment status of the sponsor</p>
  </div>

</div>

    </div>
  </div>

</div>

          <!-- STEP 2: Passport & Visa Status -->
          <div x-show="step === 2" class="px-4 py-4 sm:p-6 space-y-4 sm:space-y-5">

            <!-- Progress Header -->
            <div class="flex items-center justify-between mb-3 sm:mb-4 pb-2 sm:pb-3 border-b border-gray-200">
              <h3 class="text-sm sm:text-base font-semibold text-gray-900">Passport & Visa Status</h3>
              <div class="flex gap-1.5 sm:gap-2">
                <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-gray-300"></div>
                <div class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-sky-500"></div>
              </div>
              <div class="text-xs sm:text-sm text-gray-500">Step 2 of 2</div>
            </div>

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

<!-- Applicant Status (Multi-Select) -->
<div @click.away="statusOpen = false" class="relative">
  <label for="applicant_status" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700 z-10">
    Applicant Status <span class="text-red-500">*</span>
  </label>
  
  <!-- Dropdown Button -->
  <button 
    type="button"
    @click="toggleStatusDropdown()"
    class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent flex items-center justify-between"
    :class="{ 'text-gray-500': !selectedStatuses || selectedStatuses.length === 0, 'text-gray-900': selectedStatuses && selectedStatuses.length > 0 }">
    <span x-text="getStatusDisplayText()"></span>
    <svg class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': statusOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
  </button>

  <!-- Dropdown Options - Positioned below and left-aligned -->
  <div x-show="statusOpen"
       x-transition:enter="transition ease-out duration-100"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       x-transition:leave="transition ease-in duration-75"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95"
       class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto z-[60]">
    
    <!-- Select All / Clear All -->
    <div class="flex items-center justify-between px-3 sm:px-4 py-2 border-b border-gray-200 bg-gray-50">
      <button 
        type="button" 
        @click.stop="selectAllStatuses()"
        class="text-xs text-sky-600 hover:text-sky-700 font-medium">
        Select All
      </button>
      <button 
        type="button" 
        @click.stop="clearAllStatuses()"
        class="text-xs text-gray-600 hover:text-gray-700 font-medium">
        Clear All
      </button>
    </div>

    <!-- Checkbox Options -->
    <template x-for="statusOption in applicantStatusOptions">
      <label class="flex items-center px-3 sm:px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition">
        <input 
          type="checkbox"
          :value="statusOption.option"
          @change.stop="toggleStatus(statusOption.option)"
          :checked="isStatusSelected(statusOption.option)"
          class="w-4 h-4 text-sky-600 border-gray-300 rounded focus:ring-sky-500 focus:ring-2 cursor-pointer">
        <span class="ml-3 text-sm text-gray-700" x-text="statusOption.label"></span>
      </label>
    </template>
  </div>

  <!-- Hidden inputs for form submission -->
  <template x-if="Array.isArray(selectedStatuses)">
    <template x-for="(status, statusIdx) in selectedStatuses" :key="statusIdx">
      <input type="hidden" name="applicant_status[]" :value="status">
    </template>
  </template>

  <p class="text-xs text-gray-500 mt-1.5">This determines which conditional requirements apply in visa processing.</p>
</div>

            <!-- Visa Type -->
            <div class="relative">
              <label for="visa_type" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                Visa Type <span class="text-red-500">*</span>
              </label>
              <select id="visa_type" name="visa_type" x-model="visaType" required
                      class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                <option value="">Select visa type</option>
                <template x-for="type in visaTypeOptions" :key="type">
                  <option :value="type" x-text="type"></option>
                </template>
              </select>
              <p class="text-xs text-gray-500 mt-1.5">Purpose of travel</p>
            </div>

            <!-- Relationship with Lead Guest (Companions only) -->
            <div x-show="applicantType === 'companion'" class="relative">
              <label for="relationship" class="absolute top-0 left-3 -translate-y-1/2 bg-white px-1 text-xs font-semibold text-gray-700">
                Relationship with Lead Guest <span class="text-red-500">*</span>
              </label>
              <select id="relationship" name="relationship" x-model="relationship"
                      :required="applicantType === 'companion'"
                      class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-3 sm:py-3.5 pt-5 text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                <option value="spouse">Spouse</option>
                <option value="child">Child</option>
                <option value="parent">Parent</option>
                <option value="sibling">Sibling</option>
                <option value="friend">Friend</option>
                <option value="colleague">Colleague</option>
                <option value="other">Other</option>
              </select>
              <p class="text-xs text-gray-500 mt-1.5">How is this companion related to the lead applicant?</p>
            </div>

          </div>

        </div>

        <!-- Navigation Buttons -->
        <div class="sticky bottom-0 flex justify-between items-center px-4 py-3 sm:px-6 sm:py-2 bg-gray-50 gap-2 sm:gap-3 z-10 border-t border-gray-200 mb-4 sm:mb-2">
          <template x-if="step === 1">
            <div class="flex w-full justify-between gap-2 sm:gap-3">
              <button type="button" @click="$store.modals.editVisaClient = false; $store.modals.editVisaClientData = null"
                      class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
                Cancel
              </button>
              <button type="button" @click="step++" :disabled="!canProceedStep1()"
                      class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 disabled:opacity-50 transition-colors">
                Next
              </button>
            </div>
          </template>

          <template x-if="step === 2">
            <div class="flex w-full justify-between gap-2 sm:gap-3">
              <button type="button" @click="step--"
                      class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
                Back
              </button>
              <button type="submit" :disabled="!canProceedStep2()"
                      class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 disabled:opacity-50 transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save Changes
              </button>
            </div>
          </template>
        </div>

      </form>
    </div>
  </div>
</div>

<script>
window.editVisaClientForm = window.editVisaClientForm || function() {
  console.log('editVisaClientForm initializing');
  return {
    step: 1,
    applicantType: 'lead',
    applicantId: null,
    clientId: null,
    fullName: '',
    email: '',
    phone: '',
    address: '',
    financialSource: '',
    passportNumber: '',
    passportExpiry: '',
    selectedStatuses: [],
    statusOpen: false,
    visaType: '',
    relationship: '',
    applicantData: null,
    previewUrl: '../images/default_client_profile.png',
    existingPhoto: '',
    visaTypeOptions: [],
    applicantStatusOptions: [],
    // New: Sponsor status for conditional requirements (future UI can bind this)
    sponsorStatus: '',

    /**
     * Returns true if a requirement should be shown for this applicant, based on the new conditional logic.
     * Supports:
     * - Applicant Status
     * - Sponsored (financialSource === 'sponsored')
     * - Sponsor Status (if sponsored, matches sponsor status)
     * @param {object} req - requirement object from requirements_json
     */
    shouldShowRequirement(req) {
      if (!req || !req.category || req.category.toLowerCase() !== 'conditional' || !req.condition) return true;
      const cond = req.condition;
      if (cond.type === 'applicant_status') {
        // Show if any selected status matches the required value
        return Array.isArray(this.selectedStatuses) && this.selectedStatuses.includes(cond.value);
      }
      if (cond.type === 'sponsor_status') {
        // Only applies if sponsored
        if (this.financialSource !== 'sponsored') return false;
        // Sponsor status value: 'employed' or 'self_employed'
        return this.sponsorStatus === cond.value;
      }
      // Default: show
      return true;
    },
    
    toggleStatusDropdown() {
      this.statusOpen = !this.statusOpen;
    },
    
    toggleStatus(status) {
      const idx = this.selectedStatuses.indexOf(status);
      if (idx === -1) {
        this.selectedStatuses.push(status);
      } else {
        this.selectedStatuses.splice(idx, 1);
      }
      console.log('Status toggled:', status, 'Current:', this.selectedStatuses);
    },
    
    isStatusSelected(status) {
      const selected = Array.isArray(this.selectedStatuses) && this.selectedStatuses.includes(status);
      return selected;
    },
    
    getStatusDisplayText() {
      if (!this.selectedStatuses || this.selectedStatuses.length === 0) return 'Select applicant status';
      if (this.selectedStatuses.length === 1) {
        const found = this.applicantStatusOptions.find(o => o.option === this.selectedStatuses[0]);
        return found ? found.label : this.selectedStatuses[0];
      }
      return `${this.selectedStatuses.length} statuses selected`;
    },
    
    selectAllStatuses() {
      this.selectedStatuses = this.applicantStatusOptions.map(o => o.option);
    },
    
    clearAllStatuses() {
      this.selectedStatuses = [];
    },
    
    isValidEmail() {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email);
    },
    
    isValidPhone() {
      return /^09\d{9}$/.test(this.phone);
    },
    
    getTodayDate() {
      const today = new Date();
      return today.toISOString().split('T')[0];
    },
    
    isPassportValid() {
      if (!this.passportExpiry) return false;
      const expiry = new Date(this.passportExpiry);
      const sixMonthsFromNow = new Date();
      sixMonthsFromNow.setMonth(sixMonthsFromNow.getMonth() + 6);
      return expiry >= sixMonthsFromNow;
    },
    
    checkPassportValidity() {},
    
    normalizeSelectValue(value, allowed) {
      if (!value) return '';
      const raw = String(value).trim();
      if (!raw) return '';
      const allowedValues = Array.isArray(allowed) ? allowed.map((item) => String(item)) : [];
      if (allowedValues.includes(raw)) return raw;
      const lower = raw.toLowerCase();
      const lowerMap = new Map(allowedValues.map((item) => [item.toLowerCase(), item]));
      if (lowerMap.has(lower)) return lowerMap.get(lower);
      const normalized = lower.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
      if (lowerMap.has(normalized)) return lowerMap.get(normalized);
      return raw;
    },
    
    canProceedStep1() {
      return this.fullName.trim() !== '' && this.isValidEmail() && this.isValidPhone() && this.address.trim() !== '' && this.financialSource !== '';
    },
    
    canProceedStep2() {
      const baseValid = this.passportNumber.trim() !== '' && 
                       this.passportExpiry && 
                       this.isPassportValid() && 
                       this.selectedStatuses.length > 0 &&
                       this.visaType !== '';
      if (this.applicantType === 'companion') {
        return baseValid && this.relationship !== '';
      }
      return baseValid;
    },
    
    applyStoreData(newData) {
      const applicant = newData.applicant || {};

  console.log('🔍 Raw applicant data received:', applicant);
  console.log('🔍 applicant_status_options:', applicant.applicant_status_options);
  console.log('🔍 applicant_status:', applicant.applicant_status);
  console.log('🔍 visa_type_options:', applicant.visa_type_options);

      const financialAllowed = ['self_funded', 'sponsored'];
      
      // ✅ Load available options from visa package
      this.applicantStatusOptions = Array.isArray(applicant.applicant_status_options) && applicant.applicant_status_options.length > 0
        ? [...applicant.applicant_status_options]
        : [];
      
      this.visaTypeOptions = Array.isArray(applicant.visa_type_options) && applicant.visa_type_options.length > 0
        ? [...applicant.visa_type_options]
        : [];
      
      console.log('✅ Loaded from visa package:', {
        applicantStatusOptions: this.applicantStatusOptions,
        visaTypeOptions: this.visaTypeOptions
      });
      
      const statusAllowed = this.applicantStatusOptions.map(o => o.option);
      const visaAllowed = this.visaTypeOptions;
      const relationshipAllowed = ['spouse', 'child', 'parent', 'sibling', 'friend', 'colleague', 'other'];
      
      this.fullName = applicant.name || '';
      this.email = applicant.email || '';
      this.phone = applicant.phone || '';
      this.address = applicant.address || '';
      
      const financialRaw = applicant.financial_source || '';
      const financialAlias = financialRaw === 'sponsor' ? 'sponsored' : financialRaw;
      this.financialSource = this.normalizeSelectValue(financialAlias, financialAllowed);
      
      this.passportNumber = applicant.passport || '';
      this.passportExpiry = applicant.passport_expiry || '';
      
      // ✅ Load client's selected statuses from database
      if (Array.isArray(applicant.applicant_status)) {
        this.selectedStatuses = [...applicant.applicant_status];
      } else if (applicant.applicant_status) {
        this.selectedStatuses = [this.normalizeSelectValue(applicant.applicant_status, statusAllowed)];
      } else {
        this.selectedStatuses = [];
      }
      
      console.log('✅ Client\'s selected statuses from DB:', this.selectedStatuses);
      console.log('✅ Status options to display:', this.applicantStatusOptions);
      
      this.visaType = this.normalizeSelectValue(applicant.visa_type, visaAllowed);
      this.relationship = this.normalizeSelectValue(applicant.relationship, relationshipAllowed);
      
      if (newData.applicantType === 'companion') {
        this.existingPhoto = applicant.companions_photo || '';
      } else {
        this.existingPhoto = applicant.client_profile_photo || '';
      }
      this.previewUrl = this.existingPhoto
        ? ('../uploads/client_profiles/' + this.existingPhoto)
        : '../images/default_client_profile.png';
      
      this.applicantType = newData.applicantType || 'lead';
      this.applicantId = newData.applicantId || null;
      this.clientId = newData.clientId || null;
      this.applicantData = applicant;
      this.step = 1;
      
      console.log('✅ Form fully populated');
    },
    
    init() {
      console.log('Form init called');
      
      window.addEventListener('edit-visa-client-data', (event) => {
        const payload = event.detail;
        if (payload && payload.applicant) {
          console.log('Received edit-visa-client-data event');
          this.applyStoreData(payload);
        }
      });
      
      this.$watch('$store.modals.editVisaClientData', (newData) => {
        if (newData && newData.applicant) {
          console.log('Store data changed, updating form');
          this.applyStoreData(newData);
        }
      });
      
      this.$watch('$store.modals.editVisaClient', (isOpen) => {
        if (isOpen) {
          console.log('Modal opened, checking for data...');
          const existing = this.$store.modals.editVisaClientData;
          if (existing && existing.applicant) {
            console.log('Applying data on modal open');
            this.applyStoreData(existing);
          }
        }
      });
    },
    
    submitEditVisaClient(formEl) {
      const formData = new FormData(formEl);
      formEl.classList.add('submitting');
      
      const self = this;
      
      fetch('../actions/process_edit_visa_client.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(res => res.json())
        .then(data => {
          formEl.classList.remove('submitting');
          
          if (data.success) {
            const message = data.message || 'Visa applicant updated successfully.';
            if (typeof window.showToast === 'function') {
              window.showToast(message, 'success');
            }
          } else {
            const message = data.message || 'Failed to update applicant.';
            if (typeof window.showToast === 'function') {
              window.showToast(message, 'error');
            }
          }
          
          if (data.success || data.close_modal) {
            if (typeof Alpine !== 'undefined' && Alpine.store) {
              Alpine.store('modals').editVisaClient = false;
              Alpine.store('modals').editVisaClientData = null;
            } else if (self.$store && self.$store.modals) {
              self.$store.modals.editVisaClient = false;
              self.$store.modals.editVisaClientData = null;
            }
            
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          }
        })
        .catch(err => {
          formEl.classList.remove('submitting');
          console.error('Fetch error:', err);
          if (typeof window.showToast === 'function') {
            window.showToast('Network error. Please try again.', 'error');
          }
        });
    }
  };
};
</script>