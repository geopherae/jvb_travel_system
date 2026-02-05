<?php
/**
 * Visa Application Status Calculator Helper
 * 
 * Automatically calculates and updates visa application status based on document submission states.
 * 
 * STATUS LOGIC:
 * - 'Complete': ALL required documents are approved
 * - 'Rejected': ALL required documents are rejected
 * - 'Awaiting Docs': Default state (pending, mixed statuses, or no submissions)
 * 
 * This function is called after:
 * - Document approval (approve_visa_requirement.php)
 * - Document rejection (reject_visa_requirement.php)
 * - New document submission (submit_visa_document.php)
 * - Document deletion (delete_visa_document.php)
 */

namespace VisaStatusHelper;

/**
 * Recalculate and update visa application status based on document submissions
 * 
 * @param mysqli $conn Database connection
 * @param int $visa_application_id Visa application ID
 * @return array Result with success status and new status value
 */
function recalculateVisaApplicationStatus($conn, $visa_application_id) {
    try {
        // Get application client_id
        $appStmt = $conn->prepare("SELECT client_id FROM client_visa_applications WHERE id = ? LIMIT 1");
        $appStmt->bind_param("i", $visa_application_id);
        $appStmt->execute();
        $appResult = $appStmt->get_result();
        $appRow = $appResult->fetch_assoc();
        $appStmt->close();

        if (!$appRow || empty($appRow['client_id'])) {
            throw new Exception('Visa application not found or missing client_id');
        }

        $clientId = (int) $appRow['client_id'];

        // Fetch client/companion requirements JSON for this application
        $reqStmt = $conn->prepare("
            SELECT companion_id, requirements_json
            FROM client_visa_requirements
            WHERE client_id = ?
              AND (companion_id IS NULL OR companion_id IN (
                SELECT id FROM client_visa_companions WHERE visa_application_id = ?
              ))
        ");
        $reqStmt->bind_param("ii", $clientId, $visa_application_id);
        $reqStmt->execute();
        $reqResult = $reqStmt->get_result();

        $requiredItems = [];
        while ($row = $reqResult->fetch_assoc()) {
            $companionId = $row['companion_id'] !== null ? (int) $row['companion_id'] : null;
            $reqs = json_decode($row['requirements_json'] ?? '[]', true) ?? [];
            foreach ($reqs as $req) {
                $isRequired = (bool) ($req['required'] ?? false);
                $reqId = $req['id'] ?? ($req['requirement_id'] ?? null);
                if ($isRequired && !empty($reqId)) {
                    $key = ($companionId === null ? 'lead' : $companionId) . ':' . $reqId;
                    $requiredItems[$key] = [
                        'companion_id' => $companionId,
                        'requirement_id' => (string) $reqId
                    ];
                }
            }
        }
        $reqStmt->close();

        $total_required = count($requiredItems);
        $required_approved = 0;
        $required_rejected = 0;

        if ($total_required > 0) {
            $docStmt = $conn->prepare("
                SELECT status
                FROM visa_document_submissions
                WHERE visa_application_id = ?
                  AND requirement_id = ?
                  AND companion_id <=> ?
                ORDER BY uploaded_at DESC, id DESC
                LIMIT 1
            ");

            foreach ($requiredItems as $item) {
                $companionId = $item['companion_id'];
                $reqId = $item['requirement_id'];
                $docStmt->bind_param("isi", $visa_application_id, $reqId, $companionId);
                $docStmt->execute();
                $docResult = $docStmt->get_result();

                if ($docResult->num_rows > 0) {
                    $submission = $docResult->fetch_assoc();
                    $status = strtolower($submission['status'] ?? '');
                    if ($status === 'approved') {
                        $required_approved++;
                    } elseif ($status === 'rejected') {
                        $required_rejected++;
                    }
                }
            }

            $docStmt->close();
        }
        
        // Calculate new status
        $new_status = 'Awaiting Docs'; // Default
        
        if ($total_required > 0) {
            // ALL required documents are approved
            if ($required_approved === $total_required) {
                $new_status = 'Complete';
            }
            // ALL required documents are rejected
            elseif ($required_rejected === $total_required) {
                $new_status = 'Rejected';
            }
            // Mixed or pending states
            else {
                $new_status = 'Awaiting Docs';
            }
        }
        
        // Get current status before update
        $oldStatusStmt = $conn->prepare("
            SELECT status, visa_package_id FROM client_visa_applications WHERE id = ?
        ");
        $oldStatusStmt->bind_param("i", $visa_application_id);
        $oldStatusStmt->execute();
        $oldStatusResult = $oldStatusStmt->get_result();
        $oldStatusData = $oldStatusResult->fetch_assoc();
        $old_status = $oldStatusData['status'] ?? '';
        $visa_package_id = $oldStatusData['visa_package_id'] ?? null;
        $oldStatusStmt->close();

        // Update application status
        $updateStmt = $conn->prepare("
            UPDATE client_visa_applications 
            SET status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $updateStmt->bind_param("si", $new_status, $visa_application_id);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Send notification if status changed to Complete
        if ($old_status !== 'Complete' && $new_status === 'Complete' && $visa_package_id) {
            try {
                require_once __DIR__ . '/../actions/notify.php';
                $notifyManager = new \NotificationManager($conn);
                
                // Get visa package name
                $pkgStmt = $conn->prepare("SELECT visa_package_name FROM visa_packages WHERE id = ?");
                $pkgStmt->bind_param("i", $visa_package_id);
                $pkgStmt->execute();
                $pkgResult = $pkgStmt->get_result();
                $pkgData = $pkgResult->fetch_assoc();
                $pkgStmt->close();
                
                if ($pkgData && $clientId) {
                    $notifyManager->send([
                        'recipient_type' => 'client',
                        'recipient_id' => $clientId,
                        'event' => 'visa_application_complete',
                        'context' => [
                            'client_id' => $clientId,
                            'visa_application_id' => $visa_application_id,
                            'visa_package_name' => $pkgData['visa_package_name'] ?? 'your visa'
                        ]
                    ]);
                }
            } catch (\Exception $e) {
                error_log("[VisaStatusHelper] Failed to send completion notification: " . $e->getMessage());
                // Continue execution even if notification fails
            }
        }
        
        return [
            'success' => true,
            'status' => $new_status,
            'details' => [
                'total_required' => $total_required,
                'approved' => $required_approved,
                'rejected' => $required_rejected,
                'pending' => max(0, $total_required - $required_approved - $required_rejected)
            ]
        ];
        
    } catch (\Exception $e) {
        error_log("[VisaStatusHelper] Error recalculating status: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get human-readable status label with color class
 * 
 * @param string $status Status value
 * @return array with 'label' and 'color_class'
 */
function getStatusDisplay($status) {
    $displays = [
        'Awaiting Docs' => [
            'label' => 'Awaiting Documents',
            'color_class' => 'bg-yellow-100 text-yellow-700 border-yellow-300'
        ],
        'Rejected' => [
            'label' => 'Rejected',
            'color_class' => 'bg-red-100 text-red-700 border-red-300'
        ],
        'Complete' => [
            'label' => 'Complete',
            'color_class' => 'bg-green-100 text-green-700 border-green-300'
        ]
    ];
    
    return $displays[$status] ?? [
        'label' => $status,
        'color_class' => 'bg-gray-100 text-gray-700 border-gray-300'
    ];
}

/**
 * Batch recalculate statuses for multiple applications
 * Useful for data migration or batch updates
 * 
 * @param mysqli $conn Database connection
 * @param array $application_ids Array of visa application IDs
 * @return array Results for each application
 */
function batchRecalculateStatuses($conn, $application_ids) {
    $results = [];
    
    foreach ($application_ids as $app_id) {
        $results[$app_id] = recalculateVisaApplicationStatus($conn, $app_id);
    }
    
    return $results;
}

/**
 * Get all applications that need status recalculation
 * Returns applications where status might be outdated
 * 
 * @param mysqli $conn Database connection
 * @return array Array of application IDs
 */
function getApplicationsNeedingRecalculation($conn) {
    $stmt = $conn->query("
        SELECT DISTINCT id 
        FROM client_visa_applications 
        WHERE status IN ('Awaiting Docs', 'Rejected', 'Complete')
        ORDER BY id
    ");
    
    $app_ids = [];
    while ($row = $stmt->fetch_assoc()) {
        $app_ids[] = $row['id'];
    }
    
    return $app_ids;
}
