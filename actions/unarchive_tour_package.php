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
    $checkStmt = $conn->prepare("SELECT id, package_name FROM tour_packages WHERE id = ? AND is_deleted = 1");
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
    $stmt = $conn->prepare("UPDATE tour_packages SET is_deleted = 0 WHERE id = ?");
    $stmt->bind_param('i', $packageId);

    if ($stmt->execute()) {
        // Log the action
        $actor = getActorContext();
        logClientOnboardingAudit(
            $conn,
            0,
            'tour_package_unarchived',
            [
                'package_id' => $packageId,
                'package_name' => $package['package_name'],
                'source' => 'unarchive_tour_package.php'
            ],
            $actor
        );

        echo json_encode([
            'success' => true,
            'message' => 'Package unarchived successfully!'
        ]);
    } else {
        throw new Exception('Failed to unarchive package: ' . $conn->error);
    }

    $stmt->close();

} catch (Exception $e) {
    error_log('Error unarchiving tour package: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
