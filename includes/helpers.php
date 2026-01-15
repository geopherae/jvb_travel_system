<?php
declare(strict_types=1);

/**
 * 🖼️ Returns the public URL for a client's profile photo.
 * Falls back to a default image if no photo is set or the file is missing.
 *
 * @param array  $client  Array containing client data (expected key: 'client_profile_photo')
 * @param string $default Fallback image path (default: generic profile image)
 * @return string Public URL to the photo or fallback
 */
function getClientAvatar(array $client, string $default = '../images/default_client_profile.png'): string
{
    $photoFile = trim($client['client_profile_photo'] ?? '');
    if ($photoFile === '') {
        return $default;
    }

    $uploadDir    = __DIR__ . '/../Uploads/client_profiles/';
    $absolutePath = realpath($uploadDir . $photoFile);
    $publicUrl    = '../Uploads/client_profiles/' . rawurlencode($photoFile);

    // Simple static cache to avoid repeated file_exists() calls
    static $existsCache = [];
    $cacheKey = $absolutePath;

    if (!array_key_exists($cacheKey, $existsCache)) {
        $existsCache[$cacheKey] = $absolutePath !== false && file_exists($absolutePath);
    }

    return $existsCache[$cacheKey] ? $publicUrl : $default;
}

/**
 * 🖼️ Returns the public URL for an admin's profile photo.
 * Falls back to a default image if no photo is set or the file is missing.
 *
 * @param array  $admin   Array containing admin data (expected key: 'admin_photo')
 * @param string $default Fallback image path
 * @return string Public URL to the photo or fallback
 */
function getAdminAvatar(array $admin, string $default = '../images/default_client_profile.png'): string
{
    $photoFile = trim($admin['admin_photo'] ?? '');
    if ($photoFile === '') {
        return $default;
    }

    $uploadDir    = __DIR__ . '/../Uploads/admin_photo/';
    $absolutePath = realpath($uploadDir . $photoFile);
    $publicUrl    = '../Uploads/admin_photo/' . rawurlencode($photoFile);

    static $existsCache = [];
    $cacheKey = $absolutePath;

    if (!array_key_exists($cacheKey, $existsCache)) {
        $existsCache[$cacheKey] = $absolutePath !== false && file_exists($absolutePath);
    }

    return $existsCache[$cacheKey] ? $publicUrl : $default;
}

/**
 * 🔐 Verifies a submitted CSRF token against the session token.
 *
 * @param string $token The token submitted via form/headers
 * @return bool True if valid, false otherwise
 */
function verify_csrf_token(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 🛡️ Ensures a CSRF token exists in the current session.
 * Generates one if missing.
 */
function ensure_csrf_token(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * ✅ Polyfill for json_validate() (available in PHP 8.3+)
 * Checks if a string is valid JSON.
 *
 * @param mixed $json The value to check (usually a string)
 * @return bool True if valid JSON, false otherwise
 */
if (!function_exists('json_validate')) {
    function json_validate($json): bool
    {
        if (!is_string($json)) {
            return false;
        }

        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
}

/* 
 * Additional helper functions can be added below this line.
 * Keep the file clean and well-documented!
 */