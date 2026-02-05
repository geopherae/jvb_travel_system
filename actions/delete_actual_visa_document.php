<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log_helper.php';

use function Auth\guard;
use function Auth\getActorContext;
use function LogHelper\logClientOnboardingAudit;

// Only admins can delete actual visa documents
guard('admin');

header('Content-Type: application/json');

try {
  // Validate input
  $documentId = (int)($_POST['document_id'] ?? 0);
  
  if (!$documentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing document ID.']);
    exit;
  }

  // Fetch document details
  $stmt = $conn->prepare("
    SELECT 
      cavd.id,
      cavd.visa_application_id,
      cavd.file_name,
      cavd.file_path,
      cva.client_id,
      c.full_name
    FROM client_actual_visa_documents cavd
    JOIN client_visa_applications cva ON cavd.visa_application_id = cva.id
    JOIN clients c ON cva.client_id = c.id
    WHERE cavd.id = ?
  ");
  $stmt->bind_param("i", $documentId);
  $stmt->execute();
  $doc = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$doc) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Document not found.']);
    exit;
  }

  // Delete physical file
  $filePath = __DIR__ . '/../' . ltrim($doc['file_path'], '/');
  if (file_exists($filePath)) {
    if (!unlink($filePath)) {
      error_log("Failed to delete actual visa file: " . $filePath);
      // Continue anyway - we'll delete the DB record
    }
  }

  // Delete from database
  $deleteStmt = $conn->prepare("DELETE FROM client_actual_visa_documents WHERE id = ?");
  $deleteStmt->bind_param("i", $documentId);
  
  if (!$deleteStmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete document record.']);
    exit;
  }
  $deleteStmt->close();

  // Audit log
  $actor = getActorContext();
  logClientOnboardingAudit(
    $conn,
    $doc['client_id'],
    'visa_actual_document_deleted',
    [
      'document_id' => $documentId,
      'file_name' => $doc['file_name'],
      'visa_application_id' => $doc['visa_application_id'],
    ],
    $actor
  );

  // Success response
  echo json_encode([
    'success' => true,
    'message' => 'Actual visa document deleted successfully.'
  ]);

} catch (Exception $e) {
  error_log("Delete Actual Visa Document Error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
}
