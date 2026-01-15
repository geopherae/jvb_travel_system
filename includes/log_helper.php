<?php
namespace LogHelper;

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/auth.php';

use function Auth\getActorContext;

/**
 * Logs a general event with actor context.
 */
function logEvent(
  string $actionType,
  int $logEvent,
  string $targetType,
  array $changes = [],
  string $severity = 'info',
  string $module = 'general',
  ?string $kpiTag = null,
  ?string $impact = null
): void {
  $actor = getActorContext();
  logAction(
    $GLOBALS['conn'],
    $actionType,
    $actor['id'] ?? 0,
    $actor['role'] ?? 'guest',
    $targetId,
    $targetType,
    $changes,
    $severity,
    $module,
    $kpiTag ?? 'generic_event',
    $impact ?? 'low'
  );
}

/**
 * Logs a notification view event.
 */
function logNotificationView(int $notificationId, string $notificationText): void {
  logEvent(
    'VIEW_NOTIFICATION',
    $notificationId,
    'notification',
    ['message' => $notificationText],
    'info',
    'notifications',
    'notification_flow',
    'medium'
  );
}

/**
 * Logs a system-level error.
 */
function logError(
  string $actionType,
  string $errorMessage,
  string $module = 'general',
  ?string $kpiTag = null,
  ?string $impact = null
): void {
  $actor = getActorContext();
  logAction(
    $GLOBALS['conn'],
    $actionType,
    $actor['id'] ?? 0,
    $actor['role'] ?? 'guest',
    0,
    'system',
    ['error' => $errorMessage],
    'error',
    $module,
    $kpiTag ?? 'system_error',
    $impact ?? 'high'
  );
}

/**
 * Logs multiple events in batch.
 */
function logBatch(array $actions): void {
  foreach ($actions as $action) {
    logEvent(
      $action['actionType'],
      $action['targetId'],
      $action['targetType'],
      $action['changes'] ?? [],
      $action['severity'] ?? 'info',
      $action['module'] ?? 'general',
      $action['kpiTag'] ?? 'batch_event',
      $action['impact'] ?? 'low'
    );
  }
}

/**
 * Logs a page view with enhanced metadata.
 */
function logView(string $actorRole = 'guest'): void {
  $conn = $GLOBALS['conn'];

  $stmt = $conn->prepare("
    INSERT INTO audit_logs (
      action_type, actor_id, actor_role,
      target_id, target_type, changes,
      severity, module, timestamp,
      session_id, ip_address, user_agent,
      kpi_tag, business_impact
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
  ");

  $actionType = 'VIEW_PAGE';
  $actorId = 0;
  $targetId = 0;
  $targetType = 'page';
  $changes = ['page' => basename($_SERVER['SCRIPT_FILENAME'])];
  $severity = 'info';
  $module = 'general';
  $sessionId = session_id();
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
  $kpiTag = 'page_view';
  $impact = 'low';

  $jsonChanges = json_encode($changes, JSON_UNESCAPED_UNICODE);

  $stmt->bind_param(
    "sisisssssssss",
    $actionType,
    $actorId,
    $actorRole,
    $targetId,
    $targetType,
    $jsonChanges,
    $severity,
    $module,
    $sessionId,
    $ip,
    $userAgent,
    $kpiTag,
    $impact
  );

  $stmt->execute();
  $stmt->close();
}

/**
 * Logs a unified event, optionally as a system-level log.
 */
function logUnified(
  string $actionType,
  int $targetId,
  string $targetType,
  array $data = [],
  string $severity = 'info',
  string $module = 'general',
  bool $isSystem = false,
  ?string $kpiTag = null,
  ?string $impact = null
): void {
  $actor = getActorContext();
  $conn = $GLOBALS['conn'];

  $resolvedKpiTag = $kpiTag ?? 'unified_event';
  $resolvedImpact = $impact ?? 'low';

  if ($isSystem) {
    logSystemEvent(
      $conn,
      $actionType,
      $actor['id'] ?? 0,
      $actor['role'] ?? 'guest',
      $targetId,
      $targetType,
      $data,
      $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
      $severity,
      $module,
      $resolvedKpiTag,
      $resolvedImpact
    );
  } else {
    logAction(
      $conn,
      $actionType,
      $actor['id'] ?? 0,
      $actor['role'] ?? 'guest',
      $targetId,
      $targetType,
      $data,
      $severity,
      $module,
      $resolvedKpiTag,
      $resolvedImpact
    );
  }
}

/**
 * Core audit log writer.
 */
function logAction(
  \mysqli $conn,
  string $actionType,
  int $actorId,
  string $actorRole,
  int $targetId,
  string $targetType,
  array $changes,
  string $severity,
  string $module,
  string $kpiTag,
  string $impact
): void {
  $stmt = $conn->prepare("
    INSERT INTO audit_logs (
      action_type, actor_id, actor_role,
      target_id, target_type, changes,
      severity, module, timestamp,
      kpi_tag, business_impact
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
  ");

  $jsonChanges = json_encode($changes, JSON_UNESCAPED_UNICODE);

  $stmt->bind_param(
    "sisissssss",
    $actionType,
    $actorId,
    $actorRole,
    $targetId,
    $targetType,
    $jsonChanges,
    $severity,
    $module,
    $kpiTag,
    $impact
  );

  $stmt->execute();
  $stmt->close();
}

/**
 * System-level log writer.
 */
function logSystemEvent(
  \mysqli $conn,
  string $actionType,
  int $actorId,
  string $actorRole,
  int $targetId,
  string $targetType,
  array $payload,
  string $ipAddress,
  string $userAgent,
  string $severity,
  string $module,
  string $kpiTag,
  string $impact
): void {
  $stmt = $conn->prepare("
    INSERT INTO system_logs (
      action_type, actor_id, actor_role,
      target_id, target_type, payload,
      ip_address, user_agent,
      severity_level, module, created_at,
      kpi_tag, business_impact
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
  ");

  $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

  $stmt->bind_param(
    "sisissssssss",
    $actionType,
    $actorId,
    $actorRole,
    $targetId,
    $targetType,
    $jsonPayload,
    $ipAddress,
    $userAgent,
    $severity,
    $module,
    $kpiTag,
    $impact
  );

  $stmt->execute();
  $stmt->close();
}

function logAdminDocumentUpload(\mysqli $conn, array $data): void {
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (
            action_type, actor_id, actor_role,
            target_id, target_type, changes,
            severity, module, timestamp,
            session_id, ip_address, user_agent,
            kpi_tag, business_impact, kpi_subtag
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?
        )
    ");

    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $action_type     = 'upload_document_admin';
    $actor_id        = $data['admin_id'];
    $actor_role      = 'admin';
    $target_id       = $data['client_id'];
    $target_type     = 'client';
    $changes         = json_encode([
        'file_name'     => $data['file_name'],
        'document_type' => $data['document_type'],
        'mime_type'     => $data['mime_type'],
        'path'          => $data['file_path'],
        'source'        => $data['source'] ?? 'upload_document_admin.php'
    ], JSON_UNESCAPED_UNICODE);
    $severity        = 'normal';
    $module          = 'documents';
    $kpi_tag         = 'document_uploaded';
    $business_impact = 'moderate';
    $kpi_subtag      = $data['document_type'] ?? 'unspecified';

    $stmt->bind_param(
        "sissssssssssss",
        $action_type,
        $actor_id,
        $actor_role,
        $target_id,
        $target_type,
        $changes,
        $severity,
        $module,
        $session_id,
        $ip_address,
        $user_agent,
        $kpi_tag,
        $business_impact,
        $kpi_subtag
    );

    $stmt->execute();
    $stmt->close();
}


function logDocumentUpload(\mysqli $conn, array $data): void {
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (
            action_type, actor_id, actor_role,
            target_id, target_type, changes,
            severity, module, timestamp,
            session_id, ip_address, user_agent,
            kpi_tag, business_impact, kpi_subtag
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?
        )
    ");

    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $action_type     = 'upload_document';
    $actor_id        = $data['actor_id'];
    $actor_role      = 'client';
    $target_id       = $data['client_id'];
    $target_type     = 'client';
    $changes         = json_encode([
        'file_name'     => $data['file_name'],
        'document_type' => $data['document_type'],
        'mime_type'     => $data['mime_type'],
        'path'          => $data['file_path'],
        'source'        => $data['source'] ?? 'upload_document_client.php'
    ], JSON_UNESCAPED_UNICODE);
    $severity        = 'normal';
    $module          = 'documents';
    $kpi_tag         = 'document_uploaded';
    $business_impact = 'low';
    $kpi_subtag      = 'upload_document' ?? 'unspecified';

    $stmt->bind_param(
        "sissssssssssss",
        $action_type,
        $actor_id,
        $actor_role,
        $target_id,
        $target_type,
        $changes,
        $severity,
        $module,
        $session_id,
        $ip_address,
        $user_agent,
        $kpi_tag,
        $business_impact,
        $kpi_subtag
    );

    $stmt->execute();
    $stmt->close();
}

function generateBookingSummary(array $fields): string {
  $summary = [];

  if (!empty($fields['booking_number'])) {
    $summary[] = "Booking #{$fields['booking_number']}";
  }
  if (!empty($fields['status'])) {
    $summary[] = "Status → {$fields['status']}";
  }
  if (!empty($fields['trip_date_start'])) {
    $summary[] = "Start → {$fields['trip_date_start']}";
  }
  if (!empty($fields['trip_date_end'])) {
    $summary[] = "End → {$fields['trip_date_end']}";
  }
  if (!empty($fields['booking_date'])) {
    $summary[] = "Booked on {$fields['booking_date']}";
  }
  if (!empty($fields['assigned_admin_id'])) {
    $summary[] = "Assigned to Admin #{$fields['assigned_admin_id']}";
  }

  return implode(', ', $summary);
}

function generatePackageSummary(array $fields): string {
  $summary = [];

  if (!empty($fields['package_name'])) {
    $summary[] = "Package: {$fields['package_name']}";
  }
  if (!empty($fields['price'])) {
    $summary[] = "₱" . number_format((float)$fields['price'], 2);
  }
  if (!empty($fields['day_duration']) || !empty($fields['night_duration'])) {
    $summary[] = "{$fields['day_duration']}D/{$fields['night_duration']}N";
  }
  if (!empty($fields['origin']) && !empty($fields['destination'])) {
    $summary[] = "{$fields['origin']} → {$fields['destination']}";
  }
  if (!empty($fields['is_favorite'])) {
    $summary[] = "★ Favorite";
  }

  return implode(', ', $summary);
}

/**
 * Generate a clear and detailed summary for package update audit logs.
 * Each changed field is explicitly described for better traceability.
 *
 * @param array $fields Associative array of fields that were updated
 * @return string Human-readable summary of changes
 */
function generatePackageUpdateSummary(array $fields): string
{
    $changes = [];

    if (!empty($fields['package_name'] ?? '')) {
        $changes[] = "package name to '{$fields['package_name']}'";
    }

    if (array_key_exists('price', $fields)) {
        $formattedPrice = '₱' . number_format((float)$fields['price'], 2);
        $changes[] = "price to {$formattedPrice}";
    }

    if (array_key_exists('day_duration', $fields) || array_key_exists('night_duration', $fields)) {
        $days = $fields['day_duration'] ?? 0;
        $nights = $fields['night_duration'] ?? 0;
        $changes[] = "duration to {$days}D/{$nights}N";
    }

    if (!empty($fields['origin'] ?? '') && !empty($fields['destination'] ?? '')) {
        $changes[] = "route to {$fields['origin']} → {$fields['destination']}";
    }

    if (array_key_exists('is_favorite', $fields)) {
        $action = $fields['is_favorite'] ? 'marked as favorite' : 'removed from favorites';
        $changes[] = $action;
    }

    // Fallback for updates that only touch minor/unlisted fields
    if (empty($changes)) {
        return 'Applied minor changes';
    }

    // Prefix with "Updated" and join with commas for clean readability
    return 'Updated ' . implode(', ', $changes);
}

function generateClientSummary(array $fields): string {
  $summary = [];

  if (!empty($fields['full_name'])) {
    $summary[] = "Name: {$fields['full_name']}";
  }
  if (!empty($fields['email'])) {
    $summary[] = "Email: {$fields['email']}";
  }
  if (!empty($fields['phone_number'])) {
    $summary[] = "Phone: {$fields['phone_number']}";
  }
  if (!empty($fields['address'])) {
    $summary[] = "Address: {$fields['address']}";
  }

  return implode(', ', $summary);
}

//Reassign Package Payload
function generateReassignmentSummary(int $clientId, ?int $oldPackageId, int $newPackageId): string {
  $old = $oldPackageId ? "from Package #$oldPackageId" : "from none";
  return "Client #$clientId reassigned $old to Package #$newPackageId";
}

function generateDocumentUploadSummary(int $clientId, string $documentType, string $fileName): string {
  return "Client #$clientId uploaded a document of type '$documentType' named '$fileName'.";
}

//Unassign Package Payload
function generateUnassignSummary(int $clientId): string {
  return "Client #$clientId unassigned from tour package. Itinerary deleted, booking fields cleared, status set to 'No Assigned Package'.";
}

// Archive Client Payload
function generateArchiveSummary(int $clientId, int $adminId): string {
  return "Client #$clientId was archived by Admin #$adminId";
}

function logAuditAction(\mysqli $conn, array $params): void {
  $actor_id        = (int) ($params['actor_id'] ?? 0);
  $actor_role      = $params['actor_role'] ?? 'admin';
  $action_type     = $params['action_type'] ?? 'unknown_action';
  $target_id       = (int) ($params['target_id'] ?? 0);
  $target_type     = $params['target_type'] ?? 'unknown_target';
  $changes         = json_encode($params['changes'] ?? [], JSON_UNESCAPED_UNICODE);
  $severity        = $params['severity'] ?? 'normal';
  $module          = $params['module'] ?? 'general';
  $timestamp       = date('Y-m-d H:i:s');
  $session_id      = session_id();
  $ip_address      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $user_agent      = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
  $kpi_tag         = $params['kpi_tag'] ?? 'misc_event';
  $business_impact = $params['business_impact'] ?? 'low';

  $stmt = $conn->prepare("
    INSERT INTO audit_logs (
      action_type, actor_id, actor_role,
      target_id, target_type, changes,
      severity, module, timestamp,
      session_id, ip_address, user_agent,
      kpi_tag, business_impact
    ) VALUES (
      ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
  ");

  $stmt->bind_param(
    "sissssssssssss",
    $action_type,
    $actor_id,
    $actor_role,
    $target_id,
    $target_type,
    $changes,
    $severity,
    $module,
    $timestamp,
    $session_id,
    $ip_address,
    $user_agent,
    $kpi_tag,
    $business_impact
  );

  $stmt->execute();
  $stmt->close();
}

//Delete Tour Package Payload
function generatePackageDeletionSummary(int $packageId, int $affectedClients): string {
  return "Tour Package #$packageId deleted. $affectedClients client(s) were assigned to this package. All related itinerary templates and metadata removed.";
}

//Add Client Payload
function logClientOnboardingAudit($conn, array $data): bool {
  $stmt = $conn->prepare("
    INSERT INTO audit_logs (
      action_type, actor_id, actor_role,
      target_id, target_type, changes,
      severity, module, timestamp,
      session_id, ip_address, user_agent,
      kpi_tag, business_impact
    ) VALUES (
      ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
  ");

  $action_type     = 'add_client';
  $actor_id        = $data['actor_id'] ?? 0;
  $actor_role      = 'admin';
  $target_id       = $data['client_id'] ?? 0;
  $target_type     = 'client';
  $changes         = json_encode($data['payload'], JSON_UNESCAPED_UNICODE);
  $severity        = 'normal';
  $module          = 'client';
  $timestamp       = date('Y-m-d H:i:s');
  $session_id      = session_id();
  $ip_address      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $user_agent      = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
  $kpi_tag         = 'client_onboarding';
  $business_impact = 'moderate';

  $stmt->bind_param(
    "sissssssssssss",
    $action_type,
    $actor_id,
    $actor_role,
    $target_id,
    $target_type,
    $changes,
    $severity,
    $module,
    $timestamp,
    $session_id,
    $ip_address,
    $user_agent,
    $kpi_tag,
    $business_impact
  );

  return $stmt->execute();
}

function logDocumentApproval($conn, array $data): bool {
  $stmt = $conn->prepare("
    INSERT INTO audit_logs (
      action_type, actor_id, actor_role,
      target_id, target_type, changes,
      severity, module, timestamp,
      session_id, ip_address, user_agent,
      kpi_tag, business_impact
    ) VALUES (
      ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?
    )
  ");

  $action_type     = 'approve_document';
  $actor_id        = $data['actor_id'] ?? 0;
  $actor_role      = 'admin';
  $target_id       = $data['document_id'] ?? 0;
  $target_type     = 'document';
  $changes         = json_encode([
    'client_id'     => $data['client_id'] ?? null,
    'document_name' => $data['document_name'] ?? null,
    'status'        => 'Approved',
    'source'        => $data['source'] ?? null
  ], JSON_UNESCAPED_UNICODE);
  $severity        = 'normal';
  $module          = 'documents';
  $session_id      = session_id();
  $ip_address      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $user_agent      = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
  $kpi_tag         = 'document_approved';
  $business_impact = 'moderate';

  $stmt->bind_param(
    "sisssssssssss",
    $action_type,
    $actor_id,
    $actor_role,
    $target_id,
    $target_type,
    $changes,
    $severity,
    $module,
    $session_id,
    $ip_address,
    $user_agent,
    $kpi_tag,
    $business_impact
  );

  return $stmt->execute();
}

function logDocumentRejection($conn, array $data): bool {
  $stmt = $conn->prepare("
    INSERT INTO audit_logs (
      action_type, actor_id, actor_role,
      target_id, target_type, changes,
      severity, module, timestamp,
      session_id, ip_address, user_agent,
      kpi_tag, business_impact
    ) VALUES (
      ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?
    )
  ");

  $action_type     = 'reject_document';
  $actor_id        = $data['actor_id'] ?? 0;
  $actor_role      = 'admin';
  $target_id       = $data['document_id'] ?? 0;
  $target_type     = 'document';
  $changes         = json_encode([
    'client_id'     => $data['client_id'] ?? null,
    'document_name' => $data['document_name'] ?? null,
    'status'        => 'Rejected',
    'reason'        => $data['comments'] ?? null,
    'source'        => $data['source'] ?? null
  ], JSON_UNESCAPED_UNICODE);
  $severity        = 'normal';
  $module          = 'documents';
  $session_id      = session_id();
  $ip_address      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $user_agent      = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
  $kpi_tag         = 'document_rejected';
  $business_impact = 'moderate';

  $stmt->bind_param(
    "sisssssssssss",
    $action_type,
    $actor_id,
    $actor_role,
    $target_id,
    $target_type,
    $changes,
    $severity,
    $module,
    $session_id,
    $ip_address,
    $user_agent,
    $kpi_tag,
    $business_impact
  );

  return $stmt->execute();
}

function logDocumentUpdate($conn, array $data): bool {
  $stmt = $conn->prepare("
    INSERT INTO audit_logs (
      action_type, actor_id, actor_role,
      target_id, target_type, changes,
      severity, module, timestamp,
      session_id, ip_address, user_agent,
      kpi_tag, business_impact
    ) VALUES (
      ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?
    )
  ");

  $action_type     = 'update_document';
  $actor_id        = $data['actor_id'] ?? 0;
  $actor_role      = 'admin';
  $target_id       = $data['document_id'] ?? 0;
  $target_type     = 'document';

  $changes = json_encode([
    'client_id'     => $data['client_id'] ?? null,
    'file_name'     => $data['file_name'] ?? null,
    'document_type' => $data['document_type'] ?? null,
    'status'        => $data['status'] ?? null,
    'comments'      => $data['comments'] ?? null,
    'source'        => $data['source'] ?? null
  ], JSON_UNESCAPED_UNICODE);

  $severity        = 'normal';
  $module          = 'documents';
  $session_id      = session_id();
  $ip_address      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $user_agent      = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
  $kpi_tag         = 'document_update';
  $business_impact = 'moderate';

  $stmt->bind_param(
    "sisssssssssss",
    $action_type,
    $actor_id,
    $actor_role,
    $target_id,
    $target_type,
    $changes,
    $severity,
    $module,
    $session_id,
    $ip_address,
    $user_agent,
    $kpi_tag,
    $business_impact
  );

  return $stmt->execute();
}

