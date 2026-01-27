<?php
/**
 * Convert Visa to Booking Action
 * 
 * Admin action to convert an approved visa application to a booking.
 * Transitions client from visa workflow to booking workflow.
 * 
 * POST Parameters:
 *   - application_id (int): Visa application ID (must be in 'approved_for_submission' status)
 *   - tour_package_id (int, optional): Tour package to assign immediately
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
$tour_package_id = filter_var($_POST['tour_package_id'] ?? null, FILTER_VALIDATE_INT);

if (!$application_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required field: application_id'
    ]);
    exit;
}

try {
    // Verify application exists and get client ID
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

    // Verify application is in 'approved_for_submission' status
    if ($application['status'] !== 'approved_for_submission') {
        throw new Exception('Application must be in "approved_for_submission" status to convert to booking');
    }

    $client_id = $application['client_id'];

    // Verify tour package exists (if specified)
    if ($tour_package_id) {
        $pkgStmt = $conn->prepare("SELECT id FROM tour_packages WHERE id = ?");
        $pkgStmt->bind_param("i", $tour_package_id);
        $pkgStmt->execute();
        if ($pkgStmt->get_result()->num_rows === 0) {
            throw new Exception('Tour package not found');
        }
        $pkgStmt->close();
    }

    // Update visa application status to 'booking'
    $statusUpdate = 'booking';
    $appUpdateStmt = $conn->prepare("
        UPDATE client_visa_applications 
        SET status = ?
        WHERE id = ?
    ");
    $appUpdateStmt->bind_param("si", $statusUpdate, $application_id);
    $appUpdateStmt->execute();
    $appUpdateStmt->close();

    // Update client's processing_type and optionally assign tour package
    if ($tour_package_id) {
        $clientStmt = $conn->prepare("
            UPDATE clients 
            SET processing_type = 'both',
                assigned_package_id = ?,
                status = 'Confirmed'
            WHERE id = ?
        ");
        $clientStmt->bind_param("ii", $tour_package_id, $client_id);
        $clientStmt->execute();
        $clientStmt->close();
    } else {
        $clientStmt = $conn->prepare("
            UPDATE clients 
            SET processing_type = 'both',
                status = 'Confirmed'
            WHERE id = ?
        ");
        $clientStmt->bind_param("i", $client_id);
        $clientStmt->execute();
        $clientStmt->close();
    }

    // Log action
    require_once __DIR__ . '/../../includes/log_helper.php';
    LogHelper\logClientOnboardingAudit(
        $conn,
        $client_id,
        'visa_converted_to_booking',
        [
            'visa_application_id' => $application_id,
            'tour_package_id' => $tour_package_id
        ],
        $actor,
        'High',
        'visa_processing'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Visa application converted to booking workflow',
        'client_id' => $client_id
    ]);

} catch (Exception $e) {
    error_log("[visa_actions/convert_visa_to_booking] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => defined('ENV') && ENV === 'development' 
            ? $e->getMessage() 
            : 'Failed to convert to booking'
    ]);
}
