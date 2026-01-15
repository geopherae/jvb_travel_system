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

// ✅ Update all unread notifications to read
$stmt = $conn->prepare("
  UPDATE notifications
  SET status = 'read'
  WHERE recipient_type = ? AND recipient_id = ? AND status = 'unread' AND dismissed = 0
");

if (!$stmt) {
  error_log("[mark_all_notifications_read] Failed to prepare statement: " . $conn->error);
  http_response_code(500);
  echo json_encode(['error' => 'Database error']);
  exit;
}

$stmt->bind_param("si", $recipientType, $recipientId);

if (!$stmt->execute()) {
  error_log("[mark_all_notifications_read] Update failed: " . $stmt->error);
  http_response_code(500);
  echo json_encode(['error' => 'Database error']);
  exit;
}

$updatedCount = $stmt->affected_rows;
echo json_encode(['success' => true, 'updated' => $updatedCount]);
