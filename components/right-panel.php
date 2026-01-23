<?php
include_once __DIR__ . '/../includes/profile-info.php';
include_once __DIR__ . '/../includes/unread-notification-check.php';
?>

<aside class="hidden lg:flex flex-col w-80 h-screen fixed top-0 right-0 bg-white border-l border-gray-200 z-1">

  <!-- 🔝 Profile Header -->
  <div class="flex items-center justify-between px-6 py-4 border-b">
    <!-- 👤 Profile Info -->
    <div class="relative">
      <button id="profile-dropdown-toggle"
              class="flex items-center gap-3 cursor-pointer hover:bg-gray-100 active:bg-gray-200 transition duration-200 py-1 px-2 rounded"
              type="button">
        <img src="<?= htmlspecialchars($profilePhoto) ?>"
             alt="Profile of <?= htmlspecialchars($profileName) ?>"
             class="w-8 h-8 rounded-full object-cover"
             loading="lazy"
             onerror="handleImageError(this)">
        <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($profileName); ?></span>
      </button>
      
      <!-- Profile Dropdown -->
      <?php include 'profile-dropdown.php'; ?>
    </div>

    <!-- 🔔 Notification Bell -->
    <div id="notification-bell" class="relative">
      <button id="toggle-notification-overlay"
              title="Toggle Notifications"
              class="text-gray-500 hover:text-sky-600 transition relative p-2 rounded-lg hover:bg-sky-50">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 2a6 6 0 00-6 6v2.586l-.707.707A1 1 0 004 13h12a1 1 0 00.707-1.707L16 10.586V8a6 6 0 00-6-6zm0 16a2 2 0 002-2H8a2 2 0 002 2z"/>
        </svg>

        <!-- 🔴 Unread Dot -->
        <span class="unread-indicator absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full ring-2 ring-white shadow-md"
              style="display: none;"></span>
      </button>
    </div>
  </div>

  <!-- 📅 Calendar & Client Tools -->
  <div class="flex flex-col space-y-4 px-6 py-4 overflow-y-auto">

    <!-- 🗓️ Calendar Widget -->
    <div><?php include '../components/calendar-widget.php'; ?></div>

    <!-- 📊 Admin Metrics -->
    <?php if (isset($_SESSION['admin'])) include '../admin/admin_right_panel_metrics.php'; ?>
            

  </div>
</aside>

<!-- 🔽 Notification Overlay -->
<div id="notification-overlay"
     class="hidden absolute top-16 right-6 w-96 min-h-[8rem] max-h-[calc(4*theme(spacing.20))] overflow-y-auto bg-white rounded-2xl shadow-md z-50">
  <div id="notification-overlay-list" class="p-2 space-y-3">
    <p class="text-sm text-gray-500">Loading notifications...</p>
  </div>
</div>

<!-- 📦 Scripts -->
<script src="../includes/utils.js"></script>
<script src="../includes/notifications.js"></script>
