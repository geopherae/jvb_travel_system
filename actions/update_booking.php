<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');
header('Content-Type: application/json');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../actions/notify.php';
require_once __DIR__ . '/../includes/log_helper.php';
use function LogHelper\generateBookingSummary;

try {
    // 🚦 Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Invalid request method.');
    }

    // 🔍 Validate booking ID
    $clientId = isset($_POST['client_id']) ? (int) $_POST['client_id'] : null;
    $bookingNumber = trim($_POST['booking_number'] ?? '');
    
    if (!$clientId || $bookingNumber === '') {
        http_response_code(400);
        throw new Exception('Missing or invalid booking data.');
    }

    // 🧑‍💼 Retrieve admin info
    $adminInfo = $_SESSION['admin'] ?? null;
    if (!$adminInfo || !isset($adminInfo['id'])) {
        http_response_code(401);
        throw new Exception('Session expired or unauthorized.');
    }
    $adminId = $adminInfo['id'];

    // 📝 Get admin name
    $stmt = $conn->prepare("SELECT first_name FROM admin_accounts WHERE id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $adminResult = $stmt->get_result();
    $admin = $adminResult->fetch_assoc();
    $stmt->close();

    if (!$admin) {
        http_response_code(404);
        throw new Exception('Admin not found.');
    }
    $adminName = $admin['first_name'];

    // 🧼 Extract and validate booking fields
    $status        = trim($_POST['status'] ?? '');
    $tripStart     = !empty($_POST['trip_date_start']) ? $_POST['trip_date_start'] : null;
    $tripEnd       = !empty($_POST['trip_date_end']) ? $_POST['trip_date_end'] : null;
    $bookingDate   = !empty($_POST['booking_date']) ? $_POST['booking_date'] : null;
    $transferTourHotline = !empty($_POST['transfer_tour_hotline']) ? trim($_POST['transfer_tour_hotline']) : null;
    $assignedRaw   = $_POST['assigned_admin_id'] ?? null;
    $assignedAdmin = is_numeric($assignedRaw) && $assignedRaw > 0 ? (int)$assignedRaw : null;
    $updatedAt     = $_POST['updated_at'] ?? date('Y-m-d H:i:s');
    $rawJson       = $_POST['itinerary_json'] ?? '[]';
    $decodedJson   = json_decode($rawJson, true);

    if (!is_array($decodedJson)) {
        http_response_code(422);
        throw new Exception('Invalid itinerary JSON.');
    }

    // Date validation
    if ($tripStart && $tripEnd && strtotime($tripEnd) < strtotime($tripStart)) {
        http_response_code(422);
        throw new Exception('Return date cannot be earlier than departure date.');
    }

    if ($bookingDate && $tripStart && strtotime($bookingDate) > strtotime($tripStart)) {
        http_response_code(422);
        throw new Exception('Booking date cannot be later than departure date.');
    }

    // 🏨 Hotel & Room Type (default to N/A if empty)
    $hotel    = trim($_POST['hotel'] ?? '');
    $roomType = trim($_POST['room_type'] ?? '');
    if ($hotel === '') $hotel = 'N/A';
    if ($roomType === '') $roomType = 'N/A';

    // ✈️ Flight Details
    $departureFlightNo   = trim($_POST['departure_flight_number'] ?? '');
    $returnFlightNo      = trim($_POST['return_flight_number'] ?? '');
    $departureRoute      = preg_replace('/[^A-Z]/i', '', trim($_POST['departure_route'] ?? ''));
    $returnRoute         = preg_replace('/[^A-Z]/i', '', trim($_POST['return_route'] ?? ''));
    $departureStart      = $_POST['departure_time_start'] ?? '';
    $departureEnd        = $_POST['departure_time_end'] ?? '';
    $returnStart         = $_POST['return_time_start'] ?? '';
    $returnEnd           = $_POST['return_time_end'] ?? '';

    // Format times nicely (e.g., 08:00 → 08:00AM)
    $formatTime = function($time) {
        if (!$time) return '';
        $timestamp = strtotime($time);
        return $timestamp !== false ? date("h:iA", $timestamp) : '';
    };

    // Build flight details only if we have meaningful data
    $departureLine = trim(sprintf(
        "%s %s %s-%s",
        $departureFlightNo,
        $departureRoute,
        $formatTime($departureStart),
        $formatTime($departureEnd)
    ));
    
    $returnLine = trim(sprintf(
        "%s %s %s-%s",
        $returnFlightNo,
        $returnRoute,
        $formatTime($returnStart),
        $formatTime($returnEnd)
    ));

    // Only include lines that have actual content
    $flightDetailsLines = array_filter([$departureLine, $returnLine], function($line) {
        return $line !== '' && $line !== '-';
    });
    
    $flightDetails = !empty($flightDetailsLines) ? implode("\n", $flightDetailsLines) : 'N/A';

    // 🆙 Check current booking status
    $stmt = $conn->prepare("SELECT status FROM clients WHERE id = ?");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentClient = $result->fetch_assoc();
    $stmt->close();

    if (!$currentClient) {
        http_response_code(404);
        throw new Exception('Client booking not found.');
    }

    $isNewlyConfirmed = ($currentClient['status'] !== 'Confirmed' && $status === 'Confirmed');

    // 🆙 Update booking (with confirmed_at if status becomes Confirmed)
    if ($isNewlyConfirmed) {
        $stmt = $conn->prepare("
            UPDATE clients SET
                booking_number = ?, status = ?,
                trip_date_start = ?, trip_date_end = ?,
                booking_date = ?, transfer_tour_hotline = ?, 
                assigned_admin_id = ?,
                hotel = ?, room_type = ?, flight_details = ?,
                confirmed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param(
            "ssssssisssi",
            $bookingNumber, $status,
            $tripStart, $tripEnd,
            $bookingDate, $transferTourHotline,
            $assignedAdmin,
            $hotel, $roomType, $flightDetails,
            $clientId
        );
    } else {
        $stmt = $conn->prepare("
            UPDATE clients SET
                booking_number = ?, status = ?,
                trip_date_start = ?, trip_date_end = ?,
                booking_date = ?, transfer_tour_hotline = ?,
                assigned_admin_id = ?,
                hotel = ?, room_type = ?, flight_details = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            "ssssssisssi",
            $bookingNumber, $status,
            $tripStart, $tripEnd,
            $bookingDate, $transferTourHotline,
            $assignedAdmin,
            $hotel, $roomType, $flightDetails,
            $clientId
        );
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update booking: ' . $stmt->error);
    }
    $stmt->close();

    // 🗺 Upsert itinerary
    $stmt = $conn->prepare("SELECT id FROM client_itinerary WHERE client_id = ?");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $stmt = $conn->prepare("UPDATE client_itinerary SET itinerary_json = ? WHERE client_id = ?");
        $stmt->bind_param("si", $rawJson, $clientId);
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO client_itinerary (client_id, itinerary_json) VALUES (?, ?)");
        $stmt->bind_param("is", $clientId, $rawJson);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update itinerary: ' . $stmt->error);
    }
    $stmt->close();

    // 🔔 Notify client
    notify([
        'recipient_type' => 'client',
        'recipient_id'   => $clientId,
        'event'          => 'booking_updated',
        'context'        => [
            'booking_number' => $bookingNumber,
            'admin_id'       => $adminId,
            'admin_name'     => $adminName
        ]
    ]);

    // 🧾 Log audit
    $auditPayload = [
        'client_id' => $clientId,
        'actor_id'  => $adminId,
        'fields_changed' => [
            'booking_number', 'status', 'trip_date_start', 'trip_date_end',
            'booking_date', 'transfer_tour_hotline', 'assigned_admin_id', 
            'hotel', 'room_type', 'flight_details'
        ],
        'summary'   => generateBookingSummary([
            'booking_number'        => $bookingNumber,
            'status'                => $status,
            'trip_date_start'       => $tripStart,
            'trip_date_end'         => $tripEnd,
            'booking_date'          => $bookingDate,
            'transfer_tour_hotline' => $transferTourHotline,
            'assigned_admin_id'     => $assignedAdmin,
            'hotel'                 => $hotel,
            'room_type'             => $roomType,
            'flight_details'        => $flightDetails
        ]),
        'source' => 'update_booking.php'
    ];

    $auditJson = json_encode($auditPayload, JSON_UNESCAPED_UNICODE);
    if ($auditJson === false) {
        throw new Exception('Failed to encode audit payload.');
    }

    $stmt = $conn->prepare("
        INSERT INTO audit_logs (
            action_type, actor_id, actor_role,
            target_id, target_type, changes,
            severity, module, timestamp,
            session_id, ip_address, user_agent,
            kpi_tag, business_impact
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $actionType     = 'update_booking';
    $actorRole      = 'admin';
    $targetType     = 'client';
    $severity       = 'normal';
    $module         = 'booking';
    $sessionId      = session_id();
    $ipAddress      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent      = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $kpiTag         = 'booking_update';
    $businessImpact = 'moderate';

    $stmt->bind_param(
        "sissssssssssss",
        $actionType,
        $adminId,
        $actorRole,
        $clientId,
        $targetType,
        $auditJson,
        $severity,
        $module,
        $updatedAt,
        $sessionId,
        $ipAddress,
        $userAgent,
        $kpiTag,
        $businessImpact
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to log audit: ' . $stmt->error);
    }
    $stmt->close();

    // ✅ Toast trigger
    $_SESSION['modal_status'] = 'booking_update_success';
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => "Booking updated successfully by $adminName."
    ]);

} catch (Exception $e) {
    error_log("Booking update error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>