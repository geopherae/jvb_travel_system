<?php
// Fetch distinct roles and survey types
$rolesResult = $conn->query("SELECT DISTINCT user_role FROM user_survey_status");
$typesResult = $conn->query("SELECT DISTINCT survey_type FROM user_survey_status");

// Get selected filters
$selectedRole = $_GET['role'] ?? '';
$selectedType = $_GET['type'] ?? '';
$selectedSurveyId = $_GET['survey_id'] ?? '';

// Fetch surveys based on selected role/type
$surveyListQuery = "
  SELECT s.id, s.user_id, s.user_role, s.survey_type, s.created_at,
         CASE 
           WHEN s.user_role = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
           WHEN s.user_role = 'client' THEN c.full_name
           ELSE 'Unknown'
         END AS user_name
  FROM user_survey_status s
  LEFT JOIN admin_accounts a ON s.user_id = a.id AND s.user_role = 'admin'
  LEFT JOIN clients c ON s.user_id = c.id AND s.user_role = 'client'
  WHERE 1 = 1
";

$params = [];
$types = '';
if ($selectedRole !== '') {
  $surveyListQuery .= " AND s.user_role = ?";
  $params[] = $selectedRole;
  $types .= 's';
}
if ($selectedType !== '') {
  $surveyListQuery .= " AND s.survey_type = ?";
  $params[] = $selectedType;
  $types .= 's';
}

$surveyListQuery .= " ORDER BY s.created_at DESC";
$listStmt = $conn->prepare($surveyListQuery);
if (!empty($params)) {
  $listStmt->bind_param($types, ...$params);
}
$listStmt->execute();
$surveyList = $listStmt->get_result();
?>

<div class="bg-white rounded-lg shadow-md p-6 w-full max-w-6xl mx-auto">
  <h2 class="text-xl font-semibold text-gray-800 mb-6">Survey Viewer</h2>

  <!-- Filter Controls -->
  <form method="GET" id="surveyFilterForm" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div>
      <label for="roleSelect" class="block text-sm font-medium text-gray-700 mb-1">User Role</label>
      <select name="role" id="roleSelect" class="w-full border-gray-300 rounded-md shadow-sm text-sm px-3 py-2"
              onchange="document.getElementById('surveyFilterForm').submit()">
        <option value="">All Roles</option>
        <?php while ($r = $rolesResult->fetch_assoc()): ?>
          <option value="<?= $r['user_role'] ?>" <?= $selectedRole === $r['user_role'] ? 'selected' : '' ?>>
            <?= ucfirst($r['user_role']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div>
      <label for="typeSelect" class="block text-sm font-medium text-gray-700 mb-1">Survey Type</label>
      <select name="type" id="typeSelect" class="w-full border-gray-300 rounded-md shadow-sm text-sm px-3 py-2"
              onchange="document.getElementById('surveyFilterForm').submit()">
        <option value="">All Survey Types</option>
        <?php while ($t = $typesResult->fetch_assoc()): ?>
          <?php
            $label = match($t['survey_type']) {
              'admin_weekly_survey' => 'Weekly Survey',
              'trip_completion'     => 'Client Trip Completed',
              'first_login'         => 'First Login',
              default               => ucfirst(str_replace('_', ' ', $t['survey_type']))
            };
          ?>
          <option value="<?= $t['survey_type'] ?>" <?= $selectedType === $t['survey_type'] ? 'selected' : '' ?>>
            <?= $label ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div>
      <label for="surveySelect" class="block text-sm font-medium text-gray-700 mb-1">Survey Instance</label>
      <select name="survey_id" id="surveySelect" class="w-full border-gray-300 rounded-md shadow-sm text-sm px-3 py-2">
        <option value="">Select Survey</option>
        <?php while ($row = $surveyList->fetch_assoc()): ?>
          <option value="<?= $row['id'] ?>" <?= $selectedSurveyId == $row['id'] ? 'selected' : '' ?>>
            <?= $row['user_name'] ?> • <?= date('M d, Y', strtotime($row['created_at'])) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div class="flex items-end">
      <button type="submit"
              class="min-w-[120px] max-w-[160px] bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700 transition">
        View
      </button>
    </div>
  </form>

  <?php $listStmt->close(); ?>

  <!-- Prompt or Survey Card -->
  <?php if ($selectedSurveyId === ''): ?>
    <div class="bg-gray-50 border border-gray-200 rounded-md p-6 text-center text-sm text-gray-600">
      <p>Select a role and survey type above to filter available surveys. Then choose a specific survey to view its full report.</p>
    </div>
  <?php else: ?>
    <?php
      $detailStmt = $conn->prepare("
        SELECT s.*, 
               CASE 
                 WHEN s.user_role = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                 WHEN s.user_role = 'client' THEN c.full_name
                 ELSE 'Unknown'
               END AS user_name
        FROM user_survey_status s
        LEFT JOIN admin_accounts a ON s.user_id = a.id AND s.user_role = 'admin'
        LEFT JOIN clients c ON s.user_id = c.id AND s.user_role = 'client'
        WHERE s.id = ?
      ");
      $detailStmt->bind_param("i", $selectedSurveyId);
      $detailStmt->execute();
      $survey = $detailStmt->get_result()->fetch_assoc();
      $detailStmt->close();

      $payload = json_decode($survey['response_payload'], true);
      $responses = $payload['responses'] ?? [];
      $surveyLabel = match($survey['survey_type']) {
        'admin_weekly_survey' => 'Weekly Survey',
        'trip_completion'     => 'Client Trip Completed',
        'first_login'         => 'First Login',
        default               => ucfirst(str_replace('_', ' ', $survey['survey_type']))
      };
    ?>

    <div class="border-t pt-6">
      <h3 class="text-lg font-semibold text-gray-800 mb-2"><?= $surveyLabel ?></h3>
      <p class="text-sm text-gray-600 mb-4">
        <strong><?= htmlspecialchars($survey['user_name']) ?></strong> (<?= ucfirst($survey['user_role']) ?>)<br>
        Created: <?= htmlspecialchars($survey['created_at']) ?> |
        <?= $survey['is_completed'] ? 'Completed: ' . htmlspecialchars($survey['completed_at']) : 'Pending' ?>
      </p>

      <?php if (!empty($responses)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <?php foreach ($responses as $question => $answer): ?>
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4">
              <div class="text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($question) ?></div>
              <div class="text-sm text-gray-800"><?= $answer !== '' ? htmlspecialchars($answer) : '—' ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-sm text-gray-500 italic">No responses recorded.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>