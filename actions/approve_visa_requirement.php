<?php
/**
 * Approve Visa Requirement Document Action
 * 
 * Admin action to approve a submitted visa document.
 * Updates submission status and records approval timestamp.
 * 
 * POST Parameters:
 *   - submission_id (int): Document submission ID
 *   - admin_comments (string, optional): Admin comments on approval
 * 
 * Response: JSON with success status
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) exit('Access denied.');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/visa_status_helper.php';

use function Auth\getActorContext;

header('Content-Type: application/json');

// Admin check
$actor = getActorContext();
if ($actor['role'] !== 'superadmin' && $actor['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Admin access required'
    ]);
    exit;
}

// Validate input
$submission_id = filter_var($_POST['submission_id'] ?? null, FILTER_VALIDATE_INT);
$admin_comments = trim($_POST['admin_comments'] ?? '');

if (!$submission_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required field: submission_id'
    ]);
    exit;
}

try {
    // Verify submission exists and get details for notification
    $submissionStmt = $conn->prepare("
        SELECT vds.id, vds.visa_application_id, vds.requirement_name,
               cva.client_id, c.full_name as client_name,
               vp.visa_package_name
        FROM visa_document_submissions vds
        JOIN client_visa_applications cva ON vds.visa_application_id = cva.id
        JOIN clients c ON cva.client_id = c.id
        JOIN visa_packages vp ON cva.visa_package_id = vp.id
        WHERE vds.id = ?
    ");
    $submissionStmt->bind_param("i", $submission_id);
    $submissionStmt->execute();
    $submissionResult = $submissionStmt->get_result();
    
    if ($submissionResult->num_rows === 0) {
        throw new Exception('Document submission not found');
    }
    
    $submission = $submissionResult->fetch_assoc();
    $submissionStmt->close();

    // Update submission status
    $now = date('Y-m-d H:i:s');
    $admin_id = $actor['id'];
    
    $updateStmt = $conn->prepare("
        UPDATE visa_document_submissions 
        SET status = 'approved', 
            approved_at = ?, 
            approved_by_admin_id = ?,
            admin_comments = ?
        WHERE id = ?
    ");
    $updateStmt->bind_param("sisi", $now, $admin_id, $admin_comments, $submission_id);
    $updateStmt->execute();
    $updateStmt->close();

    // Send notification to client
    require_once __DIR__ . '/notify.php';
    $notifyManager = new NotificationManager($conn);
    $notifyManager->send([
        'recipient_type' => 'client',
        'recipient_id' => $submission['client_id'],
        'event' => 'document_approved',
        'context' => [
            'client_id' => $submission['client_id'],
            'document_name' => $submission['requirement_name'],
            'visa_package_name' => $submission['visa_package_name'],
            'approved_by' => $actor['full_name'] ?? 'Admin'
        ]
    ]);

    // Recalculate application status
    \VisaStatusHelper\recalculateVisaApplicationStatus($conn, (int)$submission['visa_application_id']);

    // Log action
    require_once __DIR__ . '/../../includes/log_helper.php';
    LogHelper\logClientOnboardingAudit(
        $conn,
        null, // Will be fetched from application
        'visa_document_approved',
        [
            'submission_id' => $submission_id,
            'admin_comments' => $admin_comments
        ],
        $actor,
        'High',
        'visa_processing'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Document approved successfully'
    ]);

} catch (Exception $e) {
    error_log("[visa_actions/approve_visa_requirement] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => defined('ENV') && ENV === 'development' 
            ? $e->getMessage() 
            : 'Failed to approve document'
    ]);
}
