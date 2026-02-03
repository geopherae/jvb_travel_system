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

    // === CHECK FOR GROUP ACCESS CODE (VISA APPLICATIONS) ===
    // Group access code allows any member of a group application to see all applicants' documents
    if (VISA_PROCESSING_ENABLED) {
        $groupAccessStmt = $conn->prepare("
            SELECT id AS app_id, client_id, application_mode, status
            FROM client_visa_applications
            WHERE group_access_code = ?
        ");
        if (!$groupAccessStmt) {
            throw new Exception("Group access query preparation failed: " . $conn->error);
        }

        $groupAccessStmt->bind_param("s", $access_code);
        $groupAccessStmt->execute();
        $groupAccessResult = $groupAccessStmt->get_result();

        if ($groupAccessResult->num_rows === 1) {
            $groupApp = $groupAccessResult->fetch_assoc();
            $groupLeadClientId = $groupApp['client_id'];

            // Fetch group lead client info for session
            $groupLeadStmt = $conn->prepare("
                SELECT id, full_name, email, client_profile_photo, processing_type
                FROM clients
                WHERE id = ?
            ");
            $groupLeadStmt->bind_param("i", $groupLeadClientId);
            $groupLeadStmt->execute();
            $groupLeadResult = $groupLeadStmt->get_result();
            
            if ($groupLeadResult->num_rows === 1) {
                $groupLead = $groupLeadResult->fetch_assoc();
                
                // Generate session token
                $new_token = bin2hex(random_bytes(32));
                $now = time();

                session_regenerate_id(true);

                // Reset rate limiting on successful login
                unset($_SESSION['client_attempts'], $_SESSION['client_last_attempt']);

                // Set session for group access
                $_SESSION['client_id']        = (int)$groupLeadClientId;
                $_SESSION['is_client']        = true;
                $_SESSION['is_companion']     = false;
                $_SESSION['group_access_enabled'] = true; // KEY: Flag for group access
                $_SESSION['visa_app_id']      = (int)$groupApp['app_id']; // Link to specific visa app
                $_SESSION['session_token']    = $new_token;
                $_SESSION['last_activity']    = $now;
                $_SESSION['processing_type']  = 'visa'; // Group access is always visa
                
                $_SESSION['client'] = [
                    'id'                  => (int)$groupLeadClientId,
                    'full_name'           => (string)$groupLead['full_name'],
                    'email'               => (string)$groupLead['email'],
                    'access_code'         => (string)$access_code, // Store the group access code
                    'client_profile_photo'=> (string)$groupLead['client_profile_photo'],
                    'processing_type'     => 'visa',
                    'is_companion'        => false,
                    'is_group_access'     => true
                ];

                $_SESSION['show_disclaimer'] = true;
                error_log("Group access login successful: app_id={$groupApp['app_id']}, client_id={$groupLeadClientId}, group_access_code={$access_code}");
                
                $groupLeadStmt->close();
                $groupAccessStmt->close();
                $conn->close();
                header("Location: client_visa_dashboard.php");
                exit;
            }
            $groupLeadStmt->close();
        }
        $groupAccessStmt->close();
    }

    // === CHECK VISA COMPANION FIRST ===
    // If access_code exists in client_visa_companions, they're a group member
    if (VISA_PROCESSING_ENABLED) {
        $companionStmt = $conn->prepare("
            SELECT id AS companion_id, client_id, full_name, email, access_code
            FROM client_visa_companions
            WHERE access_code = ?
        ");
        if (!$companionStmt) {
            throw new Exception("Companion query preparation failed: " . $conn->error);
        }

        $companionStmt->bind_param("s", $access_code);
        $companionStmt->execute();
        $companionResult = $companionStmt->get_result();

        if ($companionResult->num_rows === 1) {
            $companion = $companionResult->fetch_assoc();

            // Generate session token for companion
            $new_token = bin2hex(random_bytes(32));
            $now = time();
            $now_datetime = date('Y-m-d H:i:s', $now);

            // Update companion record with session token
            $updateCompanionStmt = $conn->prepare("
                UPDATE client_visa_companions 
                SET session_token = ?, last_activity = ? 
                WHERE id = ?
            ");
            $updateCompanionStmt->bind_param("ssi", $new_token, $now_datetime, $companion['companion_id']);
            $updateCompanionStmt->execute();
            $updateCompanionStmt->close();

            session_regenerate_id(true);

            // Reset rate limiting on successful login
            unset($_SESSION['client_attempts'], $_SESSION['client_last_attempt']);

            // Set session for companion - ALWAYS visa processing
            $_SESSION['client_id']       = (int)$companion['client_id']; // Link to main client
            $_SESSION['companion_id']    = (int)$companion['companion_id']; // Their own companion ID
            $_SESSION['is_client']       = true;
            $_SESSION['is_companion']    = true; // Flag to identify companion login
            $_SESSION['session_token']   = $new_token;
            $_SESSION['last_activity']   = $now;
            $_SESSION['processing_type'] = 'visa'; // Companions always see visa processing
            
            $_SESSION['client'] = [
                'id'                  => (int)$companion['companion_id'],
                'full_name'           => (string)$companion['full_name'],
                'email'               => (string)$companion['email'],
                'access_code'         => (string)$companion['access_code'],
                'client_profile_photo'=> '', // Companions don't have profile photos yet
                'processing_type'     => 'visa',
                'is_companion'        => true
            ];

            $_SESSION['show_disclaimer'] = true;
            error_log("Companion login successful: companion_id={$companion['companion_id']}, access_code={$companion['access_code']}, linked_to_client_id={$companion['client_id']}");
            
            $companionStmt->close();
            $conn->close();
            // Companions always have visa processing
            header("Location: client_visa_dashboard.php");
            exit;
        }
        $companionStmt->close();
    }

    // === CLIENT LOGIN (Main Account) ===
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
        $_SESSION['is_companion']    = false; // Main client, not a companion
        $_SESSION['session_token']   = $new_token;           // For single-session validation
        $_SESSION['last_activity']   = $now;                 // For inactivity timeout
        
        // Track workflow type based on processing_type from DB
        if (VISA_PROCESSING_ENABLED && isset($client['processing_type'])) {
            $_SESSION['processing_type'] = (string)$client['processing_type']; // 'booking', 'visa', or 'both'
        } else {
            $_SESSION['processing_type'] = 'booking'; // Fallback to booking if feature disabled
        }
        
        $_SESSION['client'] = [
            'id'                  => (int)$client['id'],
            'full_name'           => (string)$client['full_name'],
            'email'               => (string)$client['email'],
            'access_code'         => (string)$client['access_code'],
            'client_profile_photo'=> (string)$client['client_profile_photo'],
            'processing_type'     => $_SESSION['processing_type'],
            'is_companion'        => false
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
        
        // Redirect based on processing type
        // If 'visa' only, go to visa dashboard
        // If 'booking' or 'both', go to booking dashboard
        $redirectUrl = ($processingType === 'visa') ? 'client_visa_dashboard.php' : 'client_dashboard.php';
        
        $clientStmt->close();
        $conn->close();
        header("Location: $redirectUrl");
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