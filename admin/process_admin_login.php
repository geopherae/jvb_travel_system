<?php
declare(strict_types=1);

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// ✅ Sanitize Input Early
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// ✅ Database Connection
require_once __DIR__ . '/../actions/db.php';

// ✅ Fetch Admin Record
$sql = "SELECT 
            id, first_name, last_name, username, email, phone_number, role, password_hash, admin_photo,
            messenger_link, admin_profile, is_active, session_timeout, is_primary_contact
        FROM admin_accounts
        WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $admin = $result->fetch_assoc();

    // ✅ Superadmin Bypass
    $isSuperadmin = ($admin['username'] === 'chriscahill' && $admin['role'] === 'superadmin');

    // ✅ CSRF Check (skip for superadmin)
    if (!$isSuperadmin && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? ''))) {
        $_SESSION['login_error'] = "Invalid request (CSRF mismatch).";
        header("Location: admin_login.php");
        exit();
    }

    // ✅ Rate Limiting (skip for superadmin)
    if (!$isSuperadmin) {
        $_SESSION['attempts'] = ($_SESSION['attempts'] ?? 0);
        $_SESSION['last_attempt'] = ($_SESSION['last_attempt'] ?? time());

        if ($_SESSION['attempts'] >= 5 && (time() - $_SESSION['last_attempt']) < 300) {
            $_SESSION['login_error'] = "Too many failed attempts. Please try again in 5 minutes.";
            header("Location: admin_login.php");
            exit();
        }
    }

    // ✅ Verify Password
    if (password_verify($password, $admin['password_hash'])) {

        // === SINGLE SESSION + PER-ADMIN TIMEOUT SETUP ===
        $new_token = bin2hex(random_bytes(32)); // 64-char secure token
        $now = time();
        $now_datetime = date('Y-m-d H:i:s', $now);

        // Update DB: overwrite old token + set last activity
        $updateStmt = $conn->prepare("
            UPDATE admin_accounts 
            SET session_token = ?, last_activity = ? 
            WHERE id = ?
        ");
        $updateStmt->bind_param("ssi", $new_token, $now_datetime, $admin['id']);
        $updateStmt->execute();
        $updateStmt->close();

        session_regenerate_id(true);

        // Reset rate limiting on success
        unset($_SESSION['attempts'], $_SESSION['last_attempt']);

        // Store session data
        $_SESSION['admin_id']       = (int)$admin['id'];
        $_SESSION['is_admin']       = true;
        $_SESSION['session_token']  = $new_token;                    // For single session check
        $_SESSION['last_activity']  = $now;                          // For inactivity timeout
        $_SESSION['session_timeout_minutes'] = (int)$admin['session_timeout']; // Store for use in checks

        $_SESSION['admin'] = [
            'id'                 => (int)$admin['id'],
            'first_name'         => (string)$admin['first_name'],
            'last_name'          => (string)$admin['last_name'],
            'username'           => (string)$admin['username'],
            'email'              => (string)$admin['email'],
            'phone_number'       => (string)$admin['phone_number'],
            'messenger_link'     => (string)$admin['messenger_link'],
            'admin_profile'      => (string)$admin['admin_profile'],
            'is_active'          => (bool)$admin['is_active'],
            'session_timeout'    => (int)$admin['session_timeout'],
            'is_primary_contact'=> (bool)$admin['is_primary_contact'],
            'role'               => (string)($admin['role'] ?? 'Read-Only'),
            'admin_photo'        => (string)($admin['admin_photo'] ?? '')
        ];

        $_SESSION['show_disclaimer'] = true;

        error_log("Admin login successful: id={$admin['id']}, username={$admin['username']}");
        header("Location: ../admin/admin_dashboard.php");
        exit();
    }
}

// ❌ Login Failed
if (!$isSuperadmin) {
    $_SESSION['attempts'] = ($_SESSION['attempts'] ?? 0) + 1;
    $_SESSION['last_attempt'] = time();
}
$_SESSION['login_error'] = "Invalid username or password.";
header("Location: admin_login.php");
exit();
?>