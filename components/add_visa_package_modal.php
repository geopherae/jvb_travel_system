<!-- ➕ Add Visa Package Modal -->
<div
  x-show="$store.addVisaPackageModal.isOpen"
  x-cloak
  x-transition.opacity
  x-data="visaPackageFormData()"
  class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 px-3 sm:px-4 backdrop-blur-sm"
  @keydown.escape.window="$store.addVisaPackageModal.close()"
  @click.self="$store.addVisaPackageModal.close()"
>
    <div class="bg-white rounded-t-2xl sm:rounded-lg shadow-xl w-full max-w-5xl min-h-[calc(100vh-64px)] max-h-[calc(100vh-64px)] sm:max-h-[95vh] flex flex-col overflow-hidden transition-all">
      <!-- Modal Header -->
      <div class="flex items-center justify-between px-6 pt-6 pb-4">
        <h2 class="text-xl font-bold text-sky-700">Add New Visa Package</h2>
        <button
          type="button"
          @click="$store.addVisaPackageModal.close()"
          class="text-slate-500 hover:text-red-500 text-2xl font-bold"
          aria-label="Close modal"
        >
          ×
        </button>
      </div>

      <form method="POST" action="../actions/process_add_visa_package.php" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden"
            @submit="handleFormSubmit($event)" x-data="{ section: 'details' }">

        <div class="flex flex-1 overflow-hidden">
          <!-- Left Sidebar Navigation -->
          <div class="w-[30%] border-r bg-slate-50 overflow-y-auto">
            <!-- Preview Card -->
            <div class="p-4 border-b bg-white">
              <div class="relative mb-3">
                <img
                  :src="previewUrl || '../images/default_visa_cover.jpg'"
                  alt="Cover Preview"
                  class="w-full h-32 object-cover rounded-lg"
                />
                <label
                  for="visa-cover-upload-add"
                  class="absolute bottom-2 right-2 bg-white/95 backdrop-blur-sm px-2 py-1 rounded text-xs cursor-pointer text-slate-700 font-medium shadow-sm hover:bg-white transition"
                >
                  Change
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
              <h3 class="text-sm font-semibold text-slate-800 truncate mb-1" x-text="visaPackageName || 'Unnamed Package'"></h3>
              <div class="flex flex-wrap gap-1">
                <span class="inline-block bg-purple-100 text-purple-700 font-medium px-2 py-0.5 rounded text-xs" x-text="country || 'Country'"></span>
                <span class="inline-block bg-slate-200 text-slate-600 font-medium px-2 py-0.5 rounded text-xs" x-text="(processingDays || 0) + 'd'"></span>
              </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="p-2">
              <button 
                type="button"
                @click="section = 'details'" 
                :class="section === 'details' ? 'bg-sky-100 text-sky-700 font-semibold' : 'text-slate-700 hover:bg-slate-100'"
                class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition flex items-center gap-2 mb-1"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Details
              </button>

              <button 
                type="button"
                @click="section = 'inclusions'" 
                :class="section === 'inclusions' ? 'bg-sky-100 text-sky-700 font-semibold' : 'text-slate-700 hover:bg-slate-100'"
                class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition flex items-center justify-between gap-2 mb-1"
              >
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                  </svg>
                  Inclusions
                </div>
                <span class="text-xs bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full font-medium" x-text="inclusions.length"></span>
              </button>

              <button 
                type="button"
                @click="section = 'requirements'" 
                :class="section === 'requirements' ? 'bg-sky-100 text-sky-700 font-semibold' : 'text-slate-700 hover:bg-slate-100'"
                class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition flex items-center justify-between gap-2 mb-1"
              >
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  Requirements
                </div>
                <span class="text-xs bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full font-medium" x-text="requirements.length"></span>
              </button>

              <button 
                type="button"
                @click="section = 'pricing'" 
                :class="section === 'pricing' ? 'bg-sky-100 text-sky-700 font-semibold' : 'text-slate-700 hover:bg-slate-100'"
                class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition flex items-center justify-between gap-2"
              >
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  Pricing & Statuses
                </div>
                <span class="text-xs bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full font-medium" x-text="visaTypes.length + applicantStatusOptions.length"></span>
              </button>
            </nav>
          </div>

          <!-- Main Content Area -->
          <div class="flex-1 overflow-y-auto bg-white">
            <!-- Details Section -->
            <div x-show="section === 'details'" x-transition class="p-6 max-w-3xl">
              <h3 class="text-lg font-semibold text-slate-800 mb-4">Package Details</h3>
              <div class="space-y-4">
                <label class="block">
                  <span class="text-sm font-medium text-slate-700 mb-1.5 block">Visa Package Name <span class="text-red-500">*</span></span>
                  <input type="text" x-model="visaPackageName" class="w-full border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent" placeholder="e.g. USA Tourist Visa" required />
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <label class="block">
                    <span class="text-sm font-medium text-slate-700 mb-1.5 block">Country <span class="text-red-500">*</span></span>
                    <input type="text" x-model="country" class="w-full border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent" placeholder="e.g. United States" required />
                  </label>
                  <label class="block">
                    <span class="text-sm font-medium text-slate-700 mb-1.5 block">Processing Days</span>
                    <input type="number" min="0" x-model.number="processingDays" class="w-full border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent" placeholder="e.g. 10" />
                  </label>
                </div>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700 mb-1.5 block">Description</span>
                  <textarea x-model="description" rows="4" class="w-full border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent" placeholder="Short package description"></textarea>
                </label>
              </div>
            </div>

            <!-- Inclusions Section -->
            <div x-show="section === 'inclusions'" x-transition class="p-6 max-w-3xl">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Inclusions</h3>
                <span class="text-sm text-slate-500" x-text="inclusions.length + ' item' + (inclusions.length !== 1 ? 's' : '')"></span>
              </div>
              <div class="space-y-3">
                <template x-for="(item, index) in inclusions" :key="'inc-' + index">
                  <div class="border border-slate-300 rounded-lg bg-slate-50 p-3">
                    <div class="flex items-center gap-3">
                      <span class="text-xs font-semibold text-slate-500 min-w-[24px]" x-text="(index + 1) + '.'"></span>
                      <input type="text" x-model="inclusions[index]" class="flex-1 border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent" placeholder="Inclusion item" />
                      <button type="button" @click="removeInclusion(index)" class="text-red-500 hover:text-red-600 p-1" aria-label="Remove inclusion">
                        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </template>
                <button type="button" @click="addInclusion()" class="text-sky-600 text-sm font-medium hover:text-sky-700 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                  </svg>
                  Add Inclusion
                </button>
              </div>
            </div>

<!-- Requirements Section -->
<div x-show="section === 'requirements'" x-transition class="p-6 max-w-3xl">
  <div class="flex items-center justify-between mb-2">
    <h3 class="text-lg font-semibold text-slate-800">Requirements</h3>
    <span class="text-sm text-slate-500" x-text="requirements.length + ' requirement' + (requirements.length !== 1 ? 's' : '')"></span>
  </div>
  <p class="text-xs text-slate-500 mb-4">
    Conditional requirements are only applied when the applicant status matches the selection below.
  </p>
  <div class="space-y-4">
    <template x-for="(req, index) in requirements" :key="'req-' + index">
      <div class="border border-slate-300 rounded-lg bg-slate-50 p-4 space-y-3">
        <div class="flex items-start justify-between">
          <span class="text-xs font-semibold text-slate-500" x-text="'Requirement ' + (index + 1)"></span>
          <button type="button" @click="removeRequirement(index)" class="text-red-500 text-xs font-semibold hover:text-red-600">Remove</button>
        </div>
        <label class="block">
          <span class="text-sm font-medium text-slate-700 mb-1.5 block">Category</span>
          <select x-model="req.category" class="w-full border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
            <option value="primary">Primary</option>
            <option value="financial">Financial</option>
            <option value="conditional">Conditional</option>
            <option value="other">Other</option>
          </select>
        </label>
        <label class="block">
          <span class="text-sm font-medium text-slate-700 mb-1.5 block">Name</span>
          <input type="text" x-model="req.name" class="w-full border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent" placeholder="Requirement name" />
        </label>
        <label class="block">
          <span class="text-sm font-medium text-slate-700 mb-1.5 block">Description</span>
          <input type="text" x-model="req.description" class="w-full border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent" placeholder="Requirement description" />
        </label>
        <!-- Show condition fields ONLY for Conditional category -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="req.category === 'conditional'">
          <label class="block">
            <span class="text-sm font-medium text-slate-700 mb-1.5 block">Condition Type</span>
            <select x-model="req.condition.type" class="w-full border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
              <option value="applicant_status">Applicant Status</option>
              <option value="sponsor_status">Sponsor Status</option>
            </select>
          </label>
          <label class="block">
            <span class="text-sm font-medium text-slate-700 mb-1.5 block">Status Value</span>
            <select x-model="req.condition.value" class="w-full border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
              <option value="">Select status</option>
              <option value="employed">Employed</option>
              <option value="self_employed">Self-Employed</option>
              <option value="business_owner">Business Owner</option>
              <option value="self_employed_business_owner">Self-Employed/Business Owner</option>
              <option value="corporation">Corporation</option>
              <option value="student">Student</option>
              <option value="senior_citizen_retired">Senior Citizen/Retired</option>
              <option value="married">Married</option>
              <option value="widow">Widowed</option>
              <option value="visit_family_friend">Visiting Family/Friend</option>
              <option value="company">Company</option>
            </select>
          </label>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" x-model="req.required" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
          <span class="font-medium">Required</span>
        </label>
      </div>
    </template>
    <button type="button" @click="addRequirement()" class="text-sky-600 text-sm font-medium hover:text-sky-700 flex items-center gap-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
      </svg>
      Add Requirement
    </button>
  </div>
</div>

            <!-- Pricing & Statuses Section -->
            <div x-show="section === 'pricing'" x-transition class="p-6 max-w-3xl">
              <h3 class="text-lg font-semibold text-slate-800 mb-6">Pricing & Client Statuses</h3>
              
              <!-- Visa Types -->
              <div class="mb-8">
                <div class="flex items-center justify-between mb-4 pb-2 border-b">
                  <h4 class="text-md font-semibold text-slate-700">Visa Types</h4>
                  <span class="text-xs text-slate-500 font-medium" x-text="visaTypes.length + '/5'"></span>
                </div>
                <div class="space-y-3">
                  <template x-for="(type, index) in visaTypes" :key="'type-' + index">
                    <div class="border border-slate-300 rounded-lg bg-slate-50 p-4">
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                        <label class="block">
                          <span class="text-sm font-medium text-slate-700 mb-1.5 block">Visa Type</span>
                          <input type="text" x-model="type.type" class="w-full border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent" placeholder="e.g. Tourist, Business" />
                        </label>
                        <label class="block">
                          <span class="text-sm font-medium text-slate-700 mb-1.5 block">Price (₱)</span>
                          <input type="number" min="0" x-model.number="type.price" class="w-full border border-slate-300 px-3 py-2 rounded-lg text-sm bg-white focus:ring-2 focus:ring-sky-500 focus:border-transparent" placeholder="e.g. 5000" />
                        </label>
                      </div>
                      <div class="flex justify-end">
                        <button type="button" @click="removeVisaType(index)" class="text-red-500 text-xs font-semibold hover:text-red-600">Remove</button>
                      </div>
                    </div>
                  </template>
                  <button 
                    type="button" 
                    @click="addVisaType()" 
                    :disabled="visaTypes.length >= 5"
                    :class="visaTypes.length >= 5 ? 'text-slate-400 cursor-not-allowed' : 'text-sky-600 hover:text-sky-700'"
                    class="text-sm font-medium flex items-center gap-1"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Visa Type
                  </button>
                </div>
              </div>

              <!-- Client Statuses -->
              <div>
                <div class="flex items-center justify-between mb-4 pb-2 border-b">
                  <h4 class="text-md font-semibold text-slate-700">Client Statuses</h4>
                  <span class="text-xs text-slate-500 font-medium" x-text="applicantStatusOptions.length + ' selected'"></span>
                </div>
                <p class="text-xs text-slate-500 mb-4">
                  Select which client statuses are applicable for this visa package.
                </p>
                <div class="grid grid-cols-2 gap-2">
                  <!-- Predefined Status Options -->
                  <label class="flex-shrink-0 flex items-center gap-3 p-3 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 cursor-pointer transition">
                    <input 
                      type="checkbox" 
                      value="Employed" 
                      @change="toggleApplicantStatus('Employed')"
                      :checked="applicantStatusOptions.includes('Employed')"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                    />
                    <span class="text-sm text-slate-700">Employed</span>
                  </label>

                  <label class="flex-shrink-0 flex items-center gap-3 p-3 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 cursor-pointer transition">
                    <input 
                      type="checkbox" 
                      value="Self-Employed" 
                      @change="toggleApplicantStatus('Self-Employed')"
                      :checked="applicantStatusOptions.includes('Self-Employed')"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                    />
                    <span class="text-sm text-slate-700">Self-Employed</span>
                  </label>

                  <label class="flex-shrink-0 flex items-center gap-3 p-3 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 cursor-pointer transition">
                    <input 
                      type="checkbox" 
                      value="Business Owner" 
                      @change="toggleApplicantStatus('Business Owner')"
                      :checked="applicantStatusOptions.includes('Business Owner')"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                    />
                    <span class="text-sm text-slate-700">Business Owner</span>
                  </label>

                  <label class="flex-shrink-0 flex items-center gap-3 p-3 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 cursor-pointer transition">
                    <input 
                      type="checkbox" 
                      value="Corporation" 
                      @change="toggleApplicantStatus('Corporation')"
                      :checked="applicantStatusOptions.includes('Corporation')"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                    />
                    <span class="text-sm text-slate-700">Corporation</span>
                  </label>

                  <label class="flex-shrink-0 flex items-center gap-3 p-3 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 cursor-pointer transition">
                    <input 
                      type="checkbox" 
                      value="Student" 
                      @change="toggleApplicantStatus('Student')"
                      :checked="applicantStatusOptions.includes('Student')"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                    />
                    <span class="text-sm text-slate-700">Student</span>
                  </label>

                  <label class="flex-shrink-0 flex items-center gap-3 p-3 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 cursor-pointer transition">
                    <input 
                      type="checkbox" 
                      value="Senior Citizen/Retired" 
                      @change="toggleApplicantStatus('Senior Citizen/Retired')"
                      :checked="applicantStatusOptions.includes('Senior Citizen/Retired')"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                    />
                    <span class="text-sm text-slate-700">Senior Citizen/Retired</span>
                  </label>

                  <label class="flex-shrink-0 flex items-center gap-3 p-3 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 cursor-pointer transition">
                    <input 
                      type="checkbox" 
                      value="Married" 
                      @change="toggleApplicantStatus('Married')"
                      :checked="applicantStatusOptions.includes('Married')"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                    />
                    <span class="text-sm text-slate-700">Married</span>
                  </label>

                  <label class="flex-shrink-0 flex items-center gap-3 p-3 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 cursor-pointer transition">
                    <input 
                      type="checkbox" 
                      value="Widowed" 
                      @change="toggleApplicantStatus('Widowed')"
                      :checked="applicantStatusOptions.includes('Widowed')"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                    />
                    <span class="text-sm text-slate-700">Widowed</span>
                  </label>

                  <label class="flex-shrink-0 flex items-center gap-3 p-3 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 cursor-pointer transition">
                    <input 
                      type="checkbox" 
                      value="Visiting Family/Friend" 
                      @change="toggleApplicantStatus('Visiting Family/Friend')"
                      :checked="applicantStatusOptions.includes('Visiting Family/Friend')"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                    />
                    <span class="text-sm text-slate-700">Visiting Family/Friend</span>
                  </label>

                  <label class="flex-shrink-0 flex items-center gap-3 p-3 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 cursor-pointer transition">
                    <input 
                      type="checkbox" 
                      value="None of the above" 
                      @change="toggleApplicantStatus('None of the above')"
                      :checked="applicantStatusOptions.includes('None of the above')"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                    />
                    <span class="text-sm text-slate-700">None of the above</span>
                  </label>
                </div>
              </div>
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
        <input type="hidden" name="applicant_status_options_json" :value="JSON.stringify(applicantStatusOptions)">

        <div class="mt-auto pt-4 border-t flex flex-col sm:flex-row sm:items-center justify-end gap-3 sm:gap-4 px-6 pb-4 sticky bottom-0 bg-white">
          <button type="button" @click="$store.addVisaPackageModal.close()" class="px-5 py-2 text-sm font-medium text-slate-600 hover:underline text-slate-800 transition">Cancel</button>
          <button type="submit" :disabled="isSubmitting" class="bg-sky-600 hover:bg-sky-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm px-4 py-2 rounded transition" x-text="isSubmitting ? 'Creating...' : 'Create Package'"></button>
        </div>
      </form>
    </div>
  </div>
</div>