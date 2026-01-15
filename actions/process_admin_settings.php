<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

// ✅ CSRF Token Check
if (!isset($_SESSION['csrf_token_settings'], $_POST['csrf_token_settings']) ||
    $_SESSION['csrf_token_settings'] !== $_POST['csrf_token_settings']) {
  exit('Invalid CSRF token (settings)');
}

// ✅ Auth Check
$adminId = $_SESSION['admin']['id'] ?? null;
if (!$adminId) exit('Unauthorized');

// ✅ Fetch Current Admin Record
$stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) exit('Admin not found');

// ✅ Sanitize Inputs
$input = [
  'first_name'      => trim($_POST['first_name'] ?? ''),
  'last_name'       => trim($_POST['last_name'] ?? ''),
  'username'        => strtolower(trim($_POST['username'] ?? '')),
  'email'           => filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL),
  'phone_number'    => preg_match('/^09\d{9}$/', $_POST['phone_number'] ?? '') ? $_POST['phone_number'] : null,
  'messenger_link'  => trim($_POST['messenger_link'] ?? ''),
  'session_timeout' => is_numeric($_POST['session_timeout'] ?? '')
                        ? max(5, min(120, (int)$_POST['session_timeout']))
                        : $current['session_timeout'],
];

// ✅ Handle Admin Profile JSON
$inputBio = trim($_POST['admin_profile'] ?? '');
$currentProfile = json_decode($current['admin_profile'] ?? '', true);
$encodedProfile = json_encode(['bio' => $inputBio]);

// ✅ Password Fields
$currentPassword = $_POST['current_password'] ?? '';
$newPassword     = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_new_password'] ?? '';

// ✅ Verify Current Password
if (!password_verify($currentPassword, $current['password_hash'])) {
  exit('Incorrect current password');
}

// ✅ Handle Photo Upload
$photoFilename = null;
if (!empty($_FILES['current_admin_photo']['name'])) {
  $uploadDir = __DIR__ . '/../uploads/admin_photo/';
  $fileTmp   = $_FILES['current_admin_photo']['tmp_name'];
  $fileName  = basename($_FILES['current_admin_photo']['name']);
  $fileExt   = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
  $allowed   = ['jpg', 'jpeg', 'png', 'webp'];

  if (in_array($fileExt, $allowed) && is_uploaded_file($fileTmp)) {
    $safeName = uniqid('admin_', true) . '.' . $fileExt;
    if (move_uploaded_file($fileTmp, $uploadDir . $safeName)) {
      $photoFilename = $safeName;
    }
  }
}

// ✅ Build Update Query
$updates = [];
$types = '';
$values = [];

foreach ($input as $key => $value) {
  if ($value !== $current[$key]) {
    $updates[] = "$key = ?";
    $types .= is_int($value) ? 'i' : 's';
    $values[] = $value;
  }
}

if (($currentProfile['bio'] ?? '') !== $inputBio) {
  $updates[] = "admin_profile = ?";
  $types .= 's';
  $values[] = $encodedProfile;
}

if ($newPassword && $newPassword === $confirmPassword && strlen($newPassword) >= 8) {
  $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
  $updates[] = "password_hash = ?";
  $types .= 's';
  $values[] = $newHash;
}

if ($photoFilename) {
  $updates[] = "admin_photo = ?";
  $types .= 's';
  $values[] = $photoFilename;
}

if (empty($updates)) {
  $_SESSION['modal_status'] = 'admin_update_success';
  header('Location: ../admin/admin_settings.php');
  exit;
}

// ✅ Execute Update
$updatesSql = implode(', ', $updates);
$values[] = $adminId;
$types .= 'i';

$stmt = $conn->prepare("UPDATE admin_accounts SET $updatesSql WHERE id = ?");
$stmt->bind_param($types, ...$values);
$stmt->execute();
$stmt->close();

// ✅ Refresh Session with Updated Admin Data
$stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$updated = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($updated) {
  $_SESSION['admin'] = $updated;
}

// ✅ Redirect with Success
$_SESSION['modal_status'] = 'admin_update_success';
header('Location: ../admin/admin_settings.php');
exit;