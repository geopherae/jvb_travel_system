<!-- 🪟 Modal: Edit Checklist -->
<div x-show="$root.showChecklistModal"
     x-transition
     class="fixed inset-0 bg-black/30 flex items-center justify-center z-50">
  <div class="bg-white w-full max-w-2xl rounded-lg shadow-lg p-6 space-y-6"
       @click.away="$root.showChecklistModal = false"
       @keydown.escape.window="$root.showChecklistModal = false">

    <!-- 🔷 Header -->
    <div class="flex justify-between items-center border-b pb-2">
      <h2 class="text-xl font-semibold text-sky-600">Edit Checklist Template</h2>
      <button @click="$root.showChecklistModal = false"
              class="text-gray-400 hover:text-gray-700 text-lg transition">✖</button>
    </div>

    <!-- 📋 Form -->
    <form method="POST"
          action="../actions/update_checklist_progress.php"
          x-data="{ tasks: <?= htmlspecialchars(json_encode($checklistData)) ?> }">

      <div class="space-y-4">
        <template x-for="(item, index) in tasks" :key="index">
          <div class="flex items-center gap-3">
            <input type="text"
                   :name="'tasks[' + index + '][label]'"
                   x-model="item.label"
                   class="flex-1 px-3 py-2 border rounded text-sm"
                   placeholder="Checklist item">

            <label class="flex items-center gap-1 text-sm text-slate-600">
              <input type="checkbox"
                     :name="'tasks[' + index + '][is_checked]'"
                     x-model="item.is_checked">
              <span>Done</span>
            </label>

            <button type="button"
                    @click="tasks.splice(index, 1)"
                    class="text-red-500 text-xs hover:underline">🗑️ Remove</button>
          </div>
        </template>
      </div>

      <!-- ➕ Add Button -->
      <button type="button"
              @click="tasks.push({ label: '', is_checked: false })"
              class="mt-4 text-sky-600 text-sm hover:underline">➕ Add task</button>

      <!-- ✅ Hidden Field -->
      <input type="hidden" :value="$root.templateId" name="template_id">

      <!-- 🚀 Submit -->
      <button type="submit"
              class="mt-6 w-full py-2 bg-sky-600 text-white rounded hover:bg-sky-700 transition">
        Save Checklist
      </button>
    </form>
  </div>
</div>

