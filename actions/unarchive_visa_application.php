<?php
// Prevent direct access (allow POST requests)
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    exit('Access denied.');
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/log_helper.php';

use function Auth\guard, Auth\getActorContext;
use function LogHelper\logClientOnboardingAudit;

guard('admin');

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

try {
    $applicationId = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;

    if ($applicationId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
        exit;
    }

    // Check if application exists and is archived
    $checkStmt = $conn->prepare("
        SELECT 
            va.id, 
            va.client_id,
            c.full_name AS client_name,
            vp.visa_package_name,
            vp.country
        FROM client_visa_applications va
        LEFT JOIN clients c ON va.client_id = c.id
        LEFT JOIN visa_packages vp ON va.visa_package_id = vp.id
        WHERE va.id = ? AND va.is_archived = 1
    ");
    $checkStmt->bind_param('i', $applicationId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Application not found or not archived.']);
        exit;
    }
    
    $application = $result->fetch_assoc();
    $checkStmt->close();

    // Update is_archived to NULL
    $updateStmt = $conn->prepare("UPDATE client_visa_applications SET is_archived = NULL WHERE id = ?");
    $updateStmt->bind_param('i', $applicationId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to unarchive application: ' . $updateStmt->error);
    }
    
    if ($updateStmt->affected_rows === 0) {
        throw new Exception('Application not found or already unarchived');
    }
    
    $updateStmt->close();

    // Log the action
    $actor = getActorContext();
    logClientOnboardingAudit(
        $conn,
        $application['client_id'],
        'visa_application_unarchived',
        [
            'application_id' => $applicationId,
            'client_name' => $application['client_name'],
            'visa_package' => $application['visa_package_name'],
            'country' => $application['country'],
            'source' => 'unarchive_visa_application.php'
        ],
        $actor
    );

    echo json_encode([
        'success' => true,
        'message' => 'Application unarchived successfully!'
    ]);
    
} catch (Exception $e) {
    error_log('Error unarchiving visa application: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>