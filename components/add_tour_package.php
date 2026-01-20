<!-- ➕ Add Tour Package Modal -->
<div x-data="tourFormData()" x-effect="days >= 2 ? nights = days - 1 : nights = 0"  class="backdrop-blur-sm fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 px-3 sm:px-4">
  <div class="align-right bg-white rounded-t-2xl sm:rounded-lg shadow-xl w-full max-w-5xl overflow-hidden transition-all max-h-[calc(100vh-24px)] sm:max-h-[95vh] flex flex-col">
    
  <!-- 🧭 Modal Header -->
  <div class="flex items-center justify-between px-6 pt-6 pb-4">
  <h2 class="text-xl font-bold text-sky-700">Add New Tour Package</h2>
  <button id="closeAddModal"
          type="button"
          class="text-slate-500 hover:text-red-500 text-xl font-bold">
    ×
  </button>
</div>


    <form method="POST" action="../actions/add_tour_package.php" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
      <!-- 🧩 Modal Body -->
      <div class="flex flex-col sm:flex-row gap-6 flex-1 overflow-y-auto px-6 pb-8">

<!-- Left Column: Image + Live Preview -->
<div class="sm:w-[50%] w-full flex flex-col bg-white rounded-lg shadow-sm overflow-hidden"
     x-data="{ 
       fileName: '',
       imageError: '',
       validateImage(event) {
         const file = event.target.files[0];
         if (file) {
           if (!['image/jpeg', 'image/png'].includes(file.type)) {
             this.imageError = 'Please upload only JPG or PNG images';
             return false;
           }
           if (file.size > 3 * 1024 * 1024) {
             this.imageError = 'File size must be less than 3MB';
             return false;
           }
           this.fileName = file.name;
           const reader = new FileReader();
           reader.onload = (e) => {
             this.previewUrl = e.target.result;
           };
           reader.readAsDataURL(file);
           this.imageError = '';
           return true;
         }
       }
     }">
  
  <!-- Image Preview -->
  <div class="relative">
    <img :src="previewUrl || '../images/default_trip_cover.jpg'" 
         :alt="fileName || 'Default Cover Image'"
         class="w-full h-52 sm:h-64 object-cover rounded-t-lg sm:rounded-lg sm:shadow"
         @error="$el.src = '../images/default_trip_cover.jpg'" />

    <!-- Upload Button -->
    <div class="absolute top-4 right-4">
      <label for="tour-cover-upload"
             class="bg-white/80 backdrop-blur px-3 py-1 rounded text-sm cursor-pointer text-slate-700 font-medium shadow hover:bg-white/90 transition">
        <span x-text="fileName ? 'Change Cover' : 'Upload Cover'"></span>
      </label>
      <input id="tour-cover-upload"
             type="file"
             name="tour_cover_image"
             accept=".jpg,.jpeg,.png"
             class="hidden"
             @change="validateImage($event)">
    </div>
  </div>

  <!-- File Info & Error Messages -->
  <div class="px-4 py-2 text-xs text-center"
       :class="imageError ? 'text-red-500' : 'text-gray-500'">
    <span x-text="imageError || (fileName ? `Selected: ${fileName}` : 'Accepted formats: JPG, PNG. Max size: 3MB.')"></span>
  </div>

<!-- Live Preview Content -->
<div class="p-2 pt-2 px-2 space-y-2">
  <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
    <h2 class="text-xl font-semibold text-slate-800 leading-tight truncate flex-1 min-w-0"
        x-text="packageName || 'Package Title Preview'">
    </h2>
    <span class="text-sky-800 font-semibold text-sm"
          x-text="price ? `₱${Number(price).toLocaleString()}` : 'Price TBD'">
    </span>
    <span class="inline-block bg-sky-100 text-sky-700 font-semibold px-3 py-1 rounded-full text-sm"
          x-text="days || nights ? `${days} Days / ${nights} Nights` : '0 Days / 0 Nights'">
    </span>
  </div>

  <!-- Requires Visa Green Pill (only if toggled) -->
  <div x-show="requiresVisa" class="mt-2">
    <span class="inline-block bg-green-100 text-green-800 font-medium px-3 py-1 rounded-full text-xs">
      Requires Visa
    </span>
  </div>

  <!-- Package Description -->
  <p class="text-sm text-slate-600 line-clamp-4"
     x-text="description || 'Package description will appear here as you type.'">
  </p>
</div>
</div>

        <!-- 🗂️ Right Column: Tabs -->
        <div class="sm:w-[50%] w-full rounded-lg bg-white">
          <!-- Tab Header -->
          <div class="flex border-b">
            <button type="button"
                    @click="tab = 'details'"
                    :class="tab === 'details'
                      ? 'px-4 py-2 text-sm font-medium text-sky-600 border-b-2 border-sky-600'
                      : 'px-4 py-2 text-sm font-medium text-slate-600 hover:text-sky-600'">
              Details
            </button>

            <button type="button"
                    @click="tab = 'itinerary'"
                    :class="tab === 'itinerary'
                      ? 'px-4 py-2 text-sm font-medium text-sky-600 border-b-2 border-sky-600'
                      : 'px-4 py-2 text-sm font-medium text-slate-600 hover:text-sky-600'">
              Itinerary
            </button>

            <button type="button"
                    @click="tab = 'inclusions'"
                    :class="tab === 'inclusions'
                      ? 'px-4 py-2 text-sm font-medium text-sky-600 border-b-2 border-sky-600'
                      : 'px-4 py-2 text-sm font-medium text-slate-600 hover:text-sky-600'">
              Inclusions
            </button>

            <button type="button"
                    @click="tab = 'exclusions'"
                    :class="tab === 'exclusions'
                      ? 'px-4 py-2 text-sm font-medium text-sky-600 border-b-2 border-sky-600'
                      : 'px-4 py-2 text-sm font-medium text-slate-600 hover:text-sky-600'">
              Exclusions
            </button>
          </div>

          <!-- Tab Content -->
          <div x-show="tab === 'details'" x-cloak>
            <?php include '../components/tabs/package-details.php'; ?>
          </div>

          <div x-show="tab === 'itinerary'" x-cloak>
            <?php include '../components/tabs/itinerary-builder.php'; ?>
          </div>

          <div x-show="tab === 'inclusions'" x-cloak>
           <?php include '../components/tabs/inclusions-builder.php'; ?>
          </div>

          <div x-show="tab === 'exclusions'" x-cloak>
           <?php include '../components/tabs/exclusions-builder.php'; ?>
          </div>
        </div>
      </div>

      <!-- 📝 Hidden Inputs -->
      <input type="hidden" name="package_name" :value="packageName">
      <input type="hidden" name="package_description" :value="description">
      <input type="hidden" name="price" :value="price">
      <input type="hidden" name="day_duration" :value="days">
      <input type="hidden" name="night_duration" :value="nights">
      <input type="hidden" name="origin" :value="origin">
      <input type="hidden" name="destination" :value="destination">
      <input type="hidden" name="checklist_template_id" :value="checklistTemplateId">
      <input type="hidden" name="inclusions_json" :value="JSON.stringify(inclusions)">
      <input type="hidden" name="exclusions_json" :value="JSON.stringify(exclusions)">
      <input type="hidden" name="itinerary_json" :value="JSON.stringify(itinerary)">
      <input type="hidden" name="is_favorite" :value="isFavorite ? 1 : 0">
      <input type="hidden" name="requires_visa" :value="requiresVisa ? 1 : 0">

      <!-- ✅ Footer Actions -->
            <div class="mt-auto pt-4 border-t flex flex-col sm:flex-row sm:items-center justify-end gap-3 sm:gap-6 px-6 pb-4 sticky bottom-0 bg-white">
      <button type="button" id="cancelAddModal"
              class="text-slate-500 hover:underline text-sm">
        Cancel
      </button>

      <button type="submit"
              :disabled="!packageName.trim() || !description.trim() || !origin || !destination || price <= 0 || days < 1"
              :class="!packageName.trim() || !description.trim() || !origin || !destination || price <= 0 || days < 1
                      ? 'bg-slate-300 cursor-not-allowed text-slate-500'
                      : 'bg-sky-600 hover:bg-sky-700 text-white'"
              class="text-sm px-6 py-2.5 rounded-lg font-medium transition shadow-sm w-full sm:w-auto">
        Save Package
      </button>
      </div>
    </form>
  </div>
</div>

