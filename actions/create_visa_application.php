<?php
/**
 * Create Visa Application Action
 * 
 * Creates a new visa application for a client.
 * Associates client with visa package and sets initial status.
 * 
 * POST Parameters:
 *   - client_id (int): Client ID
 *   - visa_package_id (int): Visa package ID
 *   - visa_type_selected (string): Selected visa type from package
 *   - applicant_status (string): Applicant status for conditional requirements
 * 
 * Response: JSON with success status and application ID
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
$client_id = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT);
$visa_package_id = filter_var($_POST['visa_package_id'] ?? null, FILTER_VALIDATE_INT);
$visa_type_selected = trim($_POST['visa_type_selected'] ?? '');
$applicant_status = trim($_POST['applicant_status'] ?? '');

if (!$client_id || !$visa_package_id || empty($visa_type_selected) || empty($applicant_status)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: client_id, visa_package_id, visa_type_selected, applicant_status'
    ]);
    exit;
}

try {
    // Verify client exists
    $clientStmt = $conn->prepare("SELECT id FROM clients WHERE id = ?");
    $clientStmt->bind_param("i", $client_id);
    $clientStmt->execute();
    if ($clientStmt->get_result()->num_rows === 0) {
        throw new Exception('Client not found');
    }
    $clientStmt->close();

    // Verify visa package exists
    $packageStmt = $conn->prepare("SELECT id FROM visa_packages WHERE id = ?");
    $packageStmt->bind_param("i", $visa_package_id);
    $packageStmt->execute();
    if ($packageStmt->get_result()->num_rows === 0) {
        throw new Exception('Visa package not found');
    }
    $packageStmt->close();

    // Create visa application
    $applicationStmt = $conn->prepare("
        INSERT INTO client_visa_applications (client_id, visa_package_id, visa_type_selected, applicant_status, status)
        VALUES (?, ?, ?, ?, 'draft')
    ");
    $applicationStmt->bind_param("iiss", $client_id, $visa_package_id, $visa_type_selected, $applicant_status);
    $applicationStmt->execute();
    $application_id = $conn->insert_id;
    $applicationStmt->close();

    // Update client's processing_type and visa_application_id
    $updateStmt = $conn->prepare("
        UPDATE clients 
        SET processing_type = CASE 
            WHEN processing_type = 'booking' THEN 'both'
            ELSE processing_type
        END,
        visa_application_id = ?
        WHERE id = ?
    ");
    $updateStmt->bind_param("ii", $application_id, $client_id);
    $updateStmt->execute();
    $updateStmt->close();

    // Log action
    require_once __DIR__ . '/../../includes/log_helper.php';
    LogHelper\logClientOnboardingAudit(
        $conn,
        $client_id,
        'visa_application_created',
        [
            'visa_package_id' => $visa_package_id,
            'visa_type_selected' => $visa_type_selected,
            'applicant_status' => $applicant_status
        ],
        $actor
    );

    echo json_encode([
        'success' => true,
        'message' => 'Visa application created successfully',
        'application_id' => $application_id
    ]);

} catch (Exception $e) {
    error_log("[visa_actions/create_visa_application] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => defined('ENV') && ENV === 'development' 
            ? $e->getMessage() 
            : 'Failed to create visa application'
    ]);
}
