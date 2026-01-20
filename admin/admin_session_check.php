<?php
// admin_session_check.php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../actions/db.php';

// Must be logged in as admin with required session keys
if (!isset($_SESSION['is_admin'], $_SESSION['admin_id'], $_SESSION['session_token'], 
          $_SESSION['last_activity'], $_SESSION['session_timeout_minutes'])) {
    session_unset();
    session_destroy();
    header("Location: ../admin/admin_login.php?msg=session_required");
    exit;
}

$admin_id     = (int)$_SESSION['admin_id'];
$session_token = $_SESSION['session_token'];
$last_activity = (int)$_SESSION['last_activity'];
$timeout_minutes = (int)$_SESSION['session_timeout_minutes'];
$timeout_seconds = $timeout_minutes * 60;

// 1. Inactivity timeout check (uses per-admin value)
if (time() - $last_activity > $timeout_seconds) {
    $logoutStmt = $conn->prepare("UPDATE admin_accounts SET session_token = NULL, last_activity = NULL WHERE id = ?");
    $logoutStmt->bind_param("i", $admin_id);
    $logoutStmt->execute();
    $logoutStmt->close();

    session_unset();
    session_destroy();
    header("Location: ../admin/admin_login.php?msg=session_expired");
    exit;
}

// 2. Single session validation
$stmt = $conn->prepare("SELECT session_token FROM admin_accounts WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    session_unset();
    session_destroy();
    header("Location: ../admin/admin_login.php?msg=invalid_user");
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

if ($row['session_token'] !== $session_token) {
    session_unset();
    session_destroy();
    header("Location: ../admin/admin_login.php?msg=logged_in_elsewhere");
    exit;
}

// Valid → update last_activity
$now = time();
$now_datetime = date('Y-m-d H:i:s', $now);
$updateStmt = $conn->prepare("UPDATE admin_accounts SET last_activity = ? WHERE id = ?");
$updateStmt->bind_param("si", $now_datetime, $admin_id);
$updateStmt->execute();
$updateStmt->close();

$_SESSION['last_activity'] = $now;

// Optional: periodic session ID regeneration
if (!isset($_SESSION['last_regenerate']) || time() - $_SESSION['last_regenerate'] > 600) {
    session_regenerate_id(true);
    $_SESSION['last_regenerate'] = time();
}
?>