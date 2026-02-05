<?php
include_once __DIR__ . '/../admin/admin_session_check.php';
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=UTF-8');

$isAdmin = true;
$isCleanReload = true;

$visaClientQuery = "
  SELECT 
    c.id, 
    c.full_name, 
    c.client_profile_photo,
    vp.country AS visa_package_country,
    DATE_FORMAT(va.created_at, '%b %e, %Y') AS applied_date,
    IFNULL(va.status, 'Awaiting Docs') AS visa_status
  FROM clients c
  LEFT JOIN client_visa_applications va ON va.id = (
    SELECT id FROM client_visa_applications
    WHERE client_id = c.id
    ORDER BY created_at DESC
    LIMIT 1
  )
  LEFT JOIN visa_packages vp ON va.visa_package_id = vp.id
  WHERE c.processing_type IN ('visa', 'both')
  ORDER BY va.created_at DESC, c.full_name ASC
";

$visaClientsResult = $conn->query($visaClientQuery);
$visaClients = $visaClientsResult ? $visaClientsResult->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/../components/visa-clients-table.php';
