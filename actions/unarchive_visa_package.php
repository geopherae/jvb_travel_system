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
    $checkStmt = $conn->prepare("SELECT id, visa_package_name FROM visa_packages WHERE id = ? AND is_active = 0");
    $checkStmt->bind_param('i', $packageId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Package not found or not archived.']);
        exit;
    }
    
    $package = $result->fetch_assoc();
    $checkStmt->close();

    // Unarchive the package
    $stmt = $conn->prepare("UPDATE visa_packages SET is_active = 1 WHERE id = ?");
    $stmt->bind_param('i', $packageId);

    if ($stmt->execute()) {
        // Log the action
        $actor = getActorContext();
        logClientOnboardingAudit(
            $conn,
            0,
            'visa_package_unarchived',
            [
                'package_id' => $packageId,
                'visa_package_name' => $package['visa_package_name'],
                'source' => 'unarchive_visa_package.php'
            ],
            $actor
        );

        echo json_encode([
            'success' => true,
            'message' => 'Visa package unarchived successfully!'
        ]);
    } else {
        throw new Exception('Failed to unarchive visa package: ' . $conn->error);
    }

    $stmt->close();

} catch (Exception $e) {
    error_log('Error unarchiving visa package: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
