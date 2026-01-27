<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/image_compression_helper.php';
require_once __DIR__ . '/../components/status_alert.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../actions/notify.php';
require_once __DIR__ . '/../includes/feature_flags.php';

use function LogHelper\logClientOnboardingAudit;

function toMysqlDate($input) {
  if (!$input) return null;
  $timestamp = strtotime($input);
  return $timestamp ? date('Y-m-d', $timestamp) : null;
}

// Sanitize inputs
$accessCode        = trim($_POST['access_code'] ?? '');
$fullName          = trim($_POST['full_name'] ?? '');
$email             = strtolower(trim($_POST['email'] ?? ''));
$phone             = trim($_POST['phone_number'] ?? '');
$address           = trim($_POST['address'] ?? '');
$processingType    = trim($_POST['processing_type'] ?? 'booking'); // Default to 'booking'

// Enforce feature flag: If visa processing is disabled, force to 'booking'
if (!VISA_PROCESSING_ENABLED && in_array($processingType, ['visa', 'both'])) {
  $processingType = 'booking';
}

$passportNumber    = trim($_POST['passport_number'] ?? '') ?: null; // Convert empty string to NULL
$passportExpiry    = toMysqlDate($_POST['passport_expiry'] ?? '');
$assignedPackageId = !empty($_POST['assigned_package_id']) ? intval($_POST['assigned_package_id']) : null;
$bookingNumber     = trim($_POST['booking_number'] ?? '');
$tripStart         = toMysqlDate($_POST['trip_date_start'] ?? '');
$tripEnd           = toMysqlDate($_POST['trip_date_end'] ?? '');
$bookingDate       = toMysqlDate($_POST['booking_date'] ?? '');

$currentAdminId    = $_SESSION['admin']['id'] ?? null;
$assignedAdminId   = !empty($_POST['assigned_admin_id']) ? intval($_POST['assigned_admin_id']) : $currentAdminId;

// Validate inputs
$errors = [];

if ($fullName === '') $errors[] = 'Full name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
if (!preg_match('/^09\d{9}$/', $phone)) $errors[] = 'Phone must start with 09 and have 11 digits.';
if ($address === '') $errors[] = 'Address is required.';

// Handle profile photo
$photoFile = $_FILES['client_profile_photo'] ?? null;
$photoName = '';

if ($photoFile && $photoFile['error'] === UPLOAD_ERR_OK) {
  $ext = strtolower(pathinfo($photoFile['name'], PATHINFO_EXTENSION));
  $maxSize = 3 * 1024 * 1024;
  $allowedExts = ['jpg', 'jpeg', 'png'];
  $allowedMimeTypes = ['image/jpeg', 'image/png'];

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $photoFile['tmp_name']);
  finfo_close($finfo);

  if (!in_array($ext, $allowedExts) || !in_array($mimeType, $allowedMimeTypes)) {
    $errors[] = 'Invalid file type. Only JPG, JPEG, PNG allowed.';
  } elseif ($photoFile['size'] > $maxSize) {
    $errors[] = 'File too large. Max 3MB allowed.';
  }

  if (empty($errors)) {
    $newName = 'client_' . time() . '_' . rand(100, 999) . '.jpg'; // Always save as JPG
    $targetDir = __DIR__ . '/../uploads/client_profiles/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

    $destinationPath = $targetDir . $newName;

    // Compress and convert image
    $success = compressImage($photoFile['tmp_name'], $destinationPath, $mimeType, 75);
    if ($success) {
      $photoName = $newName;
    } else {
      $errors[] = 'Image compression failed.';
    }
  }
}

// Nullify travel fields if no package assigned
if (!$assignedPackageId) {
  //$bookingNumber = null;
  $tripStart = null;
  $tripEnd = null;
  $bookingDate = null;
}

// Check for duplicate email
$emailCheck = $conn->prepare("SELECT id FROM clients WHERE email = ?");
$emailCheck->bind_param("s", $email);
$emailCheck->execute();
$emailCheck->store_result();

if ($emailCheck->num_rows > 0) {
  $_SESSION['form_errors'] = ['Email already exists.'];
  header("Location: admin_dashboard.php");
  exit();
}

// Insert client record
$status    = 'Awaiting Docs';
$createdAt = date('Y-m-d H:i:s');

$stmt = $conn->prepare("INSERT INTO clients (
  assigned_admin_id, full_name, email, phone_number, address,
  processing_type, client_profile_photo, access_code, assigned_package_id,
  booking_number, trip_date_start, trip_date_end,
  booking_date, passport_number, passport_expiry, status, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param(
  "ssssssssissssssss",
  $assignedAdminId,
  $fullName,
  $email,
  $phone,
  $address,
  $processingType,
  $photoName,
  $accessCode,
  $assignedPackageId,
  $bookingNumber,
  $tripStart,
  $tripEnd,
  $bookingDate,
  $passportNumber,
  $passportExpiry,
  $status,
  $createdAt
);

if ($stmt->execute()) {
  $clientId = $stmt->insert_id;

  // Insert survey tracking entries
  $surveyTypes = ['first_login'];
  $createdAt = date('Y-m-d H:i:s');

  $isCompleted = 0;
  foreach ($surveyTypes as $type) {
    $initialPayload = json_encode([
      'survey_type' => $type,
      'responses' => new stdClass(),
      'submitted_at' => null
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $surveyStmt = $conn->prepare("INSERT INTO user_survey_status 
      (user_id, user_role, survey_type, is_completed, created_at, response_payload) 
      VALUES (?, 'client', ?, ?, ?, ?)");
    $surveyStmt->bind_param("issss", $clientId, $type, $isCompleted, $createdAt, $initialPayload);
    $surveyStmt->execute();
    $surveyStmt->close();
  }

  // Fetch package name
  $packageName = '';
  if ($assignedPackageId) {
    $pkgStmt = $conn->prepare("SELECT package_name FROM tour_packages WHERE id = ?");
    $pkgStmt->bind_param("i", $assignedPackageId);
    $pkgStmt->execute();
    $pkgStmt->bind_result($packageName);
    $pkgStmt->fetch();
    $pkgStmt->close();
  }

  // Fetch admin name
  $adminName = '';
  if ($assignedAdminId) {
    $adminStmt = $conn->prepare("SELECT first_name, last_name FROM admin_accounts WHERE id = ?");
    $adminStmt->bind_param("i", $assignedAdminId);
    $adminStmt->execute();
    $adminStmt->bind_result($firstName, $lastName);
    $adminStmt->fetch();
    $adminStmt->close();
    $adminName = trim($firstName . ' ' . $lastName);
  }

  // Log audit via helper (unchanged, no passport fields included)
  logClientOnboardingAudit($conn, [
    'actor_id'   => $assignedAdminId,
    'client_id'  => $clientId,
    'payload'    => [
      'client_name'      => $fullName,
      'assigned_package' => $packageName,
      'assigned_admin'   => $adminName,
      'trip_start'       => $tripStart,
      'trip_end'         => $tripEnd,
      'source'           => 'process_add_client.php'
    ]
  ]);

  // 📢 Send Notification to All Admins (New Client Added)
  $manager = new NotificationManager($conn);
  $notifyResult = $manager->broadcastToAdmins('new_client_added', [
    'client_name' => $fullName,
    'email' => $email,
    'phone_number' => $phone,
    'package_name' => $packageName ?: 'Not Assigned',
    'assigned_admin' => $adminName,
    'client_id' => $clientId
  ]);
  error_log("[process_add_client] Notification broadcast result: " . json_encode($notifyResult));

  $_SESSION['modal_status'] = 'add_client_success';
  header("Location: ../admin/admin_dashboard.php");
  exit();
} else {
  $_SESSION['form_errors'] = ['Database error: ' . $stmt->error];
  header("Location: ../admin/admin_dashboard.php");
  exit();
}