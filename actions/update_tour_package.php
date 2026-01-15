<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../components/status_alert.php';
require_once __DIR__ . '/../includes/log_helper.php';

use function Auth\guard;
use function LogHelper\generatePackageUpdateSummary;

guard('admin');

function redirectWithStatus(string $status, ?string $errorMessage = null): void {
    $_SESSION['modal_status'] = $status;
    if ($errorMessage) {
        $_SESSION['error_message'] = $errorMessage;
    }
    header("Location: ../admin/admin_tour_packages.php");
    exit();
}

// Validate required package_id for update
$packageId = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
if ($packageId <= 0) {
    error_log("❌ Update attempted without valid package_id");
    redirectWithStatus('error', 'Invalid package ID');
}

if (!$conn) {
    error_log("❌ DB connection failed.");
    redirectWithStatus('error', 'Database connection failed');
}

// Sanitize & Normalize Inputs
$AIRPORTS = require __DIR__ . '/../includes/airports.php';

$name = trim($_POST['package_name'] ?? '');
$description = trim($_POST['package_description'] ?? '');
$rawPrice = $_POST['price'] ?? '0';
$price = number_format((float) preg_replace('/[^\d.]/', '', $rawPrice), 2, '.', '');
$days = max(1, (int) ($_POST['day_duration'] ?? 1));
$nights = max(0, (int) ($_POST['night_duration'] ?? 0));
$origin = strtoupper(trim($_POST['origin'] ?? ''));
$destination = strtoupper(trim($_POST['destination'] ?? ''));
$isFavorite = (isset($_POST['is_favorite']) && in_array($_POST['is_favorite'], ['1', 'on'], true)) ? 1 : 0;
$requiresVisa = (isset($_POST['requires_visa']) && in_array($_POST['requires_visa'], ['1', 'on'], true)) ? 1 : 0;

$validCodes = array_merge(...array_values(array_map('array_keys', $AIRPORTS)));
if (!in_array($origin, $validCodes, true) || !in_array($destination, $validCodes, true)) {
    redirectWithStatus('error', 'Invalid origin or destination airport code');
}

if ($name === '') {
    redirectWithStatus('error', 'Package name is required');
}
if (($days + $nights) <= 0) {
    redirectWithStatus('error', 'Duration must be greater than zero');
}
if ($nights > $days) {
    redirectWithStatus('error', 'Nights cannot exceed days');
}

// JSON Parsing
function safeJson(string $json): array {
    $parsed = json_decode($json, true);
    return is_array($parsed) ? $parsed : [];
}

function normalizeTime(?string $time): string {
    $time = trim((string)$time);
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) ? $time : '';
}

function normalizeItineraryJson(string $rawJson): string {
    $raw = array_slice(safeJson($rawJson), 0, 7);
    $out = [];

    foreach ($raw as $idx => $day) {
        $dayTitle = trim($day['day_title'] ?? '') ?: 'Day ' . ($idx + 1);
        $activities = [];

        foreach ($day['activities'] ?? [] as $act) {
            $title = trim($act['title'] ?? '');
            if ($title === '') continue;
            $time = normalizeTime($act['time'] ?? '');
            $activities[] = ['time' => $time, 'title' => $title];
        }

        $out[] = [
            'day_number' => $idx + 1,
            'day_title' => $dayTitle,
            'activities' => $activities
        ];
    }

    return json_encode($out);
}

$inclusionsJson = json_encode(safeJson($_POST['inclusions_json'] ?? '[]'));
$itineraryJson = normalizeItineraryJson($_POST['itinerary_json'] ?? '[]');

// Image Upload Handler
function handleImageUpload(): ?string {
    if (!isset($_FILES['tour_cover_image']) || $_FILES['tour_cover_image']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmpPath = $_FILES['tour_cover_image']['tmp_name'];
    $origName = $_FILES['tour_cover_image']['name'];
    $size = $_FILES['tour_cover_image']['size'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png'];
    $maxSizeMB = 3;

    if (!in_array($ext, $allowedExts, true) || $size > ($maxSizeMB * 1024 * 1024)) {
        redirectWithStatus('invalid_file', 'Invalid image file type or size');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        redirectWithStatus('invalid_file', 'Invalid image MIME type');
    }

    $serverDir = __DIR__ . '/../images/tour_packages_banners/';
    if (!is_dir($serverDir) && !mkdir($serverDir, 0755, true)) {
        redirectWithStatus('error', 'Failed to create image directory');
    }

    $newName = 'cover_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $fullPath = $serverDir . $newName;

    if (!move_uploaded_file($tmpPath, $fullPath)) {
        redirectWithStatus('error', 'Failed to upload image');
    }

    return $newName;
}

$existingImage = trim($_POST['existing_image'] ?? '');
$newImageFile = handleImageUpload();

if ($newImageFile && $existingImage && $existingImage !== 'default_trip_cover.jpg') {
    $oldPath = __DIR__ . '/../images/tour_packages_banners/' . basename($existingImage);
    if (file_exists($oldPath)) {
        unlink($oldPath);
    }
}

$imageFileName = $newImageFile ?: ($existingImage && $existingImage !== 'default_trip_cover.jpg' ? basename($existingImage) : '');

// Begin Transaction
$conn->begin_transaction();

try {
    // Verify package exists and not deleted
    $checkStmt = $conn->prepare("SELECT id FROM tour_packages WHERE id = ? AND is_deleted = 0");
    $checkStmt->bind_param("i", $packageId);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows === 0) {
        throw new Exception("Package ID $packageId not found or already deleted");
    }
    $checkStmt->close();

    // Enforce max 3 favorites
    if ($isFavorite === 1) {
        $favStmt = $conn->prepare("SELECT id FROM tour_packages WHERE is_favorite = 1 AND is_deleted = 0 AND id != ?");
        $favStmt->bind_param("i", $packageId);
        $favStmt->execute();
        $result = $favStmt->get_result();
        if ($result->num_rows >= 3) {
            // Unset the oldest favorite
            $oldest = $result->fetch_assoc();
            $unsetStmt = $conn->prepare("UPDATE tour_packages SET is_favorite = 0 WHERE id = ?");
            $unsetStmt->bind_param("i", $oldest['id']);
            $unsetStmt->execute();
            $unsetStmt->close();
        }
        $favStmt->close();
    }

    // Update main package
    $updateStmt = $conn->prepare("
        UPDATE tour_packages SET
            package_name = ?,
            package_description = ?,
            inclusions_json = ?,
            price = ?,
            day_duration = ?,
            night_duration = ?,
            origin = ?,
            destination = ?,
            is_favorite = ?,
            requires_visa = ?,
            tour_cover_image = ?
        WHERE id = ?
    ");
    $updateStmt->bind_param(
        "sssdiissiisi",
        $name, $description, $inclusionsJson, $price,
        $days, $nights, $origin, $destination,
        $isFavorite, $requiresVisa, $imageFileName, $packageId
    );

    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update package: " . $updateStmt->error);
    }
    $updateStmt->close();

    // Update or insert itinerary
    $itineraryCheck = $conn->prepare("SELECT id FROM tour_package_itinerary WHERE package_id = ?");
    $itineraryCheck->bind_param("i", $packageId);
    $itineraryCheck->execute();
    $itineraryCheck->store_result();

    if ($itineraryCheck->num_rows > 0) {
        $itineraryStmt = $conn->prepare("UPDATE tour_package_itinerary SET itinerary_json = ?, updated_at = NOW() WHERE package_id = ?");
        $itineraryStmt->bind_param("si", $itineraryJson, $packageId);
    } else {
        $itineraryStmt = $conn->prepare("INSERT INTO tour_package_itinerary (package_id, itinerary_json, updated_at) VALUES (?, ?, NOW())");
        $itineraryStmt->bind_param("is", $packageId, $itineraryJson);
    }
    $itineraryCheck->close();

    if (!$itineraryStmt->execute()) {
        throw new Exception("Failed to save itinerary: " . $itineraryStmt->error);
    }
    $itineraryStmt->close();

    // Audit Log
    $actor_id = (int) ($_SESSION['admin_id'] ?? 0);
    $actor_role = 'admin';
    $action_type = 'update_package';
    $target_type = 'tour_package';
    $severity = 'normal';
    $module = 'packages';
    $timestamp = date('Y-m-d H:i:s');
    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $kpi_tag = 'package_update';
    $business_impact = 'moderate';

    $audit_payload = [
        'package_id' => $packageId,
        'actor_id' => $actor_id,
        'summary' => generatePackageUpdateSummary([
            'package_name' => $name,
            'price' => $price,
            'day_duration' => $days,
            'night_duration' => $nights,
            'origin' => $origin,
            'destination' => $destination,
            'is_favorite' => $isFavorite,
            'requires_visa' => $requiresVisa
        ]),
        'source' => 'edit_modal'
    ];

    $audit_changes = json_encode($audit_payload, JSON_UNESCAPED_UNICODE);

    $audit_stmt = $conn->prepare("
        INSERT INTO audit_logs (
            action_type, actor_id, actor_role, target_id, target_type, changes,
            severity, module, timestamp, session_id, ip_address, user_agent,
            kpi_tag, business_impact
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $audit_stmt->bind_param(
        "sissssssssssss",
        $action_type, $actor_id, $actor_role, $packageId, $target_type, $audit_changes,
        $severity, $module, $timestamp, $session_id, $ip_address, $user_agent,
        $kpi_tag, $business_impact
    );
    $audit_stmt->execute();
    $audit_stmt->close();

    $conn->commit();
    $_SESSION['success_message'] = "Package updated successfully";
    redirectWithStatus('updated');

} catch (Throwable $e) {
    $conn->rollback();
    error_log("❌ Update transaction failed: " . $e->getMessage());
    redirectWithStatus('error', 'Failed to update package: ' . $e->getMessage());
}
?>