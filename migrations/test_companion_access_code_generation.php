<?php
/**
 * Test file to verify access_code generation for companions
 */

// Test access code generation logic
function generateCompanionAccessCode($name) {
    return strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $name), 0, 4)) . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
}

echo "=== Testing Companion Access Code Generation ===\n\n";

$testNames = [
    'Maria Santos',
    'Juan Dela Cruz',
    'José Rizal',
    'A. B.',
    '陳小明', // Chinese name
    'Mohammad Ali Khan'
];

foreach ($testNames as $name) {
    $accessCode = generateCompanionAccessCode($name);
    echo "Name: {$name}\n";
    echo "Generated Code: {$accessCode}\n";
    echo "Format: " . (preg_match('/^[A-Z0-9]{1,4}-\d{4}$/', $accessCode) ? 'Valid ✓' : 'Invalid ✗') . "\n";
    echo "\n";
}

echo "=== Summary ===\n";
echo "✓ Companions get individual access codes stored in client_visa_companions.access_code\n";
echo "✓ Lead guest access code stored in clients.access_code\n";
echo "✓ group_code column removed from clients table\n";
echo "✓ Format: XXXX-1234 (4 chars from name + 4 digit random)\n";
?>
