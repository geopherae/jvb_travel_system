<?php
function updateClientStatus(int $clientId, mysqli $conn): ?array {
    // 🔍 Fetch uploaded files
    $stmt = $conn->prepare("
        SELECT file_name, document_status, document_type 
        FROM uploaded_files 
        WHERE client_id = ?
    ");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $files = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $hasFiles = count($files) > 0;

    // 📋 Fetch client info + package visa requirement
    $clientStmt = $conn->prepare("
        SELECT c.assigned_package_id, c.trip_date_start, c.trip_date_end, t.requires_visa
        FROM clients c
        LEFT JOIN tour_packages t ON c.assigned_package_id = t.id
        WHERE c.id = ?
    ");
    $clientStmt->bind_param("i", $clientId);
    $clientStmt->execute();
    $clientData = $clientStmt->get_result()->fetch_assoc();
    $clientStmt->close();

    if (!$clientData) {
        return null; // Client not found
    }

    $assignedPackageId = $clientData['assigned_package_id'] ?? null;
    $tripStart = $clientData['trip_date_start'] ?? null;
    $tripEnd   = $clientData['trip_date_end'] ?? null;
    $requiresVisa = (int)($clientData['requires_visa'] ?? 0);
    $today     = date('Y-m-d');

    $newStatus = "Awaiting Docs";

    // 🧠 Document-based status logic
    if ($hasFiles) {
        $statuses = array_column($files, 'document_status');
        $uniqueStatuses = array_unique($statuses);

        $allPending  = count($uniqueStatuses) === 1 && $uniqueStatuses[0] === "Pending";
        $allRejected = count($uniqueStatuses) === 1 && $uniqueStatuses[0] === "Rejected";
        $allApproved = count($uniqueStatuses) === 1 && $uniqueStatuses[0] === "Approved";

        $hasApprovedID = false;
        $hasRejectedID = false;

        foreach ($files as $file) {
            $type = strtolower(trim($file['document_type']));
            $status = $file['document_status'];

            if (in_array($type, ['passport', 'id', 'identification card'])) {
                if ($status === "Approved") {
                    $hasApprovedID = true;
                } elseif ($status === "Rejected") {
                    $hasRejectedID = true;
                }
            }
        }

        if ($hasApprovedID || $allApproved) {
            $newStatus = "Confirmed";
        } elseif ($allRejected || $hasRejectedID) {
            $newStatus = "Resubmit Files";
        } elseif ($allPending) {
            $newStatus = "Under Review";
        } else {
            $newStatus = "Under Review";
        }
    }

    // 📦 No assigned package
    if (empty($assignedPackageId)) {
        $newStatus = "No Assigned Package";
    }

// 📅 Trip override
if (!empty($tripStart) && !empty($tripEnd)) {
  if ($today >= $tripStart && $today <= $tripEnd) {
    $newStatus = "Trip Ongoing";
  } elseif ($today > $tripEnd) {
    $newStatus = "Trip Completed";

    // 🧠 Check if trip_completion survey is already pending
    $surveyStmt = $conn->prepare("
      SELECT COUNT(*) FROM user_survey_status 
      WHERE user_id = ? AND user_role = 'client' 
      AND survey_type = 'trip_complete' AND is_completed = 0
    ");
    $surveyStmt->bind_param("i", $clientId);
    $surveyStmt->execute();
    $surveyStmt->bind_result($pendingCount);
    $surveyStmt->fetch();
    $surveyStmt->close();

    // 🧠 Trigger survey only if none is pending
    if ($pendingCount === 0) {
      $createdAt = date('Y-m-d H:i:s');
      $insertStmt = $conn->prepare("
        INSERT INTO user_survey_status 
        (user_id, user_role, survey_type, is_completed, created_at) 
        VALUES (?, 'client', 'trip_complete', 0, ?)
      ");
      $insertStmt->bind_param("is", $clientId, $createdAt);
      $insertStmt->execute();
      $insertStmt->close();
    }
  }
}

    // 📋 Compare and update status
    $check = $conn->prepare("SELECT status FROM clients WHERE id = ?");
    $check->bind_param("i", $clientId);
    $check->execute();
    $currentStatus = $check->get_result()->fetch_column();
    $check->close();

    if (!$currentStatus) {
        $currentStatus = "—";
    }

    if (strtolower($currentStatus) !== strtolower($newStatus)) {
        // 🧠 Check if status is changing to "Confirmed"
        $isConfirmed = (strtolower($newStatus) === 'confirmed');
        
        if ($isConfirmed) {
            $update = $conn->prepare("UPDATE clients SET status = ?, confirmed_at = NOW() WHERE id = ?");
        } else {
            $update = $conn->prepare("UPDATE clients SET status = ? WHERE id = ?");
        }
        $update->bind_param("si", $newStatus, $clientId);
        $update->execute();
        $update->close();

        // 🧠 Check if status changed to "Confirmed" and create survey if needed
        if (strtolower($newStatus) === 'confirmed') {
            $surveyStmt = $conn->prepare("
                SELECT COUNT(*) FROM user_survey_status 
                WHERE user_id = ? AND user_role = 'client' 
                AND survey_type = 'status_confirmed' AND is_completed = 0
            ");
            $surveyStmt->bind_param("i", $clientId);
            $surveyStmt->execute();
            $surveyStmt->bind_result($pendingCount);
            $surveyStmt->fetch();
            $surveyStmt->close();

            // 🧠 Trigger survey only if none is pending
            if ($pendingCount === 0) {
                $createdAt = date('Y-m-d H:i:s');
                $insertStmt = $conn->prepare("
                    INSERT INTO user_survey_status 
                    (user_id, user_role, survey_type, is_completed, created_at) 
                    VALUES (?, 'client', 'status_confirmed', 0, ?)
                ");
                $insertStmt->bind_param("is", $clientId, $createdAt);
                $insertStmt->execute();
                $insertStmt->close();
            }
        }

        return [
            "clientId" => $clientId,
            "from" => $currentStatus,
            "to" => $newStatus
        ];
    }

    return null;
}
?>