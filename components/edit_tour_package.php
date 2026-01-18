<template x-if="$store.editTourModal.isOpen">
  <div
    x-cloak
    x-effect="days >= 2 ? nights = days - 1 : nights = 0"
    x-data="tourFormData($store.editTourModal.tourData)"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 backdrop-blur-sm"
    @keydown.escape.window="$store.editTourModal.close()"
    @click.away="$store.editTourModal.close()"
  >
    <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[95vh] overflow-y-auto transition-all p-6">

      <!-- Modal Header -->
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-sky-700">Edit Tour Package</h2>
        <button
          type="button"
          @click="$store.editTourModal.close()"
          class="text-slate-500 hover:text-red-500 text-2xl font-bold"
          aria-label="Close modal"
        >
          ×
        </button>
      </div>

      <!-- Form -->
      <form method="POST" action="../actions/update_tour_package.php" enctype="multipart/form-data">

        <!-- Hidden package ID (required for update) -->
        <input type="hidden" name="package_id" :value="$store.editTourModal.tourData?.id">

        <div class="flex flex-col lg:flex-row gap-6">

          <!-- Left Column: Image Upload + Live Preview -->
          <div class="lg:w-1/2 w-full flex flex-col bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="relative">
              <img
                :src="previewUrl || '../images/default_trip_cover.jpg'"
                alt="Tour Cover Preview"
                class="w-full h-64 lg:h-60 object-cover"
              />

              <!-- Upload Button Overlay -->
              <div class="absolute top-4 right-4">
                <label
                  for="tour-cover-upload-edit"
                  class="bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg text-sm cursor-pointer text-slate-700 font-medium shadow hover:bg-white transition"
                >
                  Change Cover
                </label>
                <input
                  id="tour-cover-upload-edit"
                  type="file"
                  name="tour_cover_image"
                  accept=".jpg,.jpeg,.png"
                  class="hidden"
                  @change="handleCoverUpload($event)"
                >
              </div>
            </div>

            <div class="px-4 py-3 text-xs text-gray-500 text-center">
              Accepted formats: JPG, PNG · Max size: 3MB
            </div>

            <!-- Live Preview Card -->
             <div class="p-2 pt-2 px-2 space-y-2">
 <div class="flex flex-wrap items-center justify-between gap-4">
                <h3
                  class="text-xl font-semibold text-slate-800 leading-tight truncate flex-1 min-w-0"
                  x-text="packageName || 'Untitled Package'"
                ></h3>

                <span
                  class="text-sky-800 font-semibold text-sm"
                  x-text="price ? '₱' + Number(price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'Price TBD'"
                ></span>

                <span
                  class="inline-block bg-sky-100 text-sky-700 font-semibold px-4 py-1.5 rounded-full text-sm"
                  x-text="`${days || 1} Day${days != 1 ? 's' : ''} / ${nights || 0} Night${nights != 1 ? 's' : ''}`"
                ></span>
              </div>
                <template x-if="requiresVisa">
                <div class="mt-2">
                  <span class="inline-block bg-green-100 text-green-800 font-medium px-3 py-1 rounded-full text-xs">
                    Requires Visa
                  </span>
                </div>
              </template>

              <p
                class="text-sm text-slate-600 line-clamp-4"
                x-text="description || 'No description yet.'"
              ></p>
          </div>
</div>

          <!-- Right Column: Tabs (Details, Itinerary, Inclusions) -->
          <div class="lg:w-1/2 w-full">
            <!-- Tab Navigation -->
            <div class="flex border-b">
              <button
                type="button"
                @click="tab = 'details'"
                :class="tab === 'details' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'"
                class="px-5 py-3 text-sm font-medium transition"
              >
                Details
              </button>
              <button
                type="button"
                @click="tab = 'itinerary'"
                :class="tab === 'itinerary' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'"
                class="px-5 py-3 text-sm font-medium transition"
              >
                Itinerary
              </button>
              <button
                type="button"
                @click="tab = 'inclusions'"
                :class="tab === 'inclusions' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'"
                class="px-5 py-3 text-sm font-medium transition"
              >
                Inclusions
              </button>
              <button
                type="button"
                @click="tab = 'exclusions'"
                :class="tab === 'exclusions' ? 'text-sky-600 border-b-2 border-sky-600' : 'text-slate-600 hover:text-sky-600'"
                class="px-5 py-3 text-sm font-medium transition"
              >
                Exclusions
              </button>
            </div>

            <!-- Tab Panels -->
            <div x-show="tab === 'details'" x-transition>
              <?php include '../components/tabs/package-details.php'; ?>
            </div>

            <div x-show="tab === 'itinerary'" x-transition>
              <?php include '../components/tabs/itinerary-builder.php'; ?>
            </div>

            <div x-show="tab === 'inclusions'" x-transition>
              <?php include '../components/tabs/inclusions-builder.php'; ?>
            </div>

            <div x-show="tab === 'exclusions'" x-transition>
              <?php include '../components/tabs/exclusions-builder.php'; ?>
            </div>
          </div>
        </div>

        <!-- Hidden Form Fields (for submission) -->
        <input type="hidden" name="existing_image" :value="image ? image.split('/').pop() : ''">
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

        <!-- Form Actions -->
        <div class="mt-2 pt-6 border-t flex justify-end gap-4">
          <button
            type="button"
            @click="$store.editTourModal.close()"
            class="px-5 py-2 text-sm font-medium text-slate-600 hover:underline text-slate-800 transition"
          >
            Cancel
          </button>

          <button
            type="submit"
            class="bg-sky-600 hover:bg-sky-700 text-white text-sm px-4 py-2 rounded transition"
          >
            Update Package
          </button>
        </div>
      </form>
    </div>
  </div>
</template>