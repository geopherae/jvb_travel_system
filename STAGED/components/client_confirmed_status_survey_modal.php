<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['client_id']) || !isset($_SESSION['show_confirmed_status_survey_modal']) || $_SESSION['show_confirmed_status_survey_modal'] !== true) {
  return;
}

require_once '../actions/db.php';

$client_id = $_SESSION['client_id'];

$stmt = $conn->prepare("
  SELECT id  
  FROM user_survey_status  
  WHERE user_id = ?  
  AND user_role = 'client'  
  AND survey_type = 'status_confirmed'  
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

<div x-cloak id="confirmedStatusSurveyModal" 
     x-data="{ step: 1, totalSteps: 5, next() { if (this.step < this.totalSteps) this.step++ }, prev() { if (this.step > 1) this.step-- } }" 
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm px-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-xl p-6 space-y-6 relative">

    <!-- Header -->
    <div class="text-center space-y-2">
      <h2 class="text-xl font-semibold text-sky-700">Congratulations on Your Booking Confirmation! 🎉</h2>
      <p class="text-sm text-gray-600">
        Your booking is confirmed! <br> Help us improve your experience by sharing your feedback on our portal.
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

    <form action="../actions/submit_client_confirmed_status_survey.php" method="POST" class="space-y-5" id="confirmedStatusSurveyForm" novalidate onsubmit="enableConfirmedStatusSurveyFields()">
      <input type="hidden" name="survey_type" value="status_confirmed">
      <input type="hidden" name="client_id" value="<?= htmlspecialchars($_SESSION['client_id'] ?? '') ?>">
      <input type="hidden" name="template_id" value="<?= htmlspecialchars($_SESSION['template_id'] ?? '') ?>">
      <input type="hidden" name="skip_survey" id="confirmedStatusSkipSurveyFlag" value="0">

      <!-- Step 1: Perceived Usefulness -->
      <div x-show="step === 1" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          How helpful has this portal been in keeping you updated and prepared for your upcoming trip?
        </label>
        <select name="q1_perceived_usefulness" :disabled="step !== 1" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" required>
          <option value="">Select an option</option>
          <option value="5">Extremely helpful</option>
          <option value="4">Helpful</option>
          <option value="3">Neutral</option>
          <option value="2">Not very helpful</option>
          <option value="1">Not helpful at all</option>
        </select>
      </div>

      <!-- Step 2: Ease of Use -->
      <div x-show="step === 2" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          How easy was it to track the status of your documents and approvals through the portal?
        </label>
        <select name="q2_ease_of_use" :disabled="step !== 2" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" required>
          <option value="">Select an option</option>
          <option value="5">Very easy</option>
          <option value="4">Fairly easy</option>
          <option value="3">Neutral / unsure</option>
          <option value="2">Somewhat difficult</option>
          <option value="1">Very difficult</option>
        </select>
      </div>

      <!-- Step 3: Trust & Security -->
      <div x-show="step === 3" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          How confident are you that your personal and travel information is secure within the portal?
        </label>
        <select name="q3_trust_security" :disabled="step !== 3" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" required>
          <option value="">Select an option</option>
          <option value="5">Very confident</option>
          <option value="4">Confident</option>
          <option value="3">Neutral / unsure</option>
          <option value="2">Not very confident</option>
          <option value="1">Not confident at all</option>
        </select>
      </div>

      <!-- Step 4: Satisfaction with Process -->
      <div x-show="step === 4" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Overall, how satisfied are you with the process of submitting and getting your documents approved?
        </label>
        <select name="q4_satisfaction_process" :disabled="step !== 4" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" required>
          <option value="">Select an option</option>
          <option value="5">Very satisfied</option>
          <option value="4">Satisfied</option>
          <option value="3">Neutral</option>
          <option value="2">Dissatisfied</option>
          <option value="1">Very dissatisfied</option>
        </select>
      </div>

      <!-- Step 5: Behavioral Intention / Engagement -->
      <div x-show="step === 5" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          How likely are you to use this portal again for future travel bookings and requirements?
        </label>
        <select name="q5_behavioral_intention" :disabled="step !== 5" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" required>
          <option value="">Select an option</option>
          <option value="5">Very likely</option>
          <option value="4">Likely</option>
          <option value="3">Neutral</option>
          <option value="2">Unlikely</option>
          <option value="1">Very unlikely</option>
        </select>
      </div>

      <!-- Navigation Buttons -->
      <div class="flex justify-between items-center pt-4">
        <button type="button" x-show="step > 1" @click="prev" class="text-sm text-gray-500 hover:underline">← Back</button>

        <button type="button" onclick="skipConfirmedStatusSurvey()" class="text-xs text-gray-400 hover:text-gray-600">
          Skip survey
        </button>

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
  function enableConfirmedStatusSurveyFields() {
    try {
      var form = document.getElementById('confirmedStatusSurveyForm');
      if (!form) return;
      var fields = form.querySelectorAll('select, textarea, input');
      fields.forEach(function(el) {
        if (el.disabled) el.disabled = false;
      });
    } catch (e) {
      // Fail-open: if enabling fails, allow native submit
      console.error('Survey enable error:', e);
    }
  }

  function skipConfirmedStatusSurvey() {
    // Set the skip flag and close the modal via AJAX
    var form = document.getElementById('confirmedStatusSurveyForm');
    var formData = new FormData(form);
    formData.set('skip_survey', '1');
    
    fetch('../actions/submit_client_confirmed_status_survey.php', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('HTTP error ' + response.status);
      }
      return response.json();
    })
    .then(data => {
      console.log('Skip survey response:', data);
      if (data.success) {
        // Close the modal
        var modal = document.getElementById('confirmedStatusSurveyModal');
        if (modal) {
          modal.remove();
        }
      } else {
        alert('Error: ' + (data.message || 'Unable to skip survey'));
      }
    })
    .catch(error => {
      console.error('Error skipping survey:', error);
      alert('An error occurred while skipping the survey. Check browser console for details.');
    });
  }
</script>
