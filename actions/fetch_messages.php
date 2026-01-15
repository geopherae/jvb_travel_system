<?php
session_start();

error_log("CLIENT ID: " . print_r($_POST['client_id'], true));
error_log("CONTENT: " . print_r($_POST['content'], true));
error_log("SESSION: " . print_r($_SESSION, true));

require '../actions/db.php'; // adjust if db.php is elsewhere

// Identify user role from session (fallback to client)
$currentUserRole = $_SESSION['role'] ?? 'client';

// Validate and sanitize client ID
$clientId = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_INT);
if (!$clientId) {
  http_response_code(400);
  echo 'Missing or invalid client ID.';
  exit;
}

// Optional: check permissions (only if needed)
// if ($currentUserRole === 'client' && $_SESSION['client_id'] !== $clientId) {
//   http_response_code(403);
//   echo 'Unauthorized access.';
//   exit;
// }

// Mark unread messages from the opposite party as read
$markStmt = $conn->prepare(
  "UPDATE messages SET read_at = NOW() WHERE client_id = ? AND sender != ? AND read_at IS NULL"
);
$markStmt->bind_param("is", $clientId, $currentUserRole);
$markStmt->execute();
if (!$success) {
  error_log("MySQL error: " . $stmt->error);
}
$markStmt->close();

// Fetch all messages for the client
$messagesStmt = $conn->prepare(
  "SELECT id, sender, content, created_at, read_at FROM messages WHERE client_id = ? ORDER BY created_at ASC"
);
$messagesStmt->bind_param("i", $clientId);
$messagesStmt->execute();
if (!$success) {
  error_log("MySQL error: " . $stmt->error);
}
$messages = $messagesStmt->get_result();

while ($msg = $messages->fetch_assoc()):
  $isOwn = $msg['sender'] === $currentUserRole;
  $isRead = !is_null($msg['read_at']);
?>
  <div class="mb-2 <?= $isOwn ? 'text-right' : 'text-left' ?>">
    <div class="inline-block max-w-[70%] px-3 py-2 rounded-lg
                <?= $isOwn ? 'bg-blue-50 text-blue-900' : 'bg-gray-100 text-gray-800' ?>">
      <?= nl2br(htmlspecialchars($msg['content'])) ?>
    </div>

    <div class="text-xs text-gray-400 mt-1 flex <?= $isOwn ? 'justify-end' : 'justify-start' ?> items-center gap-2">
      <?= date("M d, h:i A", strtotime($msg['created_at'])) ?>
      <?php if ($isOwn && $isRead): ?>
        <span class="inline-flex items-center gap-1 text-emerald-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Seen
        </span>
      <?php endif; ?>
    </div>
  </div>
<?php endwhile;

$messages->free();
$messagesStmt->close();