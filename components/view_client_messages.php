<?php
$currentUserRole = 'client'; // or 'admin'

$messages_stmt = $conn->prepare("SELECT * FROM messages WHERE client_id = ? ORDER BY created_at ASC");
$messages_stmt->bind_param("i", $client['id']);
$messages_stmt->execute();
$messages = $messages_stmt->get_result();

// Mark all messages from the other party as read
$markReadStmt = $conn->prepare(
  "UPDATE messages SET read_at = NOW() WHERE client_id = ? AND sender != ? AND read_at IS NULL"
);
$markReadStmt->bind_param("is", $client['id'], $currentUserRole);
$markReadStmt->execute();
$markReadStmt->close();

while ($msg = $messages->fetch_assoc()):
  $isOwn = $msg['sender'] === $currentUserRole;
  $isRead = !is_null($msg['read_at']);
?>
  <div class="mb-2 <?= $isOwn ? 'text-right' : 'text-left' ?>">

    <!-- Bubble -->
    <div class="inline-block max-w-[70%] px-3 py-2 rounded-lg
                <?= $isOwn
                    ? 'bg-blue-50 text-blue-900'
                    : 'bg-gray-100 text-gray-800' ?>">
      <?= nl2br(htmlspecialchars($msg['content'])) ?>
    </div>

    <!-- Meta: Timestamp + Seen -->
    <div class="text-xs text-gray-400 mt-1 flex <?= $isOwn ? 'justify-end' : 'justify-start' ?> items-center gap-2">
      <?= date("M d, h:i A", strtotime($msg['created_at'])) ?>
      <?php if ($isOwn && $isRead): ?>
        <span class="inline-flex items-center gap-1 text-emerald-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M5 13l4 4L19 7" />
          </svg>
          Seen
        </span>
      <?php endif; ?>
    </div>
  </div>
<?php endwhile; ?>