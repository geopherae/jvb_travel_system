<?php
declare(strict_types=1);

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Load database connection
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/feature_flags.php';

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    error_log("CSRF validation failed: " . json_encode($_POST, JSON_PRETTY_PRINT));
    $_SESSION['login_error'] = "Invalid session token.";
    header("Location: login.php");
    exit;
}

// Sanitize and validate access code
$access_code = filter_var(trim($_POST['access_code'] ?? ''), FILTER_SANITIZE_STRING);
if (empty($access_code)) {
    error_log("Empty access code provided");
    $_SESSION['login_error'] = "Access code is required.";
    header("Location: login.php");
    exit;
}

// Rate limiting
$_SESSION['client_attempts'] = ($_SESSION['client_attempts'] ?? 0) + 1;
$_SESSION['client_last_attempt'] = time();

if ($_SESSION['client_attempts'] >= 5 && (time() - $_SESSION['client_last_attempt']) < 10) {
    error_log("Rate limit exceeded: attempts={$_SESSION['client_attempts']}");
    $_SESSION['login_error'] = "Too many failed attempts. Please try again later.";
    header("Location: login.php");
    exit;
}

// Superadmin bypass
$superadminUsername = 'chriscahill';
$superadminRole = 'superadmin';

try {
    $adminStmt = $conn->prepare("
        SELECT id, first_name, last_name, username, email, phone_number, role, password_hash, admin_photo,
               messenger_link, admin_profile, is_active, session_timeout, is_primary_contact
        FROM admin_accounts
        WHERE username = ? AND role = ?
    ");
    if (!$adminStmt) {
        throw new Exception("Admin query preparation failed: " . $conn->error);
    }

    $adminStmt->bind_param("ss", $access_code, $superadminRole);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();

    if ($adminResult->num_rows === 1) {
        $admin = $adminResult->fetch_assoc();
        session_regenerate_id(true);

        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['is_admin'] = true;
        $_SESSION['admin'] = [
            'id' => (int)$admin['id'],
            'first_name' => (string)$admin['first_name'],
            'last_name' => (string)$admin['last_name'],
            'username' => (string)$admin['username'],
            'email' => (string)$admin['email'],
            'phone_number' => (string)$admin['phone_number'],
            'messenger_link' => (string)$admin['messenger_link'],
            'admin_profile' => (string)$admin['admin_profile'],
            'is_active' => (bool)$admin['is_active'],
            'session_timeout' => (int)$admin['session_timeout'],
            'is_primary_contact' => (bool)$admin['is_primary_contact'],
            'role' => (string)$admin['role'],
            'admin_photo' => (string)$admin['admin_photo']
        ];

        $_SESSION['show_disclaimer'] = true;
        error_log("Superadmin login successful: id={$admin['id']}, username={$admin['username']}");
        header("Location: ../admin/admin_dashboard.php");
        $adminStmt->close();
        $conn->close();
        exit;
    }
    $adminStmt->close();

    // Client login
    $selectFields = "id, full_name, email, access_code, client_profile_photo";
    if (VISA_PROCESSING_ENABLED) {
        $selectFields .= ", processing_type";
    }
    
    $clientStmt = $conn->prepare("
        SELECT $selectFields
        FROM clients
        WHERE access_code = ?
    ");
    if (!$clientStmt) {
        throw new Exception("Client query preparation failed: " . $conn->error);
    }

    $clientStmt->bind_param("s", $access_code);
    $clientStmt->execute();
    $clientResult = $clientStmt->get_result();

    if ($clientResult->num_rows === 1) {
        $client = $clientResult->fetch_assoc();

        // === SINGLE SESSION + ACTIVITY TRACKING ===
        // Generate a strong unique session token
        $new_token = bin2hex(random_bytes(32)); // 64-character secure token
        $now = time();
        $now_datetime = date('Y-m-d H:i:s', $now);

        // Update client record: new token overwrites old one (kicks out previous session)
        // Also set last_activity for inactivity timeout
        $updateStmt = $conn->prepare("
            UPDATE clients 
            SET session_token = ?, last_activity = ? 
            WHERE id = ?
        ");
        $updateStmt->bind_param("ssi", $new_token, $now_datetime, $client['id']);
        $updateStmt->execute();
        $updateStmt->close();

        session_regenerate_id(true);

        // Reset rate limiting on successful login
        unset($_SESSION['client_attempts'], $_SESSION['client_last_attempt']);

        // Store critical session data
        $_SESSION['client_id']       = (int)$client['id'];
        $_SESSION['is_client']       = true;
        $_SESSION['session_token']   = $new_token;           // For single-session validation
        $_SESSION['last_activity']   = $now;                 // For inactivity timeout
        
        // Track workflow type (visa processing optional)
        if (VISA_PROCESSING_ENABLED && isset($client['processing_type'])) {
            $_SESSION['processing_type'] = (string)$client['processing_type'];
        } else {
            $_SESSION['processing_type'] = 'booking'; // Fallback to booking
        }
        
        $_SESSION['client'] = [
            'id'                  => (int)$client['id'],
            'full_name'           => (string)$client['full_name'],
            'email'               => (string)$client['email'],
            'access_code'         => (string)$client['access_code'],
            'client_profile_photo'=> (string)$client['client_profile_photo'],
            'processing_type'     => $_SESSION['processing_type']
        ];

        // Check for pending first-time survey (unchanged)
        $surveyStmt = $conn->prepare("
            SELECT id
            FROM user_survey_status
            WHERE user_id = ? AND user_role = 'client'
            AND survey_type = 'first_login' AND is_completed = 0
            AND created_at <= NOW()
            LIMIT 1
        ");
        if (!$surveyStmt) {
            throw new Exception("Survey query preparation failed: " . $conn->error);
        }

        $surveyStmt->bind_param("i", $client['id']);
        $surveyStmt->execute();
        $surveyResult = $surveyStmt->get_result();

        if ($surveyResult->num_rows === 1) {
            $survey = $surveyResult->fetch_assoc();
            $_SESSION['show_client_survey_modal'] = true;
            $_SESSION['survey_type'] = 'first_login';
            $_SESSION['template_id'] = (int)$survey['id'];
        }
        $surveyStmt->close();

        $_SESSION['show_disclaimer'] = true;
        $processingType = VISA_PROCESSING_ENABLED ? $client['processing_type'] ?? 'booking' : 'booking';
        error_log("Client login successful: id={$client['id']}, access_code={$client['access_code']}, processing_type=$processingType");
        header("Location: client_dashboard.php");
        $clientStmt->close();
        $conn->close();
        exit;
    }

    // Login failed
    error_log("Login failed: access_code=$access_code");
    $_SESSION['login_error'] = "Invalid Access Code.";
    header("Location: login.php");
    $clientStmt->close();
    $conn->close();
    exit;

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['login_error'] = "An error occurred during login.";
    header("Location: login.php");
    $conn->close();
    exit;
}
?>