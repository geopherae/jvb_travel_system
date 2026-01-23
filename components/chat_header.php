<?php
// Assumes included within <div x-data="messageApp"> in messages.php or messages_client.php
?>

<div class="flex items-center justify-between px-4 py-3 bg-white border-b border-gray-200"
     x-show="recipientId"
     x-data="{ selectedUser: null }"
     x-init="selectedUser = getRecipientDetails(recipientId, recipientType)"
     x-effect="selectedUser = getRecipientDetails(recipientId, recipientType)">

  <!-- Left: Avatar, Name, and Status -->
  <div class="flex items-center space-x-3">
    <!-- Back button for mobile -->
    <button class="md:hidden text-gray-600 hover:text-gray-800"
            @click="recipientId = null; selectedUser = null; sidebarOpen = true">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
    </button>

    <!-- Avatar with online indicator -->
    <div class="relative">
      <img :src="selectedUser?.avatar || '../images/default_client_profile.png'"
           alt="Recipient Avatar"
           class="w-10 h-10 rounded-full object-cover border-2 border-gray-200">
      <template x-if="recipientType === 'client' && selectedUser?.status?.toLowerCase() === 'active'">
        <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></span>
      </template>
    </div>

    <!-- Name and Status -->
    <div>
      <p class="text-base font-semibold text-gray-900"
         x-text="isClient ? (selectedUser?.name || 'Travel Agent') : (recipientType === 'admin' ? (selectedUser?.name + ' (Agent)') : (selectedUser?.name || 'Select a recipient'))"></p>
      <p class="text-xs text-gray-500"
         x-show="recipientType === 'client' && selectedUser?.status"
         x-text="selectedUser?.status"></p>
      <p class="text-xs text-amber-600"
         x-show="recipientType === 'admin' && selectedUser?.status"
         x-text="`Agent • ` + (selectedUser?.status || 'Online')"></p>
    </div>
  </div>

  <!-- Right: Actions -->
  <div class="flex items-center space-x-2">
    <a :href="recipientType === 'client' ? `view_client.php?client_id=${recipientId}` : '#'"
       class="p-2 text-sky-600 hover:text-sky-800 hover:bg-sky-50 rounded-full transition"
       title="View Client Profile"
       x-show="recipientType === 'client'">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
      </svg>
    </a>
  </div>
</div>