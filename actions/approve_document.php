<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../actions/notify.php';
require_once __DIR__ . '/../includes/log_helper.php';
use function LogHelper\logDocumentApproval;


// 🚦 Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Invalid request method.');
}

// 🔍 Validate document ID
$documentId = isset($_POST['id']) ? (int) $_POST['id'] : null;
if (!$documentId) {
  http_response_code(400);
  exit('Missing or invalid document ID.');
}

// 🧑‍💼 Retrieve admin info
$adminInfo = $_SESSION['admin'] ?? null;
if (!$adminInfo || !isset($adminInfo['id'])) {
  http_response_code(401);
  exit('Session expired or unauthorized.');
}

$adminId = $adminInfo['id'];

// 📝 Get admin name
$stmt = $conn->prepare("SELECT first_name FROM admin_accounts WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$adminResult = $stmt->get_result();
$admin = $adminResult->fetch_assoc();

if (!$admin) {
  http_response_code(404);
  exit('Admin not found.');
}

$adminName = $admin['first_name'];

// 🆙 Update document status
$stmt = $conn->prepare("
  UPDATE uploaded_files 
  SET 
    document_status = 'Approved',
    approved_at = NOW(),
    status_updated_by = ?
  WHERE id = ?
");
$stmt->bind_param("si", $adminName, $documentId);

if ($stmt->execute()) {
  if ($stmt->affected_rows > 0) {
    http_response_code(200);
    echo "✅ Document approved successfully by $adminName.";

    // 🧠 Get document name and client ID
    $stmt = $conn->prepare("SELECT file_name, client_id FROM uploaded_files WHERE id = ?");
    $stmt->bind_param("i", $documentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $doc = $result->fetch_assoc();

    if ($doc) {
      // 🔔 Notify client
      notify([
        'recipient_type' => 'client',
        'recipient_id'   => $doc['client_id'],
        'event'          => 'document_approved',
        'context'        => [
          'document_name' => $doc['file_name'],
          'admin_id'      => $adminId,
          'admin_name'    => $adminName
        ]
      ]);

      // 🧾 Log audit
      logDocumentApproval($conn, [
        'actor_id'      => $adminId,
        'document_id'   => $documentId,
        'client_id'     => $doc['client_id'],
        'document_name' => $doc['file_name'],
        'source'        => 'approve_document.php'
      ]);
    }

  } else {
    http_response_code(404);
    echo "Document not found or already approved.";
  }
} else {
  http_response_code(500);
  echo "Database error: " . $stmt->error;
}
?>