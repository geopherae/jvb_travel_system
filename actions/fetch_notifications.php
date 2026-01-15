<?php
require_once __DIR__ . '/../actions/db.php';
session_start();
header('Content-Type: application/json');

$isClient = isset($_SESSION['client_id']);
$isAdmin  = isset($_SESSION['admin']['id']);

if (!$isClient && !$isAdmin) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$recipientType = $isClient ? 'client' : 'admin';
$recipientId   = $isClient ? $_SESSION['client_id'] : $_SESSION['admin']['id'];

$stmt = $conn->prepare("
  SELECT 
    id, event_type, message, action_url, icon,
    priority, status, created_at, expires_at, metadata_json
  FROM notifications
  WHERE recipient_type = ? AND recipient_id = ?
    AND dismissed = 0
    AND (expires_at IS NULL OR expires_at > NOW())
  ORDER BY created_at DESC
  LIMIT 10
");

if (!$stmt) {
  error_log("[fetch_notifications] Failed to prepare statement: " . $conn->error);
  echo json_encode(['error' => 'Database error']);
  exit;
}

$stmt->bind_param("si", $recipientType, $recipientId);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
  $metadata = json_decode($row['metadata_json'], true);
  $row['metadata'] = is_array($metadata) ? $metadata : [];
  unset($row['metadata_json']);
  $notifications[] = $row;
}

echo json_encode($notifications, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);