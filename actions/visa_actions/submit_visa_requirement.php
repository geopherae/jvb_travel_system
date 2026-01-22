<?php
/**
 * Submit Visa Requirement Document Action
 * 
 * Handles file upload for a specific visa requirement.
 * Stores document and creates submission record in database.
 * 
 * POST Parameters (multipart/form-data):
 *   - application_id (int): Visa application ID
 *   - requirement_id (string): Requirement ID (e.g., req_1, req_emp_2)
 *   - requirement_name (string): Human-readable requirement name
 *   - companion_id (int, optional): If submission is for a companion
 *   - file (file): The document to upload (PDF or image)
 * 
 * Response: JSON with success status and submission ID
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) exit('Access denied.');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/visa_document_handler.php';

use function Auth\getActorContext;
use function VisaDocumentHandler\uploadDocument;

header('Content-Type: application/json');

// Client or Admin check
$actor = getActorContext();
$is_client = isset($actor['session_id']) && strpos($actor['session_id'], 'client_') === 0;
$is_admin = in_array($actor['role'], ['superadmin', 'admin']);

if (!$is_client && !$is_admin) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Client or Admin access required'
    ]);
    exit;
}

// Validate input
$application_id = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
$requirement_id = trim($_POST['requirement_id'] ?? '');
$requirement_name = trim($_POST['requirement_name'] ?? '');
$companion_id = filter_var($_POST['companion_id'] ?? null, FILTER_VALIDATE_INT);

if (!$application_id || empty($requirement_id) || empty($requirement_name)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: application_id, requirement_id, requirement_name'
    ]);
    exit;
}

// Validate file upload
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded'
    ]);
    exit;
}

try {
    // Verify application exists and belongs to client (if client is submitting)
    $appStmt = $conn->prepare("
        SELECT id, client_id FROM client_visa_applications WHERE id = ?
    ");
    $appStmt->bind_param("i", $application_id);
    $appStmt->execute();
    $appResult = $appStmt->get_result();
    
    if ($appResult->num_rows === 0) {
        throw new Exception('Visa application not found');
    }
    
    $application = $appResult->fetch_assoc();
    $appStmt->close();

    // If client is submitting, verify they own this application
    if ($is_client && $application['client_id'] !== $actor['id']) {
        throw new Exception('Client cannot access this application');
    }

    // Verify companion exists (if specified)
    if ($companion_id) {
        $compStmt = $conn->prepare("
            SELECT id FROM client_visa_companions WHERE id = ? AND visa_application_id = ?
        ");
        $compStmt->bind_param("ii", $companion_id, $application_id);
        $compStmt->execute();
        if ($compStmt->get_result()->num_rows === 0) {
            throw new Exception('Companion not found in this application');
        }
        $compStmt->close();
    }

    // Upload document
    $uploadResult = uploadDocument($_FILES['file'], $application_id, $requirement_id);
    if (!$uploadResult['success']) {
        throw new Exception($uploadResult['message']);
    }

    // Insert submission record
    $submissionStmt = $conn->prepare("
        INSERT INTO visa_document_submissions 
        (visa_application_id, companion_id, requirement_id, requirement_name, file_name, file_path, mime_type, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $null_companion = $companion_id ?: null;
    $submissionStmt->bind_param(
        "iisssss",
        $application_id,
        $null_companion,
        $requirement_id,
        $requirement_name,
        $uploadResult['file_name'],
        $uploadResult['file_path'],
        $uploadResult['mime_type']
    );
    $submissionStmt->execute();
    $submission_id = $conn->insert_id;
    $submissionStmt->close();

    // Update application status to 'awaiting_docs' if still in draft
    $statusStmt = $conn->prepare("
        UPDATE client_visa_applications 
        SET status = 'awaiting_docs'
        WHERE id = ? AND status = 'draft'
    ");
    $statusStmt->bind_param("i", $application_id);
    $statusStmt->execute();
    $statusStmt->close();

    // Log action
    require_once __DIR__ . '/../../includes/log_helper.php';
    LogHelper\logClientOnboardingAudit(
        $conn,
        $application['client_id'],
        'visa_document_submitted',
        [
            'application_id' => $application_id,
            'companion_id' => $companion_id,
            'requirement_id' => $requirement_id,
            'file_name' => $uploadResult['file_name'],
            'mime_type' => $uploadResult['mime_type']
        ],
        $actor
    );

    echo json_encode([
        'success' => true,
        'message' => 'Document submitted successfully',
        'submission_id' => $submission_id,
        'file_path' => $uploadResult['file_path']
    ]);

} catch (Exception $e) {
    error_log("[visa_actions/submit_visa_requirement] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => defined('ENV') && ENV === 'development' 
            ? $e->getMessage() 
            : 'Failed to submit document'
    ]);
}
