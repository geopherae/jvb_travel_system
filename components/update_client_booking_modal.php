<?php
$tooltips = require __DIR__ . '/../includes/tooltip_map.php';
require_once __DIR__ . '/../includes/tooltip_render.php';

if (!isset($conn) || !isset($client_id)) {
  echo "<p class='text-red-500 text-center text-sm'>Client context missing.</p>";
  return;
}

// Fetch client data
$client_stmt = $conn->prepare("SELECT booking_number, trip_date_start, trip_date_end, booking_date, status, assigned_admin_id FROM clients WHERE id = ?");
$client_stmt->bind_param("i", $client_id);
$client_stmt->execute();
$client_data = $client_stmt->get_result()->fetch_assoc();

// Fetch itinerary JSON
$itinerary_stmt = $conn->prepare("SELECT itinerary_json FROM client_itinerary WHERE client_id = ?");
$itinerary_stmt->bind_param("i", $client_id);
$itinerary_stmt->execute();
$itinerary_data = $itinerary_stmt->get_result()->fetch_assoc();
$itinerary_json = $itinerary_data['itinerary_json'] ?? '';
$itinerary_safe = htmlspecialchars($itinerary_json ?: '[]', ENT_QUOTES);

// Fetch ENUM values
$status_query = $conn->query("SHOW COLUMNS FROM clients LIKE 'status'");
$status_row = $status_query->fetch_assoc();
preg_match("/^enum\((.*)\)$/", $status_row['Type'], $matches);
$enum_values = isset($matches[1]) ? explode(",", str_replace("'", "", $matches[1])) : [];

// Fetch agents
$agents = $conn->query("SELECT id, admin_photo, first_name, last_name FROM admin_accounts ORDER BY first_name ASC");
?>

<div x-show="$store.modals.editBooking" x-transition x-cloak
     @keydown.escape.window="$store.modals.editBooking = false"
     class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex justify-center items-center z-50"
     role="dialog" aria-modal="true">

  <div @click.away="$store.modals.editBooking = false"
       class="bg-white max-w-2xl w-full mx-4 rounded-xl shadow-xl p-6 relative space-y-4 overflow-y-auto max-h-[90vh]">

    <!-- ❌ Close Button -->
    <button @click="$store.modals.editBooking = false"
            class="absolute top-4 right-4 text-slate-500 hover:text-red-500 text-xl font-bold"
            aria-label="Close">&times;</button>

    <!-- 📝 Header -->
    <h2 class="text-lg font-semibold text-slate-800 mb-1">Edit Booking Details</h2>
    <p class="text-sm text-slate-600 mb-2">
      Update your client’s booking details — like dates, status, or their assigned agent.
      Switch to the Itinerary tab if you need to adjust their daily schedule.
      Remember to check your edits before saving!
    </p>

    <!-- 🧠 Alpine Logic -->
    <div x-data="{
      tab: 'booking',
      invalidJson: false,
      itinerary: <?= $itinerary_safe ?>,
      originalItinerary: JSON.parse('<?= $itinerary_safe ?>'),

      addDay() {
        if (this.itinerary.length >= 7) return;
        this.itinerary.push({ day_number: this.itinerary.length + 1, day_title: '', activities: [] });
      },

      removeDay(index) {
        this.itinerary.splice(index, 1);
        this.itinerary.forEach((day, i) => day.day_number = i + 1);
      },

      addActivity(dayIndex) {
        this.itinerary[dayIndex].activities.push({ hasTime: false, time: '', title: '' });
      },

      removeActivity(dayIndex, activityIndex) {
        this.itinerary[dayIndex].activities.splice(activityIndex, 1);
      },

      resetItinerary() {
        if (confirm('Are you sure you want to reset the itinerary to its original state? This will undo all unsaved changes.')) {
          this.itinerary = JSON.parse(JSON.stringify(this.originalItinerary));
        }
      },

      async saveBooking() {
        const form = this.$refs.bookingForm;
        if (!form || this.invalidJson) return;

        try {
          const payload = new URLSearchParams(new FormData(form)).toString();
          const response = await fetch(form.action, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'Accept': 'application/json'
            },
            body: payload
          });

          const data = await response.json();

          if (response.ok && data.status === 'success') {
            $store.modals.editBooking = false;
            setTimeout(() => window.location.reload(), 2300);
          } else {
            console.error('Booking update failed:', data.message || 'Unknown error');
          }

        } catch (error) {
          console.error('Network error during booking update.', error);
        }
      }
    }">

      <!-- 🧭 Tabs -->
      <div class="border-b border-slate-200 mb-4">
        <nav class="flex space-x-4 text-sm font-medium">
          <button type="button" @click="tab = 'booking'"
                  :class="tab === 'booking' ? 'border-sky-500 text-sky-600' : 'text-slate-500'"
                  class="py-2 border-b-2 transition">
            Booking Info
          </button>
          <button type="button" @click="tab = 'itinerary'"
                  :class="tab === 'itinerary' ? 'border-sky-500 text-sky-600' : 'text-slate-500'"
                  class="py-2 border-b-2 transition">
            Itinerary
          </button>
        </nav>
      </div>

      <!-- 📝 Form -->
      <form x-ref="bookingForm" method="POST"
            action="../actions/update_booking.php"
            @submit.prevent="saveBooking">

        <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <input type="hidden" name="updated_at" value="<?= date('Y-m-d H:i:s') ?>">

        <!-- 📋 Booking Tab -->
        <div x-show="tab === 'booking'">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Booking Number</label>
              <input type="text" name="booking_number" value="<?= htmlspecialchars($client_data['booking_number']) ?>"
                     class="w-full border rounded px-3 py-1.5 text-sm" required>
            </div>

            <div>
              <div class="relative">
                <div class="flex items-center gap-2 mb-1">
                  <label class="block text-sm font-medium text-slate-700">Status</label>
                  <?= renderTooltipIcon('status', $tooltips) ?>
                </div>
                <select name="status_display" disabled class="w-full border rounded px-3 py-1.5 text-sm bg-gray-100 cursor-not-allowed">
                  <option selected><?= $client_data['status'] ?></option>
                </select>
                <input type="hidden" name="status" value="<?= $client_data['status'] ?>">
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Departure Date</label>
              <input type="date" name="trip_date_start" value="<?= $client_data['trip_date_start'] ?>"
                     class="w-full border rounded px-3 py-1.5 text-sm">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Return Date</label>
              <input type="date" name="trip_date_end" value="<?= $client_data['trip_date_end'] ?>"
                     class="w-full border rounded px-3 py-1.5 text-sm">
            </div>

            <div class="col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-1">Booking Date</label>
              <input type="date" name="booking_date" value="<?= $client_data['booking_date'] ?>"
                     class="w-full border rounded px-3 py-1.5 text-sm">
            </div>
          </div>

          <!-- 👤 Assigned Agent -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mt-4 mb-1">Assigned Agent</label>
            <?php
              $hasEligibleAgents = false;
              $agentOptions = '';

              while ($agent = $agents->fetch_assoc()) {
                if ($agent['id'] != 1) {
                  $hasEligibleAgents = true;
                  $selected = $agent['id'] == $client_data['assigned_admin_id'] ? 'selected' : '';
                  $fullName = htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']);
                  $agentOptions .= "<option value=\"{$agent['id']}\" $selected>$fullName</option>";
                }
              }
            ?>

            <?php if ($hasEligibleAgents): ?>
              <select name="assigned_admin_id" class="w-full border rounded px-3 py-1.5 text-sm">
                <?= $agentOptions ?>
              </select>
            <?php else: ?>
              <div class="text-sm italic text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">
                No eligible agents available for assignment. Please check the admin list or contact support.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- 🗺️ Itinerary Tab -->
        <div x-show="tab === 'itinerary'" x-cloak class="p-4 space-y-4 max-h-[500px] overflow-y-auto text-sm">

          <!-- 🗓️ Day Cards -->
          <div>
            <template x-for="(day, dayIndex) in itinerary" :key="dayIndex">
              <div x-data="{ open: false }" class="border rounded-lg shadow-sm bg-slate-50 px-4 pt-4 pb-3 mb-2 last:mb-0">

                <!-- Day Header -->
                <div class="flex items-center justify-between gap-3">
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

                  <!-- Activities with drag and drop -->
                  <div class="space-y-2"
                       x-init="$nextTick(() => {
                         if (window.Sortable) {
                           Sortable.create($el, {
                             animation: 150,
                             handle: '.activity-drag-handle',
                             onStart: () => $el.classList.add('opacity-75'),
                             onEnd: e => {
                               $el.classList.remove('opacity-75');
                               
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

        <!-- ✅ Submit Buttons -->
        <div class="flex justify-end gap-4 mt-6">
          <button type="button"
                  @click="$store.modals.editBooking = false"
                  class="text-sm text-gray-500 hover:underline">
            Cancel
          </button>

          <button type="submit"
                  class="px-4 py-2 text-sm font-medium text-white bg-sky-600 rounded hover:bg-sky-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>