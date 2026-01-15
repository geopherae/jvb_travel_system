<div 
  x-show="selectedPhoto !== null" 
  x-transition 
  @keydown.escape.window="selectedPhoto = null" 
  class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
>
  <template x-if="selectedPhoto">
    <div 
      x-transition 
      @click.away="selectedPhoto = null"
      class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-auto overflow-hidden flex flex-col sm:flex-row relative"
    >
      <!-- ❌ Close Button -->
      <button 
        @click="selectedPhoto = null"
        class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-xl font-bold"
        aria-label="Close"
      >
        &times;
      </button>

      <!-- 📷 Image Preview -->
      <div class="sm:w-1/2 w-full bg-gray-100 relative">
        <img 
          :src="selectedPhoto.url" 
          :alt="selectedPhoto.file_name || 'Uploaded photo'" 
          class="w-full h-full object-cover"
        />
        <div 
          x-show="selectedPhoto.caption" 
          class="absolute bottom-3 left-3 bg-black/60 text-white text-xs px-3 py-1 rounded"
          x-text="selectedPhoto.caption"
        ></div>
      </div>

      <!-- 📝 Details Panel -->
      <div class="sm:w-1/2 w-full flex flex-col justify-between">
        <div class="p-6 space-y-5 text-sm text-gray-800">
          <!-- 🏷️ Header -->
          <div class="text-base font-semibold text-gray-900">
            <span x-text="selectedPhoto.package_name || 'Package'"></span> – Day <span x-text="selectedPhoto.day || '—'"></span>
          </div>

          <!-- Caption -->
          <div>
            <label class="block font-semibold mb-1">Caption</label>
            <input 
              type="text"
              x-model="selectedPhoto.caption"
              class="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-sky-300"
              placeholder="Add a caption"
            />
          </div>

          <!-- Status -->
          <div>
            <label class="block font-semibold mb-1">Status</label>
            <span 
              :class="`inline-block px-2 py-0.5 text-xs font-bold tracking-wide rounded-full ${selectedPhoto.status_class}`" 
              x-text="selectedPhoto.document_status || 'Pending'"
            ></span>
          </div>

          <!-- Uploaded Date -->
          <div>
            <label class="block font-semibold mb-1">Uploaded</label>
            <span x-text="new Date(selectedPhoto.uploaded_at).toLocaleDateString()"></span>
          </div>
        </div>

        <!-- 🧭 Footer Actions -->
        <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
          <button 
            @click="deletePhoto(selectedPhoto.id)"
            class="text-red-600 hover:text-red-700 font-medium text-sm"
          >
            Delete
          </button>

          <button 
            @click="savePhotoDetails"
            class="bg-sky-500 text-white px-4 py-2 rounded hover:bg-sky-400 transition text-sm font-medium"
          >
            Save Changes
          </button>
        </div>
      </div>
    </div>
  </template>
</div>