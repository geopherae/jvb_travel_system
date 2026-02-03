<!-- 🔍 File Viewer Modal -->
<!-- Fixed position keeps it above sidebar, inside wrapper maintains Alpine scope -->
<div x-show="modals.viewer"
     x-transition
     x-cloak
     class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 p-2 sm:p-4">
  <div class="bg-white w-full max-w-5xl h-[95vh] sm:h-[90vh] rounded-lg shadow-lg flex flex-col sm:flex-row overflow-hidden"
       @keydown.window.escape="closeViewer()"
       @click.outside="closeViewer()">

    <!-- 🖼️ Top/Left Panel: File Preview -->
    <div class="w-full sm:w-2/3 bg-gray-100 p-3 sm:p-6 flex items-center justify-center overflow-hidden relative flex-shrink-0 h-1/4 sm:h-full">

      <!-- PDF Viewer -->
      <template x-if="viewer.mimeType === 'application/pdf'">
        <iframe :src="viewer.path"
                class="w-full h-full border rounded-md"
                frameborder="0"></iframe>
      </template>

      <!-- Image Viewer with Zoom -->
      <template x-if="viewer.mimeType.startsWith('image/')">
        <div class="relative w-full h-full flex items-center justify-center">
          <img :src="viewer.path"
               :style="`transform: scale(${viewer.zoom})`"
               class="max-w-full max-h-full object-contain transition-transform duration-200"
               alt="Preview" />
          <div class="absolute top-2 right-2 bg-white bg-opacity-90 rounded-lg shadow-lg p-1 flex gap-1">
            <button @click="viewer.zoom = Math.min(viewer.zoom + 0.1, 2)"
                    class="text-xs px-2 py-1 hover:bg-gray-100 rounded touch-manipulation" title="Zoom In">➕</button>
            <button @click="viewer.zoom = Math.max(viewer.zoom - 0.1, 0.5)"
                    class="text-xs px-2 py-1 hover:bg-gray-100 rounded touch-manipulation" title="Zoom Out">➖</button>
            <button @click="viewer.zoom = 1"
                    class="text-xs px-2 py-1 hover:bg-gray-100 rounded touch-manipulation" title="Reset Zoom">🔄</button>
          </div>
        </div>
      </template>

      <!-- Unsupported Format -->
      <template x-if="!viewer.mimeType.startsWith('image/') && viewer.mimeType !== 'application/pdf'">
        <p class="text-sm text-gray-500 italic">Unsupported file type preview</p>
      </template>
    </div>

    <!-- 🧾 Bottom/Right Panel: Metadata -->
    <div class="w-full sm:w-1/3 bg-white p-4 sm:p-6 flex flex-col justify-between overflow-y-auto flex-1">
      <div>
        <!-- Requirement Description -->
        <label class="block text-sm font-medium text-gray-700 mb-1">Description:</label>
        <p class="text-sm text-gray-700 mb-4 leading-relaxed" x-text="viewer.requirement || 'N/A'"></p>

        <!-- Status -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Status:</label>
          <select x-model="viewer.status"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 bg-white transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
          </select>
        </div>

        <!-- Admin Comments -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Admin Comments:</label>
          <textarea
              x-model="viewer.adminComments"
              class="w-full h-24 sm:h-28 text-sm border border-gray-300 rounded px-3 py-2 resize-none overflow-y-auto bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
              placeholder="Add comments about this document...">
          </textarea>
        </div>

        <!-- Links stacked vertically -->
        <div class="space-y-3 mt-4">
          <a :href="viewer.path"
             target="_blank"
             class="block text-sm text-sky-600 hover:text-sky-700 hover:underline touch-manipulation">
            Open in Full Screen
          </a>
          
          <button @click="deleteDocument()"
                  class="block text-sm text-red-600 hover:text-red-700 hover:underline touch-manipulation">
            Delete File
          </button>
        </div>
      </div>

      <!-- 🕓 Metadata Footer -->
      <div class="text-xs text-gray-500 mt-4 sm:mt-6 border-t pt-3 sm:pt-4 space-y-2">
        <p class="break-words">
          <span class="font-medium text-gray-700">Uploaded:</span>
          <span x-text="viewer.uploadedAt 
            ? new Date(viewer.uploadedAt).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) 
            : '—'"></span>
        </p>
        <p class="break-words">
          <span class="font-medium text-gray-700">Approved:</span>
          <span x-text="viewer.approvedAt 
            ? new Date(viewer.approvedAt).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) 
            : 'Not yet approved'"></span>
        </p>
        <p class="break-words">
          <span class="font-medium text-gray-700">Updated By:</span>
          <span x-text="viewer.updatedBy || '—'"></span>
        </p>
      </div>

      <!-- 🧮 Action Buttons -->
      <div class="mt-4 flex gap-2 border-t pt-4">
        <button @click="closeViewer()"
                class="flex-1 bg-white border-2 border-gray-300 hover:border-gray-400 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-4 py-2.5 rounded-lg transition-all duration-200 touch-manipulation">
          Close
        </button>
        <button @click="saveChanges()"
                class="flex-1 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white text-sm font-semibold px-4 py-2.5 rounded-lg shadow-sm transition-all duration-200 touch-manipulation">
          Save Changes
        </button>
      </div>
    </div>
  </div>
</div>
