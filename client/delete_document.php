<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('client'); // 🔒 Ensure only authenticated clients can proceed

require_once __DIR__ . '/../actions/db.php'; // ✅ Centralized DB connection

$clientId = $_SESSION['client_id'] ?? null;
$docId    = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($docId <= 0 || !$clientId) {
  die("Invalid or missing document ID.");
}

// 🔍 Verify document ownership
$stmt = $conn->prepare("SELECT file_path FROM uploaded_files WHERE id = ? AND client_id = ?");
$stmt->bind_param("ii", $docId, $clientId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  die("Unauthorized access or document not found.");
}

$doc       = $result->fetch_assoc();
$filePath  = __DIR__ . '/../' . $doc['file_path'];

// 🗑️ Delete file from server
if ($filePath && file_exists($filePath)) {
  unlink($filePath);
}

// 🗂 Remove DB record
$del = $conn->prepare("DELETE FROM uploaded_files WHERE id = ? AND client_id = ?");
$del->bind_param("ii", $docId, $clientId);
$del->execute();

// ✅ Flag for feedback
$_SESSION['deleted_success'] = true;

// 🚦 Redirect to dashboard
header("Location: client_dashboard.php");
exit;