<div 
  x-show="uploadDay !== null" 
  x-transition 
  class="fixed inset-0 z-[50] flex items-center justify-center backdrop-blur-sm bg-black/50"
>
  <div 
    class="bg-white rounded-lg shadow-lg w-full max-w-md mx-auto overflow-hidden p-4 space-y-4" 
    @click.away="resetUploadForm()"
  >

    <!-- Modal Title -->
    <h2 class="text-lg font-semibold text-gray-800">
      Upload Photo for Day <span x-text="uploadDay"></span>
    </h2>

    <!-- Error Message -->
    <template x-if="uploadErrorMessage">
      <div class="text-sm text-red-600 bg-red-100 border border-red-300 rounded px-3 py-2">
        <span x-text="uploadErrorMessage"></span>
      </div>
    </template>

    <!-- File Upload Trigger with Preview -->
    <div 
      class="border-2 border-dashed border-sky-300 rounded-lg p-4 text-center cursor-pointer hover:border-sky-500 transition relative h-64"
      @click="$refs.fileInput.click()"
    >
      <!-- Preview Image -->
      <template x-if="uploadPreview">
        <img 
          :src="uploadPreview" 
          alt="Preview" 
          class="absolute inset-0 w-full h-full object-cover rounded-lg"
        />
      </template>

      <!-- Upload Prompt -->
      <template x-if="!uploadPreview">
        <div class="flex flex-col items-center justify-center h-full space-y-2">
          <div class="text-3xl text-sky-400">📷</div>
          <p class="text-sm text-gray-600">Click to select a photo (JPG, PNG)</p>
        </div>
      </template>
    </div>

    <!-- Hidden File Input -->
    <input 
      type="file" 
      x-ref="fileInput" 
      class="hidden" 
      accept="image/jpeg,image/png,image/webp" 
      @change="handleFileUpload($event)" 
    />

    <!-- Caption -->
    <div>
      <label class="block font-semibold mb-1 text-sm text-gray-700">Caption</label>
      <textarea 
        x-model="uploadCaption"
        rows="2"
        class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-sky-500 focus:border-sky-500 resize-none"
        placeholder="Write something about this photo..."
      ></textarea>
    </div>

    

    <!-- Location Tag -->
    <div>
      <label class="block font-semibold mb-1 text-sm text-gray-700">Assigned Package</label>
      <input 
        type="text" 
        x-model="uploadLocationTag"
        readonly
        class="w-full border border-gray-300 rounded px-3 py-2 text-sm bg-gray-100 text-gray-600 cursor-not-allowed"
        placeholder="Auto-filled from package"
      />
    </div>

    <!-- Submit Button -->
    <div class="flex justify-end pt-2">
      <button 
        @click="submitUpload"
        class="bg-sky-500 text-white px-4 py-2 rounded hover:bg-sky-400 transition text-sm font-medium"
      >
        Upload Photo
        
      </button>
    </div>
  </div>
</div>