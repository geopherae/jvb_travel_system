<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin'); // 🔒 Only admins can access this handler

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../actions/notify.php';
require_once __DIR__ . '/../includes/log_helper.php';
include __DIR__ . '/../components/status_alert.php';

use function LogHelper\logAdminDocumentUpload;

// ✅ Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  setStatusAndRedirect('upload_failed', 405, "Invalid request method.");
}

// ✅ Validate client ID
$clientId = $_POST['client_id'] ?? null;
if (!$clientId) {
  setStatusAndRedirect('upload_failed', 400, "Missing target client ID.");
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

// ✅ Insert into database (Auto-approved for admin uploads)
$adminId = $_SESSION['admin']['id'] ?? 0;
$stmt = $conn->prepare("INSERT INTO uploaded_files 
  (client_id, file_name, file_path, document_type, mime_type, document_status, uploaded_at, approved_at) 
  VALUES (?, ?, ?, ?, ?, 'Approved', NOW(), NOW())");
$stmt->bind_param("issss", $clientId, $uniqueName, $relativePath, $docType, $mimeType);
$executed = $stmt->execute();

if ($executed) {
  // ✅ Audit log
  logAdminDocumentUpload($conn, [
    'admin_id'      => $adminId,
    'client_id'     => $clientId,
    'file_name'     => $uniqueName,
    'document_type' => $docType,
    'mime_type'     => $mimeType,
    'file_path'     => $relativePath,
    'source'        => 'upload_document_admin.php'
  ]);

  // ✅ Optional: Notify client
  notify([
    'recipient_type' => 'client',
    'recipient_id'   => $clientId,
    'event'          => 'document_uploaded_by_admin',
    'context'        => [
      'document_name' => $uniqueName,
      'document_type' => $docType,
      'admin_id'      => $adminId,
      'admin_name'    => $_SESSION['admin']['first_name'] ?? 'Admin'
    ]
  ]);

  $_SESSION['modal_status'] = 'upload_success';
} else {
  $_SESSION['modal_status'] = 'upload_failed';
}

header("Location: view_client.php?client_id=" . urlencode($clientId));
exit();


// 🔧 Helpers
function setStatusAndRedirect(string $status, int $code, string $message): void {
  http_response_code($code);
  $_SESSION['modal_status'] = $status;
  error_log("Admin upload error [$code]: $message");
  header("Location: view_client.php?client_id=" . urlencode($_POST['client_id'] ?? ''));
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