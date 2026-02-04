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
    $checkStmt = $conn->prepare("SELECT id, package_name, tour_cover_image FROM tour_packages WHERE id = ? AND is_deleted = 1");
    $checkStmt->bind_param('i', $packageId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Package not found or not archived.']);
        exit;
    }
    
    $package = $result->fetch_assoc();
    $checkStmt->close();

    // Check if any clients are assigned to this package
    $clientCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM clients WHERE assigned_package_id = ?");
    $clientCheckStmt->bind_param('i', $packageId);
    $clientCheckStmt->execute();
    $clientResult = $clientCheckStmt->get_result();
    $clientCount = $clientResult->fetch_assoc()['count'];
    $clientCheckStmt->close();

    if ($clientCount > 0) {
        echo json_encode(['success' => false, 'message' => "Cannot delete: $clientCount client(s) are assigned to this package."]);
        exit;
    }

    $conn->begin_transaction();

    try {
        // Delete related itinerary
        $deleteItineraryStmt = $conn->prepare("DELETE FROM tour_package_itinerary WHERE package_id = ?");
        $deleteItineraryStmt->bind_param('i', $packageId);
        $deleteItineraryStmt->execute();
        $deleteItineraryStmt->close();

        // Delete the package
        $deleteStmt = $conn->prepare("DELETE FROM tour_packages WHERE id = ?");
        $deleteStmt->bind_param('i', $packageId);
        $deleteStmt->execute();
        $deleteStmt->close();

        // Delete cover image if exists
        if (!empty($package['tour_cover_image']) && $package['tour_cover_image'] !== 'NULL') {
            $imagePath = __DIR__ . '/../images/tour_packages_banners/' . $package['tour_cover_image'];
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }

        $conn->commit();

        // Log the action
        $actor = getActorContext();
        logClientOnboardingAudit(
            $conn,
            0,
            'tour_package_permanently_deleted',
            [
                'package_id' => $packageId,
                'package_name' => $package['package_name'],
                'source' => 'permanently_delete_tour_package.php'
            ],
            $actor
        );

        echo json_encode([
            'success' => true,
            'message' => 'Package permanently deleted!'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log('Error permanently deleting tour package: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
