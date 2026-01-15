<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
guard('admin'); // 🔐 Ensure only authenticated admins can access

require_once __DIR__ . '/../actions/db.php'; // ✅ Centralized DB connection

header('Content-Type: application/json');

// ✅ Decode incoming JSON
$data = json_decode(file_get_contents('php://input'), true);

// ✅ Validate input structure
if (!isset($data['id']) || !isset($data['status'])) {
  echo json_encode(['success' => false, 'message' => 'Invalid input']);
  exit();
}

$id     = (int) $data['id'];
$status = trim($data['status']);

// Optional: validate allowed status values
$validStatuses = ['Pending', 'Approved', 'Rejected'];
if (!in_array($status, $validStatuses)) {
  echo json_encode(['success' => false, 'message' => 'Invalid status value']);
  exit();
}

// ✅ Update document status
$stmt = $conn->prepare("UPDATE uploaded_files SET document_status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $id);

if ($stmt->execute()) {
  if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Document status updated']);
  } else {
    echo json_encode(['success' => false, 'message' => 'No document found with that ID']);
  }
} else {
  error_log("Document status update failed: " . $stmt->error);
  echo json_encode(['success' => false, 'message' => 'Database error']);
}

$stmt->close();