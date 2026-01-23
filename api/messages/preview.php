<?php
declare(strict_types=1);

ob_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

header('Content-Type: application/json');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Project root: preview.php is in /api/messages/
$projectRoot = dirname(__DIR__, 2);

require_once $projectRoot . '/actions/db.php';
require_once $projectRoot . '/includes/helpers.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    echo json_encode([]);
    exit;
}

// Input
$userId   = (int)($_GET['user_id'] ?? 0);
$userType = trim($_GET['user_type'] ?? '');

if ($userId < 1 || !in_array($userType, ['admin', 'client'], true)) {
    echo json_encode([]);
    exit;
}

// Auth
if ($userType === 'admin') {
    $sessionAdminId = $_SESSION['admin']['id'] ?? null;
    if (!$sessionAdminId || $sessionAdminId != $userId) {
        echo json_encode([]);
        exit;
    }
} elseif ($userType === 'client') {
    $sessionClientId = $_SESSION['client_id'] ?? null;
    if (!$sessionClientId || $sessionClientId != $userId) {
        echo json_encode([]);
        exit;
    }
}

// Get all threads for this user (as sender or recipient)
$userTypeV = $userType;

$stmt = $conn->prepare("
    SELECT id, user_id, user_type, recipient_id, recipient_type
    FROM threads
    WHERE (user_id = ? AND user_type = ?) 
       OR (recipient_id = ? AND recipient_type = ?)
    ORDER BY created_at DESC
");
$stmt->bind_param('isis', $userId, $userTypeV, $userId, $userTypeV);
$stmt->execute();
$result = $stmt->get_result();

$previews = [];

while ($thread = $result->fetch_assoc()) {
    $threadId = (int)$thread['id'];
    
    // Determine who the "other person" is in this conversation
    $threadUserId = (int)$thread['user_id'];
    $threadUserType = (string)$thread['user_type'];
    $threadRecipientId = (int)$thread['recipient_id'];
    $threadRecipientType = (string)$thread['recipient_type'];
    
    // If current user is the thread creator, show the recipient
    // If current user is the recipient, show the thread creator
    if ($threadUserId === $userId && $threadUserType === $userType) {
        $otherPersonId = $threadRecipientId;
        $otherPersonType = (string)$threadRecipientType;
    } else {
        $otherPersonId = $threadUserId;
        $otherPersonType = (string)$threadUserType;
    }
    
    $recipientId = $otherPersonId;
    $recipientType = strtolower((string)$otherPersonType);

    // Get the latest message in this thread
    $msgStmt = $conn->prepare("
        SELECT message_text, created_at, sender_id, sender_type
        FROM messages
        WHERE thread_id = ? AND deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $msgStmt->bind_param('i', $threadId);
    $msgStmt->execute();
    $msgResult = $msgStmt->get_result();
    $lastMsg = $msgResult->fetch_assoc();
    $msgStmt->close();
    
    // Determine if the message was sent by current user or recipient
    $sentByCurrentUser = false;
    if ($lastMsg) {
        $msgSenderId = (int)$lastMsg['sender_id'];
        $msgSenderType = $lastMsg['sender_type'];
        $sentByCurrentUser = ($msgSenderId === $userId && $msgSenderType === $userType);
    }

    // Get recipient name
    $recipientName = 'Unknown';
    if ($recipientType === 'admin') {
        $nameStmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM admin_accounts WHERE id = ?");
        $nameStmt->bind_param('i', $recipientId);
        $nameStmt->execute();
        $nameResult = $nameStmt->get_result();
        if ($row = $nameResult->fetch_assoc()) {
            $recipientName = trim($row['name']) ?: 'Admin';
        }
        $nameStmt->close();
    } elseif ($recipientType === 'client') {
        $nameStmt = $conn->prepare("SELECT full_name FROM clients WHERE id = ?");
        $nameStmt->bind_param('i', $recipientId);
        $nameStmt->execute();
        $nameResult = $nameStmt->get_result();
        if ($row = $nameResult->fetch_assoc()) {
            $recipientName = $row['full_name'] ?: 'Unknown Client';
        }
        $nameStmt->close();
    }

    $previews[] = [
        'thread_id'       => (int)$threadId,
        'recipient_id'    => (int)$recipientId,
        'recipient_type'  => strtolower((string)$recipientType),
        'message_text'    => ($lastMsg['message_text'] ?? '') ? (string)$lastMsg['message_text'] : '',
        'created_at'      => isset($lastMsg['created_at']) ? (string)$lastMsg['created_at'] : null,
        'recipient_name'  => (string)$recipientName,
        'sent_by_me'      => (bool)$sentByCurrentUser
    ];
}

$stmt->close();
$conn->close();

echo json_encode($previews);
ob_end_flush();
?>