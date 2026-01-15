<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/status-helpers.php';
session_start();

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
if (!$client_id) {
  echo json_encode([]);
  exit;
}

// 🔹 Get assigned package ID
function getAssignedPackageId(mysqli $conn, int $client_id): ?int {
  $stmt = $conn->prepare("SELECT assigned_package_id FROM clients WHERE id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_column();
}

// 🔹 Get itinerary map
function getItineraryMap(mysqli $conn, int $client_id): array {
  $stmt = $conn->prepare("SELECT itinerary_json FROM client_itinerary WHERE client_id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $json = $stmt->get_result()->fetch_column();
  $map = [];

  foreach (json_decode($json, true) ?? [] as $day) {
    $map[(int)($day['day_number'] ?? 0)] = $day['day_title'] ?? '';
  }

  return $map;
}

// 🔹 Fetch and group trip photos by day
function getPhotosGroupedByDay(mysqli $conn, int $client_id, int $package_id): array {
  $stmt = $conn->prepare("
    SELECT 
      p.id, p.file_name, p.caption, p.document_status, p.uploaded_at, 
      p.approved_at, p.status_updated_by, p.day,
      GROUP_CONCAT(t.tag_name) AS tags
    FROM client_trip_photos p
    LEFT JOIN photo_tags_map ptm ON ptm.photo_id = p.id
    LEFT JOIN photo_tags t ON t.id = ptm.tag_id
    WHERE p.client_id = ? AND p.assigned_package_id = ?
    GROUP BY p.id
    ORDER BY p.day ASC, p.uploaded_at DESC
  ");
  $stmt->bind_param("ii", $client_id, $package_id);
  $stmt->execute();
  $result = $stmt->get_result();

$grouped = [];
while ($row = $result->fetch_assoc()) {
  $status = $row['document_status'] ?? 'Pending';

  // ❌ Skip rejected photos
  if ($status === 'Rejected') continue;

  $day = (int)($row['day'] ?? 0);

  $grouped[$day][] = [
    'id'                => $row['id'],
    'file_name'         => $row['file_name'],
    'caption'           => $row['caption'] ?? '',
    'status'            => $status,
    'status_class'      => getStatusBadgeClass($status),
    'pending_overlay'   => $status === 'Pending', // ✅ Flag for frontend
    'day'               => $day,
    'uploaded_at'       => date('M j, Y', strtotime($row['uploaded_at'])),
    'approved_at'       => $row['approved_at'] ? date('M j, Y', strtotime($row['approved_at'])) : null,
    'status_updated_by' => $row['status_updated_by'] ?? '—',
    'tags'              => explode(',', $row['tags'] ?? ''),
    'url'               => "../uploads/trip_photos/client_{$client_id}/{$package_id}/" . rawurlencode($row['file_name'])
  ];
}

  return $grouped;
}

// 🔹 Execute
$assigned_package_id = getAssignedPackageId($conn, $client_id);
$itineraryMap = getItineraryMap($conn, $client_id);
$photosByDay = $assigned_package_id ? getPhotosGroupedByDay($conn, $client_id, $assigned_package_id) : [];

$galleryData = [];
foreach ($itineraryMap as $dayNum => $dayTitle) {
  $galleryData[] = [
    'day_number' => $dayNum,
    'day_title'  => $dayTitle,
    'photos'     => $photosByDay[$dayNum] ?? []
  ];
}

// 🔹 Fallback empty state
if (empty($galleryData) || array_reduce($galleryData, fn($carry, $day) => $carry && empty($day['photos']), true)) {
  $galleryData = [[
    'day_number' => 1,
    'day_title'  => 'Trip Gallery',
    'photos'     => []
  ]];
}

echo json_encode($galleryData);