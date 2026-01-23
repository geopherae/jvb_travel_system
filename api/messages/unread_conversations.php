<?php
declare(strict_types=1);

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
session_start();

$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/actions/db.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    echo json_encode([]);
    exit;
}

try {
    $userId = (int)($_GET['user_id'] ?? 0);
    $userType = trim($_GET['user_type'] ?? '');
    
    if ($userId < 1 || !in_array($userType, ['admin', 'client'], true)) {
        echo json_encode([]);
        exit;
    }

    $unreadConversations = [];
    
    // Get all threads for this user
    $stmt = $conn->prepare("
        SELECT id, user_id, user_type, recipient_id, recipient_type
        FROM threads
        WHERE (user_id = ? AND user_type = ?) 
           OR (recipient_id = ? AND recipient_type = ?)
    ");
    $stmt->bind_param('isis', $userId, $userType, $userId, $userType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($thread = $result->fetch_assoc()) {
        $threadId = (int)$thread['id'];
        
        // Determine the other person in conversation
        $threadUserId = (int)$thread['user_id'];
        $threadUserType = (string)$thread['user_type'];
        $threadRecipientId = (int)$thread['recipient_id'];
        $threadRecipientType = (string)$thread['recipient_type'];
        
        if ($threadUserId === $userId && $threadUserType === $userType) {
            $otherPersonId = $threadRecipientId;
            $otherPersonType = $threadRecipientType;
        } else {
            $otherPersonId = $threadUserId;
            $otherPersonType = $threadUserType;
        }
        
        // Check for unread messages FROM the other person TO current user
        $unreadStmt = $conn->prepare("
            SELECT COUNT(*) as unread_count
            FROM messages
            WHERE thread_id = ?
              AND sender_id = ?
              AND sender_type = ?
              AND recipient_id = ?
              AND recipient_type = ?
              AND read_at IS NULL
              AND deleted_at IS NULL
        ");
        
        if ($unreadStmt) {
            $unreadStmt->bind_param('iisii', $threadId, $otherPersonId, $otherPersonType, $userId, $userType);
            $unreadStmt->execute();
            $unreadStmt->bind_result($unreadCount);
            $unreadStmt->fetch();
            $unreadStmt->close();
            
            if ($unreadCount > 0) {
                $unreadConversations[] = [
                    'recipient_id' => $otherPersonId,
                    'unread_count' => $unreadCount
                ];
            }
        }
    }
    
    $stmt->close();
    
    echo json_encode($unreadConversations);

} catch (Throwable $e) {
    error_log('unread_conversations.php error: ' . $e->getMessage());
    echo json_encode([]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
