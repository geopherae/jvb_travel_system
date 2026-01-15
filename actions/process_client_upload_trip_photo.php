<?php
ini_set('display_errors', 0); // Hide warnings
error_reporting(E_ERROR);     // Only show fatal errors

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../actions/notify.php';

// 🧑‍💼 Auth check
$client_id = $_SESSION['client_id'] ?? null;
if (!$client_id) {
  logDebug(['error' => 'Unauthorized', 'session' => $_SESSION]);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

// 📥 File validation
$photo = $_FILES['photo'] ?? null;
if (!$photo || $photo['error'] !== UPLOAD_ERR_OK) {
  logDebug(['error' => 'File upload error', 'file' => $_FILES['photo'] ?? null]);
  echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
  exit;
}

// 📦 Form fields
$day = $_POST['day'] ?? '';
$caption = trim($_POST['caption'] ?? '');
$location_tag = trim($_POST['location_tag'] ?? '');
$assigned_package_id = $_POST['assigned_package_id'] ?? '';

if ($day === '' || $assigned_package_id === '') {
  logDebug(['error' => 'Missing required fields', 'post' => $_POST]);
  echo json_encode(['success' => false, 'error' => 'Missing required fields']);
  exit;
}

// 🧪 Debug: log incoming payload
$debugPayload = [
  'client_id' => $client_id,
  'day' => $day,
  'caption' => $caption,
  'location_tag' => $location_tag,
  'assigned_package_id' => $assigned_package_id,
  'mime_type' => mime_content_type($photo['tmp_name']),
  'file_error' => $photo['error'],
  'file_size' => $photo['size'],
  'file_name' => $photo['name']
];
logDebug(['stage' => 'incoming payload', 'payload' => $debugPayload]);

// 🛠 Validate file type and size
$mimeType = mime_content_type($photo['tmp_name']);
$validTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mimeType, $validTypes)) {
  logDebug(['error' => 'Unsupported image type', 'mime_type' => $mimeType]);
  echo json_encode(['success' => false, 'error' => 'Unsupported image type. Only JPG, PNG, or WebP allowed.']);
  exit;
}

if ($photo['size'] > 5 * 1024 * 1024) { // 5MB limit
  logDebug(['error' => 'File too large', 'file_size' => $photo['size']]);
  echo json_encode(['success' => false, 'error' => 'File size exceeds 5MB limit.']);
  exit;
}

// 🛠 Prepare paths
$uploadDir = __DIR__ . "/../uploads/trip_photos/client_{$client_id}/{$assigned_package_id}/";
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
  logDebug(['error' => 'Failed to create upload directory', 'directory' => $uploadDir]);
  echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
  exit;
}

$extension = pathinfo($photo['name'], PATHINFO_EXTENSION);
$filename = uniqid('photo_', true) . '.' . strtolower($extension);
$destinationPath = $uploadDir . $filename;

// 📤 Move uploaded file
if (!move_uploaded_file($photo['tmp_name'], $destinationPath)) {
  logDebug(['error' => 'File move failed', 'destination' => $destinationPath]);
  echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
  exit;
}

// 🗂 Save to database
$file_path = "../uploads/trip_photos/client_{$client_id}/{$assigned_package_id}/" . $filename;
$stmt = $conn->prepare("
  INSERT INTO client_trip_photos (
    client_id, assigned_package_id, file_name, file_path, mime_type,
    caption, document_type, compression_status, document_status,
    day, uploaded_at, location_tag
  ) VALUES (
    ?, ?, ?, ?, ?, ?, 'photo', 'uncompressed', 'pending',
    ?, NOW(), ?
  )
");

if (!$stmt) {
  logDebug(['error' => 'SQL prepare failed', 'mysqli_error' => $conn->error]);
  echo json_encode(['success' => false, 'error' => 'Database prepare failed']);
  exit;
}

$stmt->bind_param(
  "iissssss",
  $client_id,
  $assigned_package_id,
  $filename,
  $file_path,
  $mimeType,
  $caption,
  $day,
  $location_tag
);

$executed = $stmt->execute();
logDebug(['stage' => 'SQL execution', 'executed' => $executed, 'mysqli_error' => $stmt->error]);

if ($executed) {
  // ✅ Fetch client name and assigned admin ID
  $clientQuery = $conn->prepare("
    SELECT full_name, assigned_admin_id, assigned_package_id 
    FROM clients 
    WHERE id = ?
  ");
  $clientQuery->bind_param("i", $client_id);
  $clientQuery->execute();
  $clientResult = $clientQuery->get_result();
  $clientData = $clientResult->fetch_assoc();

  $clientName = $clientData['full_name'] ?? 'Client';
  $assignedAdminId = $clientData['assigned_admin_id'] ?? null;
  $assignedPackageId = $clientData['assigned_package_id'] ?? null;

  // ✅ Fetch package name
  $packageName = 'Unnamed Package';
  if ($assignedPackageId) {
    $packageQuery = $conn->prepare("
      SELECT package_name 
      FROM tour_packages 
      WHERE id = ?
    ");
    $packageQuery->bind_param("i", $assignedPackageId);
    $packageQuery->execute();
    $packageResult = $packageQuery->get_result();
    $packageName = $packageResult->fetch_assoc()['package_name'] ?? 'Unnamed Package';
  }

  // ✅ Send notification to assigned admin
  if ($assignedAdminId) {
    notify([
      'recipient_type' => 'admin',
      'recipient_id' => $assignedAdminId,
      'event' => 'client_uploaded_photo',
      'context' => [
        'client_id' => $client_id,
        'client_name' => $clientName,
        'package_id' => $assignedPackageId,
        'package_name' => $packageName,
        'file_name' => $filename,
        'caption' => $caption,
        'day' => $day,
        'location_tag' => $location_tag
      ]
    ]);
  }
}

echo json_encode(['success' => $executed]);
exit;

// 🧾 Debug logger function (append to single file)
function logDebug($data) {
  $logDir = __DIR__ . '/../logs/';
  if (!is_dir($logDir)) mkdir($logDir, 0777, true);

  $logFile = $logDir . "upload_debug_log.json";

  $entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'entry' => $data
  ];

  file_put_contents($logFile, json_encode($entry, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);
}