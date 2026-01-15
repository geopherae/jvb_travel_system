<?php
session_start();
require_once __DIR__ . '/../actions/db.php';

header('Content-Type: application/json');

// 🔐 Validate session
$isClient = isset($_SESSION['client_id']);
$isAdmin  = isset($_SESSION['admin']['id']);

if (!$isClient && !$isAdmin) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$recipientType = $isClient ? 'client' : 'admin';
$recipientId   = $isClient ? $_SESSION['client_id'] : $_SESSION['admin']['id'];

// 🔍 Validate notification ID
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid notification ID']);
  exit;
}

// ✅ Update status if owned by user
$stmt = $conn->prepare("
  UPDATE notifications
  SET status = 'read'
  WHERE id = ? AND recipient_type = ? AND recipient_id = ? AND dismissed = 0
");
$stmt->bind_param("isi", $id, $recipientType, $recipientId);

if (!$stmt->execute()) {
  error_log("[mark_notification_read] Update failed: " . $stmt->error);
  http_response_code(500);
  echo json_encode(['error' => 'Database error']);
  exit;
}

if ($stmt->affected_rows === 0) {
  error_log("[mark_notification_read] No rows updated for id={$id}, recipient_type={$recipientType}, recipient_id={$recipientId}");
  http_response_code(404);
  echo json_encode(['error' => 'Notification not found or already read']);
  exit;
}

echo json_encode(['success' => true]);
?>