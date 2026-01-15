<div 
  x-show="selectedTestimonial !== null" 
  x-transition 
  @keydown.escape.window="selectedTestimonial = null" 
  class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
>
  <template x-if="selectedTestimonial">
    <div 
      x-transition 
      @click.away="selectedTestimonial = null"
      class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden relative max-h-[90vh] overflow-y-auto"
    >
      <!-- ❌ Close Button -->
      <button 
        @click="selectedTestimonial = null"
        class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 text-xl font-bold z-10 transition-colors"
        aria-label="Close"
      >
        &times;
      </button>

      <!-- 🎨 Compact Header with Trip Photo -->
      <div class="relative h-40 bg-gradient-to-br from-sky-100 to-blue-100 overflow-hidden">
        <template x-if="selectedTestimonial.photo_path">
          <img 
            :src="selectedTestimonial.photo_path" 
            :alt="selectedTestimonial.name || 'Trip Photo'" 
            class="w-full h-full object-cover"
          />
          <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
        </template>
        <template x-if="!selectedTestimonial.photo_path">
          <div class="w-full h-full flex items-center justify-center">
            <svg class="w-16 h-16 text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
        </template>
      </div>

      <!-- 📝 Content Section -->
      <div class="pt-4 pb-4 px-5">
        <!-- Client Avatar & Name -->
        <div class="flex items-start gap-3 mb-3">
          <div>
            <template x-if="selectedTestimonial.client_profile_photo">
              <img 
                :src="selectedTestimonial.client_profile_photo" 
                :alt="selectedTestimonial.name" 
                class="w-12 h-12 rounded-full object-cover border-2 border-sky-100"
              />
            </template>
            <template x-if="!selectedTestimonial.client_profile_photo">
              <div class="w-12 h-12 bg-sky-500 rounded-full flex items-center justify-center text-white font-bold text-sm border-2 border-sky-100">
                <span x-text="selectedTestimonial.avatar_initial"></span>
              </div>
            </template>
          </div>
          <div class="flex-1">
            <h2 class="text-base font-bold text-gray-900">
              <span x-text="selectedTestimonial.name"></span>
            </h2>
            <!-- Package & Rating on same line -->
            <div class="flex items-center gap-2 mt-1">
              <p class="text-sm font-semibold text-sky-600">
                <span x-text="selectedTestimonial.package"></span>
              </p>
              <div class="flex gap-0.5">
                <template x-for="i in 5" :key="i">
                  <template x-if="i <= selectedTestimonial.stars">
                    <span class="text-sm text-yellow-400">★</span>
                  </template>
                  <template x-if="i > selectedTestimonial.stars">
                    <span class="text-sm text-gray-300">★</span>
                  </template>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Travel Dates & Submitted Date -->
        <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-gray-200">
          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Trip Duration</label>
            <p class="text-sm text-gray-800 font-medium" x-text="selectedTestimonial.dates || '—'"></p>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Submitted</label>
            <p class="text-sm text-gray-800 font-medium">
              <span x-text="new Date(selectedTestimonial.date).toLocaleString('en-US', { year: 'numeric', month: 'short', day: '2-digit' })"></span>
            </p>
          </div>
        </div>

        <!-- Testimonial Quote -->
        <div class="mb-4">
          <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Review</label>
          <div class="relative">
            <p class="text-sm text-gray-800 leading-relaxed pl-3">
              <span x-text="selectedTestimonial.quote"></span>
            </p>
          </div>
        </div>

        <!-- Display Status Toggle Button -->
        <div class="flex gap-2 flex-wrap justify-end">
          <button 
            @click="toggleDisplayStatus(selectedTestimonial.review_id, selectedTestimonial.displayinHomePage)"
            :class="selectedTestimonial.displayinHomePage === 0 
              ? 'bg-sky-100 text-sky-700 hover:bg-sky-200 border border-sky-300'
              : 'bg-red-100 text-red-700 hover:bg-red-200 border border-red-300'"
            class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5"
          >
            <template x-if="selectedTestimonial.displayinHomePage === 0">
              <span>📢 Make Review Public</span>
            </template>
            <template x-if="selectedTestimonial.displayinHomePage === 1">
              <span>🔒 Hide Review</span>
            </template>
          </button>
          <button 
            @click="deleteReview(selectedTestimonial.review_id)"
            class="text-sm text-red-600 border border-red-600 px-4 py-2 rounded hover:bg-red-600 hover:text-white transition-colors flex items-center gap-1.5"
          >
            <span>Delete Review</span>
          </button>
        </div>
