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
    $packageId = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;

    if ($packageId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid package ID.']);
        exit;
    }

    // Check if package exists and is archived
    $checkStmt = $conn->prepare("SELECT id, visa_package_name, visa_cover_image FROM visa_packages WHERE id = ? AND is_active = 0");
    $checkStmt->bind_param('i', $packageId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Package not found or not archived.']);
        exit;
    }
    
    $package = $result->fetch_assoc();
    $checkStmt->close();

    // Check if any visa applications reference this package
    $appCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM client_visa_applications WHERE visa_package_id = ?");
    $appCheckStmt->bind_param('i', $packageId);
    $appCheckStmt->execute();
    $appResult = $appCheckStmt->get_result();
    $appCount = $appResult->fetch_assoc()['count'];
    $appCheckStmt->close();

    if ($appCount > 0) {
        echo json_encode(['success' => false, 'message' => "Cannot delete: $appCount visa application(s) are linked to this package."]);
        exit;
    }

    // Delete the package
    $deleteStmt = $conn->prepare("DELETE FROM visa_packages WHERE id = ?");
    $deleteStmt->bind_param('i', $packageId);
    
    if ($deleteStmt->execute()) {
        // Delete cover image if exists
        if (!empty($package['visa_cover_image']) && $package['visa_cover_image'] !== 'NULL') {
            $imagePath = __DIR__ . '/../images/visa_packages_banners/' . $package['visa_cover_image'];
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }

        // Log the action
        $actor = getActorContext();
        logClientOnboardingAudit(
            $conn,
            0,
            'visa_package_permanently_deleted',
            [
                'package_id' => $packageId,
                'visa_package_name' => $package['visa_package_name'],
                'source' => 'permanently_delete_visa_package.php'
            ],
            $actor
        );

        echo json_encode([
            'success' => true,
            'message' => 'Visa package permanently deleted!'
        ]);
    } else {
        throw new Exception('Failed to delete visa package: ' . $conn->error);
    }

    $deleteStmt->close();

} catch (Exception $e) {
    error_log('Error permanently deleting visa package: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
