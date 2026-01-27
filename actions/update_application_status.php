<?php
/**
 * Update Visa Application Status Action
 * 
 * Admin action to update visa application status.
 * Moves application through workflow: draft → awaiting_docs → under_review → approved_for_submission → booking
 * 
 * POST Parameters:
 *   - application_id (int): Visa application ID
 *   - new_status (string): New status (draft, awaiting_docs, under_review, approved_for_submission, booking, rejected)
 *   - notes (string, optional): Status change notes
 * 
 * Response: JSON with success status
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) exit('Access denied.');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../includes/auth.php';

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
$application_id = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
$new_status = trim($_POST['new_status'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$application_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required field: application_id'
    ]);
    exit;
}

$valid_statuses = ['draft', 'awaiting_docs', 'under_review', 'approved_for_submission', 'booking', 'rejected'];
if (!in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status: ' . $new_status
    ]);
    exit;
}

try {
    // Verify application exists and get current status
    $appStmt = $conn->prepare("
        SELECT id, client_id, status FROM client_visa_applications WHERE id = ?
    ");
    $appStmt->bind_param("i", $application_id);
    $appStmt->execute();
    $appResult = $appStmt->get_result();
    
    if ($appResult->num_rows === 0) {
        throw new Exception('Visa application not found');
    }
    
    $application = $appResult->fetch_assoc();
    $appStmt->close();

    $old_status = $application['status'];

    // Update application status
    $updateStmt = $conn->prepare("
        UPDATE client_visa_applications 
        SET status = ?
        WHERE id = ?
    ");
    $updateStmt->bind_param("si", $new_status, $application_id);
    $updateStmt->execute();
    $updateStmt->close();

    // If moving to 'booking' status, update clients.status to 'Confirmed'
    if ($new_status === 'booking') {
        $clientStmt = $conn->prepare("
            UPDATE clients 
            SET status = 'Confirmed'
            WHERE id = ?
        ");
        $clientStmt->bind_param("i", $application['client_id']);
        $clientStmt->execute();
        $clientStmt->close();
    }

    // Log action
    require_once __DIR__ . '/../../includes/log_helper.php';
    LogHelper\logClientOnboardingAudit(
        $conn,
        $application['client_id'],
        'visa_application_status_updated',
        [
            'application_id' => $application_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'notes' => $notes
        ],
        $actor,
        'Medium',
        'visa_processing'
    );

    echo json_encode([
        'success' => true,
        'message' => "Application status updated from '$old_status' to '$new_status'"
    ]);

} catch (Exception $e) {
    error_log("[visa_actions/update_application_status] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => defined('ENV') && ENV === 'development' 
            ? $e->getMessage() 
            : 'Failed to update application status'
    ]);
}
