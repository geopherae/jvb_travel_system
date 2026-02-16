<!-- 📤 Upload Document Modal -->
<div x-cloak x-transition x-show="modals.uploadDocument" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-3 sm:p-4">
    <div @click.away="modals.uploadDocument = false"
         class="w-full max-w-2xl md:max-w-4xl max-h-[calc(100vh-2rem)] overflow-y-auto bg-white rounded-lg sm:rounded-xl shadow-xl px-4 py-5 sm:px-6 sm:py-6 md:px-8 md:py-8 transition-all space-y-4 sm:space-y-6 relative">

      <!-- Header with Close Button on Same Line -->
      <div class="flex items-center justify-between gap-3 sm:gap-4">
        <h2 class="text-base sm:text-lg font-semibold text-gray-800">Update and Upload Document</h2>
        <button @click="modals.uploadDocument = false"
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
      <div class="space-y-2 sm:space-y-3">
        <p class="text-xs sm:text-sm text-gray-600">Upload the required document for this requirement.</p>
      </div>

<!-- Upload Form for Document Submission -->
<form action="../actions/submit_visa_document.php" method="POST" enctype="multipart/form-data" class="space-y-4 sm:space-y-6" @submit.prevent="handleUploadDocument">
  <input type="hidden" name="visa_application_id" value="<?= htmlspecialchars($visaAppId) ?>">
  <input type="hidden" name="requirement_id" :value="selectedRequirementId">
  <input type="hidden" name="client_id" value="<?= htmlspecialchars($clientId) ?>">
  <?php if (!empty($currentCompanionId)): ?>
    <input type="hidden" name="companion_id" value="<?= htmlspecialchars($currentCompanionId) ?>">
  <?php else: ?>
    <input type="hidden" name="companion_id" value="">
  <?php endif; ?>

  <!-- Selected Requirement Display -->
  <div class="p-3 sm:p-4 bg-sky-50 border border-sky-100 rounded-lg">
    <p class="text-xs text-sky-700 font-semibold uppercase tracking-wide mb-1">Requirement</p>
    <p class="text-xs sm:text-sm font-semibold text-gray-900" x-text="selectedRequirementName || 'Selected Requirement'"></p>
  </div>

  <!-- Two Column Layout: Upload File | Description (responsive) -->
  <div class="grid grid-cols-1 md:grid-cols-5 gap-4 md:gap-6">
    <!-- LEFT COLUMN: Upload File -->
    <div class="md:col-span-2">
      <label for="upload_document_file" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2 sm:mb-3">Upload File</label>
      <div class="relative w-full rounded-lg sm:rounded-xl border-2 border-dashed border-sky-200 bg-gradient-to-br from-sky-50 to-blue-50 p-4 sm:p-6 transition hover:border-sky-300 hover:from-sky-100 hover:to-blue-100 focus-within:ring-2 focus-within:ring-sky-500 focus-within:border-sky-500 min-h-[140px] sm:min-h-[160px] flex items-center justify-center">
         <input type="file" name="document_file" id="upload_document_file"
           accept=".pdf,.jpg,.jpeg,.png"
           @change="selectedUploadDocFileName = $event.target.files[0]?.name || ''"
           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
        <div class="text-center pointer-events-none" x-show="!selectedUploadDocFileName">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 sm:h-10 w-8 sm:w-10 mx-auto mb-2 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          <div class="text-sky-700 text-xs sm:text-sm font-semibold mb-1">Drag & drop your file here</div>
          <p class="text-xs text-sky-600">or click to browse</p>
          <p class="text-xs text-gray-500 mt-1 sm:mt-2">PDF, JPG, JPEG, PNG • Max 10MB</p>
        </div>
        <div class="text-center pointer-events-none" x-show="selectedUploadDocFileName">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-10 sm:h-12 w-10 sm:w-12 mx-auto mb-2 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="text-gray-800 text-xs sm:text-sm font-semibold mb-1">File Selected</div>
          <p class="text-xs text-gray-600 break-all px-2" x-text="selectedUploadDocFileName"></p>
          <p class="text-xs text-sky-600 mt-1 sm:mt-2">Click to change file</p>
        </div>
      </div>
    </div>

    <!-- RIGHT COLUMN: Requirement Description (Editable for Admin only) -->
    <div class="md:col-span-3">
      <div class="h-full flex flex-col">
        <label for="editable_requirement_description" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2 sm:mb-3">Requirement Description</label>
        <?php if (!empty($_SESSION['admin']['id'])): ?>
        <textarea
          id="editable_requirement_description"
          name="editable_requirement_description"
          class="flex-1 p-3 sm:p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg border border-gray-200 min-h-[120px] sm:min-h-[160px] text-xs sm:text-sm text-gray-700 leading-relaxed w-full resize-y focus:outline-none focus:ring-2 focus:ring-sky-500"
          x-model="selectedRequirementDescription"
          placeholder="Enter requirement description..."
        ></textarea>
        <?php else: ?>
        <div class="flex-1 p-3 sm:p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg border border-gray-200 min-h-[120px] sm:min-h-[160px] text-xs sm:text-sm text-gray-700 leading-relaxed w-full">
          <span x-text="selectedRequirementDescription || '—'"></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div class="flex justify-between items-center pt-4 border-t">
    <button type="button" @click="modals.uploadDocument = false"
            class="bg-white border-2 border-gray-300 hover:border-gray-400 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-6 py-2.5 rounded-lg transition-all duration-200">
      Cancel
    </button>
    <button type="submit"
            class="bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white text-sm font-semibold px-6 py-2.5 rounded-lg shadow-sm transition-all duration-200"
            x-text="selectedUploadDocFileName ? 'Save & Upload' : 'Save Changes'">
      Save Changes
    </button>
  </div>
</form>
    </div>
  </div>
