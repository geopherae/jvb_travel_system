<?php
/**
 * TEMPORARY ADMIN SETUP PAGE
 * ⚠️ DELETE THIS FILE AFTER THE OWNER HAS CREATED THEIR ACCOUNT ⚠️
 * 
 * This page allows the travel agency owner to create their admin account
 * during initial system handover.
 */

// Prevent access after account is created
require_once __DIR__ . '/actions/db.php';

// Check if any admin exists
$checkQuery = "SELECT COUNT(*) as count FROM admin_accounts WHERE role = 'admin'";
$result = $conn->query($checkQuery);
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    die('
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Complete</title>
        <link rel="stylesheet" href="output.css">
    </head>
    <body class="bg-gray-100 flex items-center justify-center min-h-screen">
        <div class="bg-white p-8 rounded-lg shadow-lg max-w-md text-center">
            <div class="text-red-600 text-6xl mb-4">🔒</div>
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Setup Already Complete</h1>
            <p class="text-gray-600 mb-6">A admin account already exists. This setup page is no longer accessible.</p>
            <p class="text-sm text-gray-500 mb-4">⚠️ Please delete this file: <code class="bg-gray-100 px-2 py-1 rounded">setup_owner_admin.php</code></p>
            <a href="admin/admin_login.php" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Go to Admin Login</a>
        </div>
    </body>
    </html>
    ');
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $emailRaw = trim($_POST['email'] ?? '');
    $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $phoneRaw = trim($_POST['phone_number'] ?? '');
    $setupKey = trim($_POST['setup_key'] ?? '');
    
    // Simple setup key verification (change this before deploying!)
    $correctSetupKey = 'JVB2026SETUP'; // ⚠️ Change this to something secure
    
    if ($setupKey !== $correctSetupKey) {
        $error = 'Invalid setup key. Please contact the system administrator.';
    } elseif (!$firstName || !$lastName || !$email || !$username) {
        $error = 'Please fill in all required fields.';
    } elseif (!$email) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Validate phone number (optional but must be valid PH format if provided)
        $phoneNumber = null;
        if (!empty($phoneRaw)) {
            if (preg_match('/^09\d{9}$/', $phoneRaw)) {
                $phoneNumber = $phoneRaw;
            } else {
                $error = 'Phone number must be in format: 09XXXXXXXXX';
            }
        }
        
        if (!$error) {
            // Check for duplicate username or email
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM admin_accounts WHERE username = ? OR email = ?");
            $checkStmt->bind_param("ss", $username, $email);
            $checkStmt->execute();
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();
            
            if ($count > 0) {
                $error = 'Username or email already exists.';
            } else {
                // Create the admin account
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'admin';
                $sessionTimeout = 120; // 2 hours for admin
                $adminProfile = json_encode(['bio' => '']);
                
                $stmt = $conn->prepare("
                    INSERT INTO admin_accounts 
                    (first_name, last_name, username, password_hash, email, phone_number, role, is_primary_contact, session_timeout, admin_profile, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 1)
                ");
                
                $stmt->bind_param(
                    "sssssssss",
                    $firstName,
                    $lastName,
                    $username,
                    $passwordHash,
                    $email,
                    $phoneNumber,
                    $role,
                    $sessionTimeout,
                    $adminProfile
                );
                
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = 'Failed to create account. Please try again.';
                    error_log("Admin setup error: " . $stmt->error);
                }
                
                $stmt->close();
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JV-B Itinerary and Document Management System - Admin Setup</title>
    <link rel="stylesheet" href="output.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32x32.png">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    
    <?php if ($success): ?>
    <!-- Success Message -->
    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full text-center">
        <div class="text-green-500 text-6xl mb-6">✓</div>
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Account Created Successfully!</h1>
        <p class="text-gray-600 mb-6">Your admin account has been created. You can now log in to the system.</p>
        
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 text-left">
            <p class="text-sm text-yellow-800 font-semibold mb-2">⚠️ IMPORTANT SECURITY NOTICE</p>
            <p class="text-sm text-yellow-700">Please DELETE this file immediately:</p>
            <code class="block bg-yellow-100 text-yellow-900 px-3 py-2 rounded mt-2 text-xs break-all">
                setup_owner_admin.php
            </code>
        </div>
        
        <a href="admin/admin_login.php" class="inline-block bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
            Go to Admin Login
        </a>
    </div>
    
    <?php else: ?>
    <!-- Setup Form -->
    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">JV-B Itinerary and Document Management System</h1>
            <p class="text-gray-600">Admin Account Setup</p>
            <p class="text-gray-600">Thank you so much, ate Jenn — Jeff</p>
        </div>
        
        <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <p class="text-red-800 text-sm"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="space-y-4">
            
            <!-- Setup Key -->
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
                <p class="text-sm text-blue-800">Enter the setup key provided by the developer</p>
            </div>
            
            <div>
                <label for="setup_key" class="block text-sm font-medium text-gray-700 mb-2">
                    Setup Key <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    id="setup_key" 
                    name="setup_key" 
                    required 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Enter setup key"
                    value="<?php echo htmlspecialchars($_POST['setup_key'] ?? ''); ?>"
                >
            </div>
            
            <hr class="my-6">
            
            <!-- First Name -->
            <div>
                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                    First Name <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    id="first_name" 
                    name="first_name" 
                    required 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                >
            </div>
            
            <!-- Last Name -->
            <div>
                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                    Last Name <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    id="last_name" 
                    name="last_name" 
                    required 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                >
            </div>
            
            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                    Email Address <span class="text-red-500">*</span>
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                >
            </div>
            
            <!-- Phone Number -->
            <div>
                <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-2">
                    Phone Number (Optional)
                </label>
                <input 
                    type="tel" 
                    id="phone_number" 
                    name="phone_number" 
                    pattern="09\d{9}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="09XXXXXXXXX"
                    value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                >
                <p class="text-xs text-gray-500 mt-1">Format: 09XXXXXXXXX (Philippine mobile number)</p>
            </div>
            
            <!-- Username -->
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                    Username <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required 
                    pattern="[a-z0-9_]+"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="lowercase letters, numbers, underscore"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                >
                <p class="text-xs text-gray-500 mt-1">Lowercase letters, numbers, and underscores only</p>
            </div>
            
            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    Password <span class="text-red-500">*</span>
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    minlength="8"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Minimum 8 characters"
                >
                <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters long</p>
            </div>
            
            <!-- Confirm Password -->
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                    Confirm Password <span class="text-red-500">*</span>
                </label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    required 
                    minlength="8"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Re-enter password"
                >
            </div>
            
            <!-- Submit Button -->
            <button 
                type="submit" 
                class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors mt-6"
            >
                Create admin Account
            </button>
            
        </form>
        
        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
            <p class="text-xs text-gray-600 text-center">
                This account will have full system access. After creation, you can add other admin users from the admin panel.
            </p>
        </div>
    </div>
    <?php endif; ?>
    
</body>
</html>
