<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/notify.php';

session_start();
if (!isset($_SESSION['client_id'])) {
  error_log("[update_photo_details] Unauthorized access attempt.");
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$photo_id = (int)($input['photo_id'] ?? 0);
$caption  = trim($input['caption'] ?? '');
$tags     = $input['tags'] ?? [];

if (!$photo_id || !is_array($tags)) {
  error_log("[update_photo_details] Invalid input: photo_id={$photo_id}, tags=" . json_encode($tags));
  http_response_code(400);
  echo json_encode(['error' => 'Invalid input']);
  exit;
}

$client_id = $_SESSION['client_id'];
$verify_stmt = $conn->prepare("SELECT day FROM client_trip_photos WHERE id = ? AND client_id = ?");
$verify_stmt->bind_param("ii", $photo_id, $client_id);
if (!$verify_stmt->execute()) {
  error_log("[update_photo_details] Failed to verify photo ownership: " . $verify_stmt->error);
}
$day = $verify_stmt->get_result()->fetch_column();

if ($day === null) {
  error_log("[update_photo_details] Photo not found or access denied for photo_id={$photo_id}, client_id={$client_id}");
  http_response_code(403);
  echo json_encode(['error' => 'Photo not found or access denied']);
  exit;
}

// Update caption
$update_stmt = $conn->prepare("UPDATE client_trip_photos SET caption = ? WHERE id = ?");
$update_stmt->bind_param("si", $caption, $photo_id);
if (!$update_stmt->execute()) {
  error_log("[update_photo_details] Failed to update caption: " . $update_stmt->error);
}

// Clear existing tags
$clear_stmt = $conn->prepare("DELETE FROM photo_tags_map WHERE photo_id = ?");
$clear_stmt->bind_param("i", $photo_id);
if (!$clear_stmt->execute()) {
  error_log("[update_photo_details] Failed to clear existing tags: " . $clear_stmt->error);
}

// Insert or map tags
$select_tag_stmt = $conn->prepare("SELECT id FROM photo_tags WHERE tag_name = ?");
$insert_tag_stmt = $conn->prepare("INSERT INTO photo_tags (tag_name, tag_type) VALUES (?, 'Custom')");
$insert_map_stmt = $conn->prepare("INSERT INTO photo_tags_map (photo_id, tag_id) VALUES (?, ?)");

$final_tags = [];

foreach ($tags as $tag_name) {
  $tag_name = trim($tag_name);
  if ($tag_name === '') continue;

  $select_tag_stmt->bind_param("s", $tag_name);
  if (!$select_tag_stmt->execute()) {
    error_log("[update_photo_details] Failed to select tag '{$tag_name}': " . $select_tag_stmt->error);
    continue;
  }

  $tag_id = $select_tag_stmt->get_result()->fetch_column();

  if (!$tag_id) {
    $insert_tag_stmt->bind_param("s", $tag_name);
    if (!$insert_tag_stmt->execute()) {
      error_log("[update_photo_details] Failed to insert new tag '{$tag_name}': " . $insert_tag_stmt->error);
      continue;
    }
    $tag_id = $insert_tag_stmt->insert_id;
  }

  $insert_map_stmt->bind_param("ii", $photo_id, $tag_id);
  if (!$insert_map_stmt->execute()) {
    error_log("[update_photo_details] Failed to map tag '{$tag_name}' to photo_id={$photo_id}: " . $insert_map_stmt->error);
    continue;
  }

  $final_tags[] = $tag_name;
}

// 🔔 Notify all admins that the client updated photo details
$client_stmt = $conn->prepare("SELECT full_name FROM clients WHERE id = ?");
$client_stmt->bind_param("i", $client_id);
$client_stmt->execute();
$client_name = $client_stmt->get_result()->fetch_column();

$package_stmt = $conn->prepare("
  SELECT tp.package_name
  FROM client_trip_photos p
  LEFT JOIN tour_packages tp ON p.assigned_package_id = tp.id
  WHERE p.id = ?
");
$package_stmt->bind_param("i", $photo_id);
$package_stmt->execute();
$package_name = $package_stmt->get_result()->fetch_column();

error_log("[update_photo_details] Dispatching notification for client_id={$client_id}, photo_id={$photo_id}, package_name={$package_name}");

notifyAllAdmins('client_updated_photo', [
  'client_id'    => $client_id,
  'client_name'  => $client_name ?? 'Unknown',
  'day'          => $day,
  'package_name' => $package_name ?? 'Unassigned'
]);

// Return updated info for frontend hydration
echo json_encode([
  'success' => true,
  'photo_id' => $photo_id,
  'day' => $day,
  'tags' => $final_tags,
  'caption' => $caption
]);