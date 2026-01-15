<?php
require_once __DIR__ . '/../includes/client_session_check.php';
require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('client');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/status-helpers.php';
include __DIR__ . '/../components/status_alert.php';


// 🚫 Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 🧑 Get client ID from session
$client_id = $_SESSION['client_id'] ?? null;
if (!$client_id || !is_numeric($client_id)) {
  http_response_code(403);
  exit('Invalid session.');
}

// 🧠 Fetch pending survey and template_id
$surveyType = null;
$template_id = null;

$surveyQuery = $conn->prepare("
  SELECT survey_type, id
  FROM user_survey_status
  WHERE user_id = ? AND user_role = 'client' AND is_completed = 0 AND created_at <= NOW()
  ORDER BY FIELD(survey_type, 'first_login', 'status_confirmed', 'trip_complete') LIMIT 1
");
$surveyQuery->bind_param("i", $client_id);
$surveyQuery->execute();
$surveyQuery->bind_result($surveyType, $template_id);
$surveyQuery->fetch();
$surveyQuery->close();

if ($surveyType && $template_id) {
  if ($surveyType === 'first_login') {
    $_SESSION['show_client_survey_modal'] = true;
  } elseif ($surveyType === 'status_confirmed') {
    $_SESSION['show_confirmed_status_survey_modal'] = true;
  } elseif ($surveyType === 'trip_complete') {
    $_SESSION['show_trip_completion_survey'] = true;
  }
  $_SESSION['survey_type'] = $surveyType;
  $_SESSION['id'] = $template_id;
}

// 🧾 Fetch core client + package data
$stmt = $conn->prepare("
  SELECT  
    c.full_name, c.status, c.trip_date_start, c.trip_date_end,
    c.booking_date, c.booking_number, c.assigned_package_id,
    c.left_review,
    t.id AS package_id, t.package_name, t.package_description, t.price,
    t.day_duration, t.night_duration, t.tour_cover_image,
    t.inclusions_json, t.origin, t.destination,
    t.checklist_template_id
  FROM clients c
  LEFT JOIN tour_packages t ON c.assigned_package_id = t.id
  WHERE c.id = ?
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$stmt->close();

$status = $client['status'] ?? 'Unknown';

// 📅 Format trip dates
$tripDateRangeDisplay = '<span class="text-gray-400 italic">Unspecified</span>';
$start = $client['trip_date_start'] ?? null;
$end   = $client['trip_date_end'] ?? null;

if ($start && $end) {
  try {
    $startDate = new DateTime($start);
    $endDate   = new DateTime($end);
    $tripDateRangeDisplay = $startDate->format('M d') . " to " . $endDate->format('M d Y');
  } catch (Exception $e) {
    error_log("Date formatting error: " . $e->getMessage());
  }
}

// 📋 Fetch itinerary
$itineraryDays = [];
$itinerary_stmt = $conn->prepare("
  SELECT itinerary_json  
  FROM client_itinerary  
  WHERE client_id = ?  
  ORDER BY updated_at DESC  
  LIMIT 1
");
$itinerary_stmt->bind_param("i", $client_id);
$itinerary_stmt->execute();
$itinerary_result = $itinerary_stmt->get_result()->fetch_assoc();
$itinerary_stmt->close();

$parsedItinerary = json_decode($itinerary_result['itinerary_json'] ?? '[]', true);
if (json_last_error() === JSON_ERROR_NONE && is_array($parsedItinerary)) {
  foreach ($parsedItinerary as $day) {
    $itineraryDays[] = [
      'day_number' => $day['day_number'] ?? null,
      'day_title'  => $day['day_title']  ?? '',
      'activities' => $day['activities'] ?? []
    ];
  }
}

// 🧮 Duration fallback
$total_days   = count($itineraryDays);
$total_nights = max(0, $total_days - 1);

// 📁 Fetch uploaded documents
$documents = [];
$docsStmt = $conn->prepare("
  SELECT id, file_name, file_path, document_type, document_status, uploaded_at, approved_at, admin_comments, status_updated_by  
  FROM uploaded_files WHERE client_id = ?
");
$docsStmt->bind_param("i", $client_id);
$docsStmt->execute();
$docResults = $docsStmt->get_result();
$docsStmt->close();

while ($doc = $docResults->fetch_assoc()) {
  $documents[] = [
    'id'                => $doc['id'],
    'file_name'         => $doc['file_name'],
    'file_path'         => $doc['file_path'],
    'document_type'     => $doc['document_type'],
    'document_status'   => $doc['document_status'] ?? 'Pending',
    'uploaded_at'       => $doc['uploaded_at'],
    'approved_at'       => $doc['approved_at'] ?? null,
    'admin_comments'    => $doc['admin_comments'] ?? 'No comments available.',
    'status_updated_by' => $doc['status_updated_by'] ?? null,
    'view_url'          => '/uploads/client_' . $client_id . '/' . rawurlencode($doc['file_name']),
    'delete_url'        => '/client/delete_document.php?id=' . urlencode($doc['id']),
  ];
}

// 📞 Agent contact info
$agentContact = [
  'name'      => 'Travel Agent',
  'phone'     => null,
  'messenger' => null
];

$agent_stmt = $conn->prepare("
  SELECT first_name, phone_number, messenger_link  
  FROM admin_accounts  
  WHERE is_primary_contact = 1  
  LIMIT 1
");
$agent_stmt->execute();
$agent_result = $agent_stmt->get_result()->fetch_assoc();
$agent_stmt->close();

if ($agent_result) {
  $agentContact = [
    'name'      => $agent_result['first_name'] ?? 'Travel Agent',
    'phone'     => $agent_result['phone_number'] ?? null,
    'messenger' => $agent_result['messenger_link'] ?? null
  ];
}
?>

<!DOCTYPE html>
<html lang="en" class="bg-gray-50">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Client Dashboard</title>
  <meta name="robots" content="noindex, nofollow" />
  <meta name="description" content="Your travel dashboard with uploaded documents and assigned package." />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <style>[x-cloak] { display: none !important; }</style>
</head>

<body x-data="{
  sidebarOpen: false,
  showFileDiv: false,
  filePath: '',
  fileName: '',
  fileType: '',
  fileMimeType: '',
  fileStatus: '',
  adminComments: '',
  uploadedAt: '',
  approvedAt: '',
  statusUpdatedBy: '',
  zoomLevel: 1,
  originalFileName: '',
  originalFileType: '',
  originalFileStatus: '',
  originalAdminComments: '',
  showAgentModal: false,
  showAssignedPackage: false,
  openFileModal(id, path, name, type, mimeType, status, comments, uploaded, approved, updatedBy) {
    if (!path) {
      console.error('Invalid file path');
      return;
    }
    const base = window.location.origin + '/jvb_travel_system/';
    const cleanPath = path.startsWith('http') ? path : base + path.replace(/^\/+/, '');
    this.filePath = cleanPath;
    this.fileName = name;
    this.fileType = type;
    this.fileMimeType = mimeType;
    this.fileStatus = status;
    this.adminComments = comments || 'No comments available.';
    this.uploadedAt = uploaded;
    this.approvedAt = approved;
    this.statusUpdatedBy = updatedBy;
    this.zoomLevel = 1;
    this.showFileDiv = true;
    this.originalFileName = name;
    this.originalFileType = type;
    this.originalFileStatus = status;
    this.originalAdminComments = comments || 'No comments available.';
  },
  closeFileModal() {
    this.showFileDiv = false;
    this.filePath = '';
    this.fileName = '';
    this.fileType = '';
  },
  get hasFileChanged() {
    return this.fileName !== this.originalFileName;
  }
}" class="bg-gray-50 font-poppins text-gray-800">

  <!-- Mobile Toggle -->
  <button @click="sidebarOpen = !sidebarOpen" class="p-3 md:hidden absolute top-4 left-4 z-30 bg-primary text-white rounded">
    ☰
  </button>

<!-- Sidebar -->
<?php if (file_exists('../components/sidebar.php')) include '../components/sidebar.php'; ?>

<!-- Right Sidebar Panel -->
<?php
  $isAdmin = false;
  if (file_exists('../components/right-panel.php')) include '../components/right-panel.php';
?>

<main class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-6 flex flex-col relative z-0">

  <!-- Scrollable Content Container -->
  <div class="flex-1 overflow-y-auto space-y-6">

  <h2 class="text-xl font-bold">Client Dashboard</h2>

  <!-- Welcome Banner -->
  <?php
    $isAdmin = false;
    if (file_exists(__DIR__ . '/../components/welcome-card.php')) include __DIR__ . '/../components/welcome-card.php';
  ?>

  <!-- Uploaded Documents Table -->
  <?php if (file_exists('../components/documents-table.php')) include '../components/documents-table.php'; ?>

  </div>
</main>

<!-- View Assigned Package -->
<?php if (file_exists('view_assigned_package.php')) include 'view_assigned_package.php'; ?>

<!-- Disclaimer Modal -->
<?php
if (!empty($_SESSION['show_disclaimer'])) {
  echo '<div id="disclaimerWrapper">';
  if (file_exists('../components/disclaimer_popup.php')) include '../components/disclaimer_popup.php';
  echo '</div>';
  unset($_SESSION['show_disclaimer']);
}
?>

<!-- First-Time Survey Modal -->
<?php if (!empty($_SESSION['show_client_survey_modal'])): ?>
  <div id="surveyWrapper" style="display: none;">
    <?php if (file_exists('../components/client_first_time_survey_modal.php')) include '../components/client_first_time_survey_modal.php'; ?>
  </div>
<?php endif; ?>

<!-- Confirmed Status Survey Modal -->
<?php if (!empty($_SESSION['show_confirmed_status_survey_modal'])): ?>
  <div id="confirmedStatusSurveyWrapper" style="display: none;">
    <?php if (file_exists('../components/client_confirmed_status_survey_modal.php')) include '../components/client_confirmed_status_survey_modal.php'; ?>
  </div>
<?php endif; ?>

<!-- Trip Completion Survey Modal -->
<?php if (!empty($_SESSION['show_trip_completion_survey'])): ?>
  <div id="tripSurveyWrapper" style="display: none;">
    <?php if (file_exists('../components/client_trip_completion_survey_modal.php')) include '../components/client_trip_completion_survey_modal.php'; ?>
  </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const disclaimerBtn = document.querySelector('.close-disclaimer');
  const firstLoginWrapper = document.getElementById('surveyWrapper');
  const confirmedStatusSurveyWrapper = document.getElementById('confirmedStatusSurveyWrapper');
  const tripSurveyWrapper = document.getElementById('tripSurveyWrapper');

  if (disclaimerBtn) {
    disclaimerBtn.addEventListener('click', function () {
      setTimeout(() => {
        if (firstLoginWrapper) firstLoginWrapper.style.display = 'flex';
        if (confirmedStatusSurveyWrapper) confirmedStatusSurveyWrapper.style.display = 'flex';
        if (tripSurveyWrapper) tripSurveyWrapper.style.display = 'flex';
      }, 2000);
    });
  } else {
    // If no disclaimer, show any pending survey directly
    if (firstLoginWrapper) firstLoginWrapper.style.display = 'flex';
    if (confirmedStatusSurveyWrapper) confirmedStatusSurveyWrapper.style.display = 'flex';
    if (tripSurveyWrapper) tripSurveyWrapper.style.display = 'flex';
  }
});

// Initialize Alpine store for review modal
document.addEventListener('alpine:init', () => {
  Alpine.store('reviewModal', {
    show: false
  });
});
</script>

</body>
</html>
