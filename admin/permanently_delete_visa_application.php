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
            c.client_profile_photo,
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
    $clientId = (int)$application['client_id'];
    $checkStmt->close();

    $conn->begin_transaction();

    try {
        // 1. Delete from client_visa_companions (will be empty for individual applications)
        $deleteCompanionsStmt = $conn->prepare("DELETE FROM client_visa_companions WHERE client_id = ?");
        $deleteCompanionsStmt->bind_param('i', $clientId);
        $deleteCompanionsStmt->execute();
        $companionsDeleted = $deleteCompanionsStmt->affected_rows;
        $deleteCompanionsStmt->close();

        // 2. Delete from client_visa_requirements
        $deleteRequirementsStmt = $conn->prepare("DELETE FROM client_visa_requirements WHERE client_id = ?");
        $deleteRequirementsStmt->bind_param('i', $clientId);
        $deleteRequirementsStmt->execute();
        $requirementsDeleted = $deleteRequirementsStmt->affected_rows;
        $deleteRequirementsStmt->close();

        // 3. Delete from client_visa_applications (the application itself)
        $deleteApplicationStmt = $conn->prepare("DELETE FROM client_visa_applications WHERE id = ?");
        $deleteApplicationStmt->bind_param('i', $applicationId);
        $deleteApplicationStmt->execute();
        $deleteApplicationStmt->close();

        // 4. Delete from clients table (the lead guest)
        $deleteClientStmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
        $deleteClientStmt->bind_param('i', $clientId);
        $deleteClientStmt->execute();
        $deleteClientStmt->close();

        // 5. Delete client profile photo if exists
        if (!empty($application['client_profile_photo']) && $application['client_profile_photo'] !== 'NULL') {
            $photoPath = __DIR__ . '/../uploads/client_profiles/' . $application['client_profile_photo'];
            if (file_exists($photoPath)) {
                @unlink($photoPath);
            }
        }

        $conn->commit();

        // Log the action
        $actor = getActorContext();
        logClientOnboardingAudit(
            $conn,
            $clientId,
            'visa_application_permanently_deleted',
            [
                'application_id' => $applicationId,
                'client_name' => $application['client_name'],
                'visa_package' => $application['visa_package_name'],
                'country' => $application['country'],
                'companions_deleted' => $companionsDeleted,
                'requirements_deleted' => $requirementsDeleted,
                'source' => 'permanently_delete_visa_application.php'
            ],
            $actor
        );

        echo json_encode([
            'success' => true,
            'message' => 'Application permanently deleted!'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log('Error permanently deleting visa application: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>