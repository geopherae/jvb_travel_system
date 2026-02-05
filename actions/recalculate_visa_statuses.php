<?php
/**
 * Recalculate Visa Application Statuses
 * 
 * Admin action to batch recalculate all visa application statuses based on document submissions.
 * Called after database migration or to fix outdated statuses.
 * 
 * Response: JSON with count of updated applications and details
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visa_status_helper.php';

use function Auth\getActorContext;
use function VisaStatusHelper\recalculateVisaApplicationStatus;

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

try {
    // Get all visa applications
    $appsStmt = $conn->prepare("
        SELECT id FROM client_visa_applications
        ORDER BY id ASC
    ");
    $appsStmt->execute();
    $appsResult = $appsStmt->get_result();
    $applications = $appsResult->fetch_all(MYSQLI_ASSOC);
    $appsStmt->close();
    
    $totalApps = count($applications);
    $updatedCount = 0;
    $results = [];
    
    // Recalculate status for each application
    foreach ($applications as $app) {
        $appId = (int)$app['id'];
        $result = recalculateVisaApplicationStatus($conn, $appId);
        
        if ($result['success']) {
            $updatedCount++;
            $results[] = [
                'application_id' => $appId,
                'status' => $result['status'],
                'details' => $result['details']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Recalculated statuses for all visa applications",
        'total_applications' => $totalApps,
        'updated_count' => $updatedCount,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    error_log("[recalculate_visa_statuses] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => defined('ENV') && ENV === 'development' 
            ? $e->getMessage() 
            : 'Failed to recalculate visa statuses'
    ]);
}
