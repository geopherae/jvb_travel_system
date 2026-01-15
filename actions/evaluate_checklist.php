<?php
require_once __DIR__ . '/../actions/db.php'; // adjust path as needed
header('Content-Type: application/json');

$client_id = $_GET['client_id'] ?? null;
$template_id = $_GET['template_id'] ?? null;

if (!$client_id || !$template_id) {
  echo json_encode(['error' => 'Missing client_id or template_id']);
  exit;
}

// 🔹 Load checklist template
$stmt = $conn->prepare("SELECT checklist_json FROM checklist_templates WHERE id = ?");
$stmt->bind_param("i", $template_id);
$stmt->execute();
$template_json = $stmt->get_result()->fetch_column();
$template = json_decode($template_json, true) ?? [];

// 🔹 Load existing progress
$stmt = $conn->prepare("
  SELECT status_key, completed_at 
  FROM client_checklist_progress 
  WHERE client_id = ? AND template_id = ?
");
$stmt->bind_param("ii", $client_id, $template_id);
$stmt->execute();
$progress = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
  $progress[$row['status_key']] = $row['completed_at'];
}

// 🔹 Evaluation logic
foreach ($template as &$item) {
  $key = $item['status_key'];
  $item['completed_at'] = $progress[$key] ?? null;
  $item['is_completed'] = isset($progress[$key]);

  if ($item['is_completed']) continue;

  $is_complete = false;

  switch ($key) {
    case 'survey_taken':
      $stmt = $conn->prepare("SELECT is_completed FROM user_survey_status WHERE client_id = ? AND survey_type = 'first_login'");
      $stmt->bind_param("i", $client_id);
      $stmt->execute();
      $is_complete = (bool) $stmt->get_result()->fetch_column();
      break;

    case 'id_uploaded':
      $stmt = $conn->prepare("SELECT COUNT(*) FROM upload_files WHERE client_id = ? AND document_type IN ('passport', 'identification card')");
      $stmt->bind_param("i", $client_id);
      $stmt->execute();
      $is_complete = $stmt->get_result()->fetch_column() > 0;
      break;

    case 'id_approved':
      $stmt = $conn->prepare("SELECT COUNT(*) FROM upload_files WHERE client_id = ? AND document_type IN ('passport', 'identification card') AND status = 'approved'");
      $stmt->bind_param("i", $client_id);
      $stmt->execute();
      $is_complete = $stmt->get_result()->fetch_column() > 0;
      break;

    case 'itinerary_confirmed':
      $stmt = $conn->prepare("SELECT is_confirmed FROM client_itinerary WHERE client_id = ?");
      $stmt->bind_param("i", $client_id);
      $stmt->execute();
      $is_complete = (bool) $stmt->get_result()->fetch_column();
      break;

    case 'photos_uploaded':
      $stmt = $conn->prepare("SELECT COUNT(*) FROM client_trip_photos WHERE client_id = ? AND file_name IS NOT NULL AND file_name != ''");
      $stmt->bind_param("i", $client_id);
      $stmt->execute();
      $is_complete = $stmt->get_result()->fetch_column() > 0;
      break;

    case 'trip_survey_taken':
      $stmt = $conn->prepare("SELECT is_completed FROM user_survey_status WHERE client_id = ? AND survey_type = 'trip_complete'");
      $stmt->bind_param("i", $client_id);
      $stmt->execute();
      $is_complete = (bool) $stmt->get_result()->fetch_column();
      break;

    case 'facebook_visited':
      $is_complete = !empty($_SESSION["facebook_clicked_{$client_id}"]);
      break;
  }

  if ($is_complete) {
    $stmt = $conn->prepare("
      INSERT INTO client_checklist_progress (client_id, template_id, status_key) 
      VALUES (?, ?, ?) 
      ON DUPLICATE KEY UPDATE completed_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("iis", $client_id, $template_id, $key);
    $stmt->execute();

    $item['is_completed'] = true;
    $item['completed_at'] = date('Y-m-d H:i:s');
  }
}

echo json_encode($template);