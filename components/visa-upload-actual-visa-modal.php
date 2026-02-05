<!-- 📤 Upload Actual Visa Document Modal -->
<div x-cloak x-transition x-show="modals.uploadActualVisa" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-3 sm:p-4">
    <div @click.away="modals.uploadActualVisa = false"
         class="w-full max-w-2xl md:max-w-3xl max-h-[calc(100vh-2rem)] overflow-y-auto bg-white rounded-lg sm:rounded-xl shadow-xl px-4 py-5 sm:px-6 sm:py-6 md:px-8 md:py-8 transition-all space-y-4 sm:space-y-6 relative">

      <!-- Header with Close Button on Same Line -->
      <div class="flex items-center justify-between gap-3 sm:gap-4">
        <h2 class="text-base sm:text-lg font-semibold text-emerald-800 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
          </svg>
          Upload Actual Visa Document
        </h2>
        <button @click="modals.uploadActualVisa = false"
                class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-all duration-200 flex-shrink-0"
                aria-label="Close modal">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
               stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Info Banner -->
      <div class="bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 rounded-lg p-3 sm:p-4">
        <div class="flex items-start gap-3">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="flex-1">
            <p class="text-xs sm:text-sm text-emerald-700">Upload the client's actual visa document (passport stamp, visa sticker, approval letter, etc.) that was released by the embassy.</p>
          </div>
        </div>
      </div>

<!-- Upload Form for Actual Visa Document -->
<form action="../actions/upload_actual_visa.php" method="POST" enctype="multipart/form-data" class="space-y-4 sm:space-y-6" @submit.prevent="handleUploadActualVisa">
  <input type="hidden" name="visa_application_id" value="<?= htmlspecialchars($visaAppId) ?>">

  <!-- Upload File Area -->
  <div>
    <label for="actual_visa_file" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2 sm:mb-3">
      Visa Document File <span class="text-red-500">*</span>
    </label>
    <div class="relative w-full rounded-lg sm:rounded-xl border-2 border-dashed border-emerald-200 bg-gradient-to-br from-emerald-50 to-green-50 p-6 sm:p-8 transition hover:border-emerald-300 hover:from-emerald-100 hover:to-green-100 focus-within:ring-2 focus-within:ring-emerald-500 focus-within:border-emerald-500 min-h-[160px] sm:min-h-[180px] flex items-center justify-center">
      <input type="file" name="actual_visa_file" id="actual_visa_file"
             accept=".pdf,.jpg,.jpeg,.png" required
             @change="actualVisaFileName = $event.target.files[0]?.name || ''"
             class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
      <div class="text-center pointer-events-none" x-show="!actualVisaFileName">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 sm:h-12 w-10 sm:w-12 mx-auto mb-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <div class="text-emerald-700 text-sm sm:text-base font-semibold mb-1">Drag & drop visa document here</div>
        <p class="text-xs sm:text-sm text-emerald-600">or click to browse your files</p>
        <p class="text-xs text-gray-500 mt-2 sm:mt-3">Supported formats: PDF, JPG, JPEG, PNG • Maximum 10MB</p>
      </div>
      <div class="text-center pointer-events-none" x-show="actualVisaFileName">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 sm:h-14 w-12 sm:w-14 mx-auto mb-3 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="text-gray-800 text-sm sm:text-base font-semibold mb-1">File Selected</div>
        <p class="text-xs sm:text-sm text-gray-600 break-all px-4" x-text="actualVisaFileName"></p>
        <p class="text-xs sm:text-sm text-emerald-600 mt-2">Click to change file</p>
      </div>
    </div>
  </div>

  <!-- Optional Notes -->
  <div>
    <label for="visa_notes" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2 sm:mb-3">
      Notes <span class="text-gray-400 text-xs">(Optional)</span>
    </label>
    <textarea name="notes" id="visa_notes" rows="3"
              placeholder="Add any notes about this visa document (e.g., expiry date, visa type, special conditions)"
              class="w-full px-3 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 resize-none"></textarea>
  </div>

  <!-- Actions -->
  <div class="flex flex-col-reverse sm:flex-row justify-between items-stretch sm:items-center gap-3 pt-4 border-t">
    <button type="button" @click="modals.uploadActualVisa = false"
            class="w-full sm:w-auto bg-white border-2 border-gray-300 hover:border-gray-400 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-6 py-2.5 rounded-lg transition-all duration-200">
      Cancel
    </button>
    <button type="submit"
            class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-sm font-semibold px-6 py-2.5 rounded-lg shadow-sm transition-all duration-200 flex items-center justify-center gap-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
      </svg>
      Upload Visa Document
    </button>
  </div>
</form>
    </div>
  </div>
