<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php'; // Should define $conn as MySQLi connection

// ✅ CSRF Validation
if (!isset($_SESSION['csrf_token_modal'], $_POST['csrf_token_modal']) ||
    $_SESSION['csrf_token_modal'] !== $_POST['csrf_token_modal']) {
  exit('Invalid CSRF token (modal)');
}

// ✅ Sanitize Inputs
$firstName       = trim($_POST['new_first_name'] ?? '');
$lastName        = trim($_POST['new_last_name'] ?? '');
$emailRaw        = trim($_POST['new_email'] ?? '');
$email           = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
$username        = strtolower(trim($_POST['new_username'] ?? ''));
$password        = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['new_confirm_password'] ?? '';
$phoneRaw        = trim($_POST['new_phone_number'] ?? '');
$phoneNumber     = preg_match('/^09\d{9}$/', $phoneRaw) ? $phoneRaw : null;
$messengerLink   = trim($_POST['new_messenger_link'] ?? '');
$adminProfileRaw = trim($_POST['new_admin_profile_json'] ?? '');
$role            = $_POST['role'] ?? 'admin';
$defaultTimeout  = 30;

// ✅ Validate Required Fields
if (!$firstName || !$lastName || !$email || !$username || !$password || !$confirmPassword) {
  exit('Missing required fields');
}

// ✅ Validate Password Match
if ($password !== $confirmPassword) {
  exit('Passwords do not match');
}
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// ✅ Encode Admin Profile
$adminProfileArray = json_decode($adminProfileRaw, true);
$adminProfile = json_encode([
  'bio' => $adminProfileArray['bio'] ?? ''
]);

// ✅ Check for Duplicate Username or Email
$checkStmt = $conn->prepare("SELECT COUNT(*) FROM admin_accounts WHERE username = ? OR email = ?");
$checkStmt->bind_param("ss", $username, $email);
$checkStmt->execute();
$checkStmt->bind_result($count);
$checkStmt->fetch();
$checkStmt->close();

if ($count > 0) {
  $_SESSION['modal_status'] = 'duplicate_email';
  header('Location: ../admin/admin_settings.php');
  exit;
}

// ✅ Handle Photo Upload
$photoFilename = null;
if (!empty($_FILES['new_admin_photo']['name'])) {
  $uploadDir = __DIR__ . '/../uploads/admin_photo/';
  $fileTmp   = $_FILES['new_admin_photo']['tmp_name'];
  $fileName  = basename($_FILES['new_admin_photo']['name']);
  $fileExt   = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
  $allowed   = ['jpg', 'jpeg', 'png', 'webp'];

  if (in_array($fileExt, $allowed) && is_uploaded_file($fileTmp)) {
    $safeName = uniqid('admin_', true) . '.' . $fileExt;
    if (move_uploaded_file($fileTmp, $uploadDir . $safeName)) {
      $photoFilename = $safeName;
    }
  }
}

// ✅ Insert New Admin
$insertStmt = $conn->prepare("
  INSERT INTO admin_accounts (
    first_name, last_name, email, username, password_hash,
    phone_number, messenger_link, admin_profile, admin_photo, role, session_timeout
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$insertStmt->bind_param(
  "ssssssssssi",
  $firstName,
  $lastName,
  $email,
  $username,
  $passwordHash,
  $phoneNumber,
  $messengerLink,
  $adminProfile,
  $photoFilename,
  $role,
  $defaultTimeout
);

if ($insertStmt->execute()) {
  $adminId = $insertStmt->insert_id;
  $insertStmt->close();

  // ✅ Insert survey tracking entries
$surveyTypes = ['first_login', 'admin_weekly_survey'];
$createdAt = date('Y-m-d H:i:s');

foreach ($surveyTypes as $type) {
  $surveyStmt = $conn->prepare("INSERT INTO user_survey_status 
    (user_id, user_role, survey_type, is_completed, created_at) 
    VALUES (?, 'admin', ?, 0, ?)");
  $surveyStmt->bind_param("iss", $adminId, $type, $createdAt);
  $surveyStmt->execute();
  $surveyStmt->close();
}

  $_SESSION['modal_status'] = 'add_admin_success';
  header('Location: ../admin/admin_settings.php');
  exit;

} else {
  $insertStmt->close();
  $_SESSION['form_errors'] = ['Database error: ' . $insertStmt->error];
  header('Location: ../admin/admin_settings.php');
  exit;
}