<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/db.php';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/image_compression_helper.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../includes/visa_status_helper.php';


use function Auth\guard;
use function LogHelper\logClientOnboardingAudit;


// Allow both admin and client access
if (isset($_SESSION['admin']['id'])) {
  guard('admin');
  $isAdmin = true;
} else {
  guard('client');
  $isAdmin = false;
}

header('Content-Type: application/json');

try {
  // Validate input
  $visaAppId = (int)($_POST['visa_application_id'] ?? 0);
  $requirementId = trim($_POST['requirement_id'] ?? '');
  
  if (!$visaAppId || !$requirementId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing visa application or requirement ID.']);
    exit;
  }

  // Validate file upload
  if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File upload failed.']);
    exit;
  }

  // Ensure visa_document_submissions.id is AUTO_INCREMENT (fixes id=0 inserts)
  $autoIncStmt = $conn->prepare("
    SELECT EXTRA
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'visa_document_submissions'
      AND COLUMN_NAME = 'id'
    LIMIT 1
  ");
  if ($autoIncStmt) {
    $autoIncStmt->execute();
    $autoIncResult = $autoIncStmt->get_result()->fetch_assoc();
    $autoIncStmt->close();

    $extra = strtolower($autoIncResult['EXTRA'] ?? '');
    if (strpos($extra, 'auto_increment') === false) {
      $conn->query("ALTER TABLE visa_document_submissions MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT");
    }
  }

  // Repair existing rows with id=0 after ensuring AUTO_INCREMENT
  $zeroIdResult = $conn->query("SELECT COUNT(*) AS total FROM visa_document_submissions WHERE id = 0");
  if ($zeroIdResult) {
    $zeroIdCount = (int)($zeroIdResult->fetch_assoc()['total'] ?? 0);
    if ($zeroIdCount > 0) {
      $conn->query("UPDATE visa_document_submissions SET id = NULL WHERE id = 0");
    }
  }

  $file = $_FILES['document_file'];
  $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
  $maxFileSize = 10 * 1024 * 1024; // 10MB

  // Validate file
  if ($file['size'] > $maxFileSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit.']);
    exit;
  }

  // Check MIME type
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);

  if (!in_array($mimeType, $allowedMimes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, JPEG, and PNG are allowed.']);
    exit;
  }


  // Get client ID and determine companion ID
  if ($isAdmin) {
    // Admin must POST client_id and companion_id (if any)
    $clientId = (int)($_POST['client_id'] ?? 0);
    // Handle companion_id: empty string or 'null' should be treated as NULL
    $companionIdRaw = $_POST['companion_id'] ?? '';
    $companionId = (!empty($companionIdRaw) && $companionIdRaw !== 'null' && $companionIdRaw !== '0') ? (int)$companionIdRaw : null;
    $currentUserId = $companionId ?? $clientId;
    if (!$clientId) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Missing client ID for admin upload.']);
      exit;
    }
  } else {
    $clientId = (int)$_SESSION['client_id'];
    $companionId = null;
    $currentUserId = $clientId;
    // If logged in as companion, they can only upload their own documents
    if (!empty($_SESSION['is_companion']) && !empty($_SESSION['companion_id'])) {
      $companionId = (int)$_SESSION['companion_id'];
      $currentUserId = $companionId;
    }
  }


  // Verify client has access to this visa application
  $appStmt = $conn->prepare("
    SELECT id, client_id, application_mode
    FROM client_visa_applications
    WHERE id = ?
  ");
  $appStmt->bind_param("i", $visaAppId);
  $appStmt->execute();
  $appResult = $appStmt->get_result();
  $appData = $appResult->fetch_assoc();
  $appStmt->close();

  if (!$appData) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Visa application not found.']);
    exit;
  }

  // For clients, ensure they own the application
  if (!$isAdmin && $appData['client_id'] != $clientId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: This application does not belong to you.']);
    exit;
  }

  // If client is a companion, verify they can upload for this requirement
  if ($companionId) {
    $companionStmt = $conn->prepare("
      SELECT id FROM client_visa_companions
      WHERE id = ? AND visa_application_id = ?
    ");
    $companionStmt->bind_param("ii", $companionId, $visaAppId);
    $companionStmt->execute();
    $companionResult = $companionStmt->get_result();

    if ($companionResult->num_rows === 0) {
      $companionStmt->close();
      http_response_code(403);
      echo json_encode(['success' => false, 'message' => 'Unauthorized: You cannot upload documents for this application.']);
      exit;
    }
    $companionStmt->close();
  }

  // Fetch requirement info
  $pkgStmt = $conn->prepare("
    SELECT visa_package_id FROM client_visa_applications WHERE id = ?
  ");
  $pkgStmt->bind_param("i", $visaAppId);
  $pkgStmt->execute();
  $pkgResult = $pkgStmt->get_result();
  $pkgData = $pkgResult->fetch_assoc();
  $pkgStmt->close();

  $visaPackageId = $pkgData['visa_package_id'];

  // Get requirement name from package requirements_json
  $reqStmt = $conn->prepare("
    SELECT requirements_json FROM visa_packages WHERE id = ?
  ");
  $reqStmt->bind_param("i", $visaPackageId);
  $reqStmt->execute();
  $reqResult = $reqStmt->get_result();
  $reqData = $reqResult->fetch_assoc();
  $reqStmt->close();

  $requirementName = null;
  $requirements = json_decode($reqData['requirements_json'] ?? '[]', true) ?? [];
  foreach ($requirements as $req) {
    if (($req['id'] ?? '') === $requirementId) {
      $requirementName = $req['name'] ?? null;
      break;
    }
  }

  // If not found in package, check client's custom requirements
  if ($requirementName === null) {
    $clientReqStmt = $conn->prepare("
      SELECT requirements_json FROM client_visa_requirements 
      WHERE client_id = ? AND " . ($companionId ? "companion_id = ?" : "companion_id IS NULL")
    );
    
    if ($companionId) {
      $clientReqStmt->bind_param("ii", $clientId, $companionId);
    } else {
      $clientReqStmt->bind_param("i", $clientId);
    }
    
    $clientReqStmt->execute();
    $clientReqResult = $clientReqStmt->get_result();
    
    if ($clientReqResult->num_rows > 0) {
      $clientReqData = $clientReqResult->fetch_assoc();
      $clientRequirements = json_decode($clientReqData['requirements_json'] ?? '[]', true) ?? [];
      
      foreach ($clientRequirements as $req) {
        if (($req['id'] ?? '') === $requirementId) {
          $requirementName = $req['name'] ?? null;
          break;
        }
      }
    }
    $clientReqStmt->close();
  }

  // Final fallback
  if ($requirementName === null) {
    $requirementName = 'Unknown Requirement';
  }

  // Handle admin editable requirement fields (from top button upload modal)
  $editableReqName = trim($_POST['editable_requirement_name'] ?? '');
  $editableReqDescription = trim($_POST['editable_requirement_description'] ?? '');
  
  if ($isAdmin && !empty($editableReqName)) {
    // Admin has edited the requirement name/description
    // Update the client_visa_requirements table
    $updateReqFields = $_POST['editable_requirement_name'] ?? '';
    $updateReqDesc = $_POST['editable_requirement_description'] ?? '';
    
    // Update client_visa_requirements with the new name/description
    $cvreqStmt = $conn->prepare("
      SELECT requirements_json FROM client_visa_requirements 
      WHERE client_id = ? AND " . ($companionId ? "companion_id = ?" : "companion_id IS NULL")
    );
    
    if ($companionId) {
      $cvreqStmt->bind_param("ii", $clientId, $companionId);
    } else {
      $cvreqStmt->bind_param("i", $clientId);
    }
    
    $cvreqStmt->execute();
    $cvreqResult = $cvreqStmt->get_result();
    
    if ($cvreqResult->num_rows > 0) {
      $cvreqRow = $cvreqResult->fetch_assoc();
      $clientReqs = json_decode($cvreqRow['requirements_json'] ?? '[]', true) ?? [];
      
      // Find and update the specific requirement
      foreach ($clientReqs as &$req) {
        if ($req['id'] === $requirementId) {
          if (!empty($updateReqFields)) {
            $req['name'] = $updateReqFields;
            $requirementName = $updateReqFields; // Use updated name for storage
          }
          if (!empty($updateReqDesc)) {
            $req['description'] = $updateReqDesc;
          }
          break;
        }
      }
      
      // Update the database
      $updatedJson = json_encode($clientReqs, JSON_UNESCAPED_UNICODE);
      $updateCvreqStmt = $conn->prepare("
        UPDATE client_visa_requirements 
        SET requirements_json = ? 
        WHERE client_id = ? AND " . ($companionId ? "companion_id = ?" : "companion_id IS NULL")
      );
      
      if ($companionId) {
        $updateCvreqStmt->bind_param("sii", $updatedJson, $clientId, $companionId);
      } else {
        $updateCvreqStmt->bind_param("si", $updatedJson, $clientId);
      }
      
      $updateCvreqStmt->execute();
      $updateCvreqStmt->close();
    }
    $cvreqStmt->close();
  }

  // Create upload directory
  $uploadDir = __DIR__ . "/../uploads/visa_docs/client_$clientId/application_$visaAppId";
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }


  // Generate unique filename (sanitize requirement_id to alphanumeric only)
  $sanitizedReqId = preg_replace('/[^a-zA-Z0-9]/', '', $requirementId);
  $fileName = 'req_' . $sanitizedReqId . '_' . time() . '_' . rand(100, 999);

  // Handle compression for images
  if ($mimeType === 'image/jpeg' || $mimeType === 'image/png') {
    $fileName .= '.jpg';
    $targetPath = $uploadDir . '/' . $fileName;
    compressImage($file['tmp_name'], $targetPath, $mimeType, 80);
    $mimeType = 'image/jpeg'; // Store as JPEG after compression
  } else {
    $fileName .= '.pdf';
    $targetPath = $uploadDir . '/' . $fileName;
    move_uploaded_file($file['tmp_name'], $targetPath);
  }


  // Build file path for DB storage (relative to project root)
  $relativePath = 'uploads/visa_docs/client_' . $clientId . '/application_' . $visaAppId . '/' . $fileName;
  $fileNameJson = json_encode($fileName, JSON_UNESCAPED_UNICODE);

  // Check if already submitted for this requirement (overwrite if exists)
  $existingStmt = $conn->prepare("
    SELECT id FROM visa_document_submissions
    WHERE visa_application_id = ? AND requirement_id = ? AND companion_id = ?
  ");
  $existingStmt->bind_param("isi", $visaAppId, $requirementId, $companionId);
  $existingStmt->execute();
  $existingResult = $existingStmt->get_result();
  $existingDoc = $existingResult->fetch_assoc();
  $existingStmt->close();

  $now = date('Y-m-d H:i:s');
  $fileSize = filesize($targetPath);
  
  // Set status based on uploader: Admin uploads are automatically approved
  $documentStatus = $isAdmin ? 'Approved' : 'Pending';

  if ($existingDoc) {
    // Update existing submission
    $updateStmt = $conn->prepare("
      UPDATE visa_document_submissions
      SET file_name = ?, file_path = ?, mime_type = ?, file_size = ?, status = ?, uploaded_at = ?, updated_at = ?
      WHERE id = ?
    ");
    $updateStmt->bind_param("sssissi", $fileNameJson, $relativePath, $mimeType, $fileSize, $documentStatus, $now, $now, $existingDoc['id']);
    $updateStmt->execute();
    $updateStmt->close();
  } else {
    // Insert new submission
    $insertStmt = $conn->prepare("
      INSERT INTO visa_document_submissions
      (visa_application_id, companion_id, requirement_id, requirement_name, file_name, file_path, mime_type, file_size, status, uploaded_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->bind_param("iisssssiss", $visaAppId, $companionId, $requirementId, $requirementName, $fileNameJson, $relativePath, $mimeType, $fileSize, $documentStatus, $now);
    $insertStmt->execute();
    $insertStmt->close();
  }

  // Recalculate application status after document submission
  \VisaStatusHelper\recalculateVisaApplicationStatus($conn, $visaAppId);

  // Log audit action
  $actor = Auth\getActorContext();
  logClientOnboardingAudit(
    $conn,
    $clientId,
    'visa_document_submitted',
    [
      'visa_application_id' => $visaAppId,
      'requirement_id' => $requirementId,
      'requirement_name' => $requirementName,
      'companion_id' => $companionId
    ],
    $actor
  );

  $uploaderName = 'Client';
  if ($isAdmin) {
    $firstName = $_SESSION['admin']['first_name'] ?? '';
    $lastName  = $_SESSION['admin']['last_name'] ?? '';
    $uploaderName = trim($firstName . ' ' . $lastName) ?: ($_SESSION['admin']['username'] ?? 'Admin');
  } else {
    $uploaderName = $_SESSION['client']['full_name'] ?? 'Client';
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // Send Notifications
  // ═══════════════════════════════════════════════════════════════════════════
  require_once __DIR__ . '/notify.php';
  $notifyManager = new NotificationManager($conn);

  // Get client name for notifications
  $clientStmt = $conn->prepare("SELECT full_name FROM clients WHERE id = ?");
  $clientStmt->bind_param("i", $clientId);
  $clientStmt->execute();
  $clientResult = $clientStmt->get_result();
  $clientName = $clientResult->fetch_assoc()['full_name'] ?? 'Client';
  $clientStmt->close();

  // Get visa package name
  $visaPkgStmt = $conn->prepare("
    SELECT vp.visa_package_name 
    FROM client_visa_applications cva
    JOIN visa_packages vp ON cva.visa_package_id = vp.id
    WHERE cva.id = ?
  ");
  $visaPkgStmt->bind_param("i", $visaAppId);
  $visaPkgStmt->execute();
  $visaPkgResult = $visaPkgStmt->get_result();
  $visaPackageName = $visaPkgResult->fetch_assoc()['visa_package_name'] ?? 'Visa Application';
  $visaPkgStmt->close();

  if ($isAdmin) {
    // Admin uploaded → Notify client + document is already approved
    $notifyManager->send([
      'recipient_type' => 'client',
      'recipient_id' => $clientId,
      'event' => 'admin_uploaded_visa_document',
      'context' => [
        'client_id' => $clientId,
        'requirement_name' => $requirementName,
        'visa_application_id' => $visaAppId,
        'visa_package_name' => $visaPackageName,
        'uploaded_by' => $uploaderName
      ]
    ]);
  } else {
    // Client uploaded → Notify all admins
    $adminStmt = $conn->query("SELECT id FROM admin_accounts WHERE is_active = 1");
    while ($admin = $adminStmt->fetch_assoc()) {
      $notifyManager->send([
        'recipient_type' => 'admin',
        'recipient_id' => $admin['id'],
        'event' => 'client_uploaded_visa_document',
        'context' => [
          'client_id' => $clientId,
          'client_name' => $clientName,
          'requirement_name' => $requirementName,
          'visa_application_id' => $visaAppId,
          'visa_package_name' => $visaPackageName
        ]
      ]);
    }
  }

  // Set session status for toast notification
  $_SESSION['modal_status'] = 'upload_success';
  
  http_response_code(200);
  echo json_encode([
    'success' => true,
    'message' => 'Document uploaded successfully by: ' . $uploaderName
  ]);

} catch (Exception $e) {
  error_log("Visa document submission error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => ENV === 'development' ? $e->getMessage() : 'An error occurred while uploading the document.'
  ]);
}

$conn->close();
