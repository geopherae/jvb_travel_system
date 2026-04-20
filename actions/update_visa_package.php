<?php
// Prevent direct access (allow POST requests)
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    exit('Access denied.');
}

// Auth check
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/image_compression_helper.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../includes/applicant_status_helper.php';

use function Auth\guard, Auth\getActorContext, LogHelper\logClientOnboardingAudit;

guard('admin');

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

try {
    // Validate required fields
    $packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;
    $visaPackageName = isset($_POST['visa_package_name']) ? trim($_POST['visa_package_name']) : '';
    $country = isset($_POST['country']) ? trim($_POST['country']) : '';
    $processingDays = isset($_POST['processing_days']) ? (int) $_POST['processing_days'] : 0;
    $visaPackageDescription = isset($_POST['visa_package_description']) ? trim($_POST['visa_package_description']) : '';
    $inclusionsJson = isset($_POST['inclusions_json']) ? trim($_POST['inclusions_json']) : '[]';
    $requirementsJson = isset($_POST['requirements_json']) ? trim($_POST['requirements_json']) : '[]';
    $visaTypesJson = isset($_POST['visa_types_json']) ? trim($_POST['visa_types_json']) : '[]';
    $existingImage = isset($_POST['existing_image']) ? trim($_POST['existing_image']) : '';

    $errors = [];

    // Applicant status options
    $applicantStatusOptionsJson = $_POST['applicant_status_options_json'] ?? '[]';
    $applicantStatusArray = json_decode($applicantStatusOptionsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'Invalid applicant status options JSON: ' . json_last_error_msg();
    }
    $applicantStatusFormatted = convertApplicantStatusToJson($applicantStatusArray);

    // Validate package ID
    if ($packageId <= 0) {
        $errors[] = 'Invalid package ID.';
    }

    // Validate required fields
    if (empty($visaPackageName)) {
        $errors[] = 'Visa package name is required.';
    }
    if (empty($country)) {
        $errors[] = 'Country is required.';
    }

    // Validate JSON fields
    $inclusions = json_decode($inclusionsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'Invalid inclusions JSON: ' . json_last_error_msg();
    }

    $requirements = json_decode($requirementsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'Invalid requirements JSON: ' . json_last_error_msg();
    }

    $visaTypes = json_decode($visaTypesJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'Invalid visa types JSON: ' . json_last_error_msg();
    }

    // Handle image upload
    $visaCoverImageFilename = !empty($existingImage) ? $existingImage : null; // Keep existing image when no new upload is made

    if (isset($_FILES['visa_cover_image']) && $_FILES['visa_cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['visa_cover_image'];
        $maxSize = 3 * 1024 * 1024; // 3MB
        $allowedMimes = ['image/jpeg', 'image/png'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error: ' . $file['error'];
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds 3MB limit.';
        } else {
            // Check MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimes)) {
                $errors[] = 'Only JPG and PNG images are allowed.';
            } else {
                // Create upload directory if needed
                $uploadDir = __DIR__ . '/../images/visa_packages_banners';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Generate unique filename
                $visaCoverImageFilename = 'visa_' . time() . '_' . rand(100, 999) . '.jpg';
                $uploadPath = $uploadDir . '/' . $visaCoverImageFilename;

                // Compress and save image
                if (!compressImage($file['tmp_name'], $uploadPath, $mimeType, 85)) {
                    // Fallback to direct move if compression is unavailable or fails
                    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $errors[] = 'Failed to process image. Please try again.';
                    }
                }
            }
        }
    }

    // If there are errors, return them
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }

    // Prepare update statement

    $stmt = $conn->prepare("
        UPDATE visa_packages
        SET visa_package_name = ?,
            visa_package_description = ?,
            visa_cover_image = ?,
            country = ?,
            processing_days = ?,
            inclusions_json = ?,
            requirements_json = ?,
            visa_types_json = ?,
            applicant_status_options = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    $stmt->bind_param(
        'ssssissssi',
        $visaPackageName,
        $visaPackageDescription,
        $visaCoverImageFilename,
        $country,
        $processingDays,
        $inclusionsJson,
        $requirementsJson,
        $visaTypesJson,
        $applicantStatusFormatted,
        $packageId
    );

    if (!$stmt->execute()) {
        throw new Exception('Database update failed: ' . $stmt->error);
    }

    $stmt->close();

    // Log audit trail
    $actor = getActorContext();
    logClientOnboardingAudit(
        $conn,
        null, // Not a client operation
        'visa_package_updated',
        [
            'package_id' => $packageId,
            'visa_package_name' => $visaPackageName,
            'country' => $country,
            'processing_days' => $processingDays,
            'visa_cover_image' => $visaCoverImageFilename
        ],
        $actor
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Visa package updated successfully.'
    ]);

} catch (Exception $e) {
    error_log('Error updating visa package: ' . $e->getMessage());
    http_response_code(500);
    $envMode = defined('ENV') ? ENV : 'production';
    $message = ($envMode === 'development') ? $e->getMessage() : 'An error occurred while updating the package.';
    echo json_encode(['success' => false, 'message' => $message]);
}
?>
