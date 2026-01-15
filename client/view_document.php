<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
guard('client'); // 🔒 Authenticated client access only

require_once __DIR__ . '/../actions/db.php'; // ✅ Centralized DB connection

$clientId = $_SESSION['client_id'] ?? null;
$docId    = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($docId <= 0 || !$clientId) {
  die("Invalid request.");
}

// 🔍 Fetch document metadata & verify client ownership
$stmt = $conn->prepare("SELECT file_name, file_path, document_type FROM uploaded_files WHERE id = ? AND client_id = ?");
$stmt->bind_param("ii", $docId, $clientId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  die("Document not found or unauthorized.");
}

$doc       = $result->fetch_assoc();
$filePath  = realpath(__DIR__ . '/../' . $doc['file_path']);

if (!$filePath || !file_exists($filePath)) {
  die("File not found.");
}

// 🖥 Output file inline
$mimeType = mime_content_type($filePath);
header("Content-Type: $mimeType");
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
readfile($filePath);
exit;