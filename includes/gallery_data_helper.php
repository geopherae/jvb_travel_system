<?php
// 🔹 Status class helper
function getStatusClass(string $status): string {
  return match ($status) {
    'Approved' => 'bg-emerald-100 text-emerald-700 border border-emerald-300',
    'Rejected' => 'bg-red-100 text-red-700 border border-red-300',
    default    => 'bg-yellow-100 text-yellow-700 border border-yellow-300'
  };
}

function getAssignedPackageId(mysqli $conn, int $client_id): ?int {
  $stmt = $conn->prepare("SELECT assigned_package_id FROM clients WHERE id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_column();
}

function parseItineraryJson(?string $json): array {
  if (empty($json)) return [];

  $decoded = json_decode($json, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Itinerary JSON decode error: " . json_last_error_msg());
    return [];
  }

  return array_map(function($day) {
    return [
      'day_number' => (int)($day['day_number'] ?? 0),
      'day_title'  => htmlspecialchars($day['day_title'] ?? '')
    ];
  }, $decoded ?? []);
}

function getItineraryMap(mysqli $conn, int $client_id): array {
  $stmt = $conn->prepare("SELECT itinerary_json FROM client_itinerary WHERE client_id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $json = $stmt->get_result()->fetch_column();
  $parsedDays = parseItineraryJson($json);

  $map = [];
  foreach ($parsedDays as $day) {
    if ($day['day_number'] > 0) {
      $map[$day['day_number']] = $day['day_title'];
    }
  }
  return $map;
}

function getPhotosGroupedByDay(mysqli $conn, int $client_id, int $package_id): array {
  $stmt = $conn->prepare("
    SELECT 
      p.id, p.file_name, p.caption, p.day, p.uploaded_at, p.document_status,
      p.scope_tag, p.assigned_package_id,
      tp.package_name
    FROM client_trip_photos p
    LEFT JOIN tour_packages tp ON p.assigned_package_id = tp.id
    WHERE p.client_id = ? AND p.assigned_package_id = ?
    ORDER BY p.day ASC, p.uploaded_at DESC
  ");
  $stmt->bind_param("ii", $client_id, $package_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $grouped = [];
  while ($row = $result->fetch_assoc()) {
    $day = (int)($row['day'] ?? 0);
    $grouped[$day][] = [
      'id'                  => (int) $row['id'],
      'file_name'           => $row['file_name'],
      'caption'             => $row['caption'] ?? '',
      'uploaded_at'         => date('M j, Y', strtotime($row['uploaded_at'])),
      'document_status'     => $row['document_status'] ?? 'Pending',
      'status_class'        => getStatusClass($row['document_status'] ?? 'Pending'),
      'scope_tag'           => $row['scope_tag'] ?? '',
      'day'                 => $day,
      'assigned_package_id' => (int)($row['assigned_package_id'] ?? 0),
      'package_name'        => $row['package_name'] ?? 'Unassigned',
      'url'                 => "../uploads/trip_photos/client_{$client_id}/{$package_id}/" . rawurlencode($row['file_name'])
    ];
  }

  return $grouped;
}

function buildGalleryData(mysqli $conn, int $client_id): array {
  $assigned_package_id = getAssignedPackageId($conn, $client_id);
  if (!$assigned_package_id) return [];

  $itineraryMap = getItineraryMap($conn, $client_id);
  $photosByDay = getPhotosGroupedByDay($conn, $client_id, $assigned_package_id);

  $galleryData = [];
  foreach ($itineraryMap as $dayNum => $dayTitle) {
    $galleryData[] = [
      'day_number' => $dayNum,
      'day_title'  => $dayTitle,
      'photos'     => $photosByDay[$dayNum] ?? []
    ];
  }

  return $galleryData;
}