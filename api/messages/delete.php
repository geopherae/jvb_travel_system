<?php
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../actions/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$data = json_decode(file_get_contents('php://input'), true);

$message_id = $data['message_id'] ?? null;

if (!$message_id) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing message ID']);
  exit;
}

$stmt = $conn->prepare("UPDATE messages SET deleted_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $message_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);