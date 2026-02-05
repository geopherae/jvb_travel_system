<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * API: Check for New Messages (Global)
 * ═══════════════════════════════════════════════════════════════════════════
 * Returns messages received since last check
 * Used for global toast notifications across all pages
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../actions/db.php';
require_once __DIR__ . '/../../includes/auth.php';

use function Auth\guard;

// Must be logged in (either admin or client)
guard('any');

try {
    header('Content-Type: application/json');
    
    // Get current user info from session
    // Support: admins, regular clients, and visa companions
    $isAdmin = $_SESSION['is_admin'] ?? false;
    $isCompanion = $_SESSION['is_companion'] ?? false;
    
    if ($isAdmin) {
        $userId = $_SESSION['admin']['id'] ?? null;
        $userType = 'admin';
    } elseif ($isCompanion) {
        $userId = $_SESSION['companion_id'] ?? null;
        $userType = 'companion';
    } else {
        $userId = $_SESSION['client_id'] ?? null;
        $userType = 'client';
    }
    
    if (!$userId) {
        echo json_encode([]);
        exit;
    }
    
    // Get timestamp of last session activity (when they last checked)
    $lastCheckTimestamp = $_SESSION['last_message_check'] ?? date('Y-m-d H:i:s', time() - 300); // Default 5 min ago
    
    // Query: Get messages where recipient is the current user, created after last check
    // Include admin, client, and companion photos based on sender type
    $stmt = $conn->prepare("
        SELECT 
            m.id,
            m.message_text,
            m.sender_id,
            m.sender_type,
            COALESCE(
                CASE 
                    WHEN m.sender_type = 'admin' THEN (SELECT first_name FROM admin_accounts WHERE id = m.sender_id LIMIT 1)
                    WHEN m.sender_type = 'client' THEN (SELECT full_name FROM clients WHERE id = m.sender_id LIMIT 1)
                    WHEN m.sender_type = 'companion' THEN (SELECT full_name FROM client_visa_companions WHERE id = m.sender_id LIMIT 1)
                    ELSE 'Someone'
                END,
                'Someone'
            ) as sender_name,
            CASE 
                WHEN m.sender_type = 'admin' THEN (SELECT admin_photo FROM admin_accounts WHERE id = m.sender_id LIMIT 1)
                ELSE NULL
            END as admin_photo,
            CASE 
                WHEN m.sender_type = 'client' THEN (SELECT client_profile_photo FROM clients WHERE id = m.sender_id LIMIT 1)
                ELSE NULL
            END as client_photo,
            CASE 
                WHEN m.sender_type = 'companion' THEN (SELECT companions_photo FROM client_visa_companions WHERE id = m.sender_id LIMIT 1)
                ELSE NULL
            END as companion_photo,
            m.created_at
        FROM messages m
        WHERE 
            m.recipient_id = ?
            AND m.recipient_type = ?
            AND m.created_at > ?
            AND m.read_at IS NULL
        ORDER BY m.created_at DESC
        LIMIT 10
    ");
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param('iss', $userId, $userType, $lastCheckTimestamp);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $messages = [];
    
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => (int)$row['id'],
            'message_text' => $row['message_text'],
            'sender_id' => (int)$row['sender_id'],
            'sender_type' => $row['sender_type'],
            'sender_name' => $row['sender_name'],
            'admin_photo' => $row['admin_photo'],
            'client_photo' => $row['client_photo'],
            'companion_photo' => $row['companion_photo'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Update session to track when we last checked
    $_SESSION['last_message_check'] = date('Y-m-d H:i:s');
    
    // Return messages (reverse to show oldest first)
    echo json_encode(array_reverse($messages));
    
} catch (Exception $e) {
    error_log('[check_new_messages.php] Error: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Failed to check messages']);
}
