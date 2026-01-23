<?php
declare(strict_types=1);

// Suppress all output before JSON response
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Load dependencies
$baseDir = dirname(__DIR__, 2);
$dbFile   = $baseDir . '/actions/db.php';
$authFile = $baseDir . '/includes/auth.php';
$helpers  = $baseDir . '/includes/helpers.php';

if (!file_exists($dbFile) || !file_exists($authFile) || !file_exists($helpers)) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['error' => 'Internal server error: Missing configuration files']);
    exit;
}

require_once $dbFile;
require_once $authFile;
require_once $helpers;

if (!isset($conn) || !$conn instanceof mysqli) {
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Database connection unavailable']);
    exit;
}

$isDev = (defined('ENV') && ENV === 'development');
$isLocalHost = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);
$debugMode = $isDev || $isLocalHost || (isset($_GET['debug']) && $_GET['debug'] === '1');

// Catch fatals and always return JSON
register_shutdown_function(function () use ($debugMode) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_level()) {
            ob_clean();
        }
        http_response_code(500);
        $msg = $debugMode
            ? 'Fatal: ' . $err['message'] . ' at ' . $err['file'] . ':' . $err['line']
            : 'Failed to send message';
        echo json_encode(['error' => $msg]);
    }
});

// Validate input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$requiredParams = ['user_id', 'user_type', 'recipient_id', 'recipient_type', 'message_text'];
$missingParams  = array_filter($requiredParams, fn($param) => empty($input[$param]));
if ($missingParams) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters: ' . implode(', ', $missingParams)]);
    exit;
}

// Sanitize and validate input
$userId       = filter_var($input['user_id'], FILTER_VALIDATE_INT);
$userType     = filter_var($input['user_type'], FILTER_SANITIZE_STRING);
$recipientId  = filter_var($input['recipient_id'], FILTER_VALIDATE_INT);
$recipientType= filter_var($input['recipient_type'], FILTER_SANITIZE_STRING);
$messageText  = filter_var($input['message_text'], FILTER_SANITIZE_STRING);
$threadId     = isset($input['thread_id']) ? filter_var($input['thread_id'], FILTER_VALIDATE_INT) : null;

if ($userId === false || $recipientId === false || !$userType || !$recipientType || !$messageText) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameter types']);
    exit;
}

$validTypes = ['admin', 'client'];
if (!in_array($userType, $validTypes, true) || !in_array($recipientType, $validTypes, true)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user or recipient type']);
    exit;
}

// Verify session authentication
$adminId  = $_SESSION['admin']['id'] ?? null;
$clientId = $_SESSION['client_id'] ?? null;

if (
    ($userType === 'admin' && (!$adminId || $adminId !== $userId)) ||
    ($userType === 'client' && (!$clientId || $clientId !== $userId))
) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Resolve or create thread_id
try {
    $resolvedThreadId = null;
    error_log("send.php: Starting thread resolution for user=$userId ($userType) -> recipient=$recipientId ($recipientType)");
    
    $sql = "
        SELECT id 
        FROM threads 
        WHERE (
            (user_id = ? AND user_type = ? AND recipient_id = ? AND recipient_type = ?) OR
            (user_id = ? AND user_type = ? AND recipient_id = ? AND recipient_type = ?)
        )
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed for thread query: ' . $conn->error);
    }
    $stmt->bind_param('isisisis', $userId, $userType, $recipientId, $recipientType, $recipientId, $recipientType, $userId, $userType);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed for thread query: ' . $stmt->error);
    }
    $stmt->bind_result($resolvedThreadId);
    $stmt->fetch();
    $stmt->close();
    
    error_log("send.php: After SELECT, resolvedThreadId = " . var_export($resolvedThreadId, true));

    if (empty($resolvedThreadId)) {
        error_log("send.php: No existing thread found, creating new thread");
        // Use INSERT IGNORE to avoid duplicate key errors
        $sql = "INSERT IGNORE INTO threads (user_id, user_type, recipient_id, recipient_type, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed for thread insert: ' . $conn->error);
        }
        $stmt->bind_param('isis', $userId, $userType, $recipientId, $recipientType);
        
        if ($stmt->execute()) {
            error_log("send.php: INSERT executed, affected_rows=" . $stmt->affected_rows . ", insert_id=" . $conn->insert_id);
            if ($stmt->affected_rows > 0) {
                $resolvedThreadId = $conn->insert_id;
                error_log("Created new thread: thread_id=$resolvedThreadId");
            } else {
                error_log("send.php: INSERT IGNORE returned 0 rows (duplicate), refetching thread");
                // Thread exists (INSERT IGNORE prevented duplicate), refetch it
                $stmt2 = $conn->prepare("
                    SELECT id FROM threads 
                    WHERE (user_id = ? AND user_type = ? AND recipient_id = ? AND recipient_type = ?) 
                       OR (user_id = ? AND user_type = ? AND recipient_id = ? AND recipient_type = ?)
                    LIMIT 1
                ");
                if (!$stmt2) {
                    throw new Exception('Prepare failed for thread refetch: ' . $conn->error);
                }
                $stmt2->bind_param('isisisis', $userId, $userType, $recipientId, $recipientType, $recipientId, $recipientType, $userId, $userType);
                if (!$stmt2->execute()) {
                    throw new Exception('Execute failed for thread refetch: ' . $stmt2->error);
                }
                $stmt2->bind_result($resolvedThreadId);
                $stmt2->fetch();
                $stmt2->close();
                error_log("send.php: After refetch, resolvedThreadId = " . var_export($resolvedThreadId, true));
            }
        } else {
            error_log("send.php: INSERT failed - " . $stmt->error);
            throw new Exception('Thread insert/ignore failed: ' . $stmt->error);
        }
        
        $stmt->close();
        
        if (empty($resolvedThreadId)) {
            throw new Exception('Failed to resolve or create thread ID');
        }
    }

    // Validate provided thread_id if present
    if ($threadId && $threadId !== $resolvedThreadId) {
        if (ob_get_level()) ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Invalid thread ID']);
        exit;
    }

    // Debug log thread id resolution and inputs
    error_log("send.php payload: " . json_encode([
        'user_id' => $userId,
        'user_type' => $userType,
        'recipient_id' => $recipientId,
        'recipient_type' => $recipientType,
        'thread_id_input' => $threadId,
        'resolved_thread_id' => $resolvedThreadId
    ]));

    if (empty($resolvedThreadId)) {
        throw new Exception('Resolved thread ID is empty after creation');
    }

    // Insert message
    $sql = "
        INSERT INTO messages (
            thread_id, sender_id, sender_type, recipient_id, recipient_type, 
            message_text, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed for message insert: ' . $conn->error);
    }
    $stmt->bind_param('iisiss', $resolvedThreadId, $userId, $userType, $recipientId, $recipientType, $messageText);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed for message insert: ' . $stmt->error);
    }

    $messageId = $conn->insert_id;
    $createdAt = date('Y-m-d H:i:s');
    $stmt->close();

    // Get sender info (name and photo)
    $senderName = 'Unknown';
    $senderPhoto = null;
    
    if ($userType === 'admin') {
        $sql = "SELECT CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) AS sender_name, admin_photo FROM admin_accounts WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $senderName = trim($row['sender_name']) ?: 'Unknown';
            if ($row['admin_photo']) {
                $senderPhoto = '../uploads/admin_photo/' . rawurlencode($row['admin_photo']);
            }
        }
        $stmt->close();
    } else {
        $sql = "SELECT full_name, client_profile_photo FROM clients WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $senderName = trim($row['full_name']) ?: 'Unknown Client';
            if ($row['client_profile_photo']) {
                $senderPhoto = '../uploads/client_profiles/' . rawurlencode($row['client_profile_photo']);
            }
        }
        $stmt->close();
    }

    // Prepare message object
    $message = [
        'id'             => (int)$messageId,
        'thread_id'      => (int)$resolvedThreadId,
        'sender_id'      => (int)$userId,
        'sender_type'    => strtolower($userType),
        'recipient_id'   => (int)$recipientId,
        'recipient_type' => strtolower($recipientType),
        'message_text'   => (string)$messageText,
        'created_at'     => (string)$createdAt,
        'sender_name'    => $senderName,
        'sender_photo'   => $senderPhoto
    ];

    // Note: WebSocket broadcasting handled by websocket_server.php
    error_log("Message sent: " . json_encode($message, JSON_PRETTY_PRINT));

    // Return response to frontend
    if (ob_get_level()) {
        ob_clean();
    }
    http_response_code(200);
    echo json_encode($message, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Exception $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    error_log('send.php error: ' . $e->getMessage());
    error_log($e->getTraceAsString());
    http_response_code(500);
    $errorMsg = $debugMode ? ('Failed to send message: ' . $e->getMessage()) : 'Failed to send message';
    echo json_encode(['error' => $errorMsg]);
} finally {
    if (isset($conn)) $conn->close();
}

ob_end_flush();
?>