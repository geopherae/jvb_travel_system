<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/image_compression_helper.php';
require_once __DIR__ . '/../components/status_alert.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../actions/notify.php';

use function LogHelper\logClientOnboardingAudit;

function toMysqlDate($input) {
  if (!$input) return null;
  $timestamp = strtotime($input);
  return $timestamp ? date('Y-m-d', $timestamp) : null;
}

// Sanitize inputs
$processingType    = trim($_POST['processing_type'] ?? 'visa');
$applicationMode    = trim($_POST['application_mode'] ?? 'individual'); // 'individual' or 'group'
$applicationMode    = in_array($applicationMode, ['individual','group'], true) ? $applicationMode : 'individual';
$groupMembersJson   = trim($_POST['group_members_json'] ?? '');
$fullName          = trim($_POST['full_name'] ?? '');
$email             = strtolower(trim($_POST['email'] ?? ''));
$phone             = trim($_POST['phone_number'] ?? '');
$address           = trim($_POST['address'] ?? '');
$accessCode        = trim($_POST['access_code'] ?? '');
$groupCode         = trim($_POST['group_code'] ?? '') ?: null; // Use existing group code or NULL

// Passport & visa status fields for lead applicant
$passportNumber    = trim($_POST['passport_number'] ?? '') ?: null;
$passportExpiry    = toMysqlDate($_POST['passport_expiry'] ?? '');
$visaLeadApplicantStatus = trim($_POST['applicant_status'] ?? '') ?: null;

// Visa-specific fields (optional)
$visaPackageId     = !empty($_POST['visa_package_id']) ? intval($_POST['visa_package_id']) : null;
$visaTypeSelected  = trim($_POST['visa_type_selected'] ?? '') ?: null;

$currentAdminId    = $_SESSION['admin']['id'] ?? null;
$assignedAdminId   = !empty($_POST['assigned_admin_id']) ? intval($_POST['assigned_admin_id']) : $currentAdminId;

// Debug logging
error_log("[process_add_visa_client] POST data: " . json_encode($_POST));
error_log("[process_add_visa_client] Extracted visa_package_id: " . var_export($visaPackageId, true));
error_log("[process_add_visa_client] visaTypeSelected: " . var_export($visaTypeSelected, true));
error_log("[process_add_visa_client] visaLeadApplicantStatus: " . var_export($visaLeadApplicantStatus, true));

// Validate inputs
$errors = [];

if ($fullName === '') $errors[] = 'Full name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
if (!preg_match('/^09\d{9}$/', $phone)) $errors[] = 'Phone must start with 09 and have 11 digits.';
if ($address === '') $errors[] = 'Address is required.';
if (!in_array($processingType, ['booking', 'visa', 'both'])) $errors[] = 'Invalid processing type.';

// If a visa package is selected, verify it exists to avoid silent failures later
if ($visaPackageId) {
  $pkgExistsStmt = $conn->prepare("SELECT id FROM visa_packages WHERE id = ?");
  $pkgExistsStmt->bind_param("i", $visaPackageId);
  $pkgExistsStmt->execute();
  $pkgExistsStmt->store_result();
  if ($pkgExistsStmt->num_rows === 0) {
    $errors[] = 'Selected visa package was not found. Please choose a valid package.';
  }
  $pkgExistsStmt->close();
}

// Handle profile photo
$photoFile = $_FILES['client_profile_photo'] ?? null;
$photoName = '';

if ($photoFile && $photoFile['error'] === UPLOAD_ERR_OK) {
  $ext = strtolower(pathinfo($photoFile['name'], PATHINFO_EXTENSION));
  $maxSize = 3 * 1024 * 1024;
  $allowedExts = ['jpg', 'jpeg', 'png'];
  $allowedMimeTypes = ['image/jpeg', 'image/png'];

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $photoFile['tmp_name']);
  finfo_close($finfo);

  if (!in_array($ext, $allowedExts) || !in_array($mimeType, $allowedMimeTypes)) {
    $errors[] = 'Invalid file type. Only JPG, JPEG, PNG allowed.';
  } elseif ($photoFile['size'] > $maxSize) {
    $errors[] = 'File too large. Max 3MB allowed.';
  }

  if (empty($errors)) {
    $newName = 'client_' . time() . '_' . rand(100, 999) . '.jpg'; // Always save as JPG
    $targetDir = __DIR__ . '/../uploads/client_profiles/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

    $destinationPath = $targetDir . $newName;

    // Compress and convert image
    $success = compressImage($photoFile['tmp_name'], $destinationPath, $mimeType, 75);
    if ($success) {
      $photoName = $newName;
    } else {
      $errors[] = 'Image compression failed.';
    }
  }
}

// Check for errors
if (!empty($errors)) {
  $_SESSION['form_errors'] = $errors;
  header("Location: ../admin/admin_visa_dashboard.php");
  exit();
}

// Generate group code if not provided (first member of group)
if (!$groupCode) {
  $groupBase = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($fullName, 0, 6)));
  $groupSuffix = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
  $groupCode = $groupBase . '-GROUP-' . $groupSuffix;
}

// Decode group members if provided (Step 3 submission)
$groupMembers = [];
if ($applicationMode === 'group' && !empty($groupMembersJson)) {
  $groupMembers = json_decode($groupMembersJson, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    $_SESSION['form_errors'] = ['Invalid group members data.'];
    header("Location: ../admin/admin_visa_dashboard.php");
    exit();
  }
  if (count($groupMembers) > 10) {
    $_SESSION['form_errors'] = ['You can add up to 10 additional members per application. Please submit another application for the rest.'];
    header("Location: ../admin/admin_visa_dashboard.php");
    exit();
  }
}

// Check for duplicate email
$emailCheck = $conn->prepare("SELECT id FROM clients WHERE email = ?");
$emailCheck->bind_param("s", $email);
$emailCheck->execute();
$emailCheck->store_result();

if ($emailCheck->num_rows > 0) {
  $_SESSION['message'] = 'Client already exists';
  $_SESSION['message_type'] = 'error';
  header("Location: ../admin/admin_visa_dashboard.php");
  exit();
}
$emailCheck->close();

/**
 * Create a single client record with optional visa application
 */
function createVisaClient(
  $conn, $assignedAdminId, $fullName, $email, $phone, $address,
  $photoName, $accessCode, $groupCode, $processingType,
  $passportNumber, $passportExpiry, $visaLeadApplicantStatus,
  $visaPackageId, $visaTypeSelected, $applicationMode = 'individual'
) {
  $status    = 'Awaiting Docs';
  $createdAt = date('Y-m-d H:i:s');

  $stmt = $conn->prepare("INSERT INTO clients (
    assigned_admin_id, full_name, email, phone_number, address,
    client_profile_photo, access_code, group_code, processing_type,
    passport_number, passport_expiry, visa_lead_applicant_status,
    status, created_at
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

  $stmt->bind_param(
    "isssssssssssss",
    $assignedAdminId,
    $fullName,
    $email,
    $phone,
    $address,
    $photoName,
    $accessCode,
    $groupCode,
    $processingType,
    $passportNumber,
    $passportExpiry,
    $visaLeadApplicantStatus,
    $status,
    $createdAt
  );

  if (!$stmt->execute()) {
    throw new Exception('Database error: ' . $stmt->error);
  }

  $clientId = $stmt->insert_id;
  $stmt->close();

  // Create visa application if visa package selected
  $visaApplicationId = null;
  if ($visaPackageId) {
    error_log("[createVisaClient] Attempting to create visa application with visa_package_id: $visaPackageId");
    
    // Build visa_types_json if visa_type_selected is provided
    $visaTypesJson = null;
    if ($visaTypeSelected) {
      $visaTypesJson = json_encode([$visaTypeSelected], JSON_UNESCAPED_UNICODE);
    }
    // applicant_status column has JSON_VALID check; store as JSON string
    $applicantStatusJson = json_encode($visaLeadApplicantStatus ?? '', JSON_UNESCAPED_UNICODE);
    
    $visaAppSql = "INSERT INTO client_visa_applications (
      client_id, visa_package_id, application_mode, visa_type_selected, visa_types_json, applicant_status, status, created_at, updated_at, approved_at
    ) VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, NULL)";
    
    $visaAppStmt = $conn->prepare($visaAppSql);
    if (!$visaAppStmt) {
      throw new Exception("[createVisaClient] Prepare failed: " . $conn->error . " | SQL: " . $visaAppSql);
    }
    
    $bindResult = $visaAppStmt->bind_param(
      "iissssss",
      $clientId,
      $visaPackageId,
      $applicationMode,
      $visaTypeSelected,
      $visaTypesJson,
      $applicantStatusJson,
      $createdAt,
      $createdAt
    );
    if (!$bindResult) {
      throw new Exception("[createVisaClient] Bind failed: " . $visaAppStmt->error . " | Params: client=$clientId, pkg=$visaPackageId, mode=$applicationMode, typeSel=" . var_export($visaTypeSelected, true) . ", typesJson=" . var_export($visaTypesJson, true) . ", applStatus=" . var_export($visaLeadApplicantStatus, true) . ", createdAt=$createdAt");
    }
    
    if (!$visaAppStmt->execute()) {
      throw new Exception("[createVisaClient] Execute failed: " . $visaAppStmt->error . " | Params: client=$clientId, pkg=$visaPackageId, mode=$applicationMode, typeSel=" . var_export($visaTypeSelected, true) . ", typesJson=" . var_export($visaTypesJson, true) . ", applStatus=" . var_export($visaLeadApplicantStatus, true) . ", createdAt=$createdAt");
    }
    
    $visaApplicationId = $visaAppStmt->insert_id;
    error_log("[createVisaClient] Visa application created with ID: $visaApplicationId");
    
    $visaAppStmt->close();
    
    // Update client with visa_application_id
    $updateClientSql = "UPDATE clients SET visa_application_id = ? WHERE id = ?";
    $updateClientStmt = $conn->prepare($updateClientSql);
    if (!$updateClientStmt) {
      throw new Exception("[createVisaClient] Update prepare failed: " . $conn->error . " | SQL: " . $updateClientSql);
    }
    
    $updateClientStmt->bind_param("ii", $visaApplicationId, $clientId);
    if (!$updateClientStmt->execute()) {
      throw new Exception("[createVisaClient] Update execute failed: " . $updateClientStmt->error);
    }
    $updateClientStmt->close();
  } else {
    error_log("[createVisaClient] No visa_package_id provided, skipping visa application creation");
  }

  // Insert survey tracking entries
  $surveyTypes = ['first_login'];
  $isCompleted = 0;
  foreach ($surveyTypes as $type) {
    $initialPayload = json_encode([
      'survey_type' => $type,
      'responses' => new stdClass(),
      'submitted_at' => null
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $surveyStmt = $conn->prepare("INSERT INTO user_survey_status 
      (user_id, user_role, survey_type, is_completed, created_at, response_payload) 
      VALUES (?, 'client', ?, ?, ?, ?)");
    $surveyStmt->bind_param("issss", $clientId, $type, $isCompleted, $createdAt, $initialPayload);
    $surveyStmt->execute();
    $surveyStmt->close();
  }

  return ['clientId' => $clientId, 'visaApplicationId' => $visaApplicationId];
}

// Create lead guest (main client from form)
try {
  $result = createVisaClient(
    $conn, $assignedAdminId, $fullName, $email, $phone, $address,
    $photoName, $accessCode, $groupCode, $processingType,
    $passportNumber, $passportExpiry, $visaLeadApplicantStatus,
    $visaPackageId, $visaTypeSelected, $applicationMode
  );
  $clientId = $result['clientId'];
  $visaApplicationId = $result['visaApplicationId'];
} catch (Exception $e) {
  $_SESSION['form_errors'] = [$e->getMessage()];
  header("Location: ../admin/admin_visa_dashboard.php");
  exit();
}

// Track created clients for notification/audit
$createdClients = [
  ['id' => $clientId, 'name' => $fullName, 'email' => $email, 'access_code' => $accessCode]
];

// Process additional group members if present
if (!empty($groupMembers)) {
  foreach ($groupMembers as $member) {
    // Generate unique access code for each member
    $memberAccessCode = 'V-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
    
    // Validate member data
    $memberName = trim($member['fullName'] ?? '');
    $memberEmail = strtolower(trim($member['email'] ?? ''));
    $memberPhone = trim($member['phone'] ?? '');
    $memberAddress = trim($member['address'] ?? '');
    $memberRelationship = trim($member['relationship'] ?? '') ?: null;
    $memberPassportNumber = trim($member['passportNumber'] ?? '') ?: null;
    $memberPassportExpiry = toMysqlDate($member['passportExpiry'] ?? '');
    $memberApplicantStatus = trim($member['applicantStatus'] ?? '') ?: null;

    if (empty($memberName) || empty($memberEmail) || empty($memberPhone) || empty($memberAddress)) {
      continue; // Skip invalid members
    }

    // Check for duplicate email
    $emailCheck = $conn->prepare("SELECT id FROM clients WHERE email = ?");
    $emailCheck->bind_param("s", $memberEmail);
    $emailCheck->execute();
    $emailCheck->store_result();
    if ($emailCheck->num_rows > 0) {
      $emailCheck->close();
      continue; // Skip duplicate emails
    }
    $emailCheck->close();

    try {
      $memberResult = createVisaClient(
        $conn, $assignedAdminId, $memberName, $memberEmail, $memberPhone, $memberAddress,
        '', $memberAccessCode, $groupCode, $processingType,
        $memberPassportNumber, $memberPassportExpiry, $memberApplicantStatus,
        $visaPackageId, $visaTypeSelected, $applicationMode
      );
      
      $memberClientId = $memberResult['clientId'];
      $memberVisaAppId = $memberResult['visaApplicationId'];
      
      // If visa application exists, also create companion record in client_visa_companions
      if ($visaApplicationId) {
        $companionStmt = $conn->prepare("INSERT INTO client_visa_companions (
          visa_application_id, full_name, email, phone_number, passport_number, passport_expiry,
          relationship, applicant_status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $nowTs = date('Y-m-d H:i:s');
        $companionStmt->bind_param(
          "isssssssss",
          $visaApplicationId,
          $memberName,
          $memberEmail,
          $memberPhone,
          $memberPassportNumber,
          $memberPassportExpiry,
          $memberRelationship,
          $memberApplicantStatus,
          $nowTs,
          $nowTs
        );
        
        if (!$companionStmt->execute()) {
          error_log("[process_add_visa_client] Failed to insert companion: " . $companionStmt->error);
        }
        $companionStmt->close();
      }
      
      $createdClients[] = [
        'id' => $memberClientId,
        'name' => $memberName,
        'email' => $memberEmail,
        'access_code' => $memberAccessCode
      ];
    } catch (Exception $e) {
      error_log("Failed to create group member: " . $e->getMessage());
      // Continue processing other members
    }
  }
}

// Fetch visa package name if selected
$visaPackageName = '';
if ($visaPackageId) {
  $pkgStmt = $conn->prepare("SELECT country FROM visa_packages WHERE id = ?");
  $pkgStmt->bind_param("i", $visaPackageId);
  $pkgStmt->execute();
  $pkgStmt->bind_result($visaPackageName);
  $pkgStmt->fetch();
  $pkgStmt->close();
}

// Fetch admin name
$adminName = '';
if ($assignedAdminId) {
  $adminStmt = $conn->prepare("SELECT first_name, last_name FROM admin_accounts WHERE id = ?");
  $adminStmt->bind_param("i", $assignedAdminId);
  $adminStmt->execute();
  $adminStmt->bind_result($firstName, $lastName);
  $adminStmt->fetch();
  $adminStmt->close();
  $adminName = trim($firstName . ' ' . $lastName);
}

// Log audit
// Log audit for each created client
foreach ($createdClients as $client) {
  logClientOnboardingAudit($conn, [
    'actor_id'   => $assignedAdminId,
    'client_id'  => $client['id'],
    'payload'    => [
      'client_name'      => $client['name'],
      'processing_type'  => $processingType,
      'visa_package'     => $visaPackageName,
      'assigned_admin'   => $adminName,
      'group_code'       => $groupCode,
      'application_mode' => $applicationMode,
      'source'           => 'process_add_visa_client.php'
    ]
  ]);
}

// Send Notification to All Admins
$manager = new NotificationManager($conn);
$clientCount = count($createdClients);

if ($clientCount === 1) {
  // Single client notification
  $notifyResult = $manager->broadcastToAdmins('new_visa_client_added', [
    'client_name' => $fullName,
    'email' => $email,
    'phone_number' => $phone,
    'processing_type' => ucfirst($processingType),
    'visa_package' => $visaPackageName ?: 'Not Assigned',
    'assigned_admin' => $adminName,
    'client_id' => $clientId
  ]);
} else {
  // Group notification
  $notifyResult = $manager->broadcastToAdmins('new_visa_group_added', [
    'group_code' => $groupCode,
    'client_count' => $clientCount,
    'lead_guest' => $fullName,
    'processing_type' => ucfirst($processingType),
    'visa_package' => $visaPackageName ?: 'Not Assigned',
    'assigned_admin' => $adminName
  ]);
}
error_log("[process_add_visa_client] Notification broadcast result: " . json_encode($notifyResult));

// Success toast message
if ($clientCount === 1) {
  $_SESSION['message'] = "Visa client <strong>" . htmlspecialchars($fullName) . "</strong> added successfully! Access Code: <code class='font-mono'>" . htmlspecialchars($accessCode) . "</code> | Group Code: <code class='font-mono'>" . htmlspecialchars($groupCode) . "</code>";
} else {
  $_SESSION['message'] = "Successfully added <strong>$clientCount</strong> visa clients to Group <code class='font-mono'>" . htmlspecialchars($groupCode) . "</code>. Lead guest: <strong>" . htmlspecialchars($fullName) . "</strong>";
}
$_SESSION['message_type'] = 'success';

// If a visa package was selected but no visa application was created, surface a visible warning
if ($visaPackageId && empty($visaApplicationId)) {
  $_SESSION['message'] .= "<br><span class='text-yellow-700'>Note: Visa application record was not created. Check server error logs for details (search for [createVisaClient]).</span>";
  $_SESSION['message_type'] = 'warning';
}

// Store group data for "Add Another Group Member" option (only for individual mode)
if ($applicationMode === 'individual') {
  $_SESSION['visa_client_added'] = [
    'client_id' => $clientId,
    'client_name' => $fullName,
    'access_code' => $accessCode,
    'group_code' => $groupCode,
    'processing_type' => $processingType,
    'visa_package_id' => $visaPackageId,
    'assigned_admin_id' => $assignedAdminId
  ];

  // Redirect with query param to show "Add Another" option
  header("Location: ../admin/admin_visa_dashboard.php?visa_added=1");
} else {
  // Group mode: just redirect to dashboard
  header("Location: ../admin/admin_visa_dashboard.php");
}
exit();