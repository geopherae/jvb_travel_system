<!-- visa_package_modal.php -->
<script>
  // Toast notification helper
  if (typeof window.showToast !== 'function') {
    window.showToast = function (message, type = 'success') {
      const toastContainer = document.getElementById('toast-container') || (() => {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed bottom-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
        return container;
      })();

      const typeClasses = {
        success: 'bg-green-100 text-green-800 border border-green-300',
        error: 'bg-red-100 text-red-800 border border-red-300',
        warning: 'bg-yellow-100 text-yellow-800 border border-yellow-300',
        info: 'bg-blue-100 text-blue-800 border border-blue-300'
      };

      const toast = document.createElement('div');
      toast.className = `px-4 py-3 rounded-lg shadow-lg border ${typeClasses[type] || typeClasses.info}`;
      toast.textContent = message;
      toastContainer.appendChild(toast);

      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease-in-out';
        setTimeout(() => toast.remove(), 300);
      }, 3500);
    };
  }

  (function initVisaPackageModalStores() {
    const init = () => {
      if (!Alpine.store('editVisaPackageModal')) {
        Alpine.store('editVisaPackageModal', {
          isOpen: false,
          packageData: null,
          open(pkg) {
            this.packageData = pkg || null;
            this.isOpen = true;
          },
          close() {
            this.isOpen = false;
            this.packageData = null;
          }
        });
      }

      if (!Alpine.store('addVisaPackageModal')) {
        Alpine.store('addVisaPackageModal', {
          isOpen: false,
          open() {
            this.isOpen = true;
          },
          close() {
            this.isOpen = false;
          }
        });
      }

      if (!window.visaPackageFormData) {
        window.visaPackageFormData = function () {
          const safeArray = (value) => Array.isArray(value) ? value : [];
          const getCountryCode = (value) => {
            const raw = (value || '').toString().trim();
            if (!raw) return 'xx';
            const words = raw.replace(/[^a-zA-Z\s]/g, ' ').split(/\s+/).filter(Boolean);
            if (words.length === 0) return 'xx';
            return words.map((w) => w[0]).join('').toLowerCase();
          };
          const buildRequirementId = (countryValue, index) => {
            const code = getCountryCode(countryValue);
            const num = String(index + 1).padStart(3, '0');
            return `req_${code}_${num}`;
          };
          return {
            id: null,
            visaPackageName: '',
            country: '',
            processingDays: 0,
            description: '',
            previewUrl: '../images/default_visa_cover.jpg',
            coverUrl: '',
            inclusions: [],
            requirements: [],
            visaTypes: [],
            applicantStatusOptions: [],
            isSubmitting: false,
            getCountryCode,
            buildRequirementId,
            loadFrom(pkg) {
              const data = pkg || {};
              
              this.id = data.id || null;
              this.visaPackageName = data.visa_package_name || '';
              this.country = data.country || '';
              this.processingDays = data.processing_days || 0;
              this.description = data.description || '';
              this.previewUrl = data.visa_cover_image
                ? ('../images/visa_packages_banners/' + data.visa_cover_image)
                : '../images/default_visa_cover.jpg';
              this.coverUrl = data.visa_cover_image
                ? ('../images/visa_packages_banners/' + data.visa_cover_image)
                : '';
              this.inclusions = [...safeArray(data.inclusions)];
              this.requirements = safeArray(data.requirements).map((req, index) => {
                // Normalize all categories to lowercase
                let category = (req?.category || 'primary').toLowerCase();
                
                return {
                  id: req?.id || this.buildRequirementId(this.country, index),
                  name: req?.name || '',
                  description: req?.description || '',
                  required: req?.required !== undefined ? !!req.required : true,
                  category: category, // Now always lowercase: primary, financial, conditional
                  condition: {
                    type: req?.condition?.type || 'applicant_status',
                    operator: req?.condition?.operator || 'equals',
                    value: req?.condition?.value || ''
                  }
                };
              });
              this.visaTypes = safeArray(data.visa_types).map((type) => ({
                type: type?.type || '',
                price: type?.price || ''
              }));
              
              // Load applicant status options
              // Database stores: [{"option": "employed", "label": "Employed"}, ...]
              // Convert to array of label strings: ["Employed", "Self-Employed", ...]
              if (data.applicant_status_options) {
                try {
                  let parsed = data.applicant_status_options;
                  
                  // Parse JSON string if needed
                  if (typeof parsed === 'string') {
                    parsed = JSON.parse(parsed);
                  }
                  
              // Convert to array of strings
              if (Array.isArray(parsed)) {
                this.applicantStatusOptions = parsed.map(item => {
                  if (typeof item === 'string') {
                    return item;
                  } else if (typeof item === 'object' && item !== null) {
                    return item.label || item.option || '';
                  }
                  return '';
                }).filter(label => label !== '');
              } else {
                this.applicantStatusOptions = [];
              }
            } catch (e) {
              console.error('Error parsing applicant_status_options:', e);
              this.applicantStatusOptions = [];
            }
          } else {
            this.applicantStatusOptions = [];
          }
        },
            addInclusion() {
              this.inclusions.push('');
            },
            removeInclusion(index) {
              this.inclusions.splice(index, 1);
            },
            addRequirement() {
              const nextIndex = this.requirements.length;
              this.requirements.push({
                id: this.buildRequirementId(this.country, nextIndex),
                name: '',
                description: '',
                required: true,
                category: 'primary', // lowercase
                condition: { 
                  type: 'applicant_status',
                  operator: 'equals', 
                  value: '' 
                }
              });
            },
            removeRequirement(index) {
              this.requirements.splice(index, 1);
              this.requirements = this.requirements.map((req, idx) => ({
                ...req,
                id: req.id || buildRequirementId(this.country, idx)
              }));
            },
            addVisaType() {
              if (this.visaTypes.length < 5) {
                this.visaTypes.push({ type: '', price: '' });
              }
            },
            removeVisaType(index) {
              this.visaTypes.splice(index, 1);
            },
            toggleApplicantStatus(status) {
              const index = this.applicantStatusOptions.indexOf(status);
              if (index > -1) {
                // Status exists, remove it (uncheck)
                this.applicantStatusOptions.splice(index, 1);
              } else {
                // Status doesn't exist, add it (check)
                this.applicantStatusOptions.push(status);
              }
            },
            handleCoverUpload(event) {
              const file = event.target.files && event.target.files[0];
              if (!file) return;
              const reader = new FileReader();
              reader.onload = (e) => {
                this.previewUrl = e.target?.result || this.previewUrl;
              };
              reader.readAsDataURL(file);
            },
            async handleFormSubmit(event) {
              event.preventDefault();
              if (this.isSubmitting) return;
              this.isSubmitting = true;

              const form = event.target;
              const formData = new FormData(form);

              try {
                const response = await fetch(form.action, {
                  method: 'POST',
                  body: formData
                });

                const data = await response.json();

                if (response.ok && data.success) {
                  const closeDelay = 200;
                  const editStore = Alpine.store('editVisaPackageModal');
                  const addStore = Alpine.store('addVisaPackageModal');

                  if (editStore?.isOpen) {
                    editStore.close();
                  }
                  if (addStore?.isOpen) {
                    addStore.close();
                  }

                  setTimeout(() => {
                    window.showToast(data.message, 'success');
                  }, closeDelay);

                  setTimeout(() => {
                    window.location.reload();
                  }, closeDelay + 1500);
                } else {
                  window.showToast(data.message || 'Failed to update package', 'error');
                }
              } catch (error) {
                console.error('Form submission error:', error);
                window.showToast('An error occurred. Please try again.', 'error');
              } finally {
                this.isSubmitting = false;
              }
            }
          };
        };
      }
    };

    if (window.Alpine && typeof Alpine.store === 'function') {
      init();
    } else {
      document.addEventListener('alpine:init', init);
    }
  })();
</script>

<!-- 👁️ Visa Package View Modal -->
<div
  x-show="$store.visaPackageModal.isOpen"
  x-transition.opacity
  x-cloak
  class="backdrop-blur-sm fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/55 px-3 sm:px-4"
  @keydown.escape.window="$store.visaPackageModal.close()"
  role="dialog"
  aria-modal="true"
>
  <!-- Modal Container -->
  <div
    class="relative bg-white rounded-t-2xl sm:rounded-lg shadow-xl w-full max-w-[100vw] sm:max-w-5xl transition-all duration-300 max-h-none sm:max-h-[95vh] flex flex-col overflow-hidden overflow-y-auto"
    @click.away="$store.visaPackageModal.close()"
    x-transition.opacity
  >
    <!-- Close Button (top-right) -->
    <button
      @click="$store.visaPackageModal.close()"
      class="absolute top-4 right-4 text-slate-500 hover:text-red-500 text-xl font-bold z-10"
      aria-label="Close Visa Package Modal"
    >
      ×
    </button>

    <div class="w-full flex-1 overflow-y-auto flex flex-col sm:flex-row gap-2 sm:gap-4 p-4 sm:p-6 pb-16 sm:pb-6">
      <!-- Left Column -->
      <div class="sm:max-w-[55%] flex-1 flex flex-col">
        <img
          :src="$store.visaPackageModal.activePackage?.visa_cover_image ? ('../images/visa_packages_banners/' + $store.visaPackageModal.activePackage.visa_cover_image) : ($store.visaPackageModal.activePackage?.cover_url || '../images/default_visa_cover.jpg')"
          alt="Visa Package Cover"
          class="w-full h-52 sm:h-64 object-cover rounded-t-lg sm:rounded-lg sm:shadow"
        />
        <div class="p-4 pt-5 px-5 space-y-2">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800 leading-tight truncate flex-1 min-w-0"
                x-text="$store.visaPackageModal.activePackage?.visa_package_name || 'Unnamed Package'"></h2>

            <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
              <span class="inline-block bg-purple-100 text-purple-800 font-semibold px-3 py-1 rounded-full text-xs"
                    x-text="$store.visaPackageModal.activePackage?.country || 'Country TBD'"></span>
              <span class="inline-block bg-slate-100 text-slate-700 font-semibold px-3 py-1 rounded-full text-xs"
                    x-text="(($store.visaPackageModal.activePackage?.processing_days ?? 0) || 0) + ' Day' + ((($store.visaPackageModal.activePackage?.processing_days ?? 0) || 0) != 1 ? 's' : '')"></span>
            </div>
          </div>

          <p class="line-clamp-4 text-sm text-slate-600 leading-relaxed"
             x-text="$store.visaPackageModal.activePackage?.description || 'No description available.'"></p>
        </div>
      </div>

      <!-- Right Column -->
      <div class="sm:max-w-[45%] w-full flex flex-col border-t sm:border-t-0 sm:border-l border-slate-100 px-4 sm:px-5 py-4 sm:py-0 sm:pl-6">
        <!-- Tabs -->
        <div class="flex border-b mb-4 gap-3 overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 pb-1">
          <button
            type="button"
            :class="$store.visaPackageModal.tab === 'inclusions' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600'"
            @click="$store.visaPackageModal.tab = 'inclusions'"
            class="px-3 py-2 text-xs sm:text-sm font-medium focus:outline-none hover:text-sky-600 shrink-0"
          >
            Inclusions
          </button>
          <button
            type="button"
            :class="$store.visaPackageModal.tab === 'requirements' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600'"
            @click="$store.visaPackageModal.tab = 'requirements'"
            class="px-3 py-2 text-xs sm:text-sm font-medium focus:outline-none hover:text-sky-600 shrink-0"
          >
            Requirements
          </button>
          <button
            type="button"
            :class="$store.visaPackageModal.tab === 'visaTypes' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600'"
            @click="$store.visaPackageModal.tab = 'visaTypes'"
            class="px-3 py-2 text-xs sm:text-sm font-medium focus:outline-none hover:text-sky-600 shrink-0"
          >
            Visa Types
          </button>
        </div>

        <!-- Inclusions -->
        <div x-show="$store.visaPackageModal.tab === 'inclusions'" class="flex-1 pr-1 max-h-[320px] sm:max-h-[500px] overflow-y-auto space-y-5 text-left">
          <template x-if="$store.visaPackageModal.activePackage?.inclusions && $store.visaPackageModal.activePackage.inclusions.length">
            <ul class="space-y-1.5">
              <template x-for="(item, index) in $store.visaPackageModal.activePackage.inclusions" :key="'inclusion-' + index">
                <li class="flex items-start gap-2">
                  <span class="flex-shrink-0 mt-0.5">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                      <path d="M5 13l4 4L19 7" stroke="#04800c"
                            stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </span>
                  <div>
                    <p class="text-slate-700 font-medium text-sm" x-text="item"></p>
                  </div>
                </li>
              </template>
            </ul>
          </template>
          <template x-if="!$store.visaPackageModal.activePackage?.inclusions || !$store.visaPackageModal.activePackage.inclusions.length">
            <p class="text-sm text-slate-500 italic">No inclusions listed for this package.</p>
          </template>
        </div>

        <!-- Requirements -->
        <div x-show="$store.visaPackageModal.tab === 'requirements'" class="flex-1 pr-1 max-h-[320px] sm:max-h-[500px] overflow-y-auto space-y-5 text-left">
          <template x-if="$store.visaPackageModal.activePackage?.requirements && $store.visaPackageModal.activePackage.requirements.length">
            <ul class="space-y-1.5">
              <template x-for="(item, index) in $store.visaPackageModal.activePackage.requirements" :key="'requirement-' + index">
                <li class="flex items-start gap-2">
                  <span class="flex-shrink-0 mt-0.5">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="#619fe1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="#619fe1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </span>
                  <div>
                    <p class="text-slate-700 font-medium text-sm" x-text="item?.name || '—'"></p>
                    <p class="italic text-slate-600 text-xs leading-tight" x-show="item?.description" x-text="item?.description"></p>
                  </div>
                </li>
              </template>
            </ul>
          </template>
          <template x-if="!$store.visaPackageModal.activePackage?.requirements || !$store.visaPackageModal.activePackage.requirements.length">
            <p class="text-sm text-slate-500 italic">No requirements listed for this package.</p>
          </template>
        </div>

        <!-- Visa Types -->
        <div x-show="$store.visaPackageModal.tab === 'visaTypes'" class="flex-1 pr-1 max-h-[320px] sm:max-h-[500px] overflow-y-auto space-y-5 text-left">
          <template x-if="$store.visaPackageModal.activePackage?.visa_types && $store.visaPackageModal.activePackage.visa_types.length">
            <ul class="space-y-3">
              <template x-for="(item, index) in $store.visaPackageModal.activePackage.visa_types" :key="'visa-type-' + index">
                <li class="flex items-start gap-2">
                  <span class="flex-shrink-0 mt-0.5">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                      <circle cx="12" cy="12" r="10" stroke="#8b5cf6" stroke-width="2" fill="none"/>
                      <path d="M9 12l2 2 4-4" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </span>
                  <div class="flex-1">
                    <div class="flex items-center justify-between gap-3">
                      <p class="text-sky-800 font-semibold" x-text="item?.type || '—'"></p>
                      <span class="inline-block bg-sky-100 text-sky-700 font-semibold px-3 py-1 rounded-full text-sm" x-text="item?.price ? '₱' + Number(item.price).toLocaleString('en-PH') : 'Price TBD'"></span>
                    </div>
                  </div>
                </li>
              </template>
            </ul>
          </template>
          <template x-if="!$store.visaPackageModal.activePackage?.visa_types || !$store.visaPackageModal.activePackage.visa_types.length">
            <p class="text-sm text-slate-500 italic">No visa types listed for this package.</p>
          </template>
        </div>
      </div>
    </div>

    <!-- Sticky Action Buttons -->
    <div class="mb-4 sticky bottom-0 flex justify-between items-center px-4 py-3 sm:px-6 sm:py-2 bg-gray-50 gap-2 sm:gap-3 z-10 border-t border-gray-200">
      <div class="flex w-full justify-between gap-2 sm:gap-3">
        <button
          type="button"
          class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-red-500 hover:text-white bg-white border border-red-500 rounded-lg hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors"
          @click="$store.visaPackageModal.archivePackage()"
        >
          Archive Package
        </button>
        <button
          type="button"
          class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-white bg-sky-600 border border-transparent rounded-lg hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 disabled:opacity-50 transition-colors"
          @click="
            $store.editVisaPackageModal.open($store.visaPackageModal.activePackage);
            $store.visaPackageModal.close();
          "
        >
          Edit Package
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ✏️ Edit Visa Package Modal -->
<div
  x-show="$store.editVisaPackageModal.isOpen"
  x-cloak
  x-transition.opacity
  x-data="visaPackageFormData()"
  x-effect="loadFrom($store.editVisaPackageModal.packageData || {})"
  class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/55 px-3 sm:px-4 backdrop-blur-sm"
  @keydown.escape.window="$store.editVisaPackageModal.close()"
  @click.self="$store.editVisaPackageModal.close()"
>
    <div class="bg-white rounded-t-2xl sm:rounded-lg shadow-xl w-full max-w-5xl max-h-[calc(100vh-24px)] sm:max-h-[95vh] flex flex-col overflow-hidden transition-all">
      <!-- Modal Header -->
      <div class="flex items-center justify-between px-6 pt-6 pb-4">
        <h2 class="text-xl font-bold text-sky-700">Edit Visa Package</h2>
        <button
          type="button"
          @click="$store.editVisaPackageModal.close()"
          class="text-slate-500 hover:text-red-500 text-2xl font-bold"
          aria-label="Close modal"
        >
          ×
        </button>
      </div>

      <form method="POST" action="../actions/update_visa_package.php" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden"
            @submit="handleFormSubmit($event)">
        <input type="hidden" name="package_id" :value="id">

        <div class="flex flex-col lg:flex-row gap-6 flex-1 overflow-y-auto px-6 pb-8">
          <!-- Left Column: Image Upload + Live Preview -->
          <div class="lg:w-1/2 w-full flex flex-col bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="relative">
              <img
                :src="previewUrl || '../images/default_visa_cover.jpg'"
                alt="Visa Cover Preview"
                class="w-full h-64 lg:h-60 object-cover"
              />

              <div class="absolute top-4 right-4">
                <label
                  for="visa-cover-upload-edit-main"
                  class="bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg text-sm cursor-pointer text-slate-700 font-medium shadow hover:bg-white transition"
                >
                  Change Cover
                </label>
                <input
                  id="visa-cover-upload-edit-main"
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

            <div class="p-4 space-y-2">
              <h3 class="text-xl font-semibold text-slate-800 leading-tight truncate" x-text="visaPackageName || 'Unnamed Package'"></h3>
              <div class="flex flex-wrap items-center gap-2">
                <span class="inline-block bg-purple-100 text-purple-800 font-semibold px-3 py-1 rounded-full text-xs" x-text="country || 'Country TBD'"></span>
                <span class="inline-block bg-slate-100 text-slate-700 font-semibold px-3 py-1 rounded-full text-xs" x-text="(processingDays || 0) + ' Day' + ((processingDays || 0) != 1 ? 's' : '')"></span>
              </div>
              <p class="text-sm text-slate-600 line-clamp-4" x-text="description || 'No description yet.'"></p>
            </div>
          </div>

          <!-- Right Column: Tabs -->
          <div class="lg:w-1/2 w-full" x-data="{ tab: 'details' }">
            <div class="flex border-b">
              <button type="button" @click="tab = 'details'" :class="tab === 'details' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'" class="px-5 py-3 text-sm font-medium transition">Details</button>
              <button type="button" @click="tab = 'inclusions'" :class="tab === 'inclusions' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'" class="px-5 py-3 text-sm font-medium transition">Inclusions</button>
              <button type="button" @click="tab = 'requirements'" :class="tab === 'requirements' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'" class="px-5 py-3 text-sm font-medium transition">Requirements</button>
              <button type="button" @click="tab = 'visaTypes'" :class="tab === 'visaTypes' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'" class="px-5 py-3 text-sm font-medium transition">Visa Types</button>
            </div>

            <div x-show="tab === 'details'" x-transition class="p-4 space-y-4">
              <label class="block">
                <span class="text-xs font-medium text-slate-600">Visa Package Name</span>
                <input type="text" x-model="visaPackageName" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="e.g. USA Tourist Visa" />
              </label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="block">
                  <span class="text-xs font-medium text-slate-600">Country</span>
                  <input type="text" x-model="country" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="e.g. United States" />
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

            <div x-show="tab === 'inclusions'" x-transition class="p-4 space-y-4 max-h-[500px] overflow-y-auto text-sm">
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

            <div x-show="tab === 'requirements'" x-transition class="p-4 space-y-4 max-h-[500px] overflow-y-auto text-sm">
              <p class="text-xs text-slate-500">
                Conditional requirements are only applied when the applicant status matches the selection below.
              </p>
              <template x-for="(req, index) in requirements" :key="'req-' + index">
                <div class="border rounded-lg shadow-sm bg-slate-50 p-3 space-y-3">
                  <label class="block">
                    <span class="text-xs font-medium text-slate-600">Category</span>
                    <select x-model="req.category" class="w-full border px-3 py-2 rounded text-sm bg-white">
                      <option value="primary">Primary</option>
                      <option value="financial">Financial</option>
                      <option value="conditional">Conditional</option>
                      <option value="other">Other</option>
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
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="req.category === 'conditional'">
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
                        <option value="married">Married</option>
                        <option value="single">Single</option>
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

            <div x-show="tab === 'visaTypes'" x-transition class="p-4 space-y-4 max-h-[500px] overflow-y-auto text-sm">
              <template x-for="(type, index) in visaTypes" :key="'type-' + index">
                <div class="border rounded-lg shadow-sm bg-slate-50 p-3 space-y-2">
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="block">
                      <span class="text-xs font-medium text-slate-600">Type</span>
                      <input type="text" x-model="type.type" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="B1/B2 Tourist" />
                    </label>
                    <label class="block">
                      <span class="text-xs font-medium text-slate-600">Price</span>
                      <input type="number" min="0" x-model.number="type.price" class="w-full border px-3 py-2 rounded text-sm bg-white" placeholder="22000" />
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

        <input type="hidden" name="existing_image" :value="coverUrl ? coverUrl.split('/').pop() : ''">
        <input type="hidden" name="visa_package_name" :value="visaPackageName">
        <input type="hidden" name="country" :value="country">
        <input type="hidden" name="processing_days" :value="processingDays">
        <input type="hidden" name="visa_package_description" :value="description">
        <input type="hidden" name="inclusions_json" :value="JSON.stringify(inclusions)">
        <input type="hidden" name="requirements_json" :value="JSON.stringify(requirements)">
        <input type="hidden" name="visa_types_json" :value="JSON.stringify(visaTypes)">

        <div class="mt-auto pt-4 border-t flex flex-col sm:flex-row sm:items-center justify-end gap-3 sm:gap-4 px-6 pb-4 sticky bottom-0 bg-white">
          <button type="button" @click="$store.editVisaPackageModal.close()" class="px-5 py-2 text-sm font-medium text-slate-600 hover:underline text-slate-800 transition">Cancel</button>
          <button type="submit" :disabled="isSubmitting" class="bg-sky-600 hover:bg-sky-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm px-4 py-2 rounded transition" x-text="isSubmitting ? 'Updating...' : 'Update Package'"></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Include the Edit and Add modals separately -->
<?php include __DIR__ . '/edit_visa_package_modal.php'; ?>
<?php include __DIR__ . '/add_visa_package_modal.php'; ?>
