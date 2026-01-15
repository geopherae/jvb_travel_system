<!-- ✅ ALPINE SCOPE -->
x-data: {
  modal: {
    visible: false,
    title: 'Confirm Delete',
    message: 'Are you sure you want to delete this item?',
    confirmLabel: 'Delete',
    onConfirm: () => {
      // your logic here
      console.log('Confirmed!');
      this.modal.visible = false;
    }
  }
} 

<!-- ✅ Reusable Confirmation Modal -->
<div x-show="modal.visible" x-transition x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30 backdrop-blur-sm">
  <div class="bg-white p-6 rounded-md shadow-xl w-full max-w-sm mx-auto space-y-4 text-center">

    <!-- 🔠 Modal Header -->
    <h3 class="text-lg font-semibold text-gray-800" x-text="modal.title">Confirm Action</h3>

    <!-- 📝 Modal Message -->
    <p class="text-sm text-gray-600" x-text="modal.message">Are you sure you want to proceed?</p>

    <!-- 🎯 Action Buttons -->
    <div class="flex justify-center gap-3 pt-2">
      <button @click="modal.visible = false"
              class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 transition text-sm">
        Cancel
      </button>
      <button @click="modal.onConfirm"
              class="bg-sky-600 text-white px-4 py-2 rounded hover:bg-sky-700 transition text-sm font-semibold"
              x-text="modal.confirmLabel || 'Confirm'">
        Confirm
      </button>
    </div>
  </div>
</div>


<!--