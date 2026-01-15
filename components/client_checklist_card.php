<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Early exit for non-clients - prevents any output
if (!isset($_SESSION['client_id'])) {
    return;
}

$client_id = (int) $_SESSION['client_id'];

// Only output markup if we have a valid client
?>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('checklistCard', () => ({
    clientId: <?= json_encode($client_id) ?>,
    templateId: 1,
    checklist: [],
    currentTask: null,

    async loadChecklist() {
      try {
        await fetch(`../actions/evaluate_checklist.php?client_id=${this.clientId}&template_id=${this.templateId}`);
        const res = await fetch(`../actions/get_checklist.php?client_id=${this.clientId}&template_id=${this.templateId}`);
        const json = await res.json();
        this.checklist = json;
        this.currentTask = this.checklist.find(item => !item.is_completed) || null;
      } catch (err) {
        console.error("Checklist load failed:", err);
      }
    },

    completedCount() {
  return this.checklist.filter(item => item.is_completed).length;
},
progressPercent() {
  return this.checklist.length > 0
    ? Math.round((this.completedCount() / this.checklist.length) * 100)
    : 0;
}

  }));
});
</script>

<div 
  x-data="checklistCard"
  x-init="loadChecklist()"
  class="bg-white border w-auto border-slate-200 rounded-lg shadow-sm p-4 overflow-hidden relative"
>
  <!-- 🧭 Checklist Header -->
  <h3 class="text-base font-semibold text-slate-800 mb-3">Your Progress</h3>
  <div class="bg-slate-100 rounded-full h-2 mb-2">
  <div class="bg-emerald-400 h-2 rounded-full transition-all duration-300"
       :style="`width: ${progressPercent()}%`"></div>
</div>

  <!-- 🧩 Single Task Card -->
  <template x-if="currentTask">
    <div 
      x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="translate-x-full opacity-0"
      x-transition:enter-end="translate-x-0 opacity-100"
      x-transition:leave="transition ease-in duration-200"
      x-transition:leave-start="translate-x-0 opacity-100"
      x-transition:leave-end="-translate-x-full opacity-0"
      class="flex items-center gap-4"
    >
      <!-- ✅ Icon -->
      <div class="w-10 h-10 flex items-center justify-center rounded-full border-2"
           :class="currentTask.is_completed ? 'border-emerald-400 bg-emerald-50 text-emerald-600' : 'border-sky-300 bg-sky-50 text-sky-500'">
        <template x-if="currentTask.is_completed">
          <span class="text-xl">✅</span>
        </template>
        <template x-if="!currentTask.is_completed">
          <span class="text-xl">📋</span>
        </template>
      </div>

      <!-- 📌 Task Info -->
      <div class="flex-1">
        <h4 class="text-sm font-medium text-slate-800" x-text="currentTask.label"></h4>
        <p class="text-xs text-slate-500" x-text="currentTask.description"></p>
        <template x-if="currentTask.completed_at">
          <p class="text-xs text-emerald-600 mt-1">Completed at <span x-text="currentTask.completed_at"></span></p>
        </template>
      </div>
    </div>
  </template>

  <!-- 💤 Fallback -->
  <template x-if="!currentTask">
    <p class="text-sm text-gray-500 italic">All tasks completed. You're awesome!</p>
  </template>
</div>