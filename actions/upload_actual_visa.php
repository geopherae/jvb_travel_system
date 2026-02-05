<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/image_compression_helper.php';
require_once __DIR__ . '/../includes/log_helper.php';

use function Auth\guard;
use function Auth\getActorContext;
use function LogHelper\logClientOnboardingAudit;

// Only admins can upload actual visa documents
guard('admin');

header('Content-Type: application/json');

try {
  // Validate input
  $visaAppId = (int)($_POST['visa_application_id'] ?? 0);
  $notes = trim($_POST['notes'] ?? '');
  
  if (!$visaAppId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing visa application ID.']);
    exit;
  }

  // Validate file upload
  if (!isset($_FILES['actual_visa_file']) || $_FILES['actual_visa_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File upload failed.']);
    exit;
  }

  $file = $_FILES['actual_visa_file'];
  $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
  $maxFileSize = 10 * 1024 * 1024; // 10MB

  // Validate file size
  if ($file['size'] > $maxFileSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit.']);
    exit;
  }

  // Validate MIME type
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);

  if (!in_array($mimeType, $allowedMimes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, JPG, JPEG, PNG are allowed.']);
    exit;
  }

  // Get visa application details
  $appStmt = $conn->prepare("
    SELECT cva.client_id, cva.status, c.full_name
    FROM client_visa_applications cva
    JOIN clients c ON cva.client_id = c.id
    WHERE cva.id = ?
  ");
  $appStmt->bind_param("i", $visaAppId);
  $appStmt->execute();
  $app = $appStmt->get_result()->fetch_assoc();
  $appStmt->close();

  if (!$app) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Visa application not found.']);
    exit;
  }

  // Verify status is Complete
  if ($app['status'] !== 'Complete') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Can only upload visa when application status is Complete.']);
    exit;
  }

  $clientId = $app['client_id'];
  $clientName = $app['full_name'];

  // Prepare upload directory
  $uploadBaseDir = __DIR__ . '/../uploads/actual_visa';
  $uploadDir = $uploadBaseDir . '/client_' . $clientId . '/application_' . $visaAppId;

  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  // Generate filename: Visa_[client_name]_[YYYYMMDD]
  $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  
  // Sanitize client name for filename (replace spaces and special chars with underscores)
  $sanitizedClientName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $clientName);
  $sanitizedClientName = preg_replace('/_+/', '_', $sanitizedClientName); // Remove duplicate underscores
  $sanitizedClientName = trim($sanitizedClientName, '_'); // Remove leading/trailing underscores
  
  // Format date as YYYYMMDD
  $dateString = date('Ymd');
  
  $newFileName = 'Visa_' . $sanitizedClientName . '_' . $dateString . '.' . $fileExtension;
  $uploadPath = $uploadDir . '/' . $newFileName;

  // Move uploaded file
  if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
    exit;
  }

  // Store relative path for database
  $relativePath = 'uploads/actual_visa/client_' . $clientId . '/application_' . $visaAppId . '/' . $newFileName;

  // Get admin ID
  $adminId = $_SESSION['admin']['id'];

  // Insert into database
  $insertStmt = $conn->prepare("
    INSERT INTO client_actual_visa_documents (
      visa_application_id,
      file_name,
      file_path,
      file_size,
      mime_type,
      uploaded_by,
      notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  
  $insertStmt->bind_param(
    "issisis",
    $visaAppId,
    $newFileName,
    $relativePath,
    $file['size'],
    $mimeType,
    $adminId,
    $notes
  );

  if (!$insertStmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database insert failed: ' . $conn->error]);
    exit;
  }

  $insertedId = $conn->insert_id;
  $insertStmt->close();

  // Audit log
  $actor = getActorContext();
  logClientOnboardingAudit(
    $conn,
    $clientId,
    'visa_actual_document_uploaded',
    [
      'visa_application_id' => $visaAppId,
      'document_id' => $insertedId,
      'file_name' => $newFileName,
      'file_size' => $file['size'],
      'notes' => $notes ?: null,
    ],
    $actor
  );

  // Success response
  echo json_encode([
    'success' => true,
    'message' => 'Actual visa document uploaded successfully!',
    'data' => [
      'id' => $insertedId,
      'file_name' => $newFileName,
      'file_path' => $relativePath,
    ]
  ]);

} catch (Exception $e) {
  error_log("Upload Actual Visa Error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
}
