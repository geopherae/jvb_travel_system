<?php
/**
 * Staging Debug Dashboard
 * Visit: https://staging.jvandbtravel.com/debug_staging.php
 * Shows system status, error logs, and connection tests
 */

// Only allow from localhost or if admin authenticated
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
$is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_ips);

if (!$is_local) {
    http_response_code(403);
    die('Debug dashboard only available locally');
}

date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Staging Debug Dashboard</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        h1 { color: #569cd6; }
        h2 { color: #4ec9b0; margin-top: 30px; }
        .section { background: #252526; padding: 15px; margin: 10px 0; border-left: 3px solid #007acc; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #9cdcfe; }
        pre { background: #1e1e1e; overflow-x: auto; padding: 10px; border: 1px solid #3e3e42; }
    </style>
</head>
<body>

<h1>🔧 Staging Environment Debug Dashboard</h1>
<p>Last updated: <span class="info"><?= date('Y-m-d H:i:s') ?></span></p>

<!-- PHP Version -->
<div class="section">
    <h2>PHP Version</h2>
    <p><span class="info"><?= phpversion() ?></span></p>
    <?php if (version_compare(phpversion(), '8.2', '>=')): ?>
        <p><span class="success">✓ PHP 8.2+ requirement met</span></p>
    <?php else: ?>
        <p><span class="error">✗ PHP 8.2+ required (you have <?= phpversion() ?>)</span></p>
    <?php endif; ?>
</div>

<!-- Required Extensions -->
<div class="section">
    <h2>Required PHP Extensions</h2>
    <?php
    $required = ['mysqli', 'json', 'gd', 'curl'];
    foreach ($required as $ext) {
        $loaded = extension_loaded($ext);
        echo '<p>' . ($loaded ? '<span class="success">✓</span>' : '<span class="error">✗</span>') . " $ext</p>";
    }
    ?>
</div>

<!-- Directory Permissions -->
<div class="section">
    <h2>Directory Permissions</h2>
    <?php
    $dirs = [
        'logs' => __DIR__ . '/logs',
        'uploads' => __DIR__ . '/uploads',
        'uploads/client_profiles' => __DIR__ . '/uploads/client_profiles',
        'uploads/admin_photo' => __DIR__ . '/uploads/admin_photo',
    ];
    foreach ($dirs as $name => $path) {
        $exists = is_dir($path);
        $writable = is_writable($path);
        $status = $exists && $writable ? '<span class="success">✓ OK</span>' : 
                  ($exists ? '<span class="warning">⚠ Not writable</span>' : '<span class="error">✗ Not found</span>');
        echo "<p>$name: $status</p>";
    }
    ?>
</div>

<!-- Database Connection -->
<div class="section">
    <h2>Database Connection</h2>
    <?php
    $result = 'Testing...';
    $error = null;
    try {
        $conn = @new mysqli(
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_USER'] ?? 'root',
            $_ENV['DB_PASS'] ?? '',
            $_ENV['DB_NAME'] ?? 'jvb_travel_db'
        );
        
        if ($conn->connect_error) {
            $result = '<span class="error">✗ Connection failed</span>';
            $error = $conn->connect_error;
        } else {
            $result = '<span class="success">✓ Connected</span>';
        }
    } catch (Exception $e) {
        $result = '<span class="error">✗ Error</span>';
        $error = $e->getMessage();
    }
    echo "<p>Status: $result</p>";
    if ($error) {
        echo "<p class='error'>Error: $error</p>";
    }
    ?>
</div>

<!-- Error Logs -->
<div class="section">
    <h2>Recent Error Logs</h2>
    <?php
    $logFile = __DIR__ . '/logs/admin_visa_packages.log';
    if (file_exists($logFile)) {
        $lines = array_reverse(array_slice(explode("\n", file_get_contents($logFile)), -20));
        echo '<pre>';
        foreach ($lines as $line) {
            if (trim($line)) {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo '</pre>';
    } else {
        echo '<p><span class="warning">⚠ No log file yet (will be created on first error)</span></p>';
    }
    ?>
</div>

<!-- Admin Session Check Test -->
<div class="section">
    <h2>admin_session_check.php Test</h2>
    <?php
    $file = __DIR__ . '/admin/admin_session_check.php';
    if (file_exists($file)) {
        echo '<p><span class="success">✓ File exists</span></p>';
        $contents = file_get_contents($file);
        if (strpos($contents, 'guard(') !== false) {
            echo '<p><span class="success">✓ Contains guard() call</span></p>';
        } else {
            echo '<p><span class="warning">⚠ No guard() call found</span></p>';
        }
    } else {
        echo '<p><span class="error">✗ File not found</span></p>';
    }
    ?>
</div>

<!-- Database Tables Check -->
<div class="section">
    <h2>Database Tables</h2>
    <?php
    if (isset($conn) && $conn && !$conn->connect_error) {
        $result = $conn->query("SHOW TABLES");
        if ($result && $result->num_rows > 0) {
            $tables = [];
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }
            echo '<p><span class="success">✓ Tables found: ' . count($tables) . '</span></p>';
            echo '<pre>' . implode("\n", $tables) . '</pre>';
            
            // Check for visa_packages table
            if (in_array('visa_packages', $tables)) {
                echo '<p><span class="success">✓ visa_packages table exists</span></p>';
            } else {
                echo '<p><span class="error">✗ visa_packages table missing</span></p>';
            }
        } else {
            echo '<p><span class="error">✗ Could not retrieve tables</span></p>';
        }
    } else {
        echo '<p><span class="warning">⚠ Database connection failed, cannot check tables</span></p>';
    }
    ?>
</div>

</body>
</html>
