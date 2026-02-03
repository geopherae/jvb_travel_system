<?php
require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;

// Authenticate before any output
guard('any');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../components/status_alert.php';

header('Content-Type: application/json');

$actorId = $_SESSION['client_id'] ?? $_SESSION['admin']['id'] ?? null;
$role = isset($_SESSION['client_id']) ? 'client' : 'admin';

$input = json_decode(file_get_contents('php://input'), true);
$docId = isset($input['id']) ? (int) $input['id'] : 0;

if ($docId <= 0 || !$actorId) {
  echo json_encode(['success' => false, 'message' => 'Invalid document ID or session']);
  exit();
}

// 🔍 Confirm document ownership (clients) or existence (admins)
if ($role === 'client') {
  $stmt = $conn->prepare("SELECT file_path, client_id FROM uploaded_files WHERE id = ? AND client_id = ?");
  $stmt->bind_param("ii", $docId, $actorId);
} else {
  $stmt = $conn->prepare("SELECT file_path, client_id FROM uploaded_files WHERE id = ?");
  $stmt->bind_param("i", $docId);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'Document not found or unauthorized']);
  exit();
}

$doc = $result->fetch_assoc();
$filePath = __DIR__ . '/../' . $doc['file_path'];

// 🗑️ Delete physical file
if ($filePath && file_exists($filePath)) {
  unlink($filePath);
}

// 📂 Remove DB record
$delStmt = $conn->prepare("DELETE FROM uploaded_files WHERE id = ?");
$delStmt->bind_param("i", $docId);
$delStmt->execute();

// 🧾 Log the deletion
$logStmt = $conn->prepare("
  INSERT INTO system_logs (action_type, actor_id, payload, created_at)
  VALUES (?, ?, ?, NOW())
");

$actionType = 'delete_document';
$payload = json_encode([
  'deleted_doc_id'     => $docId,
  'deleted_by'         => $role,
  'target_client_id'   => $doc['client_id'],
  'file_path'          => $doc['file_path']
]);

$logStmt->bind_param("sss", $actionType, $actorId, $payload);
$logStmt->execute();

// ✅ Set toast status
$_SESSION['modal_status'] = 'document_deleted';
echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
exit();