<?php
/**
 * Compress and convert an image file
 * 
 * @param string $sourcePath Path to the source image file
 * @param string $destinationPath Path where the compressed image will be saved
 * @param string $mimeType MIME type of the source image (e.g., 'image/jpeg', 'image/png')
 * @param int $quality Quality level 0-100 (higher = better quality, larger file)
 * @return bool True on success, false on failure
 */
function compressImage($sourcePath, $destinationPath, $mimeType, $quality = 75) {
    // Check if GD library is available
    if (!extension_loaded('gd')) {
        error_log('GD library not available for image compression');
        return false;
    }

    // Validate inputs
    if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
        error_log("Source file doesn't exist or isn't readable: $sourcePath");
        return false;
    }
    
    $destDir = dirname($destinationPath);
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) {
            error_log("Could not create destination directory: $destDir");
            return false;
        }
    }
    
    if (!is_writable($destDir)) {
        error_log("Destination directory isn't writable: $destDir");
        return false;
    }
    
    $validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mimeType, $validTypes)) {
        error_log("Unsupported MIME type: $mimeType");
        return false;
    }
    
    // Clamp quality to valid range
    $quality = max(0, min(100, (int)$quality));

    // Load image based on MIME type
    $image = null;
    try {
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = @imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($sourcePath);
                }
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($sourcePath);
                break;
        }
    } catch (Exception $e) {
        error_log("Failed to load image: " . $e->getMessage());
        return false;
    }
    
    if (!$image) {
        error_log("Failed to create image resource from: $sourcePath");
        return false;
    }

    // Determine output format from destination path extension
    $destExt = strtolower(pathinfo($destinationPath, PATHINFO_EXTENSION));
    
    // Compress and save based on destination file extension
    $success = false;
    try {
        switch ($destExt) {
            case 'jpg':
            case 'jpeg':
                $success = imagejpeg($image, $destinationPath, $quality);
                break;
            case 'png':
                // PNG compression: 0 (no compression) to 9 (max compression)
                // Invert quality: high quality (75-100) = low compression (0-2)
                // Map quality 100->0, 75->2, 50->5, 0->9
                $pngCompression = (int)round((100 - $quality) / 100 * 9);
                $pngCompression = max(0, min(9, $pngCompression));
                
                // Preserve transparency
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $success = imagepng($image, $destinationPath, $pngCompression);
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    $success = imagewebp($image, $destinationPath, $quality);
                } else {
                    error_log("WebP support not available in GD library");
                }
                break;
            case 'gif':
                $success = imagegif($image, $destinationPath);
                break;
            default:
                // Default to JPEG if extension is unrecognized
                $success = imagejpeg($image, $destinationPath, $quality);
                break;
        }
    } catch (Exception $e) {
        error_log("Failed to save compressed image: " . $e->getMessage());
        $success = false;
    }

    // Clean up memory
    imagedestroy($image);
    
    if (!$success) {
        error_log("Image compression failed for: $destinationPath");
    }
    
    return $success;
}