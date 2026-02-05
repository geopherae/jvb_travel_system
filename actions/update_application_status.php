<?php
/**
 * DEPRECATED: Update Visa Application Status Action
 * 
 * ⚠️ THIS ENDPOINT IS NO LONGER USED
 * 
 * Visa application statuses are now automatically calculated and updated based on document submission states:
 * - 'Awaiting Docs': Initial state or mixed document statuses
 * - 'Rejected': ALL required documents have been rejected
 * - 'Complete': ALL required documents have been approved
 * 
 * Status updates are triggered automatically in:
 * - actions/approve_visa_requirement.php (when approving documents)
 * - actions/reject_visa_requirement.php (when rejecting documents)
 * - actions/submit_visa_document.php (when submitting documents)
 * 
 * See includes/visa_status_helper.php for the recalculation logic.
 * 
 * This file is kept for backward compatibility and to prevent 404 errors on legacy requests.
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) exit('Access denied.');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../includes/auth.php';

use function Auth\getActorContext;

header('Content-Type: application/json');

// Admin check
$actor = getActorContext();
if ($actor['role'] !== 'superadmin' && $actor['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Admin access required'
    ]);
    exit;
}

// Return deprecation notice
http_response_code(410); // Gone
echo json_encode([
    'success' => false,
    'message' => 'This endpoint is deprecated. Visa application statuses are now automatically calculated based on document submission states.',
    'more_info' => 'See includes/visa_status_helper.php for status calculation logic'
]);

