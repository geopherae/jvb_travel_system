<?php
session_start();

// ✅ Load dependencies
require_once __DIR__ . '../../actions/db.php';
require_once __DIR__ . '../../includes/auth.php';
require_once __DIR__ . '../../includes/helpers.php';

use function Auth\guard;
guard('admin');

// ✅ Validate client ID
$client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : null;
if (!$client_id) {
  echo "Client not specified.";
  exit();
}

// ✅ Fetch client details
$client_stmt = $conn->prepare("
  SELECT 
    id,
    full_name,
    email,
    phone_number,
    address,
    client_profile_photo,
    passport_number,
    passport_expiry,
    processing_type,
    trip_date_start,
    trip_date_end,
    assigned_admin_id,
    access_code,
    visa_application_id,
    created_at,
    status
  FROM clients
  WHERE id = ?
");
$client_stmt->bind_param("i", $client_id);
$client_stmt->execute();
$client = $client_stmt->get_result()->fetch_assoc();

if (!$client) {
  echo "Client not found.";
  exit();
}

// ✅ Fetch all visa applications for this client
$visa_apps_stmt = $conn->prepare("
  SELECT 
    cva.id,
    cva.visa_package_id,
    cva.application_mode,
    cva.created_at,
    cva.status,
    vp.country,
    vp.processing_days
  FROM client_visa_applications cva
  JOIN visa_packages vp ON cva.visa_package_id = vp.id
  WHERE cva.client_id = ?
  ORDER BY cva.created_at DESC
");
$visa_apps_stmt->bind_param("i", $client_id);
$visa_apps_stmt->execute();
$visa_applications = $visa_apps_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ✅ For each visa application, fetch companions (if group application)
foreach ($visa_applications as &$app) {
  $companions_stmt = $conn->prepare("
    SELECT 
      id,
      full_name,
      relationship,
      applicant_status,
      email,
      phone_number,
      passport_number,
      passport_expiry,
      access_code,
      created_at
    FROM client_visa_companions
    WHERE visa_application_id = ?
    ORDER BY created_at ASC
  ");
  $companions_stmt->bind_param("i", $app['id']);
  $companions_stmt->execute();
  $app['companions'] = $companions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $companions_stmt->close();
}

// ✅ Default image
$default_avatar = '../images/default_client_profile.png';
$profileImg = !empty($client['client_profile_photo'])
  ? '../uploads/client_profiles/' . rawurlencode($client['client_profile_photo'])
  : $default_avatar;

// ✅ Status colors for new auto-calculated statuses
$statusColors = [
  'Awaiting Docs' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
  'Rejected' => 'bg-red-100 text-red-700 border-red-300',
  'Complete' => 'bg-green-100 text-green-700 border-green-300',
];

// ✅ Build applicant list for dropdown/store (lead + companions)
$applicantsList = [];
$applicantsList[] = [
  'name' => $client['full_name'] ?? 'Unnamed Client',
  'relationship' => 'Lead Applicant',
];

if (!empty($visa_applications) && !empty($visa_applications[0]['companions'])) {
  foreach ($visa_applications[0]['companions'] as $companion) {
    $applicantsList[] = [
      'name' => $companion['full_name'] ?? 'Unnamed',
      'relationship' => $companion['relationship'] ?? 'Companion',
    ];
  }
}

$hasGroupApplicants = count($applicantsList) > 1;
// Safe JSON for Alpine store (escape special chars to prevent script injection)
$applicantsJson = json_encode(
  $applicantsList,
  JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>View Client - Visa Applications</title>
  <?php include __DIR__ . '/../components/favicon_links.php'; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.store('modals', {
        clientId: null,
        reassignVisa: false,
      });

      Alpine.store('applicantSelector', {
        currentIdx: 0,
        totalApplicants: <?= count($applicantsList) ?>,
        applicants: <?= $applicantsJson ?>
      });
    });
  </script>
  <style>[x-cloak] { display: none !important; }</style>
</head>

<body class="text-gray-800 font-sans" x-data="{ sidebarOpen: false }">

<!-- Includes -->
<?php $isAdmin = true; include '../components/admin_sidebar.php'; ?>
<?php $isAdmin = true; include '../components/right-panel.php'; ?>
<?php include '../components/status_alert.php'; ?>
<?php include '../components/reassign-visa-modal.php'; ?>

<!-- Mobile Toggle -->
<button @click="sidebarOpen = !sidebarOpen" class="p-3 md:hidden absolute top-4 left-4 z-20 bg-primary text-white rounded">>
  ☰
</button>

<main class="ml-0 lg:ml-64 lg:mr-80 min-h-screen overflow-y-auto p-4 sm:p-6 space-y-6 sm:space-y-8 relative z-0">

  <!-- 🧭 Page Title -->
  <h2 class="text-xl sm:text-2xl font-bold">Client Visa Applications</h2>

  <div class="space-y-6">

  <!-- Two-column grid for cards (responsive: 1 col mobile, 2 cols on medium+ screens) -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-2 items-start">
    <!-- 👤 Client Info Dashboard -->
    <div class="h-full">
      <?php 
        // Prepare data for the new component
        $visa_application = !empty($visa_applications) ? [
          'application_mode' => $visa_applications[0]['application_mode'] ?? 'individual',
          'country' => $visa_applications[0]['country'] ?? 'Unknown',
          'processing_days' => $visa_applications[0]['processing_days'] ?? 0,
          'applicant_status' => $visa_applications[0]['applicant_status'] ?? 'draft',
        ] : [
          'application_mode' => 'individual',
          'country' => 'Unknown',
          'processing_days' => 0,
          'applicant_status' => 'draft',
        ];
        $companions = !empty($visa_applications) ? ($visa_applications[0]['companions'] ?? []) : [];
        $isAdmin = true;
        include __DIR__ . '/../components/visa_client_info_dashboard.php';
      ?>


    <!-- 🛂 Visa Package Card -->
    <div class="h-full">
      <?php include __DIR__ . '/../components/visa-package-card.php'; ?>
    </div>
  </div>

    <!-- Documents -->
    <?php 
      if (!empty($visa_applications)) {
        $app = $visa_applications[0];
        $visa_application_id = $app['id'];
        $application_mode = $app['application_mode'];
        $visa_application_status = $app['status']; // Pass status to component
        include '../components/visa-document-table.php'; 
      }
    ?>

  </div>

</main>

</body>
</html>
