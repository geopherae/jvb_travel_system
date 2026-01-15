<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/status-helpers.php';
require_once __DIR__ . '/../includes/helpers.php';

$AIRPORTS = require __DIR__ . '/../includes/airports.php';


// 🧼 Sanitize input
$packageId   = (int) ($_POST['package_id'] ?? 0);
$name        = trim($_POST['package_name'] ?? '');
$description = trim($_POST['package_description'] ?? '');
$price       = (float) ($_POST['price'] ?? 0);
$days        = (int) ($_POST['day_duration'] ?? 0);
$nights      = (int) ($_POST['night_duration'] ?? 0);
$isFavorite  = isset($_POST['is_favorite']) ? 1 : 0;
$origin      = strtoupper(trim($_POST['origin'] ?? ''));
$destination = strtoupper(trim($_POST['destination'] ?? ''));

// ❌ Guard clause
if ($packageId <= 0 || empty($name) || ($days + $nights) <= 0 || $nights > $days) {
  header("Location: ../admin/admin_tour_packages.php?status=error");
  exit();
}

function redirectWithStatus(string $status): void {
  $_SESSION['modal_status'] = $status;
  header("Location: ../admin/admin_tour_packages.php");
  exit();
}

// ✈️ Flatten airport codes for validation
$validCodes = [];
foreach ($AIRPORTS as $region => $codes) {
  $validCodes = array_merge($validCodes, array_keys($codes));
}
if (!in_array($origin, $validCodes) || !in_array($destination, $validCodes)) {
  error_log("❌ Invalid airport code: $origin or $destination");
  redirectWithStatus('invalid_airport');
}

// 🧾 Validate JSON
function validateJson(string $json): string {
  $parsed = json_decode($json, true);
  return is_array($parsed) ? json_encode($parsed) : json_encode([]);
}

$inclusionsJson = validateJson($_POST['inclusions_json'] ?? '[]');
$itineraryJson  = validateJson($_POST['itinerary_json'] ?? '[]');

// 🖼 Image upload
function handleImageUpload(): ?string {
  if (!isset($_FILES['tour_cover_image']) || $_FILES['tour_cover_image']['error'] !== UPLOAD_ERR_OK) return null;

  $ext = strtolower(pathinfo($_FILES['tour_cover_image']['name'], PATHINFO_EXTENSION));
  $allowedExts = ['jpg', 'jpeg', 'png'];
  $maxSizeMB = 3;

  if (!in_array($ext, $allowedExts)) redirectWithStatus('invalid_file');
  if ($_FILES['tour_cover_image']['size'] > ($maxSizeMB * 1024 * 1024)) redirectWithStatus('too_large');

  $serverDir  = '../images/tour_packages_banners/';
  $newName    = 'cover_' . time() . '_' . rand(1000,9999) . '.' . $ext;
  $fullPath   = $serverDir . $newName;

  if (!file_exists($serverDir) && !mkdir($serverDir, 0755, true)) {
    error_log("❌ Failed to create image directory.");
    redirectWithStatus('error');
  }

  if (move_uploaded_file($_FILES['tour_cover_image']['tmp_name'], $fullPath)) {
    return $newName; // ✅ Return filename only
  }

  error_log("❌ Image move failed: $fullPath");
  redirectWithStatus('error');
}

$imageFileName = handleImageUpload() ?? ($_POST['existing_image'] ?? '');

// ⭐ Favorite enforcement — max 3
if ($isFavorite === 1) {
  $existingFavs = $conn->query("SELECT id FROM tour_packages WHERE is_favorite = 1 ORDER BY id ASC");
  if ($existingFavs && $existingFavs->num_rows >= 3) {
    $oldest = $existingFavs->fetch_assoc();
    $conn->query("UPDATE tour_packages SET is_favorite = 0 WHERE id = " . (int) $oldest['id']);
  }
}

$demotedId = null;
if ($isFavorite === 1 && !$isAlreadyFavorite) {
  $result = $conn->query("SELECT id FROM tour_packages WHERE is_favorite = 1 ORDER BY id ASC");
  if ($result && $result->num_rows >= 3) {
    while ($row = $result->fetch_assoc()) {
      $existingId = (int)$row['id'];
      if ($existingId !== $packageId) {
        $updateStmt = $conn->prepare("UPDATE tour_packages SET is_favorite = 0 WHERE id = ?");
        $updateStmt->bind_param("i", $existingId);
        $updateStmt->execute();
        $demotedId = $existingId;
        break;
      }
    }
  }
}

// 🔒 Transaction-safe update
$conn->begin_transaction();

try {
  $sql = $imageFilename
    ? "UPDATE tour_packages SET package_name = ?, package_description = ?, price = ?, day_duration = ?, night_duration = ?, is_favorite = ?, tour_cover_image = ?, inclusions_json = ?, origin = ?, destination = ? WHERE id = ?"
    : "UPDATE tour_packages SET package_name = ?, package_description = ?, price = ?, day_duration = ?, night_duration = ?, is_favorite = ?, inclusions_json = ?, origin = ?, destination = ? WHERE id = ?";

  $stmt = $conn->prepare($sql);
  if ($imageFilename) {
    $stmt->bind_param("ssddisssssi", $name, $description, $price, $days, $nights, $isFavorite, $imageFilename, $inclusionsJson, $origin, $destination, $packageId);
  } else {
    $stmt->bind_param("ssddissssi", $name, $description, $price, $days, $nights, $isFavorite, $inclusionsJson, $origin, $destination, $packageId);
  }
  $stmt->execute();

  // 📍 Save itinerary separately
  $checkItinerary = $conn->prepare("SELECT id FROM tour_package_itinerary WHERE package_id = ?");
  $checkItinerary->bind_param("i", $packageId);
  $checkItinerary->execute();
  $result = $checkItinerary->get_result();

  if ($result && $result->num_rows > 0) {
    $updateItinerary = $conn->prepare("UPDATE tour_package_itinerary SET itinerary_json = ?, updated_at = NOW() WHERE package_id = ?");
    $updateItinerary->bind_param("si", $itineraryJson, $packageId);
    $updateItinerary->execute();
  } else {
    $insertItinerary = $conn->prepare("INSERT INTO tour_package_itinerary (package_id, itinerary_json, updated_at) VALUES (?, ?, NOW())");
    $insertItinerary->bind_param("is", $packageId, $itineraryJson);
    $insertItinerary->execute();
  }

  $conn->commit();
  $_SESSION['modal_status'] = 'updated';
  header("Location: ../admin/admin_tour_packages.php"); 

  exit();
} catch (Exception $e) {
  $conn->rollback();
  $_SESSION['modal_status'] = 'invalid_file';
  error_log("❌ Update failed for package {$packageId}: " . $e->getMessage());
  header("Location: ../admin/admin_tour_packages.php?status=error");
  exit();
}
?>