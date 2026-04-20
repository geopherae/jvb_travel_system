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

use function Auth\guard, Auth\getActorContext, LogHelper\logClientOnboardingAudit;

guard('admin');

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

/**
 * Convert applicant status labels to standardized JSON format
 * Uses a predefined mapping to ensure consistent option values
 */
function convertApplicantStatusToJson(array $statusLabels) {
    // Predefined mapping of labels to option values
    $statusMapping = [
        'Employed' => 'employed',
        'Self-Employed' => 'self_employed',
        'Business Owner' => 'business_owner',
        'Corporation' => 'corporation',
        'Student' => 'student',
        'Senior Citizen/Retired' => 'senior_citizen_retired',
        'Married' => 'married',
        'Widowed' => 'widowed',
        'Visiting Family/Friend' => 'visiting_family_friend',
        'None of the above' => 'none'
    ];
    
    $result = [];
    
    foreach ($statusLabels as $label) {
        $label = trim($label);
        if (empty($label)) continue;
        
        // Use predefined option value if available, otherwise generate from label
        if (isset($statusMapping[$label])) {
            $option = $statusMapping[$label];
        } else {
            // Fallback: generate option value from label
            $option = strtolower($label);
            $option = str_replace(['/', ' ', '(', ')', '-'], '_', $option);
            $option = preg_replace('/[^a-z0-9_]/', '', $option);
            $option = preg_replace('/_+/', '_', $option);
            $option = trim($option, '_');
        }
        
        $result[] = [
            'option' => $option,
            'label' => $label
        ];
    }
    
    return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

try {
    // Validate required fields
    $visaPackageName = isset($_POST['visa_package_name']) ? trim($_POST['visa_package_name']) : '';
    $country = isset($_POST['country']) ? trim($_POST['country']) : '';
    $processingDays = isset($_POST['processing_days']) ? (int) $_POST['processing_days'] : 0;
    $visaPackageDescription = isset($_POST['visa_package_description']) ? trim($_POST['visa_package_description']) : '';
    $inclusionsJson = isset($_POST['inclusions_json']) ? trim($_POST['inclusions_json']) : '[]';
    $requirementsJson = isset($_POST['requirements_json']) ? trim($_POST['requirements_json']) : '[]';
    $visaTypesJson = isset($_POST['visa_types_json']) ? trim($_POST['visa_types_json']) : '[]';
    $applicantStatusOptionsJson = isset($_POST['applicant_status_options_json']) ? trim($_POST['applicant_status_options_json']) : '[]';

    $errors = [];

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

// After validating requirements JSON, normalize the structure
$requirements = json_decode($requirementsJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $errors[] = 'Invalid requirements JSON: ' . json_last_error_msg();
} else {
    // Normalize requirement conditions to match Canada format
    foreach ($requirements as &$req) {
        // Ensure condition structure exists
        if (!isset($req['condition'])) {
            $req['condition'] = [
                'type' => 'applicant_status',
                'operator' => 'equals',
                'value' => ''
            ];
        }
        // Ensure condition.type is never empty - default to applicant_status
        if (empty($req['condition']['type'])) {
            $req['condition']['type'] = 'applicant_status';
        }
        // Ensure operator exists
        if (!isset($req['condition']['operator'])) {
            $req['condition']['operator'] = 'equals';
        }
    }
    // Re-encode normalized requirements
    $requirementsJson = json_encode($requirements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

    $visaTypes = json_decode($visaTypesJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'Invalid visa types JSON: ' . json_last_error_msg();
    }

    $applicantStatusOptionsArray = json_decode($applicantStatusOptionsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'Invalid applicant status options JSON: ' . json_last_error_msg();
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }

    // Convert applicant status options to proper format with option and label
    $applicantStatusOptionsFormatted = convertApplicantStatusToJson($applicantStatusOptionsArray);

    // Handle image upload
    $coverImageFilename = '';
    if (isset($_FILES['visa_cover_image']) && $_FILES['visa_cover_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../images/visa_packages_banners/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file = $_FILES['visa_cover_image'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png'];
        if (!in_array($mimeType, $allowedMimes)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG and PNG images are allowed.']);
            exit;
        }

        if ($file['size'] > 3 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Image must be smaller than 3MB.']);
            exit;
        }

        $coverImageFilename = 'visa_package_' . time() . '_' . rand(100, 999) . '.jpg';
        $uploadPath = $uploadDir . $coverImageFilename;

        // Use compression helper
        if (!compressImage($file['tmp_name'], $uploadPath, $mimeType, 85)) {
            $phpError = error_get_last();
            error_log('Image compression failed. Tmp: ' . $file['tmp_name'] . ', Dest: ' . $uploadPath . ', PHP Error: ' . print_r($phpError, true));
            echo json_encode(['success' => false, 'message' => 'Failed to process image. Check server logs for details.']);
            exit;
        }
    }

    // Insert into database
    $stmt = $conn->prepare("
        INSERT INTO visa_packages (
            visa_package_name, 
            country, 
            processing_days, 
            visa_package_description, 
            visa_cover_image, 
            inclusions_json, 
            requirements_json, 
            visa_types_json, 
            applicant_status_options,
            is_active, 
            created_at, 
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
    ");

    $stmt->bind_param(
        'ssissssss',
        $visaPackageName,
        $country,
        $processingDays,
        $visaPackageDescription,
        $coverImageFilename,
        $inclusionsJson,
        $requirementsJson,
        $visaTypesJson,
        $applicantStatusOptionsFormatted
    );

    if ($stmt->execute()) {
        $newPackageId = $conn->insert_id;

        // Log the action
        $actor = getActorContext();
        logClientOnboardingAudit(
            $conn,
            0,
            'visa_package_created',
            [
                'package_id' => $newPackageId,
                'visa_package_name' => $visaPackageName,
                'country' => $country,
                'processing_days' => $processingDays,
                'source' => 'process_add_visa_package.php'
            ],
            $actor
        );

        echo json_encode([
            'success' => true,
            'message' => 'Visa package created successfully!',
            'package_id' => $newPackageId
        ]);
    } else {
        throw new Exception('Database error: ' . $conn->error);
    }

} catch (Exception $e) {
    error_log('Error creating visa package: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>