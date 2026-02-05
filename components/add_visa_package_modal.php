<!-- ➕ Add Visa Package Modal -->
<div
  x-show="$store.addVisaPackageModal.isOpen"
  x-cloak
  x-transition.opacity
  x-data="visaPackageFormData()"
  class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/55 px-2 sm:px-4 py-4 backdrop-blur-sm overflow-y-auto"
  @keydown.escape.window="$store.addVisaPackageModal.close()"
  @click.self="$store.addVisaPackageModal.close()"
>
    <div class="bg-white rounded-t-2xl sm:rounded-lg shadow-xl w-full sm:max-w-5xl max-h-none sm:max-h-[95vh] flex flex-col overflow-hidden transition-all">
      <!-- Modal Header -->
      <div class="flex items-center justify-between px-4 sm:px-6 pt-4 sm:pt-6 pb-3 sm:pb-4 flex-shrink-0">
        <h2 class="text-lg sm:text-xl font-bold text-sky-700">Add New Visa Package</h2>
        <button
          type="button"
          @click="$store.addVisaPackageModal.close()"
          class="text-slate-500 hover:text-red-500 text-2xl font-bold flex-shrink-0 ml-2"
          aria-label="Close modal"
        >
          ×
        </button>
      </div>

      <form method="POST" action="../actions/process_add_visa_package.php" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden"
            @submit="handleFormSubmit($event)">
        
        <div class="flex flex-col lg:flex-row gap-3 sm:gap-6 flex-1 overflow-y-auto px-4 sm:px-6 pb-4 sm:pb-8">
          <!-- Left Column: Image Upload + Live Preview -->
          <div class="lg:w-1/2 w-full flex flex-col bg-white rounded-lg shadow-sm overflow-hidden min-h-0">
            <div class="relative">
              <img
                :src="previewUrl || '../images/default_visa_cover.jpg'"
                alt="Visa Cover Preview"
                class="w-full h-40 sm:h-64 object-cover"
              />

              <div class="absolute top-4 right-4">
                <label
                  for="visa-cover-upload-add"
                  class="bg-white/90 backdrop-blur-sm px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm cursor-pointer text-slate-700 font-medium shadow hover:bg-white transition"
                >
                  Upload Cover
                </label>
                <input
                  id="visa-cover-upload-add"
                  type="file"
                  name="visa_cover_image"
                  accept=".jpg,.jpeg,.png"
                  class="hidden"
                  @change="handleCoverUpload($event)"
                >
              </div>
            </div>

            <div class="px-4 py-3 text-xs text-gray-500 text-center">
              Accepted formats: JPG, PNG · Max size: 3MB
            </div>

            <div class="p-3 sm:p-4 space-y-2">
              <h3 class="text-lg sm:text-xl font-semibold text-slate-800 leading-tight truncate" x-text="visaPackageName || 'Unnamed Package'"></h3>
              <div class="flex flex-wrap items-center gap-2">
                <span class="inline-block bg-purple-100 text-purple-800 font-semibold px-3 py-1 rounded-full text-xs" x-text="country || 'Country TBD'"></span>
                <span class="inline-block bg-slate-100 text-slate-700 font-semibold px-3 py-1 rounded-full text-xs" x-text="(processingDays || 0) + ' Day' + ((processingDays || 0) != 1 ? 's' : '')"></span>
              </div>
              <p class="text-xs sm:text-sm text-slate-600 line-clamp-4" x-text="description || 'No description yet.'"></p>
            </div>
          </div>

          <!-- Right Column: Tabs -->
          <div class="lg:w-1/2 w-full flex flex-col min-h-0" x-data="{ tab: 'details' }">
            <div class="flex border-b overflow-x-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100 flex-shrink-0">
              <button type="button" @click="tab = 'details'" :class="tab === 'details' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'" class="flex-1 sm:flex-none px-3 sm:px-5 py-2 sm:py-3 text-xs sm:text-sm font-medium transition whitespace-nowrap">Details</button>
              <button type="button" @click="tab = 'inclusions'" :class="tab === 'inclusions' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'" class="flex-1 sm:flex-none px-3 sm:px-5 py-2 sm:py-3 text-xs sm:text-sm font-medium transition whitespace-nowrap">Inclusions</button>
              <button type="button" @click="tab = 'requirements'" :class="tab === 'requirements' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'" class="flex-1 sm:flex-none px-3 sm:px-5 py-2 sm:py-3 text-xs sm:text-sm font-medium transition whitespace-nowrap">Requirements</button>
              <button type="button" @click="tab = 'visaTypes'" :class="tab === 'visaTypes' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'" class="flex-1 sm:flex-none px-3 sm:px-5 py-2 sm:py-3 text-xs sm:text-sm font-medium transition whitespace-nowrap">Visa Types</button>
            </div>

            <div class="flex-1 overflow-y-auto min-h-0">
              <div x-show="tab === 'details'" x-transition class="p-3 sm:p-4 space-y-3 sm:space-y-4 text-sm">
              <label class="block">
                <span class="text-xs font-medium text-slate-600">Visa Package Name <span class="text-red-500">*</span></span>
                <input type="text" x-model="visaPackageName" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="e.g. USA Tourist Visa" required />
              </label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <label class="block">
                  <span class="text-xs font-medium text-slate-600">Country <span class="text-red-500">*</span></span>
                  <input type="text" x-model="country" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="e.g. United States" required />
                </label>
                <label class="block">
                  <span class="text-xs font-medium text-slate-600">Processing Days</span>
                  <input type="number" min="0" x-model.number="processingDays" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="e.g. 10" />
                </label>
              </div>
              <label class="block">
                <span class="text-xs font-medium text-slate-600">Description</span>
                <textarea x-model="description" rows="3" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="Short package description"></textarea>
              </label>
            </div>

              <div x-show="tab === 'inclusions'" x-transition class="p-3 sm:p-4 space-y-3 sm:space-y-4 max-h-[500px] overflow-y-auto text-xs sm:text-sm">
              <template x-for="(item, index) in inclusions" :key="'inc-' + index">
                <div class="border rounded-lg shadow-sm bg-slate-50 p-3">
                  <div class="flex items-center gap-2">
                    <input type="text" x-model="inclusions[index]" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="Inclusion item" />
                    <button type="button" @click="removeInclusion(index)" class="text-red-500 hover:text-red-600" aria-label="Remove inclusion">
                      <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M6 4a1 1 0 011-1h6a1 1 0 011 1v1h3a1 1 0 110 2h-1v9a2 2 0 01-2 2H6a2 2 0 01-2-2V7H3a1 1 0 110-2h3V4zm2 3a1 1 0 10-2 0v8a1 1 0 102 0V7zm6-1a1 1 0 10-2 0v8a1 1 0 102 0V6z" clip-rule="evenodd" />
                      </svg>
                    </button>
                  </div>
                </div>
              </template>
              <button type="button" @click="addInclusion()" class="text-sky-600 text-sm hover:underline">+ Add Inclusion</button>
            </div>

            <div x-show="tab === 'requirements'" x-transition class="p-3 sm:p-4 space-y-3 sm:space-y-4 max-h-[500px] overflow-y-auto text-xs sm:text-sm">
              <p class="text-xs text-slate-500">
                Conditional requirements are only applied when the applicant status matches the selection below.
              </p>
              <template x-for="(req, index) in requirements" :key="'req-' + index">
                <div class="border rounded-lg shadow-sm bg-slate-50 p-3 space-y-3">
                  <label class="block">
                    <span class="text-xs font-medium text-slate-600">Category</span>
                    <select x-model="req.category" class="w-full border px-3 py-2 rounded text-sm bg-white">
                      <option value="Primary">Primary</option>
                      <option value="Conditional">Conditional</option>
                      <option value="Other">Other</option>
                    </select>
                  </label>
                  <label class="block">
                    <span class="text-xs font-medium text-slate-600">Name</span>
                    <input type="text" x-model="req.name" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="Requirement name" />
                  </label>
                  <label class="block">
                    <span class="text-xs font-medium text-slate-600">Description</span>
                    <input type="text" x-model="req.description" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="Requirement description" />
                  </label>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="req.category === 'Conditional'">
                    <label class="block">
                      <span class="text-xs font-medium text-slate-600">Condition Type</span>
                      <input type="text" class="w-full border px-3 py-2 rounded text-sm bg-gray-100 text-slate-600" value="Applicant Status" readonly />
                    </label>
                    <label class="block">
                      <span class="text-xs font-medium text-slate-600">Applicant Status</span>
                      <select x-model="req.condition.value" class="w-full border px-3 py-2 rounded text-sm bg-white">
                        <option value="">Select status</option>
                        <option value="employed">Employed</option>
                        <option value="self-employed">Self-employed</option>
                        <option value="student">Student</option>
                        <option value="unemployed">Unemployed</option>
                        <option value="retired">Retired</option>
                      </select>
                    </label>
                  </div>
                  <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-xs text-slate-600">
                      <input type="checkbox" x-model="req.required" class="rounded border-slate-300" />
                      Required
                    </label>
                    <button type="button" @click="removeRequirement(index)" class="text-red-500 text-xs font-semibold hover:underline">Remove</button>
                  </div>
                </div>
              </template>
              <button type="button" @click="addRequirement()" class="text-sky-600 text-sm hover:underline">+ Add Requirement</button>
            </div>

            <div x-show="tab === 'visaTypes'" x-transition class="p-3 sm:p-4 space-y-3 sm:space-y-4 max-h-[500px] overflow-y-auto text-xs sm:text-sm">
              <template x-for="(type, index) in visaTypes" :key="'type-' + index">
                <div class="border rounded-lg shadow-sm bg-slate-50 p-3 space-y-2">
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="block">
                      <span class="text-xs font-medium text-slate-600">Visa Type</span>
                      <input type="text" x-model="type.type" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="e.g. Tourist, Business" />
                    </label>
                    <label class="block">
                      <span class="text-xs font-medium text-slate-600">Price (₱)</span>
                      <input type="number" min="0" x-model.number="type.price" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="e.g. 5000" />
                    </label>
                  </div>
                  <div class="flex justify-end">
                    <button type="button" @click="removeVisaType(index)" class="text-red-500 text-xs font-semibold hover:underline">Remove</button>
                  </div>
                </div>
              </template>
              <button type="button" @click="addVisaType()" class="text-sky-600 text-sm hover:underline">+ Add Visa Type</button>
            </div>
          </div>
        </div>

        <input type="hidden" name="visa_package_name" :value="visaPackageName">
        <input type="hidden" name="country" :value="country">
        <input type="hidden" name="processing_days" :value="processingDays">
        <input type="hidden" name="visa_package_description" :value="description">
        <input type="hidden" name="inclusions_json" :value="JSON.stringify(inclusions)">
        <input type="hidden" name="requirements_json" :value="JSON.stringify(requirements)">
        <input type="hidden" name="visa_types_json" :value="JSON.stringify(visaTypes)">

        <div class="mt-auto pt-3 sm:pt-4 border-t flex flex-col sm:flex-row sm:items-center justify-end gap-2 sm:gap-4 px-4 sm:px-6 pb-3 sm:pb-4 flex-shrink-0 bg-white">
          <button type="button" @click="$store.addVisaPackageModal.close()" class="px-4 sm:px-5 py-2 text-xs sm:text-sm font-medium text-slate-600 hover:underline text-slate-800 transition">Cancel</button>
          <button type="submit" :disabled="isSubmitting" class="bg-sky-600 hover:bg-sky-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs sm:text-sm px-4 sm:px-5 py-2 rounded transition w-full sm:w-auto" x-text="isSubmitting ? 'Creating...' : 'Create Package'"></button>
        </div>
      </form>
    </div>
  </div>
</div>
