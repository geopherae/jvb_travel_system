<?php
date_default_timezone_set('Asia/Manila');
// Prevent direct access (allow POST requests from forms)
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Access denied.');
}

// Set JSON header early for all responses
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/image_compression_helper.php';
require_once __DIR__ . '/../includes/log_helper.php';

use function Auth\guard;
use function LogHelper\logClientOnboardingAudit;

$response = ['success' => false, 'message' => ''];

// Check authentication with error handling
try {
    guard('admin');
} catch (Exception $e) {
    $response['message'] = 'Authentication failed. Please log in again.';
    echo json_encode($response);
    exit;
}

try {
    // Get POST data
    $visaApplicationId = trim($_POST['visa_application_id'] ?? '');
    $clientId = trim($_POST['client_id'] ?? '');
    $companionId = trim($_POST['companion_id'] ?? '');
    $requirementId = trim($_POST['requirement_id'] ?? '');
    $requirementType = trim($_POST['requirement_type'] ?? 'admin_added');
    $editableReqName = trim($_POST['editable_requirement_name'] ?? '');
    $editableReqDescription = trim($_POST['editable_requirement_description'] ?? '');
    
    // Validate inputs
    if (!$clientId) {
        throw new Exception('Missing required client information.');
    }

    // Fetch current visa requirements for this applicant
    // Companion_id will be NULL for lead applicant, populated for companions
    $companionIdForQuery = !empty($companionId) ? (int)$companionId : null;
    
    // Build dynamic query for NULL-safe comparison
    if ($companionIdForQuery === null) {
        $stmt = $conn->prepare('
            SELECT requirements_json
            FROM client_visa_requirements
            WHERE client_id = ? AND companion_id IS NULL
            LIMIT 1
        ');
        if (!$stmt) throw new Exception('Database error: ' . $conn->error);
        $stmt->bind_param('i', $clientId);
    } else {
        $stmt = $conn->prepare('
            SELECT requirements_json
            FROM client_visa_requirements
            WHERE client_id = ? AND companion_id = ?
            LIMIT 1
        ');
        if (!$stmt) throw new Exception('Database error: ' . $conn->error);
        $stmt->bind_param('ii', $clientId, $companionIdForQuery);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Create new record if it doesn't exist
        $requirementsJson = json_encode([], JSON_UNESCAPED_UNICODE);
        $visaType = '';
        
        if ($companionIdForQuery === null) {
            // For lead applicant (companion_id = NULL)
            $insertStmt = $conn->prepare('
                INSERT INTO client_visa_requirements (client_id, companion_id, visa_type, requirements_json)
                VALUES (?, NULL, ?, ?)
            ');
            if (!$insertStmt) throw new Exception('Database error: ' . $conn->error);
            $insertStmt->bind_param('iss', $clientId, $visaType, $requirementsJson);
        } else {
            // For companions (companion_id = integer)
            $insertStmt = $conn->prepare('
                INSERT INTO client_visa_requirements (client_id, companion_id, visa_type, requirements_json)
                VALUES (?, ?, ?, ?)
            ');
            if (!$insertStmt) throw new Exception('Database error: ' . $conn->error);
            $insertStmt->bind_param('iiss', $clientId, $companionIdForQuery, $visaType, $requirementsJson);
        }
        
        if (!$insertStmt->execute()) {
            throw new Exception('Failed to create visa requirements record: ' . $insertStmt->error);
        }
        $insertStmt->close();
        $visaReq = ['requirements_json' => $requirementsJson];
    } else {
        $visaReq = $result->fetch_assoc();
    }
    $stmt->close();

    // Decode current requirements
    $requirements = json_decode($visaReq['requirements_json'] ?? '[]', true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $requirements = [];
    }

    // Initialize file handling (optional)
    $fileUploaded = false;

    // Handle file upload if provided
    if (!empty($_FILES['document_file']['tmp_name'])) {
        $file = $_FILES['document_file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }

        $mimeType = mime_content_type($file['tmp_name']);
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
        
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('File type not allowed. Please upload PDF, JPG, or PNG.');
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File size exceeds 10MB limit.');
        }

        // Create upload directory
        $uploadDir = __DIR__ . '/../uploads/client_visa_documents/' . $clientId . '/' . $visaApplicationId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Compress image if applicable
        $fileName = $mimeType === 'application/pdf' 
            ? 'requirement_' . time() . '_' . rand(100, 999) . '.pdf'
            : 'requirement_' . time() . '_' . rand(100, 999) . '.jpg';
        
        $filePath = $uploadDir . '/' . $fileName;
        
        if ($mimeType !== 'application/pdf') {
            compressImage($file['tmp_name'], $filePath, $mimeType, 85);
        } else {
            move_uploaded_file($file['tmp_name'], $filePath);
        }

        $fileUploaded = true;
    }

    // Create new requirement with standard schema format
    $newRequirement = [
        'id' => $requirementId ?: 'req_custom_' . time(),
        'name' => $editableReqName ?: 'Custom Requirement',
        'description' => $editableReqDescription ?: '',
        'required' => false,
        'category' => 'other',
        'condition' => null
    ];

    // Add to requirements array
    $requirements[] = $newRequirement;

    // Update visa requirements record
    $updatedRequirementsJson = json_encode($requirements, JSON_UNESCAPED_UNICODE);
    
    if ($companionIdForQuery === null) {
        $stmt = $conn->prepare('
            UPDATE client_visa_requirements
            SET requirements_json = ?, updated_at = NOW()
            WHERE client_id = ? AND companion_id IS NULL
        ');
        if (!$stmt) throw new Exception('Database error: ' . $conn->error);
        $stmt->bind_param('si', $updatedRequirementsJson, $clientId);
    } else {
        $stmt = $conn->prepare('
            UPDATE client_visa_requirements
            SET requirements_json = ?, updated_at = NOW()
            WHERE client_id = ? AND companion_id = ?
        ');
        if (!$stmt) throw new Exception('Database error: ' . $conn->error);
        $stmt->bind_param('sii', $updatedRequirementsJson, $clientId, $companionIdForQuery);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update requirements: ' . $stmt->error);
    }
    $stmt->close();

    // Log the action
    $actor = Auth\getActorContext();
    logClientOnboardingAudit($conn, $clientId, 'visa_requirement_added', [
        'visa_application_id' => $visaApplicationId,
        'applicant_id' => $companionIdForQuery ?: $clientId,
        'is_companion' => !empty($companionIdForQuery),
        'requirement_name' => $newRequirement['name'],
        'requirement_id' => $newRequirement['id'],
        'file_uploaded' => $fileUploaded
    ], $actor);

    $response['success'] = true;
    $response['message'] = 'Requirement added successfully' . ($fileUploaded ? ' with document.' : '.');

} catch (Exception $e) {
    error_log('Error in add_visa_requirement.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    $response['message'] = ENV === 'development' ? $e->getMessage() : 'An error occurred while adding the requirement.';
}

echo json_encode($response);
?>
