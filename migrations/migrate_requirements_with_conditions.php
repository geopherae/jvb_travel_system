<?php
/**
 * Migration: Add Conditional Requirements Structure
 * 
 * Updates requirements_json schema to include:
 * - category: 'primary', 'conditional', or 'exemption'
 * - condition: object with type, operator, and value for conditional requirements
 * 
 * Run with: php migrations/migrate_requirements_with_conditions.php
 */

require_once __DIR__ . '/../actions/db.php';

echo "Starting migration: Add Conditional Requirements Structure\n";
echo str_repeat("=", 60) . "\n\n";

// Fetch all visa packages
$stmt = $conn->prepare("SELECT id, country, requirements_json FROM visa_packages");
$stmt->execute();
$result = $stmt->get_result();
$packages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($packages)) {
    echo "❌ No visa packages found.\n";
    exit(1);
}

echo "Found " . count($packages) . " visa package(s). Processing...\n\n";

$updated = 0;
$failed = 0;

foreach ($packages as $pkg) {
    echo "Processing: {$pkg['country']}\n";
    
    $requirements = json_decode($pkg['requirements_json'], true);
    
    if (!is_array($requirements)) {
        echo "  ⚠️  Invalid JSON. Skipping.\n";
        $failed++;
        continue;
    }
    
    // Transform requirements based on package type
    $transformed = transformRequirementsForPackage($pkg['country'], $requirements);
    
    // Update database
    $newJson = json_encode($transformed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $updateStmt = $conn->prepare("UPDATE visa_packages SET requirements_json = ? WHERE id = ?");
    $updateStmt->bind_param("si", $newJson, $pkg['id']);
    
    if ($updateStmt->execute()) {
        echo "  ✅ Updated (" . count($transformed) . " requirements)\n";
        $updated++;
    } else {
        echo "  ❌ Failed to update: " . $updateStmt->error . "\n";
        $failed++;
    }
    
    $updateStmt->close();
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Migration Summary:\n";
echo "  Updated: $updated\n";
echo "  Failed: $failed\n";
echo "  Total: " . count($packages) . "\n";
echo str_repeat("=", 60) . "\n";

// ===== TRANSFORMATION FUNCTION =====

/**
 * Transform requirements for a specific visa package type
 * Categorizes requirements and adds conditional logic
 */
function transformRequirementsForPackage($country, $requirements) {
    $transformed = [];
    
    foreach ($requirements as $req) {
        $item = [
            'id' => $req['id'] ?? generateReqId($req['name'] ?? ''),
            'name' => $req['name'] ?? 'Unknown',
            'description' => $req['description'] ?? '',
            'required' => $req['required'] ?? true,
            'category' => 'primary',
            'condition' => null
        ];
        
        // Detect condition type from requirement name/description
        $condition = detectCondition($req['name'] ?? '', $req['description'] ?? '');
        
        if ($condition) {
            $item['category'] = 'conditional';
            $item['condition'] = $condition;
        }
        
        $transformed[] = $item;
    }
    
    return $transformed;
}

/**
 * Detect condition from requirement name/description
 */
function detectCondition($name, $description) {
    // Employment Status Conditions
    if (stripos($name, 'Certificate of Employment') !== false || 
        stripos($description, 'Certificate of Employment') !== false) {
        return [
            'type' => 'applicant_status',
            'operator' => 'equals',
            'value' => 'employed'
        ];
    }
    
    if ((stripos($name, 'DTI') !== false || stripos($name, 'SEC Permit') !== false) &&
        (stripos($description, 'Business Owner') !== false || stripos($description, 'self-employed') !== false)) {
        return [
            'type' => 'applicant_status',
            'operator' => 'in',
            'value' => ['business_owner', 'self_employed']
        ];
    }
    
    // Student Conditions
    if (stripos($name, 'School Certificate') !== false || 
        stripos($name, 'School ID') !== false ||
        stripos($description, 'School Certificate') !== false) {
        return [
            'type' => 'applicant_status',
            'operator' => 'equals',
            'value' => 'student'
        ];
    }
    
    // Senior Citizen Conditions
    if (stripos($name, 'Senior Citizen ID') !== false ||
        stripos($name, 'Retirement Certificate') !== false ||
        stripos($description, 'Senior Citizen') !== false) {
        return [
            'type' => 'applicant_status',
            'operator' => 'in',
            'value' => ['senior_citizen', 'retired']
        ];
    }
    
    // Sponsored/Guarantee Conditions
    if (stripos($name, 'Guarantee Letter') !== false ||
        stripos($name, 'Affidavit of Support') !== false ||
        stripos($description, 'Sponsor') !== false) {
        return [
            'type' => 'application_type',
            'operator' => 'equals',
            'value' => 'sponsored'
        ];
    }
    
    // Family/Friend Visit Conditions
    if (stripos($name, 'Invitation Letter') !== false ||
        stripos($description, 'Invitation Letter') !== false) {
        return [
            'type' => 'application_type',
            'operator' => 'equals',
            'value' => 'family_visit'
        ];
    }
    
    // Professional License Conditions
    if (stripos($name, 'PRC') !== false || stripos($name, 'IBP Card') !== false) {
        return [
            'type' => 'applicant_status',
            'operator' => 'equals',
            'value' => 'professional'
        ];
    }
    
    // Visa Renewal/Dependent Conditions
    if (stripos($name, 'previous') !== false && stripos($name, 'Visa') !== false) {
        return [
            'type' => 'application_type',
            'operator' => 'equals',
            'value' => 'renewal'
        ];
    }
    
    if (stripos($name, 'Birth Certificate') !== false && stripos($description, '0-13') !== false) {
        return [
            'type' => 'applicant_age',
            'operator' => 'less_than',
            'value' => 14
        ];
    }
    
    // Married Conditions
    if (stripos($name, 'Marriage Certificate') !== false ||
        (stripos($name, 'PSA') !== false && stripos($description, 'married') !== false)) {
        return [
            'type' => 'applicant_status',
            'operator' => 'equals',
            'value' => 'married'
        ];
    }
    
    // Business/Commercial Trip Conditions
    if (stripos($name, 'Travel Order') !== false ||
        stripos($name, 'Dispatch Letter') !== false) {
        return [
            'type' => 'application_type',
            'operator' => 'equals',
            'value' => 'business_trip'
        ];
    }
    
    // Financial Requirements (Sponsored vs Self-Funded)
    if ((stripos($name, 'Bank Certificate') !== false || stripos($name, 'Bank Statement') !== false) &&
        stripos($description, 'Sponsor') !== false) {
        return [
            'type' => 'financial_source',
            'operator' => 'equals',
            'value' => 'sponsor'
        ];
    }
    
    // Company ID, ITR, etc. - tied to employment
    if (stripos($name, 'Company ID') !== false ||
        (stripos($name, 'ITR') !== false && stripos($description, 'Form 2316') !== false)) {
        return [
            'type' => 'applicant_status',
            'operator' => 'equals',
            'value' => 'employed'
        ];
    }
    
    return null;
}

/**
 * Generate a simple ID from requirement name
 */
function generateReqId($name) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '_', $name));
    return 'req_' . substr(md5($slug), 0, 8);
}

$conn->close();
echo "\nDone.\n";
