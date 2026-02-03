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
    header("Location: login.php?msg=session_required");
    exit;
}

$client_id     = (int)$_SESSION['client_id'];
$session_token = $_SESSION['session_token'];
$last_activity = (int)$_SESSION['last_activity'];
$is_companion  = !empty($_SESSION['is_companion']);
$is_group      = !empty($_SESSION['group_access_enabled']);

// 1. Inactivity check
if (time() - $last_activity > CLIENT_INACTIVITY_TIMEOUT) {
    // Timeout → logout
    if ($is_companion && isset($_SESSION['companion_id'])) {
        $companion_id = (int)$_SESSION['companion_id'];
        $logoutStmt = $conn->prepare("UPDATE client_visa_companions SET session_token = NULL, last_activity = NULL WHERE id = ?");
        $logoutStmt->bind_param("i", $companion_id);
    } else {
        // Group access doesn't need database session tracking
        $logoutStmt = $conn->prepare("UPDATE clients SET session_token = NULL, last_activity = NULL WHERE id = ?");
        $logoutStmt->bind_param("i", $client_id);
    }

    if ($logoutStmt) {
        $logoutStmt->execute();
        $logoutStmt->close();
    }

    session_unset();
    session_destroy();
    header("Location: login.php?msg=session_expired");
    exit;
}

// 2. Single-session check: verify token still matches DB
// Group access doesn't need database validation (no session_token in client_visa_applications)
if ($is_group) {
    // Group access: skip database token check, rely on PHP session only
    $row = ['session_token' => $session_token]; // Mock validation to pass through
} else {
    if ($is_companion && isset($_SESSION['companion_id'])) {
        $companion_id = (int)$_SESSION['companion_id'];
        $stmt = $conn->prepare("SELECT session_token FROM client_visa_companions WHERE id = ?");
        $stmt->bind_param("i", $companion_id);
    } else {
        $stmt = $conn->prepare("SELECT session_token FROM clients WHERE id = ?");
        $stmt->bind_param("i", $client_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        // User deleted? Very rare, but handle it
        $stmt->close();
        session_unset();
        session_destroy();
        header("Location: login.php?msg=invalid_user");
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close();
}

if (($row['session_token'] ?? '') !== $session_token) {
    // Token mismatch → someone else logged in with same account
    session_unset();
    session_destroy();
    header("Location: login.php?msg=logged_in_elsewhere");
    exit;
}

// Everything is valid → update last_activity in DB and session
$now = time();
$now_datetime = date('Y-m-d H:i:s', $now);
if ($is_companion && isset($_SESSION['companion_id'])) {
    $companion_id = (int)$_SESSION['companion_id'];
    $updateStmt = $conn->prepare("UPDATE client_visa_companions SET last_activity = ? WHERE id = ?");
    $updateStmt->bind_param("si", $now_datetime, $companion_id);
} elseif (!$is_group) {
    // Group access doesn't need database activity tracking
    $updateStmt = $conn->prepare("UPDATE clients SET last_activity = ? WHERE id = ?");
    $updateStmt->bind_param("si", $now_datetime, $client_id);
}

if (isset($updateStmt)) {
    $updateStmt->execute();
    $updateStmt->close();
}

$_SESSION['last_activity'] = $now;

// Optional: regenerate session ID periodically for extra security
if (!isset($_SESSION['last_regenerate']) || time() - $_SESSION['last_regenerate'] > 600) { // every 10 min
    session_regenerate_id(true);
    $_SESSION['last_regenerate'] = time();
}
?>