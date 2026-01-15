<?php
session_start();

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/log_helper.php';
use LogHelper\generatePackageDeletionSummary;
use LogHelper\logAuditAction;

error_log("Debug: Starting delete_tour_package.php at " . date('Y-m-d H:i:s'));

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("Debug: CSRF token mismatch, received: " . ($_POST['csrf_token'] ?? 'none') . ", expected: " . ($_SESSION['csrf_token'] ?? 'none'));
    $_SESSION['modal_status'] = 'delete_failed';
    $_SESSION['error_message'] = 'Invalid CSRF token';
    $conn->close();
    echo "Error: Invalid CSRF token";
    exit;
}

// Check if package_id is provided
if (!isset($_POST['package_id']) || !is_numeric($_POST['package_id'])) {
    error_log("Debug: Invalid or missing package_id, received: " . ($_POST['package_id'] ?? 'none'));
    $_SESSION['modal_status'] = 'delete_failed';
    $_SESSION['error_message'] = 'Invalid package ID';
    $conn->close();
    echo "Error: Invalid package ID";
    exit;
}

$packageId = (int)$_POST['package_id'];
error_log("Debug: packageId=$packageId");

// Get count of affected clients
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM clients WHERE assigned_package_id = ?");
$stmt->bind_param("i", $packageId);
if (!$stmt->execute()) {
    error_log("Debug: Client count query failed: " . $stmt->error);
    $_SESSION['modal_status'] = 'delete_failed';
    $_SESSION['error_message'] = 'Client count query failed: ' . $stmt->error;
    $stmt->close();
    $conn->close();
    echo "Error: Client count query failed";
    exit;
}
$result = $stmt->get_result();
$clientCount = $result->fetch_assoc()['count'];
$stmt->close();
error_log("Debug: clientCount=$clientCount");

// Begin transaction
$conn->begin_transaction();

try {
    // Update tour_packages to set is_deleted
    $stmt = $conn->prepare("UPDATE tour_packages SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $packageId);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    error_log("Debug: Update affected rows=$affectedRows");

    // Delete related itinerary entries
    $stmt = $conn->prepare("DELETE FROM tour_package_itinerary WHERE package_id = ?");
    $stmt->bind_param("i", $packageId);
    $stmt->execute();
    $stmt->close();

    if ($affectedRows > 0) {
        // ✅ [Step 12] Audit Log
        $actorId = (int) ($_SESSION['admin']['id'] ?? 0);
        error_log("Debug: actorId=$actorId");
        \LogHelper\logAuditAction($conn, [
            'actor_id'        => $actorId,
            'actor_role'      => 'admin',
            'action_type'     => 'soft_delete_package',
            'target_id'       => $packageId,
            'target_type'     => 'package',
            'changes'         => [
                'package_id'       => $packageId,
                'affected_clients' => $clientCount,
                'summary'          => \LogHelper\generatePackageDeletionSummary($packageId, $clientCount),
                'source'           => 'delete_tour_package.php'
            ],
            'severity'        => 'normal',
            'module'          => 'package',
            'kpi_tag'         => 'tour_delete',
            'business_impact' => 'moderate'
        ]);
        error_log("✅ [Step 12] Audit log created.");

        // Commit transaction
        $conn->commit();
        $_SESSION['modal_status'] = 'package_soft_deleted';
    } else {
        // Rollback transaction
        $conn->rollback();
        error_log("Debug: No rows affected by update");
        $_SESSION['modal_status'] = 'delete_failed';
        $_SESSION['error_message'] = 'No package found with ID ' . $packageId;
    }
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error in delete_tour_package.php: " . $e->getMessage());
    $_SESSION['modal_status'] = 'delete_failed';
    $_SESSION['error_message'] = 'Server error: ' . $e->getMessage();
}

// Close connection and redirect
$conn->close();
eheader("Location: ../admin/admin_tour_packages.php");
echo "Debug: Script completed, modal_status=" . ($_SESSION['modal_status'] ?? 'not set') . ", error_message=" . ($_SESSION['error_message'] ?? 'none');
exit;
?>