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

function columnExists($conn, $table, $column) {
  $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
  $stmt = $conn->prepare($sql);
  if (!$stmt) return false;
  $stmt->bind_param("ss", $table, $column);
  $stmt->execute();
  $stmt->bind_result($count);
  $stmt->fetch();
  $stmt->close();
  return $count > 0;
}

function hasJsonCheckConstraint($conn, $table, $column) {
  $sql = "SELECT cc.CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS cc\n          JOIN information_schema.TABLE_CONSTRAINTS tc\n            ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA\n           AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME\n         WHERE tc.TABLE_SCHEMA = DATABASE()\n           AND tc.TABLE_NAME = ?\n           AND tc.CONSTRAINT_TYPE = 'CHECK'\n           AND cc.CHECK_CLAUSE LIKE ?";
  $like = '%json_valid(`' . $column . '`)%';
  $stmt = $conn->prepare($sql);
  if (!$stmt) return false;
  $stmt->bind_param("ss", $table, $like);
  $stmt->execute();
  $stmt->store_result();
  $has = $stmt->num_rows > 0;
  $stmt->close();
  return $has;
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
$financialSource   = trim($_POST['financial_source'] ?? 'self_funded');
$financialSource   = in_array($financialSource, ['self_funded', 'sponsor'], true) ? $financialSource : 'self_funded';

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
if (empty($assignedAdminId)) $errors[] = 'Assigned admin is required.';
if ($applicationMode === 'group' && empty($visaPackageId)) {
  $errors[] = 'Group applications require a visa package selection.';
}

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
  error_log("[process_add_visa_client] Validation errors found: " . json_encode($errors));
  header("Location: ../admin/admin_visa_dashboard.php");
  exit();
}

// Note: group_code column removed from clients table
// Each companion now has individual access_code in client_visa_companions table

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
  $dupMessage = 'Client already exists with this email.';
  $_SESSION['message'] = $dupMessage;
  $_SESSION['message_type'] = 'error';
  $_SESSION['form_errors'] = [$dupMessage];
  error_log("[process_add_visa_client] Duplicate email blocked: $email");
  header("Location: ../admin/admin_visa_dashboard.php");
  exit();
}
$emailCheck->close();

// Generate unique group_code for group applications (saved in clients table for lead applicant)
$groupCode = null;
if ($applicationMode === 'group') {
  // Extract first and last name for code generation
  $nameParts = preg_split('/\s+/', trim($fullName));
  if (count($nameParts) >= 2) {
    // Use first 2 letters of first name + first 2 letters of last name
    $firstInitials = strtoupper(substr($nameParts[0], 0, 2));
    $lastInitials = strtoupper(substr($nameParts[count($nameParts) - 1], 0, 2));
    $groupCode = $firstInitials . $lastInitials . '-' . rand(1000, 9999);
  } else {
    // Fallback: use first 4 letters of name if only one word
    $groupCode = strtoupper(str_pad(substr($nameParts[0], 0, 4), 4, 'G')) . '-' . rand(1000, 9999);
  }
  error_log("[process_add_visa_client] Generated group_code for lead applicant: $groupCode (access_code: $accessCode)");
}

// Generate unique group access code for visa application (different from group_code)
$groupAccessCode = null;
if ($applicationMode === 'group') {
  // Generate group access code: XXXX-NNNN format
  $groupAccessCode = strtoupper(str_pad(substr($fullName, 0, 4), 4, 'G')) . '-' . rand(1000, 9999);
  // Ensure it's different from lead's access code
  while ($groupAccessCode === $accessCode) {
    $groupAccessCode = strtoupper(str_pad(substr($fullName, 0, 4), 4, 'G')) . '-' . rand(1000, 9999);
  }
  error_log("[process_add_visa_client] Generated group access code: $groupAccessCode (lead code: $accessCode)");
}

/**
 * Create a single client record with optional visa application
 */
function createVisaClient(
  $conn, $assignedAdminId, $fullName, $email, $phone, $address,
  $photoName, $accessCode, $processingType,
  $passportNumber, $passportExpiry, $visaLeadApplicantStatus,
  $visaPackageId, $visaTypeSelected, $applicationMode = 'individual',
  $financialSource = 'self_funded', $groupAccessCode = null, $groupCode = null
) {
  error_log("[createVisaClient] Starting with assignedAdminId=$assignedAdminId, fullName=$fullName, email=$email, visaPackageId=$visaPackageId");
  $status    = 'Awaiting Docs';
  $createdAt = date('Y-m-d H:i:s');

  $clientColumns = [
    'assigned_admin_id', 'full_name', 'email', 'phone_number', 'address',
    'client_profile_photo', 'access_code', 'processing_type',
    'passport_number', 'passport_expiry', 'visa_lead_applicant_status',
    'status', 'created_at'
  ];
  $clientPlaceholders = array_fill(0, count($clientColumns), '?');
  $clientTypes = 'issssssssssss';
  $clientValues = [
    $assignedAdminId,
    $fullName,
    $email,
    $phone,
    $address,
    $photoName,
    $accessCode,
    $processingType,
    $passportNumber,
    $passportExpiry,
    $visaLeadApplicantStatus,
    $status,
    $createdAt
  ];

  if (columnExists($conn, 'clients', 'financial_source')) {
    $clientColumns[] = 'financial_source';
    $clientPlaceholders[] = '?';
    $clientTypes .= 's';
    $clientValues[] = $financialSource;
  }

  if (columnExists($conn, 'clients', 'group_code') && $groupCode !== null) {
    $clientColumns[] = 'group_code';
    $clientPlaceholders[] = '?';
    $clientTypes .= 's';
    $clientValues[] = $groupCode;
  }

  $clientSql = "INSERT INTO clients (" . implode(', ', $clientColumns) . ") VALUES (" . implode(', ', $clientPlaceholders) . ")";
  $stmt = $conn->prepare($clientSql);
  if (!$stmt) {
    throw new Exception('Database error: ' . $conn->error);
  }

  $bindParams = [&$clientTypes];
  foreach ($clientValues as $index => $value) {
    $bindParams[] = &$clientValues[$index];
  }

  $bindResult = call_user_func_array([$stmt, 'bind_param'], $bindParams);
  if (!$bindResult) {
    throw new Exception('Database error: ' . $stmt->error);
  }

  if (!$stmt->execute()) {
    throw new Exception('Database error: ' . $stmt->error);
  }

  $clientId = $stmt->insert_id;
  error_log("[createVisaClient] Client inserted successfully with id: $clientId");
  $stmt->close();

  // Create visa application if visa package selected
  $visaApplicationId = null;
  error_log("[createVisaClient] visaPackageId type: " . gettype($visaPackageId) . ", value: " . var_export($visaPackageId, true));
  
  if ($visaPackageId !== null && $visaPackageId > 0) {
    error_log("[createVisaClient] Attempting to create visa application with visa_package_id: $visaPackageId");
    
    // Insert visa application with only supported columns
    // Note: visa_type_selected is stored in client_visa_requirements, not in client_visa_applications
    $hasGroupAccessCodeCol = columnExists($conn, 'client_visa_applications', 'group_access_code');
    $hasApplicantStatusCol = columnExists($conn, 'client_visa_applications', 'applicant_status');
    $applicantStatusIsJson = hasJsonCheckConstraint($conn, 'client_visa_applications', 'applicant_status');
    $finalApplicantStatus = $visaLeadApplicantStatus;
    if ($applicantStatusIsJson && $visaLeadApplicantStatus !== null) {
      $finalApplicantStatus = json_encode($visaLeadApplicantStatus, JSON_UNESCAPED_UNICODE);
    }

    // Build columns: client_id, visa_package_id, application_mode, applicant_status, [optional: group_access_code]
    $visaAppColumns = "client_id, visa_package_id, application_mode";
    $visaAppPlaceholders = "?, ?, ?";
    $bindTypes = "iis";  // i=int, i=int, s=string (application_mode)
    $bindValues = [
      $clientId,
      intval($visaPackageId),
      $applicationMode
    ];

    // Add visa_type if column exists
    if (columnExists($conn, 'client_visa_applications', 'visa_type') && $visaTypeSelected !== null) {
      $visaAppColumns .= ", visa_type";
      $visaAppPlaceholders .= ", ?";
      $bindTypes .= "s";
      $bindValues[] = $visaTypeSelected;
    }

    // Add applicant_status if column exists and provided
    if ($hasApplicantStatusCol && $finalApplicantStatus !== null) {
      $visaAppColumns .= ", applicant_status";
      $visaAppPlaceholders .= ", ?";
      $bindTypes .= "s";
      $bindValues[] = $finalApplicantStatus;
    }

    // Add group_access_code if column exists
    if ($hasGroupAccessCodeCol) {
      $finalGroupAccessCode = $groupAccessCode !== null ? $groupAccessCode : $accessCode;
      $visaAppColumns .= ", group_access_code";
      $visaAppPlaceholders .= ", ?";
      $bindTypes .= "s";
      $bindValues[] = $finalGroupAccessCode;
      error_log("[createVisaClient] Group access code included: $finalGroupAccessCode");
    }

    $visaAppSql = "INSERT INTO client_visa_applications (
      $visaAppColumns, status, created_at, updated_at
    ) VALUES ($visaAppPlaceholders, 'Awaiting Docs', ?, ?)";;
    
    error_log("[createVisaClient] Preparing SQL: " . $visaAppSql);
    error_log("[createVisaClient] Bind types: $bindTypes, Values: " . json_encode($bindValues));
    
    $visaAppStmt = $conn->prepare($visaAppSql);
    if (!$visaAppStmt) {
      throw new Exception("[createVisaClient] Prepare failed: " . $conn->error . " | SQL: " . $visaAppSql);
    }
    
    // Add created_at and updated_at timestamps
    $bindTypes .= "ss";
    $bindValues[] = $createdAt;
    $bindValues[] = $createdAt;

    // Build bind_param array
    $bindParams = [&$bindTypes];
    foreach ($bindValues as $index => $value) {
      $bindParams[] = &$bindValues[$index];
    }

    $bindResult = call_user_func_array([$visaAppStmt, 'bind_param'], $bindParams);
    if (!$bindResult) {
      throw new Exception("[createVisaClient] Bind failed: " . $visaAppStmt->error);
    }
    
    if (!$visaAppStmt->execute()) {
      throw new Exception("[createVisaClient] Execute failed: " . $visaAppStmt->error . " (Check database constraints and data types)");
    }
    
    $visaApplicationId = $visaAppStmt->insert_id;
    error_log("[createVisaClient] Visa application created with ID: $visaApplicationId");
    
    $visaAppStmt->close();
    
    // Update client with visa_application_id
    if ($visaApplicationId > 0) {
      $updateClientSql = "UPDATE clients SET visa_application_id = ? WHERE id = ?";
      $updateClientStmt = $conn->prepare($updateClientSql);
      if (!$updateClientStmt) {
        throw new Exception("[createVisaClient] Update prepare failed: " . $conn->error . " | SQL: " . $updateClientSql);
      }
      
      $updateClientStmt->bind_param("ii", $visaApplicationId, $clientId);
      if (!$updateClientStmt->execute()) {
        throw new Exception("[createVisaClient] Update execute failed: " . $updateClientStmt->error);
      }
      error_log("[createVisaClient] Client $clientId updated with visa_application_id: $visaApplicationId");
      $updateClientStmt->close();
      
      // 🆕 Copy visa requirements from visa_packages template to client_visa_requirements
      // This mirrors the tour_packages → client itinerary pattern
      $fetchVisaPkgSql = "SELECT requirements_json FROM visa_packages WHERE id = ?";
      $fetchVisaPkgStmt = $conn->prepare($fetchVisaPkgSql);
      if (!$fetchVisaPkgStmt) {
        throw new Exception("[createVisaClient] Fetch visa package prepare failed: " . $conn->error);
      }
      
      $fetchVisaPkgStmt->bind_param("i", $visaPackageId);
      if (!$fetchVisaPkgStmt->execute()) {
        throw new Exception("[createVisaClient] Fetch visa package execute failed: " . $fetchVisaPkgStmt->error);
      }
      
      $fetchVisaPkgStmt->bind_result($requirementsJson);
      $fetchVisaPkgStmt->fetch();
      $fetchVisaPkgStmt->close();
      
      // Validate and use default if requirements_json is empty
      $requirementsJson = $requirementsJson ?: json_encode([], JSON_UNESCAPED_UNICODE);
      
      // Validate JSON
      if (json_decode($requirementsJson, true) === null && $requirementsJson !== '[]') {
        error_log("[createVisaClient] Warning: Invalid requirements_json from visa_package $visaPackageId, using empty array");
        $requirementsJson = json_encode([], JSON_UNESCAPED_UNICODE);
      }
      
      // Insert into client_visa_requirements for the main client (companion_id = NULL)
      $visaReqSql = "INSERT INTO client_visa_requirements 
        (client_id, companion_id, visa_type, requirements_json, created_at, updated_at) 
        VALUES (?, NULL, ?, ?, ?, ?)";
      
      $visaReqStmt = $conn->prepare($visaReqSql);
      if (!$visaReqStmt) {
        throw new Exception("[createVisaClient] Insert visa requirements prepare failed: " . $conn->error);
      }
      
      $visaReqStmt->bind_param("issss", $clientId, $visaTypeSelected, $requirementsJson, $createdAt, $createdAt);
      if (!$visaReqStmt->execute()) {
        throw new Exception("[createVisaClient] Insert visa requirements execute failed: " . $visaReqStmt->error);
      }
      
      error_log("[createVisaClient] Client $clientId: Visa requirements copied from package $visaPackageId (visa_type: $visaTypeSelected)");
      $visaReqStmt->close();
    }
  } else {
    error_log("[createVisaClient] No visa_package_id provided (value: " . var_export($visaPackageId, true) . "), skipping visa application creation");
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
    $photoName, $accessCode, $processingType,
    $passportNumber, $passportExpiry, $visaLeadApplicantStatus,
    $visaPackageId, $visaTypeSelected, $applicationMode,
    $financialSource, $groupAccessCode, $groupCode
  );
  $clientId = $result['clientId'];
  $visaApplicationId = $result['visaApplicationId'];
  error_log("[process_add_visa_client] Client created successfully: id=$clientId, visaApplicationId=$visaApplicationId, group_code=$groupCode");
} catch (Exception $e) {
  error_log("[process_add_visa_client] Exception during client creation: " . $e->getMessage());
  $_SESSION['form_errors'] = [$e->getMessage()];
  header("Location: ../admin/admin_visa_dashboard.php");
  exit();
}

// Track created clients for notification/audit
$createdClients = [
  ['id' => $clientId, 'name' => $fullName, 'email' => $email, 'access_code' => $accessCode]
];

// Process additional group members if present
error_log("[process_add_visa_client] Checking for group members. groupMembers count: " . count($groupMembers) . ", visaApplicationId: " . var_export($visaApplicationId, true));
if (!empty($groupMembers)) {
  error_log("[process_add_visa_client] Processing " . count($groupMembers) . " group members");
  foreach ($groupMembers as $idx => $member) {
    error_log("[process_add_visa_client] Processing member #$idx: " . json_encode($member));
    
    // Validate member data
    $memberName = trim($member['fullName'] ?? '');
    $memberEmail = strtolower(trim($member['email'] ?? ''));
    $memberPhone = trim($member['phone'] ?? '');
    $memberAge = !empty($member['age']) ? intval($member['age']) : null;
    $memberRelationship = trim($member['relationship'] ?? '') ?: null;
    $memberPassportNumber = trim($member['passportNumber'] ?? '') ?: null;
    $memberPassportExpiry = toMysqlDate($member['passportExpiry'] ?? '');
    $memberApplicantStatus = trim($member['applicantStatus'] ?? '') ?: null;
    $memberFinancialSource = trim($member['financialSource'] ?? '') ?: null;
    $memberVisaType = trim($member['visaType'] ?? '') ?: $visaTypeSelected;  // Use their own visa_type or fall back to lead's

    if (empty($memberName)) {
      continue; // Skip invalid members
    }

    // Generate unique access code for each companion (format: xxxx-yyyy)
    $nameParts = preg_split('/\s+/', trim($memberName));
    if (count($nameParts) >= 2) {
      $companionAccessCode = strtoupper(substr($nameParts[0], 0, 2) . substr($nameParts[count($nameParts) - 1], 0, 2)) . '-' . rand(1000, 9999);
    } else {
      $companionAccessCode = strtoupper(str_pad(substr($nameParts[0], 0, 4), 4, 'X')) . '-' . rand(1000, 9999);
    }
    
    // Insert companion directly into client_visa_companions table (companions don't get client records)
    if ($visaApplicationId) {
      // Build dynamic INSERT to handle optional columns
      $companionColumns = [
        'visa_application_id', 'full_name', 'email', 'phone_number', 'access_code', 
        'relationship', 'applicant_status', 'passport_number', 'passport_expiry', 
        'created_at', 'updated_at'
      ];
      $companionPlaceholders = array_fill(0, count($companionColumns), '?');
      $companionTypes = 'issssssssss';
      $nowTs = date('Y-m-d H:i:s');
      $companionValues = [
        $visaApplicationId,
        $memberName,
        $memberEmail,
        $memberPhone,
        $companionAccessCode,
        $memberRelationship,
        $memberApplicantStatus,
        $memberPassportNumber,
        $memberPassportExpiry,
        $nowTs,
        $nowTs
      ];
      
      // Add financial_source if column exists
      if (columnExists($conn, 'client_visa_companions', 'financial_source') && $memberFinancialSource !== null) {
        $companionColumns[] = 'financial_source';
        $companionPlaceholders[] = '?';
        $companionTypes .= 's';
        $companionValues[] = $memberFinancialSource;
      }
      
      $companionSql = "INSERT INTO client_visa_companions (" . implode(', ', $companionColumns) . ") VALUES (" . implode(', ', $companionPlaceholders) . ")";
      
      $companionStmt = $conn->prepare($companionSql);
      if (!$companionStmt) {
        error_log("[process_add_visa_client] Companion prepare failed: " . $conn->error);
        continue;
      }
      
      // Build bind_param array
      $bindParams = [&$companionTypes];
      foreach ($companionValues as $index => $value) {
        $bindParams[] = &$companionValues[$index];
      }
      
      $bindResult = call_user_func_array([$companionStmt, 'bind_param'], $bindParams);
      if (!$bindResult) {
        error_log("[process_add_visa_client] Companion bind failed: " . $companionStmt->error);
        $companionStmt->close();
        continue;
      }
      
      if (!$companionStmt->execute()) {
        error_log("[process_add_visa_client] Companion execute failed: " . $companionStmt->error . " | SQL: " . $companionSql);
        $companionStmt->close();
        continue;
      }
      
      $companionId = $companionStmt->insert_id;
      $companionStmt->close();
      
      // Safeguard: Ensure companion ID is never 0 (should be auto-increment >= 1)
      if ($companionId <= 0) {
        error_log("[process_add_visa_client] ERROR: Companion inserted with invalid ID: $companionId. This indicates a database schema issue.");
        continue;
      }
      
      // 🆕 Copy visa requirements from visa_packages template to client_visa_requirements for this companion
      $fetchVisaPkgSql = "SELECT requirements_json FROM visa_packages WHERE id = ?";
      $fetchVisaPkgStmt = $conn->prepare($fetchVisaPkgSql);
      if ($fetchVisaPkgStmt) {
        $fetchVisaPkgStmt->bind_param("i", $visaPackageId);
        if ($fetchVisaPkgStmt->execute()) {
          $fetchVisaPkgStmt->bind_result($companionRequirementsJson);
          $fetchVisaPkgStmt->fetch();
          $fetchVisaPkgStmt->close();
          
          // Validate and use default if requirements_json is empty
          $companionRequirementsJson = $companionRequirementsJson ?: json_encode([], JSON_UNESCAPED_UNICODE);
          
          // Validate JSON
          if (json_decode($companionRequirementsJson, true) === null && $companionRequirementsJson !== '[]') {
            $companionRequirementsJson = json_encode([], JSON_UNESCAPED_UNICODE);
          }
          
          // Insert into client_visa_requirements for this companion
          $visaReqSql = "INSERT INTO client_visa_requirements 
            (client_id, companion_id, visa_type, requirements_json, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?)";
          
          $visaReqStmt = $conn->prepare($visaReqSql);
          if ($visaReqStmt) {
            $visaReqStmt->bind_param("iissss", $clientId, $companionId, $memberVisaType, $companionRequirementsJson, $nowTs, $nowTs);
            $visaReqStmt->execute();
            $visaReqStmt->close();
          }
        }
      }
      
      // Track companion for notification/audit
      $createdClients[] = [
        'id' => 'companion_' . $companionId,
        'name' => $memberName,
        'email' => $memberEmail ?: 'N/A',
        'access_code' => $companionAccessCode,
        'is_companion' => true
      ];
      
      $companionStmt->close();
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
// Log audit for each created client and companion
foreach ($createdClients as $client) {
  // Only log for actual client records (not companions)
  if (!isset($client['is_companion']) || !$client['is_companion']) {
    logClientOnboardingAudit($conn, [
      'actor_id'   => $assignedAdminId,
      'client_id'  => $client['id'],
      'payload'    => [
        'client_name'      => $client['name'],
        'processing_type'  => $processingType,
        'visa_package'     => $visaPackageName,
        'assigned_admin'   => $adminName,
        'application_mode' => $applicationMode,
        'access_code'      => $client['access_code'],
        'source'           => 'process_add_visa_client.php'
      ]
    ]);
  }
}

// Send Notification to All Admins
$manager = new NotificationManager($conn);
$companionCount = count(array_filter($createdClients, function($c) { return isset($c['is_companion']) && $c['is_companion']; }));

if ($companionCount === 0) {
  // Single client notification (individual application)
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
  // Group notification (application with companions)
  $notifyResult = $manager->broadcastToAdmins('new_visa_group_added', [
    'companion_count' => $companionCount,
    'lead_guest' => $fullName,
    'processing_type' => ucfirst($processingType),
    'visa_package' => $visaPackageName ?: 'Not Assigned',
    'assigned_admin' => $adminName,
    'client_id' => $clientId
  ]);
}
error_log("[process_add_visa_client] Notification broadcast result: " . json_encode($notifyResult));

// Success toast message
if ($companionCount === 0) {
  // Individual application
  $_SESSION['message'] = "Visa client <strong>" . htmlspecialchars($fullName) . "</strong> added successfully! Access Code: <code class='font-mono'>" . htmlspecialchars($accessCode) . "</code>";
} else {
  // Group application with companions
  $_SESSION['message'] = "Successfully added <strong>" . htmlspecialchars($fullName) . "</strong> with <strong>$companionCount</strong> companion(s). Lead Access Code: <code class='font-mono'>" . htmlspecialchars($accessCode) . "</code>";
  
  // Add companion access codes to message
  $companionCodes = [];
  foreach ($createdClients as $client) {
    if (isset($client['is_companion']) && $client['is_companion']) {
      $companionCodes[] = htmlspecialchars($client['name']) . ": <code class='font-mono'>" . htmlspecialchars($client['access_code']) . "</code>";
    }
  }
  if (!empty($companionCodes)) {
    $_SESSION['message'] .= "<br><span class='text-sm'>Companions: " . implode(', ', $companionCodes) . "</span>";
  }
}
$_SESSION['message_type'] = 'success';

// If a visa package was selected but no visa application was created, surface a visible warning
if ($visaPackageId && empty($visaApplicationId)) {
  $_SESSION['message'] .= "<br><span class='text-yellow-700'>Note: Visa application record was not created. Check server error logs for details (search for [createVisaClient]).</span>";
  $_SESSION['message_type'] = 'warning';
}

// Store client data for potential follow-up actions (only for individual mode)
if ($applicationMode === 'individual') {
  $_SESSION['visa_client_added'] = [
    'client_id' => $clientId,
    'client_name' => $fullName,
    'access_code' => $accessCode,
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
