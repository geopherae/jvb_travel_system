<?php
require_once __DIR__ . '/../actions/db.php';

// 📊 Count documents by status
$statusStmt = $conn->prepare("
  SELECT document_status, COUNT(*) AS count
  FROM uploaded_files
  GROUP BY document_status
");
$statusStmt->execute();
$statusResult = $statusStmt->get_result();

$labels = [];
$data   = [];

while ($row = $statusResult->fetch_assoc()) {
  $labels[] = $row['document_status'];
  $data[]   = (int) $row['count'];
}

// 🧾 Output as JSON for charting
echo json_encode([
  'labels' => $labels,
  'data'   => $data
]);