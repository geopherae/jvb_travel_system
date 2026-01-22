<div id="profile-dropdown" class="hidden absolute mt-2 w-48 sm:w-42 bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden z-50">
  <div class="py-2">
    <?php if (isset($_SESSION['admin'])): ?>
      <!-- Admin Links -->
      <a href="../admin/admin_settings.php" class="font-semibold block px-4 py-2 text-sm text-sky-700 hover:bg-sky-100">Settings</a>
      <a href="../admin/admin_logout.php" class="font-semibold block px-4 py-2 text-sm text-red-700 hover:bg-red-100">Logout</a>
    <?php else: ?>
      <!-- Client Links -->
      <a href="../client/logout.php" class="font-semibold block px-4 py-2 text-sm text-red-700 hover:bg-red-100">Logout</a>
    <?php endif; ?>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('profile-dropdown-toggle');
    const dropdown = document.getElementById('profile-dropdown');

    // Toggle dropdown on click
    toggle.addEventListener('click', function(event) {
      event.preventDefault();
      dropdown.classList.toggle('hidden');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
      if (!toggle.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.add('hidden');
      }
    });

    // Close dropdown on escape key
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        dropdown.classList.add('hidden');
      }
    });
  });
</script>