<?php
declare(strict_types=1);

ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Auth
if (empty($_SESSION['admin']['id'])) {
    header('Location: admin_login.php');
    exit;
}

$adminId = (int)$_SESSION['admin']['id'];

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/actions/db.php';
require_once $projectRoot . '/includes/helpers.php';
require_once $projectRoot . '/includes/clients.php';
include_once $projectRoot . '/includes/header.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    die('Database connection failed.');
}

// Load clients with assigned_admin_id for prioritization
$clients = [];
$sql = "SELECT id, full_name, client_profile_photo, status, assigned_admin_id FROM clients ORDER BY full_name ASC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $clients[] = [
            'id' => (int)$row['id'],
            'full_name' => $row['full_name'] ?: 'Unknown Client',
            'client_profile_photo' => $row['client_profile_photo'] ?? null,
            'status' => $row['status'] ?? '',
            'assigned_admin_id' => $row['assigned_admin_id'] ? (int)$row['assigned_admin_id'] : null
        ];
    }
    $result->free();
}

// Load other admins (excluding current admin)
$admins = [];
$sql = "SELECT id, first_name, last_name, admin_photo, role FROM admin_accounts WHERE id != ? AND is_active = 1 ORDER BY first_name ASC";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $admins[] = [
            'id' => (int)$row['id'],
            'first_name' => $row['first_name'] ?: 'Admin',
            'last_name' => $row['last_name'] ?: '',
            'admin_photo' => $row['admin_photo'] ?? null,
            'role' => $row['role'] ?? 'admin',
            'full_name' => trim(($row['first_name'] ?: '') . ' ' . ($row['last_name'] ?: ''))
        ];
    }
    $result->free();
    $stmt->close();
}

// Default to first client
$selectedRecipientId = $clients[0]['id'] ?? null;
$selectedThreadId = null;

if ($selectedRecipientId) {
    $stmt = $conn->prepare("
        SELECT id FROM threads 
        WHERE (user_id = ? AND user_type = 'admin' AND recipient_id = ? AND recipient_type = 'client')
           OR (user_id = ? AND user_type = 'client' AND recipient_id = ? AND recipient_type = 'admin')
        LIMIT 1
    ");
    $stmt->bind_param('iiii', $adminId, $selectedRecipientId, $selectedRecipientId, $adminId);
    $stmt->execute();
    $stmt->bind_result($selectedThreadId);
    $stmt->fetch();
    $stmt->close();
}

// Check for unread notifications
$hasUnreadNotifications = false;
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM notifications
    WHERE status = 'unread'
      AND dismissed = 0
      AND recipient_type = 'admin'
      AND recipient_id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $stmt->bind_result($unreadCount);
    $stmt->fetch();
    $stmt->close();
    $hasUnreadNotifications = ($unreadCount > 0);
}

$alpineData = [
    'isAdmin' => true,
    'userId' => $adminId,
    'userType' => 'admin',
    'recipientType' => 'client',
    'initialRecipientId' => $selectedRecipientId,
    'initialThreadId' => $selectedThreadId,
    'clients' => $clients,
    'admins' => $admins
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Messages</title>
    <?php include __DIR__ . '/../components/favicon_links.php'; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans text-gray-800 bg-gray-100 h-screen flex flex-col overflow-hidden">

    <button @click="sidebarOpen = !sidebarOpen"
            class="fixed top-4 left-4 z-50 p-3 bg-sky-600 text-white rounded-full shadow-lg md:hidden">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>

    <?php include $projectRoot . '/components/admin_sidebar.php'; ?>
    <?php include $projectRoot . '/components/right-panel.php'; ?>

    <main class="flex-1 flex flex-col md:ml-64 md:mr-80 pt-16 md:pt-6 md:px-6 md:pb-6 overflow-hidden"
          x-data="messageApp()"
          x-init="
              sidebarOpen = false;
              recipientId = <?= json_encode($selectedRecipientId) ?>;
              threadId = <?= json_encode($selectedThreadId) ?>;
              messages = [];
              seenMessageIds = new Set();
              lastFetched = null;
              $nextTick(() => {
                  if (recipientId) {
                      debounceFetchInitialMessages();
                  }
              });
          ">

        <div class="flex-1 flex flex-col md:flex-row max-w-full mx-auto w-full overflow-hidden md:rounded-2xl md:shadow-lg bg-white">
            <!-- Client List (Sidebar on desktop, hidden/show on mobile) -->
            <aside class="w-full md:w-96 bg-white border-r border-gray-200 flex flex-col hidden md:flex"
                   :class="{'!flex': sidebarOpen, 'hidden': !sidebarOpen}">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800 mb-3">Messages</h2>
                    <!-- Search Input -->
                    <div class="relative">
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Search agents & clients..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                        <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <ul class="divide-y divide-gray-100">
                        <!-- Admins Section -->
                        <template x-if="filteredAdmins.length > 0">
                            <li>
                                <div class="px-4 py-2 bg-amber-50 border-y border-amber-200">
                                    <p class="text-xs text-amber-600 uppercase tracking-wide font-semibold">JV-B Admins</p>
                                </div>
                            </li>
                        </template>
                        
                        <template x-for="admin in filteredAdmins" :key="`admin_${admin.id}`">
                            <li>
                                <button @click="
                                    recipientId = admin.id;
                                    recipientType = 'admin';
                                    sidebarOpen = false;
                                    $nextTick(() => {
                                        threadId = null;
                                        messages = [];
                                        seenMessageIds.clear();
                                        lastFetched = null;
                                        debounceFetchInitialMessages();
                                    });
                                "
                                        :class="recipientId === admin.id && recipientType === 'admin' ? 'bg-amber-50 border-r-4 border-amber-500' : 'hover:bg-gray-50'"
                                        class="w-full text-left px-4 py-4 transition-colors flex items-center gap-4">
                                    <img :src="getRecipientDetails(admin.id, 'admin')?.avatar || '../images/default_client_profile.png'"
                                         alt="Avatar"
                                         class="w-12 h-12 rounded-full object-cover flex-shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="font-medium text-gray-900 truncate" x-text="admin.full_name"></p>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800"
                                                  x-text="admin.role"></span>
                                        </div>
                                        <p class="text-sm text-gray-500 truncate" x-text="getLastMessagePreview(admin.id, 'admin') || 'No messages yet'"></p>
                                    </div>
                                    <p class="text-xs text-gray-400 whitespace-nowrap" x-text="getLastMessageTime(admin.id)"></p>
                                </button>
                            </li>
                        </template>

                        <!-- Clients Section -->
                        <template x-if="filteredClients.length > 0">
                            <li>
                                <div class="px-4 py-2 bg-sky-50 border-y border-sky-200">
                                    <p class="text-xs text-sky-600 uppercase tracking-wide font-semibold">Clients</p>
                                </div>
                            </li>
                        </template>
                        
                        <template x-for="(client, index) in filteredClients" :key="`client_${client.id}`">
                            <li>
                                <!-- Separator after assigned clients -->
                                <div x-show="index === myAssignedClientsCount && myAssignedClientsCount > 0" 
                                     class="px-4 py-2 bg-gray-50 border-y border-gray-200">
                                    <p class="text-xs text-gray-400 uppercase tracking-wide">Other Clients</p>
                                </div>
                                
                                <button @click="
                                    recipientId = client.id;
                                    recipientType = 'client';
                                    sidebarOpen = false;
                                    $nextTick(() => {
                                        threadId = null;
                                        messages = [];
                                        seenMessageIds.clear();
                                        lastFetched = null;
                                        debounceFetchInitialMessages();
                                    });
                                "
                                        :class="recipientId === client.id && recipientType === 'client' ? 'bg-sky-50 border-r-4 border-sky-600' : 'hover:bg-gray-50'"
                                        class="w-full text-left px-4 py-4 transition-colors flex items-center gap-4">
                                    <img :src="getRecipientDetails(client.id, 'client')?.avatar || '../images/default_client_profile.png'"
                                         alt="Avatar"
                                         class="w-12 h-12 rounded-full object-cover flex-shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="font-medium text-gray-900 truncate" x-text="client.full_name"></p>
                                            <span x-show="isAssignedToMe(client.id)" 
                                                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-100 text-sky-800">
                                                Assigned
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500 truncate" x-text="getLastMessagePreview(client.id, 'client') || 'No messages yet'"></p>
                                    </div>
                                    <p class="text-xs text-gray-400 whitespace-nowrap" x-text="getLastMessageTime(client.id)"></p>
                                </button>
                            </li>
                        </template>
                        <template x-if="filteredClients.length === 0 && filteredAdmins.length === 0 && searchQuery.trim()">
                            <li class="px-6 py-8 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                No contacts match your search.
                            </li>
                        </template>
                        <template x-if="clients.length === 0 && admins.length === 0">
                            <li class="px-6 py-8 text-center text-gray-500">No contacts found.</li>
                        </template>
                    </ul>
                </div>
            </aside>

            <!-- Chat Area (Full on mobile when no selection, hidden until selection) -->
            <section class="flex-1 flex flex-col bg-sky-50" x-show="recipientId">
                <?php include $projectRoot . '/components/chat_header.php'; ?>

                <div id="messageContainer"
                     class="flex-1 overflow-y-auto p-4 space-y-6 bg-gradient-to-b from-sky-50 to-white"
                     x-show="(messages && messages.length > 0) || !isLoading"
                     x-ref="messageContainer"
                     @scroll="if ($refs.messageContainer.scrollTop < 100) loadMoreMessages()">
                    <template x-if="messages.length === 0">
                        <div class="text-center text-gray-500 py-12 italic">
                            No messages yet. Start the conversation!
                        </div>
                    </template>
                    <template x-for="msg in messages" :key="msg.id">
                        <div class="flex items-end gap-3 max-w-lg"
                             :class="msg.sender_id === userId ? 'ml-auto flex-row-reverse' : 'mr-auto flex-row'">
                            <img :src="msg.sender_photo || '../images/default_client_profile.png'"
                                 alt="Avatar"
                                 class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                            <div :class="msg.sender_id === userId
                                ? 'bg-sky-600 text-white rounded-3xl rounded-br-md'
                                : 'bg-white text-gray-800 rounded-3xl rounded-bl-md shadow-md'"
                                 class="px-4 py-2.5 max-w-full">
                                <p class="text-sm break-words" x-text="msg.message_text"></p>
                                <p class="text-xs mt-1 opacity-70 text-right"
                                   x-text="new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})">
                                </p>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show="isLoading && (!messages || messages.length === 0)" class="flex-1 flex items-center justify-center text-gray-500">
                    Loading messages...
                </div>

                <div class="p-4 bg-white border-t border-gray-200">
                    <form @submit.prevent="sendMessage()" class="flex gap-3">
                        <textarea x-model="newMessage"
                                  @keydown.enter="!$event.ctrlKey && (sendMessage(), $event.preventDefault())"
                                  placeholder="Type your message... (Ctrl+Enter for new line)"
                                  rows="1"
                                  class="flex-1 resize-none rounded-full border border-gray-300 px-5 py-3 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200"
                                  :disabled="isLoading || !recipientId"></textarea>
                        <button type="submit"
                                :disabled="!canSendMessage || isLoading"
                                class="px-6 py-3 bg-sky-600 hover:bg-sky-700 disabled:bg-gray-300 text-white font-medium rounded-full transition">
                            Send
                        </button>
                    </form>
                </div>
            </section>

            <!-- Placeholder when no chat selected (desktop) -->
            <div class="hidden md:flex flex-1 items-center justify-center text-gray-400 bg-sky-50" x-show="!recipientId">
                <div class="text-center">
                    <svg class="w-24 h-24 mx-auto mb-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.97-4.03 9-9 9-1.48 0-2.9-.36-4.13-1l-4.87 1 1-4.87C2.36 14.9 2 13.48 2 12c0-4.97 4.03-9 9-9s9 4.03 9 9z"/>
                    </svg>
                    <p class="text-xl">Select a client to start messaging</p>
                </div>
            </div>
        </div>
    </main>

    <script>
        window.initialData = <?= json_encode($alpineData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>
    <script src="../includes/messages_poller.js?v=1.0.3"></script>
    <script src="../assets/js/messages.js?v=1.0.3"></script>
</body>
</html>

<?php
if (isset($conn)) $conn->close();
?>