<?php
require_once __DIR__ . '/db.php';

if (!isset($_GET['id'])) {
  http_response_code(400);
  exit();
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("
  SELECT 
    id, package_name AS name, package_description AS description, price, 
    day_duration AS days, night_duration AS nights, origin, destination,
    tour_cover_image AS image_raw, inclusions_json, package_itinerary_json AS itinerary_json,
    is_favorite
  FROM tour_packages 
  LEFT JOIN tour_package_itinerary ON tour_package_itinerary.package_id = tour_packages.id
  WHERE tour_packages.id = ? AND is_deleted = 0
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$tour = $result->fetch_assoc();

if (!$tour) {
  http_response_code(404);
  exit();
}

$image = $tour['image_raw']
  ? '../images/tour_packages_banners/' . ltrim($tour['image_raw'], '/')
  : '../images/default_trip_cover.jpg';

$tour['image'] = $image;
$tour['inclusions'] = json_decode($tour['inclusions_json'] ?? '[]', true) ?? [];
$tour['itinerary'] = json_decode($tour['itinerary_json'] ?? '[]', true) ?? [];

unset($tour['image_raw'], $tour['inclusions_json'], $tour['itinerary_json']);

header('Content-Type: application/json');
echo json_encode($tour);