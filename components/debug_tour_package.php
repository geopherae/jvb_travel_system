<?php
session_start();
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
$itinerary    = trim($_POST['itinerary_json'] ?? '');
$templateId   = !empty($_POST['checklist_template_id']) ? (int) $_POST['checklist_template_id'] : null;

// 🖼 Simulate tour cover image handling
$defaultImage     = '../images/default_trip_cover.jpg';
$uploadDir        = '../images/tour_packages_banners/';
$imageFilename    = basename($defaultImage); // fallback
$targetSavePath   = $uploadDir . $imageFilename;

if (
  isset($_FILES['tour_cover_image']) &&
  $_FILES['tour_cover_image']['error'] === UPLOAD_ERR_OK &&
  is_uploaded_file($_FILES['tour_cover_image']['tmp_name'])
) {
  $ext = strtolower(pathinfo($_FILES['tour_cover_image']['name'], PATHINFO_EXTENSION));
  $allowedExts = ['jpg', 'jpeg', 'png'];

  if (in_array($ext, $allowedExts)) {
    $imageFilename  = 'cover_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $targetSavePath = $uploadDir . $imageFilename;
  } else {
    $imageFilename  = '❌ Invalid file type';
    $targetSavePath = 'Upload rejected';
  }
}

// 🧮 Total duration
$totalDuration = $days + $nights;

// 🧾 Display debug output
echo "<h2>🧪 Tour Package Debug Preview</h2>";
echo "<ul style='font-family: monospace; line-height: 1.6;'>";

echo "<li><strong>Package Name:</strong> {$name}</li>";
echo "<li><strong>Description:</strong> {$description}</li>";
echo "<li><strong>Price:</strong> ₱" . number_format($price, 2) . "</li>";
echo "<li><strong>Day Duration:</strong> {$days}</li>";
echo "<li><strong>Night Duration:</strong> {$nights}</li>";
echo "<li><strong>Total Duration:</strong> {$totalDuration}</li>";
echo "<li><strong>Origin:</strong> {$origin}</li>";
echo "<li><strong>Destination:</strong> {$destination}</li>";
echo "<li><strong>Is Favorite:</strong> " . ($isFavorite ? '1' : '0') . "</li>";
echo "<li><strong>Checklist Template ID:</strong> " . ($templateId ?? 'None') . "</li>";
echo "<li><strong>Itinerary JSON:</strong><pre>" . htmlspecialchars($itinerary) . "</pre></li>";
echo "<li><strong>Inclusions JSON:</strong><pre>" . htmlspecialchars($inclusions) . "</pre></li>";
echo "<li><strong>Tour Cover Image Filename:</strong> {$imageFilename}</li>";
echo "<li><strong>Target Save Path:</strong> {$targetSavePath}</li>";

echo "</ul>";