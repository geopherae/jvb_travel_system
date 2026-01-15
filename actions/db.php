<?php
// Prevent direct access via browser
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
  http_response_code(403);
  exit('Access denied.');
}

// Load environment variables from .env file
if (file_exists(__DIR__ . '/../.env')) {
  $envFile = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($envFile as $line) {
    if (strpos(trim($line), '#') === 0) continue; // Skip comments
    if (strpos($line, '=') === false) continue;
    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);
    if (!isset($_ENV[$key])) {
      $_ENV[$key] = $value;
    }
  }
}

// Define environment
if (!defined('ENV')) define('ENV', $_ENV['ENV'] ?? 'production');

// Database credentials
$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$db   = $_ENV['DB_NAME'] ?? 'jvb_travel_db';

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Handle connection errors quietly or verbosely based on environment
if ($conn->connect_error) {
  if (ENV === 'development') {
    die("Connection failed: " . $conn->connect_error);
  } else {
    http_response_code(500);
    exit('Database error. Please try again later.');
  }
}

// Optional charset enforcement
$conn->set_charset('utf8mb4');

// ✅ Do not expose $conn or sensitive config publicly
?>