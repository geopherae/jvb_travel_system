<?php
require_once __DIR__ . '/../actions/db.php'; // adjust path as needed

header('Content-Type: application/json');

$client_id   = $_GET['client_id']   ?? null;
$template_id = $_GET['template_id'] ?? null;

if (!$client_id || !$template_id) {
  echo json_encode(['error' => 'Missing client_id or template_id']);
  exit;
}

// 🔹 Get checklist template
$stmt = $conn->prepare("SELECT checklist_json FROM checklist_templates WHERE id = ?");
$stmt->bind_param("i", $template_id);
$stmt->execute();
$template_json = $stmt->get_result()->fetch_column();
$template = json_decode($template_json, true) ?? [];

// 🔹 Get client progress JSON
$stmt = $conn->prepare("SELECT progress_json FROM client_checklist_progress WHERE client_id = ? AND template_id = ?");
$stmt->bind_param("ii", $client_id, $template_id);
$stmt->execute();
$progress_json = $stmt->get_result()->fetch_column();
$progress = json_decode($progress_json, true) ?? [];

// 🔹 Merge progress into checklist
foreach ($template as &$item) {
  $key = $item['status_key'];
  $item['completed_at'] = $progress[$key] ?? null;
  $item['is_completed'] = isset($progress[$key]);
}

echo json_encode($template);