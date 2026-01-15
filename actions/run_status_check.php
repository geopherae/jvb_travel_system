<?php
session_start();
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../actions/client_status_checker.php';

header('Content-Type: application/json');

// Optional caching: Skip full check if done recently
$cacheTime = 2; // seconds (reduced from 60 for better responsiveness)
$lastCheck = $_SESSION['last_status_check'] ?? 0;
$timeSinceLastCheck = time() - $lastCheck;

if ($timeSinceLastCheck < $cacheTime) {
  echo json_encode([
    "updated" => [],
    "count" => 0,
    "cached" => true,
    "seconds_remaining" => $cacheTime - $timeSinceLastCheck,
    "message" => "Cached: Status checked {$timeSinceLastCheck}s ago. Next check in " . ($cacheTime - $timeSinceLastCheck) . "s."
  ]);
  exit;
}
$_SESSION['last_status_check'] = time();

$updatedClients = [];

try {
    // 🔄 Fetch all client IDs
    $sql = "SELECT id FROM clients";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Failed to fetch clients: " . $conn->error);
    }

    if ($result->num_rows === 0) {
        echo json_encode([
            "updated" => [],
            "count" => 0,
            "message" => "No clients found."
        ]);
        exit;
    }

    // 🔁 Run status check for each client
    while ($row = $result->fetch_assoc()) {
        $statusChange = updateClientStatus((int)$row['id'], $conn);
        if ($statusChange) {
            $updatedClients[] = $statusChange;
        }
    }

    // ✅ Return JSON response
    echo json_encode([
        "updated" => $updatedClients,
        "count" => count($updatedClients)
    ]);
} catch (Exception $e) {
    // ⚠️ Error response
    http_response_code(500);
    echo json_encode([
        "error" => true,
        "message" => $e->getMessage()
    ]);
}
?>