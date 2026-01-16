<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['client_id']) || !isset($_SESSION['show_client_survey_modal']) || $_SESSION['show_client_survey_modal'] !== true) {
  return;
}

require_once '../actions/db.php';

$client_id = $_SESSION['client_id'];

$stmt = $conn->prepare("
  SELECT id  
  FROM user_survey_status  
  WHERE user_id = ?  
  AND user_role = 'client'  
  AND survey_type = 'first_login'  
  AND is_completed = 0  
  AND created_at <= NOW()
  LIMIT 1
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
  $_SESSION['template_id'] = $row['id'];
}

$stmt->close();
?>

<div x-cloak id="clientSurveyModal" 
     x-data="{ step: 1, totalSteps: 5, next() { if (this.step < this.totalSteps) this.step++ }, prev() { if (this.step > 1) this.step-- } }" 
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm px-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-xl p-6 space-y-6 relative">

    <!-- Header -->
    <div class="text-center space-y-2">
      <h2 class="text-xl font-semibold text-sky-700">We’d Love Your Thoughts 💬</h2>
      <p class="text-sm text-gray-600">
        This short survey is part of an ongoing research and usability study. Your insights help us improve with better tools and experiences.
      </p>
    </div>

    <!-- Progress Bar -->
    <div class="flex items-center justify-between gap-2 px-2">
      <div class="flex-1 h-2 rounded-full bg-gray-200">
        <div class="h-2 rounded-full bg-sky-500 transition-all duration-300" 
             :style="`width: ${(step / totalSteps) * 100}%`"></div>
      </div>
      <span class="text-xs text-gray-500" x-text="`Question ${step} of ${totalSteps}`"></span>
    </div>

    <form action="../actions/submit_client_first_time_survey.php" method="POST" class="space-y-5" id="clientSurveyForm" novalidate onsubmit="enableClientFirstTimeSurveyFields()">
      <input type="hidden" name="survey_type" value="first_login">
      <input type="hidden" name="client_id" value="<?= htmlspecialchars($_SESSION['client_id'] ?? '') ?>">
      <input type="hidden" name="template_id" value="<?= htmlspecialchars($_SESSION['template_id'] ?? '') ?>">

      <!-- Step 1: Perceived Usefulness -->
      <div x-show="step === 1" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          How useful do you expect this portal to be for viewing your travel plans and documents?
        </label>
        <select name="q1_expected_usefulness" :disabled="step !== 1" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" required>
          <option value="5">Extremely useful</option>
          <option value="4">Useful</option>
          <option value="3">Neutral</option>
          <option value="2">Not very useful</option>
          <option value="1">Not useful at all</option>
        </select>
      </div>

      <!-- Step 2: Ease of Navigation -->
      <div x-show="step === 2" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          From your first impression, how easy does the portal seem to navigate?
        </label>
        <select name="q2_expected_ease" :disabled="step !== 2" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" required>
          <option value="5">Very easy</option>
          <option value="4">Fairly easy</option>
          <option value="3">Neutral / unsure</option>
          <option value="2">Somewhat difficult</option>
          <option value="1">Very difficult</option>
        </select>
      </div>

      <!-- Step 3: Confidence in Uploading -->
      <div x-show="step === 3" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          How confident are you in uploading your travel documents through this portal?
        </label>
        <select name="q3_upload_confidence" :disabled="step !== 3" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" required>
          <option value="5">Very confident</option>
          <option value="4">Confident</option>
          <option value="3">Neutral / unsure</option>
          <option value="2">Not very confident</option>
          <option value="1">Not confident at all</option>
        </select>
      </div>

      <!-- Step 4: Task Simplicity -->
      <div x-show="step === 4" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          How simple do you expect it will be to complete common tasks (like booking or uploading documents) in this portal?
        </label>
        <select name="q4_task_simplicity" :disabled="step !== 4" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" required>
          <option value="5">Very simple</option>
          <option value="4">Fairly simple</option>
          <option value="3">Neutral / unsure</option>
          <option value="2">Somewhat complicated</option>
          <option value="1">Very complicated</option>
        </select>
      </div>

      <!-- Step 5: Feature Interest -->
      <div x-show="step === 5" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          How interested are you in trying the portal’s features (e.g., itinerary viewer, photo uploads, travel reviews)?
        </label>
        <select name="q5_feature_interest" :disabled="step !== 5" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" required>
          <option value="5">Extremely interested</option>
          <option value="4">Interested</option>
          <option value="3">Neutral</option>
          <option value="2">Not very interested</option>
          <option value="1">Not interested at all</option>
        </select>
      </div>

      <!-- Navigation Buttons -->
      <div class="flex justify-between items-center pt-4">
        <button type="button" x-show="step > 1" @click="prev" class="text-sm text-gray-500 hover:underline">← Back</button>

        <form action="../actions/submit_client_first_time_survey.php" method="POST" style="margin: 0;">
          <input type="hidden" name="survey_type" value="first_login">
          <input type="hidden" name="client_id" value="<?= htmlspecialchars($_SESSION['client_id'] ?? '') ?>">
          <input type="hidden" name="template_id" value="<?= htmlspecialchars($_SESSION['template_id'] ?? '') ?>">
          <input type="hidden" name="skip_survey" value="1">
          <button type="submit" class="text-xs text-gray-400 hover:text-gray-600">
            Skip survey
          </button>
        </form>

        <template x-if="step < totalSteps">
          <button type="button" @click="next" class="bg-sky-600 text-white px-4 py-2 rounded hover:bg-sky-700 transition">
            Next →
          </button>
        </template>

        <template x-if="step === totalSteps">
          <button type="submit" class="bg-sky-600 text-white px-4 py-2 rounded hover:bg-sky-700 transition">
            Submit
          </button>
        </template>
      </div>
    </form>
  </div>
</div>

<script>
  function enableClientFirstTimeSurveyFields() {
    try {
      var form = document.getElementById('clientSurveyForm');
      if (!form) return;
      var fields = form.querySelectorAll('select, textarea, input');
      fields.forEach(function(el) {
        if (el.disabled) el.disabled = false;
      });
    } catch (e) {
      console.error('Survey enable error:', e);
    }
  }
</script>