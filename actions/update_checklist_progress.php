<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$client_id   = $data['client_id']   ?? null;
$template_id = $data['template_id'] ?? null;
$status_key  = $data['status_key']  ?? null;

if (!$client_id || !$template_id || !$status_key) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing required fields']);
  exit;
}

// 🔍 Fetch existing progress
$stmt = $conn->prepare("SELECT progress_json FROM client_checklist_progress WHERE client_id = ? AND template_id = ?");
$stmt->bind_param("ii", $client_id, $template_id);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();

$progress = $existing ? json_decode($existing['progress_json'], true) ?? [] : [];

// 🕒 Add or update timestamp
$progress[$status_key] = date('Y-m-d H:i:s');
$progress_json = json_encode($progress);

// 🔄 Insert or update row
$stmt = $conn->prepare("
  INSERT INTO client_checklist_progress (client_id, template_id, progress_json)
  VALUES (?, ?, ?)
  ON DUPLICATE KEY UPDATE progress_json = VALUES(progress_json), updated_at = CURRENT_TIMESTAMP
");
$stmt->bind_param("iis", $client_id, $template_id, $progress_json);

if ($stmt->execute()) {
  echo json_encode(['success' => true, 'progress' => $progress]);
} else {
  http_response_code(500);
  echo json_encode(['error' => 'Database error', 'details' => $stmt->error]);
}