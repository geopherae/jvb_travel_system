<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../actions/db.php';

if (isset($_SESSION['admin_id'], $_SESSION['is_admin'])) {
    $admin_id = (int)$_SESSION['admin_id'];

    $stmt = $conn->prepare("UPDATE admin_accounts SET session_token = NULL, last_activity = NULL WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->close();

    error_log("Admin logout successful: admin_id={$admin_id}");
}

$conn->close();

$_SESSION = [];
unset($_SESSION['csrf_token'], $_SESSION['admin_id'], $_SESSION['is_admin'], $_SESSION['session_token'], $_SESSION['last_activity']);

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: ../admin/admin_login.php?msg=logged_out");
exit();
?>