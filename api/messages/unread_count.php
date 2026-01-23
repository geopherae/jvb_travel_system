<?php
declare(strict_types=1);

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
session_start();

$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/actions/db.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    echo json_encode(['has_unread' => false]);
    exit;
}

try {
    $hasUnread = false;
    
    // Check for admin
    if (!empty($_SESSION['admin']['id'])) {
        $adminId = (int)$_SESSION['admin']['id'];
        $stmt = $conn->prepare("
            SELECT COUNT(*) as unread_count 
            FROM messages 
            WHERE recipient_id = ? 
              AND recipient_type = 'admin' 
              AND read_at IS NULL
              AND deleted_at IS NULL
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $stmt->bind_result($unreadCount);
            $stmt->fetch();
            $stmt->close();
            $hasUnread = $unreadCount > 0;
        }
    } 
    // Check for client
    elseif (!empty($_SESSION['client_id'])) {
        $clientId = (int)$_SESSION['client_id'];
        $stmt = $conn->prepare("
            SELECT COUNT(*) as unread_count 
            FROM messages 
            WHERE recipient_id = ? 
              AND recipient_type = 'client' 
              AND read_at IS NULL
              AND deleted_at IS NULL
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('i', $clientId);
            $stmt->execute();
            $stmt->bind_result($unreadCount);
            $stmt->fetch();
            $stmt->close();
            $hasUnread = $unreadCount > 0;
        }
    }
    
    echo json_encode(['has_unread' => $hasUnread]);

} catch (Throwable $e) {
    error_log('unread_count.php error: ' . $e->getMessage());
    echo json_encode(['has_unread' => false]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
