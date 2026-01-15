<!-- 🗺️ Travel Itinerary Tab -->
<div x-show="tab === 'itinerary'" x-cloak class="p-4 space-y-4 max-h-[500px] overflow-y-auto text-sm">

  <!-- Removed sortable wrapper and day-level Sortable.init -->
  <!-- Days are now in fixed order (no dragging) -->
  <div>

    <!-- 🗓️ Day Cards -->
    <template x-for="(day, dayIndex) in itinerary" :key="dayIndex">
      <div x-data="{ open: false }" class="border rounded-lg shadow-sm bg-slate-50 px-4 pt-4 pb-3 mb-2 last:mb-0">

        <!-- Day Header - Drag handle is now decorative only -->
        <div class="flex items-center justify-between gap-3">
          <!-- Non-draggable handle (visual only) 
          <span class="text-slate-300 text-lg shrink-0 cursor-default">☰</span>-->

          <div class="flex items-center gap-3 w-full">
            <span class="text-xs font-semibold bg-sky-100 text-sky-800 px-2 py-1 rounded-full shrink-0"
                  x-text="'Day ' + (dayIndex + 1)"></span>
            
            <input type="text"
                   x-model="day.day_title"
                   class="bg-white border px-2 py-1 text-sm rounded w-[65%] overflow-hidden text-ellipsis"
                   placeholder="Untitled Day"
                   maxlength="50"
                   aria-label="Day title" />
          </div>

          <button type="button"
                  @click="open = !open"
                  class="text-sky-600 text-xs font-semibold hover:underline focus:outline-none"
                  :aria-expanded="open"
                  :aria-controls="'day-body-' + dayIndex">
            <span x-text="open ? 'Hide' : 'Edit'"></span>
          </button>
        </div>

        <!-- Day Body -->
        <div x-show="open"
             x-transition
             :id="'day-body-' + dayIndex"
             class="mt-4 space-y-4">

          <!-- Activities remain fully draggable -->
          <div class="space-y-2"
               x-init="$nextTick(() => {
                 if (window.Sortable) {
                   Sortable.create($el, {
                     animation: 150,
                     handle: '.activity-drag-handle',
                     onStart: () => $el.classList.add('opacity-75'),
                     onEnd: e => {
                       $el.classList.remove('opacity-75');
                       
                       // Safe reordering with Alpine.raw
                       let acts = Alpine.raw(day.activities);
                       const moved = acts.splice(e.oldIndex, 1)[0];
                       acts.splice(e.newIndex, 0, moved);
                       day.activities = acts;
                     }
                   });
                 }
               })">

            <template x-for="(activity, activityIndex) in day.activities" :key="activityIndex">
              <div class="flex items-center gap-2 text-sm group focus-within:[&>.remove-activity]:opacity-100">
                <span class="activity-drag-handle cursor-grab text-slate-300 hover:text-slate-500 text-lg shrink-0">☰</span>

                <div class="flex items-center gap-1 shrink-0">
                  <input type="checkbox" x-model="activity.hasTime" class="rounded" />
                  <input type="time"
                         x-model="activity.time"
                         x-show="activity.hasTime"
                         class="border px-2 py-1 rounded text-xs w-[80px]"
                         aria-label="Activity time" />
                </div>

                <input type="text"
                       x-model="activity.title"
                       class="border px-2 py-1 rounded text-sm flex-1"
                       placeholder="Activity title"
                       aria-label="Activity title" />

                <button type="button"
                        @click="removeActivity(dayIndex, activityIndex)"
                        class="remove-activity opacity-0 group-hover:opacity-100 transition shrink-0"
                        aria-label="Remove activity"
                        title="Remove this activity">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-500 hover:text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M10 3h4a1 1 0 011 1v1H9V4a1 1 0 011-1z" />
                  </svg>
                </button>
              </div>
            </template>
          </div>

          <button type="button"
                  @click="addActivity(dayIndex)"
                  class="text-sky-600 text-sm font-medium hover:underline">
            + Add Activity
          </button>

          <button type="button"
                  @click="removeDay(dayIndex)"
                  class="text-red-500 text-xs font-semibold hover:underline block mt-3">
            Remove Day
          </button>
        </div>
      </div>
    </template>
  </div>

  <!-- Bottom Controls -->
  <div class="flex justify-between items-start pt-2">
    <div class="flex flex-col">
      <button type="button"
              @click="addDay()"
              :disabled="itinerary.length >= 7"
              :class="itinerary.length >= 7 ? 'opacity-50 cursor-not-allowed' : 'text-sky-600 hover:underline'"
              class="text-sm font-medium">
        + Add Blank Day
      </button>
      <small x-show="itinerary.length >= 7" class="text-xs text-red-500 mt-1">
        Maximum of 7 days reached.
      </small>
    </div>

    <button type="button"
            @click="resetItinerary()"
            class="text-sm text-red-600 hover:underline font-medium">
      ⟳ Reset Itinerary to Original
    </button>
  </div>

  <input type="hidden" name="itinerary_json" :value="JSON.stringify(itinerary)">
</div>