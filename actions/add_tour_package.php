<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../components/status_alert.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../actions/notify.php';

use function Auth\guard;
use function LogHelper\generatePackageSummary;

guard('admin');

function redirectWithStatus(string $status, ?string $errorMessage = null): void {
    $_SESSION['modal_status'] = $status;
    if ($errorMessage) {
        $_SESSION['error_message'] = $errorMessage;
    }
    header("Location: ../admin/admin_tour_packages.php");
    exit();
}

// ✅ Validate DB connection
if (!$conn) {
    error_log("❌ DB connection failed.");
    redirectWithStatus('error', 'Database connection failed');
}

// 🧼 Sanitize & Normalize Inputs
$AIRPORTS = require __DIR__ . '/../includes/airports.php';
$name = trim($_POST['package_name'] ?? '');
$description = trim($_POST['package_description'] ?? '');
$rawPrice = $_POST['price'] ?? '0';
$price = number_format((float) preg_replace('/[^\d.]/', '', $rawPrice), 2, '.', '');
$days = max(0, (int) ($_POST['day_duration'] ?? 0));
$nights = max(0, (int) ($_POST['night_duration'] ?? 0));
$origin = strtoupper(trim($_POST['origin'] ?? ''));
$destination = strtoupper(trim($_POST['destination'] ?? ''));

// Stricter validation for is_favorite and requires_visa
$isFavorite = (isset($_POST['is_favorite']) && in_array($_POST['is_favorite'], ['1', 'on'], true)) ? 1 : 0;
$requiresVisa = (isset($_POST['requires_visa']) && in_array($_POST['requires_visa'], ['1', 'on'], true)) ? 1 : 0;

$packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : null;
$isNewPackage = !$packageId;

error_log("Debug: is_favorite received: " . ($_POST['is_favorite'] ?? 'not set') . ", normalized to: $isFavorite");
error_log("Debug: requires_visa received: " . ($_POST['requires_visa'] ?? 'not set') . ", normalized to: $requiresVisa");

// ✈️ Validate Airport Codes
$validCodes = [];
foreach ($AIRPORTS as $region => $codes) {
    $validCodes = array_merge($validCodes, array_keys($codes));
}
if (!in_array($origin, $validCodes, true) || !in_array($destination, $validCodes, true)) {
    error_log("❌ Invalid airport code: origin=$origin, destination=$destination");
    redirectWithStatus('error', 'Invalid origin or destination airport code');
}

// 🚦 Basic Validation
if ($name === '') {
    error_log("❌ Package name is empty");
    redirectWithStatus('error', 'Package name is required');
}
if (($days + $nights) <= 0) {
    error_log("❌ Invalid duration: days=$days, nights=$nights");
    redirectWithStatus('error', 'Duration must be greater than zero');
}
if ($nights > $days) {
    error_log("❌ Nights ($nights) exceed days ($days)");
    redirectWithStatus('error', 'Nights cannot exceed days');
}

// 🧾 JSON Parsing Helpers
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
$exclusionsJson = json_encode(safeJson($_POST['exclusions_json'] ?? '[]'));
$itineraryJson = normalizeItineraryJson($_POST['itinerary_json'] ?? '[]');

// 🖼 Image Upload Handler (unchanged)
function handleImageUpload(): ?string {
    if (!isset($_FILES['tour_cover_image']) || $_FILES['tour_cover_image']['error'] !== UPLOAD_ERR_OK) return null;

    $tmpPath = $_FILES['tour_cover_image']['tmp_name'];
    $origName = $_FILES['tour_cover_image']['name'];
    $size = $_FILES['tour_cover_image']['size'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png'];
    $maxSizeMB = 3;

    if (!in_array($ext, $allowedExts, true) || $size > ($maxSizeMB * 1024 * 1024)) {
        error_log("❌ Invalid image: ext=$ext, size=$size");
        redirectWithStatus('invalid_file', 'Invalid image file type or size');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        error_log("❌ Invalid MIME type: $mime");
        redirectWithStatus('invalid_file', 'Invalid image MIME type');
    }

    $serverDir = __DIR__ . '/../images/tour_packages_banners/';
    if (!file_exists($serverDir) && !mkdir($serverDir, 0755, true)) {
        error_log("❌ Failed to create image directory: $serverDir");
        redirectWithStatus('error', 'Failed to create image directory');
    }

    $newName = 'cover_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $fullPath = $serverDir . $newName;

    if (!move_uploaded_file($tmpPath, $fullPath)) {
        error_log("❌ Failed to move uploaded image to: $fullPath");
        redirectWithStatus('error', 'Failed to upload image');
    }
    return $newName;
}

// 🖼 Image Logic (unchanged)
$existingImage = trim($_POST['existing_image'] ?? '');
$existingImageFile = basename($existingImage);
$newImageFile = handleImageUpload();

if ($newImageFile && $existingImageFile !== 'default_trip_cover.jpg') {
    $oldPath = __DIR__ . '/../images/tour_packages_banners/' . $existingImageFile;
    if (file_exists($oldPath)) {
        if (!unlink($oldPath)) {
            error_log("❌ Failed to delete old image: $oldPath");
        }
    }
}

$imageFileName = $newImageFile ?? ($existingImageFile !== 'default_trip_cover.jpg' ? $existingImageFile : '');

// ✅ Transaction Block
$conn->begin_transaction();

try {
    // 📝 Validate Package ID for Updates
    if ($packageId) {
        $stmt = $conn->prepare("SELECT id FROM tour_packages WHERE id = ? AND is_deleted = 0");
        $stmt->bind_param("i", $packageId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $stmt->close();
            throw new Exception("Package ID $packageId not found or is deleted");
        }
        $stmt->close();
    }

    // ⭐ Favorite Enforcement (unchanged)
    if ($isFavorite === 1) {
        error_log("Debug: Processing favorite enforcement for packageId=" . ($packageId ?: 'new'));
        $existingFavsStmt = $conn->prepare("SELECT id FROM tour_packages WHERE is_favorite = 1 AND is_deleted = 0 AND id != ? ORDER BY id ASC");
        $excludeId = $packageId ?: 0;
        $existingFavsStmt->bind_param("i", $excludeId);
        if (!$existingFavsStmt->execute()) {
            throw new Exception("Failed to query favorite packages: " . $existingFavsStmt->error);
        }
        $result = $existingFavsStmt->get_result();
        $favoriteCount = $result->num_rows;
        error_log("Debug: Found $favoriteCount favorite packages (excluding packageId=$excludeId)");
        if ($favoriteCount >= 3) {
            $oldest = $result->fetch_assoc();
            if (!$oldest) {
                throw new Exception("No valid favorite package found to unset despite count=$favoriteCount");
            }
            $stmt = $conn->prepare("UPDATE tour_packages SET is_favorite = 0 WHERE id = ?");
            $stmt->bind_param("i", $oldest['id']);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new Exception("Failed to unset favorite package ID {$oldest['id']}: " . $stmt->error);
            }
            error_log("Debug: Unset favorite package ID {$oldest['id']}");
            $stmt->close();
        }
        $existingFavsStmt->close();
    }

    // 📝 Insert or Update Tour Package — NOW WITH requires_visa
    if ($packageId) {
        $stmt = $conn->prepare("UPDATE tour_packages SET
            package_name = ?, package_description = ?, inclusions_json = ?, exclusions_json = ?, price = ?, day_duration = ?, night_duration = ?,
            origin = ?, destination = ?, is_favorite = ?, requires_visa = ?, tour_cover_image = ?
            WHERE id = ?");
        // NOTE: Image filename must be bound as string to preserve the path/filename
        $stmt->bind_param("ssssdiissiisi", $name, $description, $inclusionsJson, $exclusionsJson, $price, $days, $nights, $origin, $destination, $isFavorite, $requiresVisa, $imageFileName, $packageId);
    } else {
        $stmt = $conn->prepare("INSERT INTO tour_packages
            (package_name, package_description, inclusions_json, exclusions_json, price, day_duration, night_duration, origin, destination, is_favorite, requires_visa, tour_cover_image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        // NOTE: Image filename must be bound as string to preserve the path/filename
        $stmt->bind_param("ssssdiissiis", $name, $description, $inclusionsJson, $exclusionsJson, $price, $days, $nights, $origin, $destination, $isFavorite, $requiresVisa, $imageFileName);
    }

    if (!$stmt->execute()) throw new Exception("Failed to save package: " . $stmt->error);
    if (!$packageId) $packageId = $stmt->insert_id;
    $stmt->close();
    error_log("Debug: Package " . ($isNewPackage ? "created" : "updated") . " with ID $packageId, requires_visa=$requiresVisa");

    // 🧭 Save Itinerary (unchanged)
    $norm = json_decode($itineraryJson, true);
    if (!is_array($norm)) {
        error_log("Debug: Invalid itinerary_json, resetting to empty array");
        $itineraryJson = json_encode([]);
    }

    $check = $conn->prepare("SELECT id FROM tour_package_itinerary WHERE package_id = ?");
    $check->bind_param("i", $packageId);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $stmtItinerary = $conn->prepare("UPDATE tour_package_itinerary SET itinerary_json = ?, updated_at = NOW() WHERE package_id = ?");
        $stmtItinerary->bind_param("si", $itineraryJson, $packageId);
    } else {
        $stmtItinerary = $conn->prepare("INSERT INTO tour_package_itinerary (package_id, itinerary_json, updated_at) VALUES (?, ?, NOW())");
        $stmtItinerary->bind_param("is", $packageId, $itineraryJson);
    }
    $check->close();

    if (!$stmtItinerary->execute()) {
        throw new Exception("Failed to save itinerary: " . $stmtItinerary->error);
    }
    $stmtItinerary->close();

    // 🧾 Audit Log — now includes requires_visa
    $actor_id = (int) ($_SESSION['admin_id'] ?? 0);
    $actor_role = 'admin';
    $action_type = $isNewPackage ? 'create_package' : 'update_package';
    $target_type = 'tour_package';
    $severity = 'normal';
    $module = 'packages';
    $timestamp = date('Y-m-d H:i:s');
    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $kpi_tag = $isNewPackage ? 'package_create' : 'package_update';
    $business_impact = 'moderate';

    $audit_payload = [
        'package_id' => $packageId,
        'actor_id' => $actor_id,
        'fields_changed' => ['package_name', 'price', 'day_duration', 'night_duration', 'origin', 'destination', 'is_favorite', 'requires_visa'],
        'summary' => generatePackageSummary([
            'package_name' => $name,
            'price' => $price,
            'day_duration' => $days,
            'night_duration' => $nights,
            'origin' => $origin,
            'destination' => $destination,
            'is_favorite' => $isFavorite,
            'requires_visa' => $requiresVisa
        ]),
        'source' => 'admin_tour_packages.php'
    ];

    $audit_changes = json_encode($audit_payload, JSON_UNESCAPED_UNICODE);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to encode audit log changes: " . json_last_error_msg());
    }

    $audit_stmt = $conn->prepare("
        INSERT INTO audit_logs (
            action_type, actor_id, actor_role,
            target_id, target_type, changes,
            severity, module, timestamp,
            session_id, ip_address, user_agent,
            kpi_tag, business_impact
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $audit_stmt->bind_param(
        "sissssssssssss",
        $action_type,
        $actor_id,
        $actor_role,
        $packageId,
        $target_type,
        $audit_changes,
        $severity,
        $module,
        $timestamp,
        $session_id,
        $ip_address,
        $user_agent,
        $kpi_tag,
        $business_impact
    );

    if (!$audit_stmt->execute()) {
        throw new Exception("Failed to save audit log: " . $audit_stmt->error);
    }
    $audit_stmt->close();

    // 📢 Send Notification to All Admins (Only for New Packages)
    if ($isNewPackage) {
        $manager = new NotificationManager($conn);
        $notifyResult = $manager->broadcastToAdmins('tour_package_created', [
            'package_name' => $name,
            'origin' => $origin,
            'destination' => $destination,
            'price' => number_format((float) $price, 2),
            'day_duration' => $days,
            'night_duration' => $nights
        ]);
        error_log("[add_tour_package] Notification broadcast result: " . json_encode($notifyResult));
    }

    // ✅ Commit transaction
    $conn->commit();
    $_SESSION['success_message'] = "Package " . ($isNewPackage ? "created" : "updated") . " successfully";
    redirectWithStatus($isNewPackage ? 'created' : 'updated');

} catch (Throwable $e) {
    error_log("❌ Transaction error: " . $e->getMessage());
    $conn->rollback();
    redirectWithStatus('error', $e->getMessage());
}
?>