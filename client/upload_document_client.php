<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('client'); // 🔒 Only authenticated clients allowed

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/checklist_helpers.php';
require_once __DIR__ . '/../actions/notify.php';
require_once __DIR__ . '/../includes/log_helper.php';
include __DIR__ . '/../components/status_alert.php';

use function LogHelper\logDocumentUpload;

// ✅ Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit("Invalid request method.");
}

// ✅ Validate session
$clientId = $_SESSION['client_id'] ?? null;
if (!$clientId) {
  http_response_code(403);
  exit("Unauthorized access.");
}

// ✅ Validate document type
$docType = $_POST['document_type'] ?? '';
$allowedTypes = ['Passport', 'Valid ID', 'Visa', 'Service Voucher', 'Airline Ticket', 'PH Travel Tax', 'Acknowledgement Receipt', 'Other'];
if (!in_array($docType, $allowedTypes)) {
  setStatusAndRedirect('upload_failed', 400, "Invalid document type.");
}

// ✅ Validate file
$file = $_FILES['document_file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
  setStatusAndRedirect('upload_failed', 400, "File upload failed.");
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
if (!in_array($mimeType, $allowedMime)) {
  setStatusAndRedirect('invalid_file', 415, "Unsupported file type.");
}

if ($file['size'] > 25 * 1024 * 1024) {
  setStatusAndRedirect('too_large', 413, "File too large.");
}

// ✅ Generate sanitized file name
$uniqueName = generateFileName($file, $_POST['custom_name'] ?? '');
$uploadDir = __DIR__ . "/../uploads/client_{$clientId}";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$targetPath   = $uploadDir . '/' . $uniqueName;
$relativePath = "uploads/client_{$clientId}/{$uniqueName}";

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
  setStatusAndRedirect('upload_failed', 500, "Failed to store uploaded file.");
}

// ✅ Insert into database
$stmt = $conn->prepare("INSERT INTO uploaded_files 
  (client_id, file_name, file_path, document_type, mime_type, document_status, uploaded_at) 
  VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
$stmt->bind_param("issss", $clientId, $uniqueName, $relativePath, $docType, $mimeType);
$executed = $stmt->execute();

if ($executed) {
  // ✅ Checklist progress
  evaluateChecklistTask($conn, $clientId, 1, 'id_uploaded');
  evaluateChecklistTask($conn, $clientId, 1, 'id_approved');

  // ✅ Notify assigned admin
  $clientMeta = fetchClientMeta($conn, $clientId);
  $packageName = fetchPackageName($conn, $clientMeta['assigned_package_id'] ?? null);

  if (!empty($clientMeta['assigned_admin_id'])) {
    notify([
      'recipient_type' => 'admin',
      'recipient_id'   => $clientMeta['assigned_admin_id'],
      'event'          => 'client_uploaded_document',
      'context'        => [
        'client_id'     => $clientId,
        'client_name'   => $clientMeta['full_name'] ?? 'Client',
        'document_name'     => $uniqueName,
        'document_type' => $docType,
        'package_id'    => $clientMeta['assigned_package_id'] ?? null,
        'package_name'  => $packageName
      ]
    ]);
  }

  // ✅ Audit log
  logDocumentUpload($conn, [
    'actor_id'      => $clientId,
    'client_id'     => $clientId,
    'file_name'     => $uniqueName,
    'document_type' => $docType,
    'mime_type'     => $mimeType,
    'file_path'     => $relativePath,
    'source'        => 'upload_document_client.php'
  ]);

  $_SESSION['modal_status'] = 'upload_success';
} else {
  $_SESSION['modal_status'] = 'upload_failed';
}

header("Location: ../client/client_dashboard.php");
exit();


// 🔧 Helpers
function setStatusAndRedirect(string $status, int $code, string $message): void {
  http_response_code($code);
  $_SESSION['modal_status'] = $status;
  error_log("Upload error [$code]: $message");
  header("Location: ../client/client_dashboard.php");
  exit();
}

function generateFileName(array $file, string $customName): string {
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

  if ($customName !== '') {
    $base = preg_replace('/[^a-zA-Z0-9 \-]/', '', $customName);
    $base = preg_replace('/\s+/', ' ', $base);
    $base = mb_substr($base, 0, 60);
    return $base . '.' . $ext;
  }

  $original = pathinfo($file['name'], PATHINFO_FILENAME);
  $clean = preg_replace('/[^a-zA-Z0-9 \-]/', '', $original);
  $clean = preg_replace('/\s+/', ' ', $clean);
  $clean = mb_substr($clean, 0, 50);
  $hash = substr(md5(time() . $file['tmp_name']), 0, 6);
  return $clean . ' ' . $hash . '.' . $ext;
}

function fetchClientMeta(mysqli $conn, int $clientId): array {
  $stmt = $conn->prepare("SELECT full_name, assigned_admin_id, assigned_package_id FROM clients WHERE id = ?");
  $stmt->bind_param("i", $clientId);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc() ?? [];
}

function fetchPackageName(mysqli $conn, ?int $packageId): string {
  if (!$packageId) return 'Unnamed Package';
  $stmt = $conn->prepare("SELECT package_name FROM tour_packages WHERE id = ?");
  $stmt->bind_param("i", $packageId);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc()['package_name'] ?? 'Unnamed Package';
}