<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../actions/db.php';

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

$ids = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : [];

if (!is_array($ids) || empty($ids)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid notification IDs']);
  exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids)) . 'si';

$stmt = $conn->prepare("
  UPDATE notifications
  SET dismissed = 1
  WHERE id IN ($placeholders)
    AND recipient_type = ?
    AND recipient_id = ?
");

$params = [...$ids, $recipientType, $recipientId];
$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
  error_log("[dismiss_bulk] Update failed: " . $stmt->error);
  http_response_code(500);
  echo json_encode(['error' => 'Database error']);
  exit;
}

echo json_encode(['success' => true]);