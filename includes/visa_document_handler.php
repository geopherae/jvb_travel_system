<?php
/**
 * Visa Document Handler
 * 
 * Handles uploading, validating, and managing visa-related documents.
 * Supports PDFs (as-is) and Images (stored as-is, no compression).
 * 
 * Usage:
 *   $handler = new VisaDocumentHandler();
 *   $result = $handler->uploadDocument($file, $application_id, $requirement_id);
 */

namespace VisaDocumentHandler;

define('VISA_UPLOAD_DIR', __DIR__ . '/../uploads/visa_documents');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_MIME_TYPES', [
    'application/pdf',
    'image/jpeg',
    'image/png'
]);

/**
 * Initialize upload directory if it doesn't exist
 */
function ensureUploadDirExists($application_id) {
    $dir = VISA_UPLOAD_DIR . '/' . $application_id;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Validate uploaded file
 * 
 * @param array $file $_FILES array element
 * @param string $application_id
 * @return array ['success' => bool, 'message' => string, 'mime_type' => string]
 */
function validateFile($file, $application_id = null) {
    $result = [
        'success' => false,
        'message' => '',
        'mime_type' => ''
    ];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum size',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
            UPLOAD_ERR_PARTIAL => 'File upload incomplete',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write file',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
        ];
        $result['message'] = $errorMessages[$file['error']] ?? 'Unknown upload error';
        return $result;
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        $result['message'] = 'File size exceeds 10MB limit';
        return $result;
    }

    // Check file size is not zero
    if ($file['size'] === 0) {
        $result['message'] = 'File is empty';
        return $result;
    }

    // Validate MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
        $result['message'] = 'File type not allowed. Allowed: PDF, JPG, PNG';
        return $result;
    }

    // Additional validation for PDFs
    if ($mime_type === 'application/pdf') {
        // Verify PDF header
        $handle = fopen($file['tmp_name'], 'rb');
        $header = fread($handle, 4);
        fclose($handle);
        
        if ($header !== '%PDF') {
            $result['message'] = 'Invalid PDF file';
            return $result;
        }
    }

    // Additional validation for images
    if (strpos($mime_type, 'image/') === 0) {
        // Verify image is valid
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $result['message'] = 'Invalid image file';
            return $result;
        }
    }

    $result['success'] = true;
    $result['mime_type'] = $mime_type;
    return $result;
}

/**
 * Upload visa document
 * 
 * @param array $file $_FILES array element
 * @param int $application_id
 * @param string $requirement_id
 * @return array ['success' => bool, 'message' => string, 'file_path' => string, 'file_name' => string]
 */
function uploadDocument($file, $application_id, $requirement_id) {
    $result = [
        'success' => false,
        'message' => '',
        'file_path' => '',
        'file_name' => ''
    ];

    // Validate input
    if (empty($application_id) || empty($requirement_id)) {
        $result['message'] = 'Missing application or requirement ID';
        return $result;
    }

    // Validate file
    $validation = validateFile($file, $application_id);
    if (!$validation['success']) {
        $result['message'] = $validation['message'];
        return $result;
    }

    // Ensure upload directory exists
    $upload_dir = ensureUploadDirExists($application_id);

    // Generate unique filename
    // Format: {app_id}_{requirement_id}_{timestamp}_{random}.{ext}
    $ext = getFileExtension($validation['mime_type']);
    $unique_id = time() . '_' . rand(100, 999);
    $file_name = $application_id . '_' . $requirement_id . '_' . $unique_id . '.' . $ext;
    $file_path = $upload_dir . '/' . $file_name;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        $result['message'] = 'Failed to save file to server';
        return $result;
    }

    // Set proper permissions
    chmod($file_path, 0644);

    $result['success'] = true;
    $result['file_path'] = 'uploads/visa_documents/' . $application_id . '/' . $file_name;
    $result['file_name'] = $file_name;
    $result['mime_type'] = $validation['mime_type'];

    error_log("[VisaDocumentHandler] File uploaded: application_id=$application_id, requirement_id=$requirement_id, file=$file_name");

    return $result;
}

/**
 * Delete visa document
 * 
 * @param string $file_path Relative file path (e.g., uploads/visa_documents/123/...)
 * @param int $submission_id Database submission ID (for logging)
 * @return array ['success' => bool, 'message' => string]
 */
function deleteDocument($file_path, $submission_id = null) {
    $result = [
        'success' => false,
        'message' => ''
    ];

    // Convert relative path to absolute
    $absolute_path = __DIR__ . '/../' . $file_path;

    if (!file_exists($absolute_path)) {
        $result['message'] = 'File not found';
        return $result;
    }

    if (!is_file($absolute_path)) {
        $result['message'] = 'Path is not a file';
        return $result;
    }

    // Security check: ensure file is in visa documents directory
    $real_path = realpath($absolute_path);
    $real_visa_dir = realpath(VISA_UPLOAD_DIR);
    
    if (strpos($real_path, $real_visa_dir) !== 0) {
        $result['message'] = 'Invalid file path';
        error_log("[VisaDocumentHandler] Security: Attempted to delete file outside visa directory: $real_path");
        return $result;
    }

    if (!unlink($absolute_path)) {
        $result['message'] = 'Failed to delete file';
        return $result;
    }

    $result['success'] = true;
    error_log("[VisaDocumentHandler] File deleted: file_path=$file_path, submission_id=$submission_id");

    return $result;
}

/**
 * Get file extension from MIME type
 * 
 * @param string $mime_type
 * @return string
 */
function getFileExtension($mime_type) {
    $mimeMap = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];
    
    return $mimeMap[$mime_type] ?? 'bin';
}

/**
 * Get file MIME type from file path
 * 
 * @param string $file_path Relative file path
 * @return string MIME type
 */
function getFileMimeType($file_path) {
    $absolute_path = __DIR__ . '/../' . $file_path;
    
    if (!file_exists($absolute_path)) {
        return 'application/octet-stream';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $absolute_path);
    finfo_close($finfo);

    return $mime_type ?: 'application/octet-stream';
}

/**
 * Check if file is viewable in browser (image or PDF)
 * 
 * @param string $mime_type
 * @return bool
 */
function isViewable($mime_type) {
    return in_array($mime_type, ['application/pdf', 'image/jpeg', 'image/png']);
}

/**
 * Get human-readable file size
 * 
 * @param int $bytes
 * @return string
 */
function humanFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
}
