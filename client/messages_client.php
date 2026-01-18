<?php
declare(strict_types=1);

// Set session timeout (24 hours)
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Auth check
if (empty($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = (int)$_SESSION['client_id'];

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$projectRoot = dirname(__DIR__);

// Includes
require_once $projectRoot . '/actions/db.php';
require_once $projectRoot . '/includes/helpers.php';
include_once $projectRoot . '/includes/header.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    die('Database connection failed.');
}

// Get client's assigned admin (travel agent)
$assignedAdminId = null;
$assignedAdminName = 'Travel Agent';
$assignedAdminPhoto = null;

$stmt = $conn->prepare("
    SELECT c.assigned_admin_id, a.first_name, a.last_name, a.admin_photo
    FROM clients c
    LEFT JOIN admin_accounts a ON c.assigned_admin_id = a.id
    WHERE c.id = ?
");
$stmt->bind_param('i', $client_id);
$stmt->execute();
$stmt->bind_result($assignedAdminId, $adminFirstName, $adminLastName, $adminPhoto);
$stmt->fetch();
$stmt->close();

if ($assignedAdminId) {
    $assignedAdminName = trim(($adminFirstName ?? '') . ' ' . ($adminLastName ?? '')) ?: 'Travel Agent';
    $assignedAdminPhoto = $adminPhoto;
} else {
    // If no assigned admin, find the first non-superadmin admin
    $stmt = $conn->prepare("SELECT id, first_name, last_name, admin_photo FROM admin_accounts WHERE id != 1 ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $stmt->bind_result($assignedAdminId, $adminFirstName, $adminLastName, $adminPhoto);
    $stmt->fetch();
    $stmt->close();
    
    if ($assignedAdminId) {
        $assignedAdminName = trim(($adminFirstName ?? '') . ' ' . ($adminLastName ?? '')) ?: 'Travel Agent';
        $assignedAdminPhoto = $adminPhoto;
    }
}

// Get or create thread
$threadId = null;
if ($assignedAdminId) {
    $stmt = $conn->prepare("
        SELECT id FROM threads 
        WHERE (user_id = ? AND user_type = 'client' AND recipient_id = ? AND recipient_type = 'admin')
           OR (user_id = ? AND user_type = 'admin' AND recipient_id = ? AND recipient_type = 'client')
        LIMIT 1
    ");
    $stmt->bind_param('iiii', $client_id, $assignedAdminId, $assignedAdminId, $client_id);
    $stmt->execute();
    $stmt->bind_result($threadId);
    $stmt->fetch();
    $stmt->close();
}

// Check for unread notifications
$hasUnreadNotifications = false;
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM notifications
    WHERE status = 'unread'
      AND dismissed = 0
      AND recipient_type = 'client'
      AND recipient_id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $stmt->bind_result($unreadCount);
    $stmt->fetch();
    $stmt->close();
    $hasUnreadNotifications = ($unreadCount > 0);
}

$alpineData = [
    'isClient'           => true,
    'isAdmin'            => false,
    'userId'             => $client_id,
    'userType'           => 'client',
    'recipientType'      => 'admin',
    'initialRecipientId' => $assignedAdminId,
    'initialThreadId'    => $threadId,
    'clients'            => [],
    'admins'             => $assignedAdminId ? [[
        'id' => $assignedAdminId,
        'first_name' => $adminFirstName ?? '',
        'last_name' => $adminLastName ?? '',
        'admin_photo' => $adminPhoto
    ]] : []
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Travel Agent</title>
    <?php include __DIR__ . '/../components/favicon_links.php'; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans text-gray-800 bg-gray-100 h-screen flex flex-col overflow-hidden" x-data="{ sidebarOpen: false }">

    <button @click="sidebarOpen = !sidebarOpen"
            class="fixed top-4 left-4 z-50 p-3 bg-sky-600 text-white rounded-full shadow-lg md:hidden">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>

    <?php include $projectRoot . '/components/sidebar.php'; ?>
    <?php include $projectRoot . '/components/right-panel.php'; ?>

    <main class="flex-1 flex flex-col md:ml-64 md:mr-80 pt-16 md:pt-6 md:px-6 md:pb-6 overflow-hidden"
          x-data="messageApp()"
          x-init="
              recipientId = <?= json_encode($assignedAdminId) ?>;
              threadId = <?= json_encode($threadId) ?>;
              messages = [];
              seenMessageIds = new Set();
              lastFetched = null;
              $nextTick(() => {
                  if (recipientId) {
                      debounceFetchInitialMessages();
                  }
              });
          ">

        <!-- Single Chat Interface -->
        <div class="flex-1 flex flex-col max-w-full mx-auto w-full overflow-hidden md:rounded-2xl md:shadow-lg bg-white">
            <section class="flex-1 flex flex-col bg-sky-50">
                <?php include $projectRoot . '/components/chat_header.php'; ?>

                <div id="messageContainer"
                     class="flex-1 overflow-y-auto p-4 space-y-6 bg-gradient-to-b from-sky-50 to-white"
                     x-show="(messages && messages.length > 0) || !isLoading"
                     x-ref="messageContainer">
                    <template x-if="messages.length === 0">
                        <div class="text-center text-gray-500 py-12 italic">
                            <svg class="w-16 h-16 mx-auto mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.97-4.03 9-9 9-1.48 0-2.9-.36-4.13-1l-4.87 1 1-4.87C2.36 14.9 2 13.48 2 12c0-4.97 4.03-9 9-9s9 4.03 9 9z"/>
                            </svg>
                            <p class="text-lg">No messages yet.</p>
                            <p class="text-sm mt-2">Start a conversation with your travel agent!</p>
                        </div>
                    </template>
                    <template x-for="msg in messages" :key="msg.id">
                        <div class="flex items-end gap-3 max-w-lg"
                             :class="msg.sender_type === 'client' ? 'ml-auto flex-row-reverse' : 'mr-auto flex-row'">
                            <img :src="msg.sender_photo || '../images/default_client_profile.png'"
                                 alt="Avatar"
                                 class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                            <div :class="msg.sender_type === 'client'
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
                    <svg class="animate-spin h-8 w-8 text-sky-600 mr-3" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
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
        </div>
    </main>

    <script>
        window.initialData = <?= json_encode($alpineData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>
    <script src="../includes/messages_poller.js"></script>
    <script src="../assets/js/messages.js"></script>
</body>
</html>

<?php
if (isset($conn)) $conn->close();
?>