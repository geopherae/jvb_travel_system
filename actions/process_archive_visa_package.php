<?php
// Prevent direct access (allow POST requests)
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    exit('Access denied.');
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/log_helper.php';

use function Auth\guard, Auth\getActorContext, LogHelper\logClientOnboardingAudit;

guard('admin');

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

try {
    $packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;
    if ($packageId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid package ID.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE visa_packages SET is_active = 0, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $packageId);

    if ($stmt->execute()) {
        $actor = getActorContext();
        logClientOnboardingAudit(
            $conn,
            0,
            'visa_package_archived',
            [
                'package_id' => $packageId,
                'source' => 'process_archive_visa_package.php'
            ],
            $actor
        );

        echo json_encode(['success' => true, 'message' => 'Visa package archived successfully.']);
    } else {
        throw new Exception('Database error: ' . $conn->error);
    }
} catch (Exception $e) {
    error_log('Error archiving visa package: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
