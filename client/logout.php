<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../actions/db.php'; // Your database connection file

// If the user is logged in as a client, clean up their session data in the DB
if (isset($_SESSION['client_id'], $_SESSION['is_client'])) {
    $client_id = (int)$_SESSION['client_id'];

    // Clear session_token and last_activity to fully invalidate any lingering session
    $stmt = $conn->prepare("
        UPDATE clients 
        SET session_token = NULL, last_activity = NULL 
        WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $stmt->close();
    }
    // Optional: log the logout
    error_log("Client logout successful: client_id={$client_id}");
}

$conn->close();

// 🧹 Clear all session variables
$_SESSION = [];

// 🛡️ Explicitly unset any sensitive or leftover keys (good practice)
unset(
    $_SESSION['csrf_token'],
    $_SESSION['client_id'],
    $_SESSION['is_client'],
    $_SESSION['session_token'],
    $_SESSION['last_activity'],
    $_SESSION['client']
);

// 🔒 Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 🔥 Destroy the session completely
session_destroy();

// ✅ Redirect to client login page with optional success message
header("Location: ../client/login.php?msg=logged_out");
exit();
?>