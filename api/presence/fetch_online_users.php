<?php
declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Enable error suppression in production, show in development
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    $projectRoot = dirname(dirname(__DIR__));
    require_once $projectRoot . '/actions/db.php';

    if (!isset($conn) || !$conn instanceof mysqli) {
        throw new Exception('Database connection failed.');
    }

    // 3-minute threshold for "online" status
    $onlineAdmins = [];
    $onlineClients = [];

    // Query active admins (last_activity within 3 minutes)
    $stmt = $conn->prepare("
        SELECT id FROM admin_accounts
        WHERE last_activity > NOW() - INTERVAL 3 MINUTE
        AND is_active = 1
    ");

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $onlineAdmins[] = (int)$row['id'];
        }
        $result->free();
        $stmt->close();
    }

    // Query active clients (last_activity within 3 minutes)
    $stmt = $conn->prepare("
        SELECT id FROM clients
        WHERE last_activity > NOW() - INTERVAL 3 MINUTE
    ");

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $onlineClients[] = (int)$row['id'];
        }
        $result->free();
        $stmt->close();
    }

    // Return JSON response
    echo json_encode([
        'success' => true,
        'online_admins' => $onlineAdmins,
        'online_clients' => $onlineClients
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Presence API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch online status',
        'error' => (ENV === 'development') ? $e->getMessage() : 'Server error'
    ], JSON_UNESCAPED_UNICODE);
}

if (isset($conn)) {
    $conn->close();
}
?>
