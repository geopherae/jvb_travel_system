<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../actions/notify.php';
require_once __DIR__ . '/../includes/log_helper.php';
use function LogHelper\logDocumentUpdate;

// ✅ Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Invalid request method']);
  exit;
}

// ✅ Required fields
$documentId = isset($_POST['id']) ? (int) $_POST['id'] : null;
$fileName   = isset($_POST['file_name']) ? trim($_POST['file_name']) : '';

if (!$documentId || $fileName === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing or invalid document ID or file name']);
  exit;
}

// 🧑‍💼 Admin info
$adminInfo = $_SESSION['admin'] ?? null;
$adminId   = $adminInfo['id'] ?? 0;
$adminName = $adminInfo['first_name'] ?? 'Admin';

// 🧼 Sanitize optional fields
$docType  = trim($_POST['document_type'] ?? '');
$status   = trim($_POST['document_status'] ?? '');
$comments = trim($_POST['admin_comments'] ?? '');

$approvedAt = ($status === 'Approved') ? date('Y-m-d H:i:s') : null;

// 🧠 Fetch original values
$stmt = $conn->prepare("SELECT client_id, file_name, document_type, document_status, admin_comments FROM uploaded_files WHERE id = ?");
$stmt->bind_param("i", $documentId);
$stmt->execute();
$result = $stmt->get_result();
$original = $result->fetch_assoc();

$clientId         = $original['client_id'] ?? null;
$originalFileName = $original['file_name'] ?? '';
$originalDocType  = $original['document_type'] ?? '';
$originalStatus   = $original['document_status'] ?? '';
$originalComments = $original['admin_comments'] ?? '';

if (!$clientId) {
  http_response_code(404);
  echo json_encode(['success' => false, 'error' => 'Document not found']);
  exit;
}

// 📝 Update document
$stmt = $conn->prepare("
  UPDATE uploaded_files
  SET 
    file_name = ?, 
    document_type = ?, 
    document_status = ?, 
    admin_comments = ?, 
    status_updated_by = ?, 
    approved_at = ?
  WHERE id = ?
");
$stmt->bind_param("ssssssi", $fileName, $docType, $status, $comments, $adminName, $approvedAt, $documentId);

if ($stmt->execute()) {
  // 🔔 Notify client if status changed
  if ($status && $status !== $originalStatus) {
    // Map status to correct event type
    $eventType = 'document_pending_review';
    if ($status === 'Approved') {
      $eventType = 'document_approved';
    } elseif ($status === 'Rejected') {
      $eventType = 'document_rejected';
    }
    
    notify([
      'recipient_type' => 'client',
      'recipient_id'   => $clientId,
      'event'          => $eventType,
      'context'        => [
        'document_name' => $fileName,
        'admin_id'      => $adminId,
        'admin_name'    => $adminName,
        'reason'        => $comments ?: 'No reason provided'
      ]
    ]);
  }

  // 🧾 Determine kpi_subtag
  $kpi_subtag = null;
  if ($status === 'Approved' && $status !== $originalStatus) {
    $kpi_subtag = 'approve_document';
  } elseif ($status === 'Rejected' && $status !== $originalStatus) {
    $kpi_subtag = 'reject_document';
  } elseif (
    $fileName !== $originalFileName ||
    $docType !== $originalDocType ||
    $comments !== $originalComments
  ) {
    $kpi_subtag = 'edit_metadata';
  }

  // 🧾 Log audit
  logDocumentUpdate($conn, [
    'actor_id'      => $adminId,
    'client_id'     => $clientId,
    'document_id'   => $documentId,
    'file_name'     => $fileName,
    'document_type' => $docType,
    'status'        => $status,
    'comments'      => $comments,
    'source'        => 'update_document.php',
    'kpi_subtag'    => $kpi_subtag
  ]);

  echo json_encode(['success' => true]);

} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Update failed']);
}
?>