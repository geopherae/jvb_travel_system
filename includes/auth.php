<?php
namespace Auth;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// NEW: Require DB connection for dynamic timeout fetching
require_once __DIR__ . '/../actions/db.php'; // Adjust path if needed; assumes $conn is global

// 🧠 Role detection
$isAdmin      = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$isClient     = isset($_SESSION['client_id']);
$isSuperadmin = (
    isset($_SESSION['admin']['role']) && $_SESSION['admin']['role'] === 'superadmin'
) || (
    isset($_SESSION['client']['username']) && $_SESSION['client']['username'] === 'chriscahill'
);

$isLoggedIn = $isAdmin || $isClient || $isSuperadmin;

/**
 * 🔐 Guard function: blocks unauthorized access
 * Returns JSON for AJAX, redirects for normal requests
 */
function guard(string $role = 'any', string $redirectTo = null): void {
    global $isAdmin, $isClient, $isSuperadmin, $conn; // NEW: Include $conn for DB query

    // Set default redirect based on role
    $redirectTo = $redirectTo ?? match ($role) {
        'admin'  => '../admin/admin_login.php',
        'client' => '../client/login.php',
        default  => '../client/login.php'
    };

    // NEW: Dynamically fetch timeout from DB based on role (in minutes, convert to seconds)
    $timeout = 3600; // Default fallback (1 hour in seconds)
    if ($isAdmin || $isSuperadmin) {
        $adminId = $_SESSION['admin']['id'] ?? null;
        if ($adminId && $conn) {
            $stmt = $conn->prepare("SELECT session_timeout FROM admin_accounts WHERE id = ?");
            $stmt->bind_param("i", $adminId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($result && is_numeric($result['session_timeout'])) {
                $timeout = (int)$result['session_timeout'] * 60; // Convert minutes to seconds
            }
        }
        // Optional: Cache in session for next call (reduces DB hits)
        $_SESSION['admin']['session_timeout'] = $timeout;
    } elseif ($isClient) {
        $clientId = $_SESSION['client_id'] ?? null;
        // No session_timeout column for clients, use default
        $timeout = 1800; // Default 30 minutes (1800 seconds)
        // Optional: Cache in session for next call
        $_SESSION['client']['session_timeout'] = $timeout;
    }

    // Session timeout check (now using dynamically fetched $timeout in seconds)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['session_expired'] = true;

        if (headers_sent()) {
            echo "<script>window.location.href='$redirectTo';</script>";
        } else {
            header("Location: $redirectTo");
        }
        exit;
    }

    // AJAX detection
    $isAjax = (
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
    );

    // Role-based authentication check
    $unauthenticated = match ($role) {
        'admin'  => !$isAdmin && !$isSuperadmin,
        'client' => !$isClient,
        'any'    => !$isAdmin && !$isClient && !$isSuperadmin,
        default  => true
    };

    if ($unauthenticated) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        } else {
            if (headers_sent()) {
                echo "<script>window.location.href='$redirectTo';</script>";
            } else {
                header("Location: $redirectTo");
            }
        }
        exit;
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

/**
 * 🧑‍💼 Actor context for logging and auditing
 * Returns user ID, role, session ID, IP, and user agent
 */
function getActorContext(): array {
    global $isAdmin, $isClient, $isSuperadmin;

    return [
        'id'         => $isAdmin || $isSuperadmin ? ($_SESSION['admin']['id'] ?? $_SESSION['client_id'] ?? 0) : ($_SESSION['client_id'] ?? 0),
        'role'       => $isSuperadmin ? 'superadmin' : ($isAdmin ? 'admin' : ($isClient ? 'client' : 'guest')),
        'session_id' => session_id(),
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
}
?>