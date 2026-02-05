<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visa_status_helper.php';

use function Auth\getActorContext;
use function VisaStatusHelper\recalculateVisaApplicationStatus;

header('Content-Type: application/json');

$actor = getActorContext();
if ($actor['role'] !== 'superadmin' && $actor['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode([
    'success' => false,
    'message' => 'Unauthorized: Admin access required'
  ]);
  exit;
}

$cacheTime = 5; // seconds
$lastCheck = $_SESSION['last_visa_status_check'] ?? 0;
$timeSinceLastCheck = time() - $lastCheck;

if ($timeSinceLastCheck < $cacheTime) {
  echo json_encode([
    'updated' => [],
    'count' => 0,
    'cached' => true,
    'seconds_remaining' => $cacheTime - $timeSinceLastCheck,
    'message' => "Cached: Visa status checked {$timeSinceLastCheck}s ago. Next check in " . ($cacheTime - $timeSinceLastCheck) . "s."
  ]);
  exit;
}
$_SESSION['last_visa_status_check'] = time();

$updatedApps = [];

try {
  $appsStmt = $conn->prepare("SELECT id, status FROM client_visa_applications ORDER BY id ASC");
  $appsStmt->execute();
  $appsResult = $appsStmt->get_result();
  $applications = $appsResult->fetch_all(MYSQLI_ASSOC);
  $appsStmt->close();

  foreach ($applications as $app) {
    $appId = (int) $app['id'];
    $oldStatus = $app['status'] ?? '';

    $result = recalculateVisaApplicationStatus($conn, $appId);
    if (!$result['success']) {
      continue;
    }

    $newStatus = $result['status'] ?? '';
    if ($newStatus !== $oldStatus) {
      $updatedApps[] = [
        'application_id' => $appId,
        'from' => $oldStatus,
        'to' => $newStatus
      ];
    }
  }

  echo json_encode([
    'updated' => $updatedApps,
    'count' => count($updatedApps)
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'error' => true,
    'message' => $e->getMessage()
  ]);
}
