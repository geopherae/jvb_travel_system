<!-- ✅ Confirmation Modal (for Delete) -->
<div x-show="confirmAction.visible" x-transition x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm px-4">
  <div @click.away="confirmAction.visible = false"
       class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden transition-all transform"
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95">

    <!-- Colored Header Bar -->
    <div class="h-2 bg-red-700"></div>

    <!-- Content Container -->
    <div class="px-6 py-6 sm:px-8 sm:py-8 space-y-6 relative">
      
      <!-- Close Button -->
      <button @click="confirmAction.visible = false"
              class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-all duration-200"
              aria-label="Close modal">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
             stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <!-- Icon & Header -->
      <div class="flex items-start gap-4">
        <!-- Delete Icon -->
        <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center bg-red-100">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>

        <!-- Text Content -->
        <div class="flex-1 pt-1">
          <template x-if="confirmAction.type === 'delete_requirement'">
            <div>
              <h2 class="text-xl font-bold text-gray-900 mb-1">Remove Requirement</h2>
              <p class="text-sm text-gray-600 leading-relaxed">Remove "<span x-text="confirmAction.requirementName"></span>" from this visa package. This action cannot be undone.</p>
            </div>
          </template>
          <template x-if="confirmAction.type !== 'delete_requirement'">
            <div>
              <h2 class="text-xl font-bold text-gray-900 mb-1">Delete Document</h2>
              <p class="text-sm text-gray-600 leading-relaxed">This action cannot be undone. The file will be permanently removed.</p>
            </div>
          </template>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3 pt-4">
        <button @click="confirmAction.visible = false"
                class="flex-1 bg-white border-2 border-gray-300 hover:border-gray-400 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-4 py-2.5 rounded-lg transition-all duration-200 shadow-sm">
          Cancel
        </button>
        <template x-if="confirmAction.type === 'delete_requirement'">
          <button @click="deleteRequirementConfirmed(confirmAction.requirementId)"
                  class="flex-1 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-all duration-200 shadow-md bg-red-700 hover:bg-red-600 hover:shadow-lg">
            Remove Requirement
          </button>
        </template>
        <template x-if="confirmAction.type !== 'delete_requirement'">
          <button @click="deleteDocumentConfirmed(confirmAction.documentId, confirmAction.type === 'delete_actual_visa' ? 'actual_visa' : 'requirement')"
                  class="flex-1 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-all duration-200 shadow-md bg-red-700 hover:bg-red-600 hover:shadow-lg">
            Delete
          </button>
        </template>
      </div>
    </div>
  </div>
</div>
