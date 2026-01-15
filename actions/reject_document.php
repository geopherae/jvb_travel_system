<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../actions/notify.php';
require_once __DIR__ . '/../includes/log_helper.php';
use function LogHelper\logDocumentRejection;

// 🚦 Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Invalid request method']);
  exit;
}

// ✅ Validate document ID
$documentId = isset($_POST['id']) ? (int) $_POST['id'] : null;
if (!$documentId) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing or invalid document ID']);
  exit;
}

// 🧑‍💼 Retrieve admin info
$adminInfo = $_SESSION['admin'] ?? null;
if (!$adminInfo || !isset($adminInfo['id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Session expired or unauthorized']);
  exit;
}

$adminId   = $adminInfo['id'];
$adminName = $adminInfo['first_name'] ?? 'Admin';

// 🧼 Sanitize additional inputs
$comments = trim($_POST['admin_comments'] ?? '');

// 🧠 Fetch existing document info
$stmt = $conn->prepare("SELECT file_name, client_id, document_type FROM uploaded_files WHERE id = ?");
$stmt->bind_param("i", $documentId);
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();

if (!$doc) {
  http_response_code(404);
  echo json_encode(['error' => 'Document not found']);
  exit;
}

$fileName   = $doc['file_name'];
$clientId   = $doc['client_id'];
$docType    = $doc['document_type'];

// 🆙 Update document status to Rejected
$stmt = $conn->prepare("
  UPDATE uploaded_files 
  SET 
    document_status = 'Rejected',
    admin_comments = ?, 
    status_updated_by = ?, 
    approved_at = NULL
  WHERE id = ?
");
$stmt->bind_param("ssi", $comments, $adminName, $documentId);

if ($stmt->execute()) {
  if ($stmt->affected_rows > 0) {
    http_response_code(200);
    echo json_encode(['success' => true]);

    // 🔔 Notify client
    notify([
      'recipient_type' => 'client',
      'recipient_id'   => $clientId,
      'event'          => 'document_rejected',
      'context'        => [
        'document_name' => $fileName,
        'admin_id'      => $adminId,
        'admin_name'    => $adminName,
        'reason'        => $comments ?: 'No reason provided'
      ]
    ]);

    // 🧾 Log audit
    logDocumentRejection($conn, [
      'actor_id'      => $adminId,
      'client_id'     => $clientId,
      'document_id'   => $documentId,
      'document_name' => $fileName,
      'comments'      => $comments,
      'source'        => 'reject_document.php'
    ]);

  } else {
    http_response_code(404);
    echo json_encode(['error' => 'Document already rejected or unchanged']);
  }
} else {
  http_response_code(500);
  echo json_encode(['error' => 'Database error: ' . $stmt->error]);
}
?>