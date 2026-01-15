<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../actions/db.php';

session_start();
if (!isset($_SESSION['client_id'])) {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$client_id = (int) $_SESSION['client_id'];

try {
  $stmt = $conn->prepare("
    SELECT 
      p.id, p.file_name, p.caption, p.day, p.uploaded_at, p.document_status,
      p.scope_tag, p.assigned_package_id,
      tp.package_name
    FROM client_trip_photos p
    LEFT JOIN tour_packages tp ON p.assigned_package_id = tp.id
    WHERE p.client_id = ?
    ORDER BY p.day ASC, p.uploaded_at DESC
  ");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $grouped = [];

  while ($row = $result->fetch_assoc()) {
    if (!isset($row['day'])) continue;
    $day = (int) $row['day'];

    $grouped[$day][] = [
      'id' => (int) $row['id'],
      'file_name' => $row['file_name'],
      'caption' => $row['caption'] ?? '',
      'uploaded_at' => date('Y-m-d H:i:s', strtotime($row['uploaded_at'])),
      'document_status' => $row['document_status'] ?? 'Pending',
      'status' => $row['document_status'] ?? 'Pending',
      'status_class' => match ($row['document_status']) {
        'Approved' => 'bg-emerald-100 text-emerald-700 border border-emerald-300',
        'Rejected' => 'bg-red-100 text-red-700 border border-red-300',
        default    => 'bg-yellow-100 text-yellow-700 border border-yellow-300'
      },
      'url' => "/uploads/client_{$client_id}/{$row['file_name']}",
      'scope_tag' => $row['scope_tag'] ?? '',
      'assigned_package_id' => isset($row['assigned_package_id']) ? (int) $row['assigned_package_id'] : null,
      'package_name' => $row['package_name'] ?? 'Unassigned'
    ];
  }

  echo json_encode(['days' => $grouped], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}