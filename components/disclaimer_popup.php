<div x-data="{ showDisclaimer: true }" x-show="showDisclaimer" x-cloak
     class="backdrop-blur-sm fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
  <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 text-center space-y-4">
    <h2 class="text-lg font-semibold text-primary">Heads up! 🚧</h2>
    <p class="text-sm text-gray-700 leading-relaxed">
      This system is currently in development. Some features may be incomplete, and occasional bugs or data loss may occur.
      If you notice anything unusual, feel free to report it to the developers.
    </p>
    <div class="flex justify-center gap-4 pt-2">
    <button @click="showDisclaimer = false" class="close-disclaimer px-4 py-2 bg-sky-700 text-white rounded hover:bg-sky-800 transition">
      I understand
    </button>
      <a href="../logout.php"
         class="px-4 py-2 bg-red-700 text-white rounded hover:bg-red-500 transition">
        Logout
      </a>
    </div>
  </div>
</div>