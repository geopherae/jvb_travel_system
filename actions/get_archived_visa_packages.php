<?php
// Prevent direct access (allow GET/POST requests)
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__) && !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'])) {
    exit('Access denied.');
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/db.php';

use function Auth\guard;

guard('admin');

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

global $conn;

try {
    $archivedPackages = [];
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection not available');
    }
    
    $stmt = $conn->prepare("
        SELECT 
            id, 
            visa_cover_image, 
            visa_package_name, 
            country, 
            processing_days, 
            visa_package_description,
            updated_at
        FROM visa_packages
        WHERE is_active = 0
        ORDER BY updated_at DESC
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $coverFile = trim($row['visa_cover_image'] ?? '');
        $coverUrl = $coverFile !== ''
            ? '../images/visa_packages_banners/' . ltrim($coverFile, '/\\')
            : '../images/default_visa_cover.jpg';

        $archivedPackages[] = [
            'id' => (int)$row['id'],
            'name' => $row['visa_package_name'] ?? 'Unnamed Package',
            'country' => $row['country'] ?? 'Unknown',
            'processing_days' => (int)($row['processing_days'] ?? 0),
            'description' => $row['visa_package_description'] ?? '',
            'image' => $coverUrl,
            'archived_at' => $row['updated_at'] ?? null
        ];
    }
    
    $stmt->close();

    echo json_encode([
        'success' => true,
        'packages' => $archivedPackages
    ]);

} catch (Exception $e) {
    error_log('Error fetching archived visa packages: ' . $e->getMessage());
    $errorMsg = defined('ENV') && ENV === 'development' 
        ? $e->getMessage() 
        : 'An error occurred while fetching archived packages.';
    echo json_encode([
        'success' => false,
        'message' => $errorMsg,
        'packages' => [],
        'debug' => defined('ENV') && ENV === 'development' ? $e->getTraceAsString() : null
    ]);
}
?>
