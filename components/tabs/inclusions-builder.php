<!-- 🎒 Travel Inclusions Tab -->
<div x-show="tab === 'inclusions'" x-cloak class="p-4 space-y-4 max-h-[500px] overflow-y-auto text-sm">

  <!-- 🧳 Inclusion Cards -->
  <template x-for="(item, index) in inclusions" :key="index">
    <div x-data="{ open: false }" class="border rounded-lg shadow-sm bg-slate-50">

      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 cursor-pointer" @click="open = !open">
        <h4 class="text-sm font-medium text-slate-700 truncate">
          <span x-text="item.title || 'Untitled Inclusion'"></span>
        </h4>
        <span class="text-sky-600 text-xs font-semibold" x-text="open ? 'Hide' : 'Edit'"></span>
      </div>

      <!-- Body -->
      <div x-show="open" x-transition class="px-4 pb-4 space-y-3">

        <!-- Icon -->
        <label class="block">
          <span class="text-xs font-medium text-slate-600">Icon</span>
          <input type="text" x-model="item.icon"
                 maxlength="2"
                 placeholder="e.g. ✈️"
                 class="w-full border px-3 py-2 rounded text-sm bg-white"
                 aria-label="Inclusion icon" />
        </label>

        <!-- Title -->
        <label class="block">
          <span class="text-xs font-medium text-slate-600">Title</span>
          <input type="text" x-model="item.title"
                 placeholder="e.g. Airport Transfer"
                 class="w-full border px-3 py-2 rounded text-sm bg-white"
                 aria-label="Inclusion title" />
        </label>

        <!-- Description -->
        <label class="block">
          <span class="text-xs font-medium text-slate-600">Description</span>
          <input type="text" x-model="item.desc"
                 placeholder="Add description"
                 class="w-full border px-3 py-2 rounded text-sm bg-white"
                 aria-label="Inclusion description" />
        </label>

        <!-- Remove Button -->
        <div class="flex justify-end">
          <button type="button" @click="remove(index)"
                  class="text-red-500 text-xs font-semibold hover:underline"
                  aria-label="Remove inclusion">
            Remove Inclusion
          </button>
        </div>
      </div>
    </div>
  </template>

  <!-- ➕ Add Inclusion -->
  <button type="button" @click="add"
          :disabled="inclusions.length >= max"
          class="text-sky-600 text-sm hover:underline disabled:opacity-50 disabled:pointer-events-none">
    + Add Inclusion
  </button>

  <!-- 🚫 Max Limit Message -->
  <template x-if="inclusions.length >= max">
    <p class="text-xs text-slate-500 mt-1">Maximum of 6 inclusions reached</p>
  </template>

  <!-- 📝 Hidden input for saving -->
  <input type="hidden" name="inclusions_json" :value="JSON.stringify(inclusions)">
</div>