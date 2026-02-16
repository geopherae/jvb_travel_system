<!-- 📤 Add Requirement Modal -->
<div x-cloak x-transition x-show="modals.addRequirement" class="backdrop-blur-sm fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 px-2 sm:px-4 py-4 overflow-y-auto">
    <div @click.away="modals.addRequirement = false"
         class="w-full max-w-4xl max-h-[calc(100vh-4rem)] overflow-y-auto bg-white rounded-xl shadow-xl px-6 py-6 sm:px-8 sm:py-8 transition-all space-y-6 relative">

      <!-- Header with Close Button on Same Line -->
      <div class="flex items-center justify-between gap-4">
        <h2 class="text-lg font-semibold text-gray-800">Add a Requirement</h2>
        <button @click="modals.addRequirement = false"
                class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-all duration-200 flex-shrink-0"
                aria-label="Close modal">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
               stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Subtitle and Notes -->
      <div class="space-y-3">
        <?php if ($isAdmin): ?>
          <p class="text-sm text-gray-600">Add a new requirement for the client if current list does not have it. File upload is optional.</p>
        <?php else: ?>
          <p class="text-sm text-gray-600">Provide the required file and details below.</p>
        <?php endif; ?>
        
        <!-- Access restriction notice for individual clients -->
        <?php if ($isClient && $clientAccessType === 'individual'): ?>
          <div class="p-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-700">
            <strong>Note:</strong> 
            <?php if (!empty($currentCompanionId)): ?>
              You can upload documents for your requirements only. Tap "Your Documents" to see your own requirements.
            <?php else: ?>
              You can upload documents for your requirements as the lead guest. To upload as a companion, use your individual access code.
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

<!-- Upload Form for Add Requirement -->
<form action="../actions/add_visa_requirement.php" method="POST" enctype="multipart/form-data" class="space-y-6" @submit.prevent="handleAddRequirement">
  <input type="hidden" name="visa_application_id" value="<?= htmlspecialchars($visaAppId) ?>">
  <input type="hidden" name="client_id" :value="applicantMeta[currentIdx]?.client_id || ''">
  <!-- Always send companion_id (empty for lead, populated for companions) -->
  <input type="hidden" name="companion_id" :value="applicantMeta[currentIdx]?.companion_id || ''">

  <!-- Two Column Layout: Requirement Type (Left) | Requirement Name (Right) -->
  <div class="grid grid-cols-5 gap-6">

  
  <!-- LEFT COLUMN: Requirement Type -->
<div class="col-span-2" @click.away="reqTypeOpen = false">
  <label class="block text-sm font-medium text-gray-700 mb-3">Requirement Type <span class="text-red-500">*</span></label>
  
  <!-- Dropdown Button -->
  <button 
    type="button"
    @click="toggleReqTypeDropdown()"
    x-ref="reqTypeButton"
    class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-left transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent flex items-center justify-between bg-white"
    :class="{ 'text-gray-500': !selectedReqType, 'text-gray-900': selectedReqType }">
    <span x-text="getReqTypeDisplayText()"></span>
    <svg class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': reqTypeOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
  </button>

  <!-- Dropdown Options - Teleported to body -->
  <template x-teleport="body">
    <div 
      x-show="reqTypeOpen"
      x-transition:enter="transition ease-out duration-100"
      x-transition:enter-start="opacity-0 scale-95"
      x-transition:enter-end="opacity-100 scale-100"
      x-transition:leave="transition ease-in duration-75"
      x-transition:leave-start="opacity-100 scale-100"
      x-transition:leave-end="opacity-0 scale-95"
      :style="`position: fixed; top: ${reqTypeDropdownTop}px; left: ${reqTypeDropdownLeft}px; width: ${reqTypeDropdownWidth}px; z-index: 9999;`"
      class="bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
      
      <!-- Options -->
      <label class="flex items-center px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition"
             @click.stop="selectReqType('admin_added')">
        <input 
          type="radio"
          name="requirement_type_display"
          value="admin_added"
          :checked="selectedReqType === 'admin_added'"
          class="w-4 h-4 text-sky-600 border-gray-300 focus:ring-sky-500 focus:ring-2 cursor-pointer">
        <span class="ml-3 text-sm text-gray-700">Custom Requirement (Other)</span>
      </label>
      
      <label class="flex items-center px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition"
             @click.stop="selectReqType('primary')">
        <input 
          type="radio"
          name="requirement_type_display"
          value="primary"
          :checked="selectedReqType === 'primary'"
          class="w-4 h-4 text-sky-600 border-gray-300 focus:ring-sky-500 focus:ring-2 cursor-pointer">
        <span class="ml-3 text-sm text-gray-700">Primary</span>
      </label>
    </div>
  </template>

  <!-- Hidden input for form submission -->
  <input type="hidden" name="requirement_type" :value="selectedReqType">
</div>

    <!-- RIGHT COLUMN: Requirement Name -->
    <div class="col-span-3">
      <label class="block text-sm font-medium text-gray-700 mb-3">Requirement Name <span class="text-red-500">*</span></label>
      <input type="text" x-model="editableReqName" name="editable_requirement_name"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-700 bg-white transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
             placeholder="Enter requirement name" required>
    </div>
  </div>

  <!-- Two Column Layout: Upload File (40%) | Description (60%) -->
  <div class="grid grid-cols-5 gap-6">
    <!-- LEFT COLUMN: Upload File (40%) -->
    <div class="col-span-2">
      <label for="add_req_file" class="block text-sm font-medium text-gray-700 mb-3">Upload File <span class="text-gray-500 font-normal">(Optional)</span></label>
      <div class="relative w-full rounded-xl border-2 border-dashed border-sky-200 bg-gradient-to-br from-sky-50 to-blue-50 p-6 transition hover:border-sky-300 hover:from-sky-100 hover:to-blue-100 focus-within:ring-2 focus-within:ring-sky-500 focus-within:border-sky-500 min-h-[160px] flex items-center justify-center">
        <input type="file" name="document_file" id="add_req_file"
               accept=".pdf,.jpg,.jpeg,.png"
               @change="selectedAddReqFileName = $event.target.files[0]?.name || ''"
               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
        <div class="text-center pointer-events-none" x-show="!selectedAddReqFileName">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto mb-2 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          <div class="text-sky-700 text-sm font-semibold mb-1">Drag & drop your file here</div>
          <p class="text-xs text-sky-600">or click to browse</p>
          <p class="text-xs text-gray-500 mt-2">PDF, JPG, JPEG, PNG • Max 10MB</p>
        </div>
        <div class="text-center pointer-events-none" x-show="selectedAddReqFileName">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-2 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="text-gray-800 text-sm font-semibold mb-1">File Selected</div>
          <p class="text-xs text-gray-600 break-all px-2" x-text="selectedAddReqFileName"></p>
          <p class="text-xs text-sky-600 mt-2">Click to change file</p>
        </div>
      </div>
    </div>

    <!-- RIGHT COLUMN: Requirement Description (60%) -->
    <div class="col-span-3">
      <div class="h-full flex flex-col">
        <label class="block text-sm font-medium text-gray-700 mb-3">Requirement Description</label>
        <textarea x-model="editableReqDescription" name="editable_requirement_description"
                  class="w-full flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-700 bg-white transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 min-h-[160px]"
                  placeholder="Enter requirement description"></textarea>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div class="flex justify-between items-center pt-4 border-t">
    <button type="button" @click="modals.addRequirement = false"
            class="bg-white border-2 border-gray-300 hover:border-gray-400 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-6 py-2.5 rounded-lg transition-all duration-200">
      Cancel
    </button>
    <button type="submit"
            class="bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white text-sm font-semibold px-6 py-2.5 rounded-lg shadow-sm transition-all duration-200">
      Add Requirement
    </button>
  </div>
</form>
    </div>
  </div>
