<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/image_compression_helper.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php';
date_default_timezone_set('Asia/Manila');

// 🧼 Sanitize inputs
$name         = trim($_POST['package_name'] ?? '');
$description  = trim($_POST['package_description'] ?? '');
$price        = (float) ($_POST['price'] ?? 0);
$days         = (int) ($_POST['day_duration'] ?? 0);
$nights       = (int) ($_POST['night_duration'] ?? 0);
$isFavorite   = isset($_POST['is_favorite']) ? 1 : 0;
$origin       = trim($_POST['origin'] ?? '');
$destination  = trim($_POST['destination'] ?? '');
$inclusions   = $_POST['inclusions_json'] ?? '[]';
$itinerary    = $_POST['itinerary_json'] ?? '[]';
$duration     = "{$days} Days / {$nights} Nights";
$createdAt    = date('Y-m-d H:i:s');
$updatedAt    = $createdAt;

// 🖼 Handle tour cover image
$defaultImage  = 'default_trip_cover.jpg';
$uploadDir     = '../images/tour_packages_banners/';
$imageFilename = $defaultImage;

if (
  isset($_FILES['tour_cover_image']) &&
  $_FILES['tour_cover_image']['error'] === UPLOAD_ERR_OK &&
  is_uploaded_file($_FILES['tour_cover_image']['tmp_name'])
) {
  $ext = strtolower(pathinfo($_FILES['tour_cover_image']['name'], PATHINFO_EXTENSION));
  $allowedExts = ['jpg', 'jpeg', 'png'];
  $allowedMimeTypes = ['image/jpeg', 'image/png'];
  $maxSize = 3 * 1024 * 1024;

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $_FILES['tour_cover_image']['tmp_name']);
  finfo_close($finfo);

  if (in_array($ext, $allowedExts) && in_array($mimeType, $allowedMimeTypes) && $_FILES['tour_cover_image']['size'] <= $maxSize) {
    $imageFilename = 'cover_' . time() . '_' . rand(1000,9999) . '.jpg'; // Always save as JPG
    $fullPath = $uploadDir . $imageFilename;

    if (!file_exists($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }

    $success = compressImage($_FILES['tour_cover_image']['tmp_name'], $fullPath, $mimeType, 75);
    if (!$success) {
      $imageFilename = $defaultImage; // fallback if compression fails
    }
  }
}

// ⭐ Favorite enforcement — max 3
if ($isFavorite === 1) {
  $existingFavs = $conn->query("SELECT id FROM tour_packages WHERE is_favorite = 1 ORDER BY id ASC");
  if ($existingFavs && $existingFavs->num_rows >= 3) {
    $oldest = $existingFavs->fetch_assoc();
    $conn->query("UPDATE tour_packages SET is_favorite = 0 WHERE id = " . (int) $oldest['id']);
  }
}

// 🔄 Begin transaction
$conn->begin_transaction();

try {
  // 📦 Insert into tour_packages
  $stmt = $conn->prepare("
    INSERT INTO tour_packages (
      tour_cover_image, package_name, package_description,
      inclusions_json, tour_inclusions, price,
      duration, day_duration, night_duration,
      origin, destination, is_favorite, checklist_template_id
    ) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, NULL)
  ");
  $stmt->bind_param(
    "sssdsiiissii",
    $imageFilename,
    $name,
    $description,
    $inclusions,
    $price,
    $duration,
    $days,
    $nights,
    $origin,
    $destination,
    $isFavorite
  );
  $stmt->execute();
  $packageId = $stmt->insert_id;
  $stmt->close();

  // 🗺 Insert itinerary into tour_package_itinerary
  $stmt2 = $conn->prepare("
    INSERT INTO tour_package_itinerary (package_id, itinerary_json, updated_at)
    VALUES (?, ?, ?)
  ");
  $stmt2->bind_param("iss", $packageId, $itinerary, $updatedAt);
  $stmt2->execute();
  $stmt2->close();

  // ✅ Commit
  $conn->commit();
  header("Location: ../admin/admin_tour_packages.php?status=created");
  exit();

} catch (Exception $e) {
  $conn->rollback();
  error_log("[PACKAGE INSERT ERROR] " . $e->getMessage());
  header("Location: ../admin/admin_tour_packages.php?status=error");
  exit();
}