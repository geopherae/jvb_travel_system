<?php
declare(strict_types=1);

ob_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/actions/db.php';
require_once $projectRoot . '/includes/helpers.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    echo json_encode([]);
    exit;
}

try {
    $userId = (int)($_GET['user_id'] ?? 0);
    $userType = trim($_GET['user_type'] ?? '');
    $recipientId = (int)($_GET['recipient_id'] ?? 0);
    $recipientType = trim($_GET['recipient_type'] ?? '');

    if ($userId < 1 || $recipientId < 1 || !in_array($userType, ['admin', 'client']) || !in_array($recipientType, ['admin', 'client'])) {
        echo json_encode([]);
        exit;
    }

    // Verify authentication
    if ($userType === 'admin' && ($_SESSION['admin']['id'] ?? 0) != $userId) {
        error_log("Unauthorized admin access attempt: session_id=" . ($_SESSION['admin']['id'] ?? 'none') . ", requested_id=$userId");
        echo json_encode([]);
        exit;
    }

    if ($userType === 'client' && ($_SESSION['client_id'] ?? 0) != $userId) {
        error_log("Unauthorized client access attempt: session_id=" . ($_SESSION['client_id'] ?? 'none') . ", requested_id=$userId");
        echo json_encode([]);
        exit;
    }

    // Resolve thread
    $stmt = $conn->prepare("
        SELECT id FROM threads 
        WHERE (user_id = ? AND user_type = ? AND recipient_id = ? AND recipient_type = ?) 
           OR (user_id = ? AND user_type = ? AND recipient_id = ? AND recipient_type = ?)
        LIMIT 1
    ");
    $stmt->bind_param('isisisis', $userId, $userType, $recipientId, $recipientType, $recipientId, $recipientType, $userId, $userType);
    $stmt->execute();
    $stmt->bind_result($threadId);
    $stmt->fetch();
    $stmt->close();

    if (!$threadId) {
        // Use INSERT IGNORE to avoid duplicate key errors
        $stmt = $conn->prepare("INSERT IGNORE INTO threads (user_id, user_type, recipient_id, recipient_type, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('issi', $userId, $userType, $recipientId, $recipientType);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $threadId = $conn->insert_id;
        } else {
            // Thread was already created, fetch it again
            $stmt2 = $conn->prepare("
                SELECT id FROM threads 
                WHERE (user_id = ? AND user_type = ? AND recipient_id = ? AND recipient_type = ?) 
                   OR (user_id = ? AND user_type = ? AND recipient_id = ? AND recipient_type = ?)
                LIMIT 1
            ");
            $stmt2->bind_param('isisisis', $userId, $userType, $recipientId, $recipientType, $recipientId, $recipientType, $userId, $userType);
            $stmt2->execute();
            $stmt2->bind_result($threadId);
            $stmt2->fetch();
            $stmt2->close();
        }
        
        $stmt->close();
    }

    // Fetch messages (delta via since timestamp or since_id)
    $since = trim($_GET['since'] ?? '');
    $sinceId = (int)($_GET['since_id'] ?? 0);
    $sinceCondition = '';
    $params = 'i';
    $paramValues = [$threadId];
    
    // Prefer id-based delta when provided; else use timestamp
    if ($sinceId > 0) {
        $sinceCondition = 'AND m.id > ?';
        $params .= 'i';
        $paramValues[] = $sinceId;
    } elseif (!empty($since)) {
        $sinceCondition = 'AND m.created_at > ?';
        $params .= 's';
        $paramValues[] = $since;
    }
    
    $sql = "
        SELECT m.id, m.thread_id, m.sender_id, m.sender_type, m.message_text, m.created_at,
               COALESCE(CONCAT(a.first_name, ' ', a.last_name), 'Admin') AS sender_name,
               c.full_name AS client_name,
               a.admin_photo, c.client_profile_photo
        FROM messages m
        LEFT JOIN admin_accounts a ON m.sender_type = 'admin' AND m.sender_id = a.id
        LEFT JOIN clients c ON m.sender_type = 'client' AND m.sender_id = c.id
        WHERE m.thread_id = ? AND m.deleted_at IS NULL $sinceCondition
        ORDER BY m.created_at ASC
        LIMIT 1000
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed for message fetch: ' . $conn->error);
    }
    
    // Bind parameters dynamically
    $stmt->bind_param($params, ...$paramValues);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed for message fetch: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $name = $row['sender_type'] === 'admin' ? $row['sender_name'] : ($row['client_name'] ?? 'Unknown Client');
        $photo = null;
        if ($row['sender_type'] === 'admin' && $row['admin_photo']) {
            $photo = getAdminAvatar(['admin_photo' => $row['admin_photo']]);
        } elseif ($row['sender_type'] === 'client' && $row['client_profile_photo']) {
            $photo = getClientAvatar(['client_profile_photo' => $row['client_profile_photo']]);
        }

        $messages[] = [
            'id' => (int)$row['id'],
            'thread_id' => (int)$row['thread_id'],
            'sender_id' => (int)$row['sender_id'],
            'sender_type' => $row['sender_type'],
            'recipient_id' => $recipientId,
            'recipient_type' => $recipientType,
            'message_text' => $row['message_text'],
            'created_at' => $row['created_at'],
            'sender_name' => trim($name),
            'sender_photo' => $photo
        ];
    }
    
    $stmt->close();

    echo json_encode($messages);

} catch (Throwable $e) {
    error_log('fetch.php error: ' . $e->getMessage());
    echo json_encode([]);
} finally {
    if (isset($conn)) $conn->close();
}

ob_end_flush();
?>