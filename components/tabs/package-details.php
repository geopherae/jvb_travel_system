<!-- 🧾 Package Details Tab -->
<div class="p-4 space-y-4 max-h-[500px] overflow-y-auto text-sm">

  <!-- ⭐ Favorite Toggle + Requires Visa -->
  <div class="flex items-center justify-between">
    <!-- Left: Mark as Popular Pick -->
    <button
      type="button"
      @click="isFavorite = !isFavorite"
      class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-800 transition"
    >
      <span class="text-xl" :class="isFavorite ? 'text-yellow-500 drop-shadow' : 'text-slate-400'">
        ★
      </span>
      <span class="font-small">Mark as Popular Pick</span>
    </button>

    <!-- Right: Requires Visa -->
    <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
      <input
        type="checkbox"
        x-model="requiresVisa"
        name="requires_visa"
        value="1"
        class="rounded text-sky-600 focus:ring-sky-500"
      />
      <span class="select-none">Requires Visa</span>
    </label>

    <!-- Hidden field for is_favorite (unchanged) -->
    <input type="hidden" name="is_favorite" :value="isFavorite ? 1 : 0" />
  </div>

  <!-- 📦 Package Name -->
  <label class="block">
    <span class="block text-xs font-medium mb-1 text-slate-700">
      Package Name <span class="text-red-500">*</span>
    </span>
    <input
      type="text"
      x-model="packageName"
      required
      class="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition"
      placeholder="Enter package name"
    />
  </label>

  <!-- 📝 Description -->
  <label class="block">
    <span class="block text-xs font-medium mb-1 text-slate-700">
      Description <span class="text-red-500">*</span>
    </span>
    <textarea
      x-model="description"
      rows="2"
      required
      class="w-full border border-slate-300 rounded-lg px-4 py-3 text-sm shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition resize-none"
      placeholder="Write a compelling description of the tour package..."
    ></textarea>
  </label>

  <!-- ✈️ Origin & Destination Airport Select -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Origin -->
    <div class="relative">
      <label class="block text-xs font-medium mb-1 text-slate-700">
        Origin Airport Code
      </label>
      <input
        type="text"
        x-model="originQuery"
        @input.debounce.300ms="filterAirports('origin')"
        @focus="filterAirports('origin')"
        placeholder="e.g. MNL"
        autocomplete="off"
        class="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
      />
      <input type="hidden" name="origin" :value="origin" />

      <div
        x-show="originMatches.length > 0"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute z-50 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-xl max-h-60 overflow-y-auto"
      >
        <template x-for="airport in originMatches" :key="airport.code">
          <div
            @click="selectAirport('origin', airport)"
            class="px-4 py-2.5 hover:bg-sky-50 cursor-pointer transition flex items-center gap-2 border-b border-slate-100 last:border-b-0"
            :title="airport.name"
          >
            <!-- Airport Code (plain bold) -->
            <div class="flex-shrink-0 w-10">
              <span class="font-bold text-sky-700 text-sm tracking-wide" x-text="airport.code"></span>
            </div>

            <!-- Airport Details -->
            <div class="flex-1 min-w-0">
              <div class="font-medium text-slate-800 text-sm truncate" x-text="airport.name"></div>
              <div class="text-xs text-slate-500"
                   x-text="airport.city ? airport.city + ', ' + airport.country : airport.country">
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Destination -->
    <div class="relative">
      <label class="block text-xs font-medium mb-1 text-slate-700">
        Destination Airport Code
      </label>
      <input
        type="text"
        x-model="destinationQuery"
        @input.debounce.300ms="filterAirports('destination')"
        @focus="filterAirports('destination')"
        placeholder="e.g. CEB"
        autocomplete="off"
        class="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
      />
      <input type="hidden" name="destination" :value="destination" />

      <div
        x-show="destinationMatches.length > 0"
        x-transition
        class="absolute z-50 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-xl max-h-60 overflow-y-auto"
      >
        <template x-for="airport in destinationMatches" :key="airport.code">
          <div
            @click="selectAirport('destination', airport)"
            class="px-4 py-2.5 hover:bg-sky-50 cursor-pointer transition flex items-center gap-2 border-b border-slate-100 last:border-b-0"
            :title="airport.name"
          >
            <!-- Airport Code (plain bold) -->
            <div class="flex-shrink-0 w-10">
              <span class="font-bold text-sky-700 text-sm tracking-wide" x-text="airport.code"></span>
            </div>

            <!-- Airport Details -->
            <div class="flex-1 min-w-0">
              <div class="font-medium text-slate-800 text-sm truncate" x-text="airport.name"></div>
              <div class="text-xs text-slate-500"
                   x-text="airport.city ? airport.city + ', ' + airport.country : airport.country">
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>

  <!-- 💰 Price + 📆 Duration -->
  <div class="grid grid-cols-12 gap-4">
    <!-- Price -->
    <div class="col-span-12 md:col-span-6">
      <label class="block text-xs font-medium mb-1 text-slate-700">
        Package Price (PHP) <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-medium">₱</span>
        <input
          type="text"
          x-model="formattedPrice"
          @input="formatPriceInput($event)"
          placeholder="25,000.00"
          required
          class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg text-sm shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
        />
      </div>
    </div>

    <!-- Days -->
    <div class="col-span-6 md:col-span-3">
      <label class="block text-xs font-medium mb-1 text-slate-700">
        Days <span class="text-red-500">*</span>
      </label>
      <input
        type="number"
        x-model.number="days"
        min="1"
        required
        class="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
      />
    </div>

    <!-- Nights -->
    <div class="col-span-6 md:col-span-3">
      <label class="block text-xs font-medium mb-1 text-slate-700">
        Nights
      </label>
      <input
        type="number"
        x-model.number="nights"
        min="0"
        class="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
      />
    </div>
  </div>

</div>