<?php
// client_session_check.php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../actions/db.php'; // your DB connection

// Inactivity timeout in seconds (e.g., 30 minutes)
define('CLIENT_INACTIVITY_TIMEOUT', 1800);

// Must be logged in as client
if (!isset($_SESSION['is_client'], $_SESSION['client_id'], $_SESSION['session_token'], $_SESSION['last_activity'])) {
    session_unset();
    session_destroy();
    header("Location: client_login.php?msg=session_required");
    exit;
}

$client_id     = (int)$_SESSION['client_id'];
$session_token = $_SESSION['session_token'];
$last_activity = (int)$_SESSION['last_activity'];

// 1. Inactivity check
if (time() - $last_activity > CLIENT_INACTIVITY_TIMEOUT) {
    // Timeout → logout
    $logoutStmt = $conn->prepare("UPDATE clients SET session_token = NULL, last_activity = NULL WHERE id = ?");
    $logoutStmt->bind_param("i", $client_id);
    $logoutStmt->execute();
    $logoutStmt->close();

    session_unset();
    session_destroy();
    header("Location: client_login.php?msg=session_expired");
    exit;
}

// 2. Single-session check: verify token still matches DB
$stmt = $conn->prepare("SELECT session_token FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // User deleted? Very rare, but handle it
    $stmt->close();
    session_unset();
    session_destroy();
    header("Location: client_login.php?msg=invalid_user");
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

if ($row['session_token'] !== $session_token) {
    // Token mismatch → someone else logged in with same account
    session_unset();
    session_destroy();
    header("Location: client_login.php?msg=logged_in_elsewhere");
    exit;
}

// Everything is valid → update last_activity in DB and session
$now = time();
$now_datetime = date('Y-m-d H:i:s', $now);
$updateStmt = $conn->prepare("UPDATE clients SET last_activity = ? WHERE id = ?");
$updateStmt->bind_param("si", $now_datetime, $client_id);
$updateStmt->execute();
$updateStmt->close();

$_SESSION['last_activity'] = $now;

// Optional: regenerate session ID periodically for extra security
if (!isset($_SESSION['last_regenerate']) || time() - $_SESSION['last_regenerate'] > 600) { // every 10 min
    session_regenerate_id(true);
    $_SESSION['last_regenerate'] = time();
}
?>