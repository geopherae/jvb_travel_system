<?php
/**
 * Soft refresh endpoint for trip photo gallery
 * Returns updated gallery data after status changes
 */
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
  // Prevent direct access
}

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : (int)($_SESSION['client_id'] ?? 0);

if (!$client_id) {
  echo json_encode(['success' => false, 'message' => 'Client ID missing']);
  exit;
}

try {
  // Get assigned package ID
  $stmt = $conn->prepare("SELECT assigned_package_id FROM clients WHERE id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $assigned_package_id = $stmt->get_result()->fetch_column();

  if (!$assigned_package_id) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
  }

  // Get itinerary map
  $stmt = $conn->prepare("SELECT itinerary_json FROM client_itinerary WHERE client_id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $json = $stmt->get_result()->fetch_column();
  $itineraryMap = [];
  foreach (json_decode($json, true) ?? [] as $day) {
    $itineraryMap[(int)($day['day_number'] ?? 0)] = $day['day_title'] ?? '';
  }

  // Get trip photos grouped by day
  $stmt = $conn->prepare("
    SELECT 
      p.id, p.file_name, p.caption, p.day, p.uploaded_at, p.document_status,
      p.scope_tag, p.location_tag, p.status_updated_by, p.approved_at,
      p.assigned_package_id,
      tp.package_name
    FROM client_trip_photos p
    LEFT JOIN tour_packages tp ON p.assigned_package_id = tp.id
    WHERE p.client_id = ? AND p.assigned_package_id = ?
    ORDER BY p.day ASC, p.uploaded_at DESC
  ");
  $stmt->bind_param("ii", $client_id, $assigned_package_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $photosByDay = [];
  while ($row = $result->fetch_assoc()) {
    $status = $row['document_status'] ?? 'Pending';
    $day = (int)($row['day'] ?? 0);

    if (!isset($photosByDay[$day])) {
      $photosByDay[$day] = [];
    }

    $photosByDay[$day][] = [
      'id'                  => (int) $row['id'],
      'file_name'           => $row['file_name'],
      'caption'             => $row['caption'] ?? '',
      'uploaded_at'         => $row['uploaded_at'],
      'document_status'     => $status,
      'status_class'        => getStatusClass($status),
      'pending_overlay'     => $status === 'Pending',
      'rejected_overlay'    => $status === 'Rejected',
      'scope_tag'           => $row['scope_tag'] ?? '',
      'location_tag'        => $row['location_tag'] ?? '',
      'day'                 => $day,
      'approved_at'         => $row['approved_at'] ? date('M j, Y', strtotime($row['approved_at'])) : null,
      'status_updated_by'   => $row['status_updated_by'] ?? '—',
      'assigned_package_id' => (int)($row['assigned_package_id'] ?? 0),
      'package_name'        => $row['package_name'] ?? 'Unassigned',
      'url'                 => "../uploads/trip_photos/client_{$client_id}/{$assigned_package_id}/" . rawurlencode($row['file_name'])
    ];
  }

  // Build gallery data
  $galleryData = [];
  foreach ($itineraryMap as $dayNum => $dayTitle) {
    $galleryData[] = [
      'day_number' => $dayNum,
      'day_title'  => $dayTitle,
      'photos'     => $photosByDay[$dayNum] ?? []
    ];
  }

  echo json_encode(['success' => true, 'data' => $galleryData]);

} catch (Exception $e) {
  error_log("Gallery refresh error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to refresh gallery']);
}

function getStatusClass($status) {
  return match($status) {
    'Approved' => 'bg-emerald-100 text-emerald-700 border border-emerald-300',
    'Rejected' => 'bg-red-100 text-red-700 border border-red-300',
    default    => 'bg-yellow-100 text-yellow-700 border border-yellow-300'
  };
}
?>
