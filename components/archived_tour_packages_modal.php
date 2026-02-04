<!-- Archived Tour Packages Modal -->
<div x-show="$store.archivedTourModal.isOpen" 
     x-cloak
     @keydown.escape.window="$store.archivedTourModal.close()"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
     style="display: none;">
  
  <div @click.away="$store.archivedTourModal.close()" 
       class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
    
    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
      <h3 class="text-lg font-semibold text-gray-900">Archived Tour Packages</h3>
      <button @click="$store.archivedTourModal.close()" 
              class="text-gray-400 hover:text-gray-600 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Loading State -->
    <div x-show="$store.archivedTourModal.loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-sky-600"></div>
    </div>

    <!-- Content -->
    <div x-show="!$store.archivedTourModal.loading" class="flex-1 overflow-y-auto p-6">
      
      <!-- Empty State -->
      <template x-if="$store.archivedTourModal.packages.length === 0">
        <div class="flex flex-col items-center justify-center py-12 text-gray-500">
          <svg class="w-12 h-12 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
          </svg>
          <h4 class="text-lg font-semibold text-gray-900 mb-1">No Archived Packages</h4>
          <p class="text-sm text-gray-600">All tour packages are active.</p>
        </div>
      </template>

      <!-- Package List -->
      <div class="space-y-2">
        <template x-for="pkg in $store.archivedTourModal.packages" :key="pkg.id">
          <div class="border border-gray-200 rounded overflow-hidden hover:border-gray-300 transition">
            <div class="flex flex-col sm:flex-row gap-2 p-2">
              
              <!-- Image -->
              <img :src="pkg.image" 
                   :alt="pkg.name"
                   class="w-full sm:w-20 h-20 object-cover rounded" />
              
              <!-- Info -->
              <div class="max-w-[70%]">
                <h4 class="font-semibold text-gray-900 truncate text-sm" x-text="pkg.name"></h4>
                <p class="text-xs text-gray-600 line-clamp-1 mt-0.5" x-text="pkg.description"></p>
                <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                  <span class="inline-block bg-gray-100 px-1.5 py-0.5 rounded text-xs" x-text="`${pkg.days}D/${pkg.nights}N`"></span>
                  <span class="font-semibold text-gray-700" x-text="pkg.price ? '₱' + Number(pkg.price).toLocaleString('en-PH') : 'TBD'"></span>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex gap-2 ml-auto">
                <button @click="$store.archivedTourModal.unarchive(pkg.id)" 
                        title="Unarchive package"
                        class="flex items-center justify-center w-9 h-9 text-sky-600 hover:text-sky-700 hover:bg-sky-50 rounded-full transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                  </svg>
                </button>
                <button @click="$store.archivedTourModal.deletePermananently(pkg.id, pkg.name)" 
                        title="Delete permanently"
                        class="flex items-center justify-center w-9 h-9 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-full transition">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Footer -->
    <div class="border-t border-gray-200 px-6 py-4 flex justify-end">
      <button @click="$store.archivedTourModal.close()" 
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 transition">
        Close
      </button>
    </div>
  </div>
</div>

