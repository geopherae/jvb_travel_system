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
            tp.id, 
            tp.tour_cover_image, 
            tp.package_name, 
            tp.package_description, 
            tp.price, 
            tp.day_duration, 
            tp.night_duration,
            tp.origin,
            tp.destination
        FROM tour_packages tp
        WHERE tp.is_deleted = 1
        ORDER BY tp.id DESC
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $coverImage = (!empty($row['tour_cover_image']) && $row['tour_cover_image'] !== 'NULL')
            ? '../images/tour_packages_banners/' . ltrim($row['tour_cover_image'], '/')
            : '../images/default_trip_cover.jpg';

        $archivedPackages[] = [
            'id' => (int)$row['id'],
            'name' => $row['package_name'] ?? 'Unnamed Package',
            'description' => $row['package_description'] ?? '',
            'price' => isset($row['price']) ? (float)$row['price'] : null,
            'days' => (int)($row['day_duration'] ?? 0),
            'nights' => (int)($row['night_duration'] ?? 0),
            'origin' => $row['origin'] ?? '',
            'destination' => $row['destination'] ?? '',
            'image' => $coverImage
        ];
    }
    
    $stmt->close();

    echo json_encode([
        'success' => true,
        'packages' => $archivedPackages
    ]);

} catch (Exception $e) {
    error_log('Error fetching archived tour packages: ' . $e->getMessage());
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
