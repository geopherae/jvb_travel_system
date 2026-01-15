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
            <p class="text-sm text-gray-700 italic" x-text="selectedPhoto.caption || 'No caption provided'"></p>
          </div>

          <!-- Location Tag -->
          <div>
            <label class="block font-semibold mb-1">Tour Package</label>
            <span class="mt-1inline-block px-3 py-1 text-xs font-semibold rounded-full border border-gray-300 text-sky-600 bg-sky-50">
              <span x-text="selectedPhoto.location_tag || 'Unspecified'"></span>
            </span>
          </div>

          <!-- Status -->
          <div>
            <label class="block font-semibold mb-1">Status</label>
            <span 
              :class="`mt-1 inline-block px-2 py-0.5 text-xs font-bold tracking-wide rounded-full ${selectedPhoto.status_class}`" 
              x-text="selectedPhoto.document_status || 'Pending'"
            ></span>
          </div>

          <!-- Uploaded Date -->
          <div>
            <label class="block font-semibold mb-1">Uploaded</label>
            <span x-text="new Date(selectedPhoto.uploaded_at).toLocaleString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true })"></span>
          </div>

          <!-- Updated By -->
          <div>
            <label class="block font-semibold mb-1">Last Updated By</label>
            <span x-text="selectedPhoto.status_updated_by || '—'"></span>
          </div>
        </div>

        <!-- 🧭 Footer Actions -->
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end items-center gap-6">
          <template x-if="isAdmin && selectedPhoto">
            <div class="flex gap-4">
              <!-- Delete (only show if Rejected) -->
              <template x-if="selectedPhoto.document_status === 'Rejected'">
                <button 
                  @click="() => { confirmDeletePhoto = true; }" 
                  class="text-red-600 hover:text-red-700 font-medium text-sm underline"
                >
                  Delete
                </button>
              </template>
              <!-- Reject -->
              <button 
                @click="() => { updateStatus('Rejected'); }" 
                :disabled="selectedPhoto.document_status === 'Rejected'" 
                :class="selectedPhoto.document_status === 'Rejected' 
                  ? 'text-gray-400 cursor-not-allowed' 
                  : 'text-red-600 hover:text-red-700 font-medium text-sm'"
              >
                Reject
              </button>
              <!-- Approve -->
              <button 
                @click="() => { updateStatus('Approved'); }" 
                :disabled="selectedPhoto.document_status === 'Approved'" 
                :class="selectedPhoto.document_status === 'Approved' 
                  ? 'text-gray-400 cursor-not-allowed' 
                  : 'text-emerald-600 hover:text-emerald-700 font-medium text-sm'"
              >
                Approve
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>
  </template>
</div>

<!-- 🗑️ Delete Confirmation Modal (outside main modal) -->
<template x-if="confirmDeletePhoto && selectedPhoto">
  <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
    <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6 space-y-4">
      <h3 class="text-lg font-semibold text-gray-900">Delete Photo?</h3>
      <p class="text-sm text-gray-600">
        This action cannot be undone. The rejected photo will be permanently deleted.
      </p>
      <div class="flex gap-3 justify-end pt-2">
        <button 
          @click="confirmDeletePhoto = false"
          class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium text-sm"
        >
          Cancel
        </button>
        <button 
          @click="() => { deletePhoto(selectedPhoto.id); confirmDeletePhoto = false; }"
          class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 font-medium text-sm"
        >
          Delete
        </button>
      </div>
    </div>
  </div>
</template>