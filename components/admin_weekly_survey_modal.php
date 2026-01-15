<?php
if (!isset($_SESSION['show_weekly_survey_modal']) || $_SESSION['show_weekly_survey_modal'] !== true) return;
?>

<div id="weeklySurveyModal" x-data="{ step: 1 }" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm px-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-xl p-6 space-y-6 relative">

    <!-- Header -->
    <div class="text-center space-y-2">
      <h2 class="text-xl font-semibold text-sky-700">Weekly Check-In 🧠</h2>
      <p class="text-sm text-gray-600">
        We’d love to hear how your week went. Your feedback helps us improve the system and support your workflow.
      </p>
    </div>

    <!-- Progress Bar -->
    <div class="flex items-center justify-between gap-2 px-2">
      <div class="flex-1 h-2 rounded-full bg-gray-200">
        <div class="h-2 rounded-full bg-sky-500 transition-all duration-300" :style="`width: ${step === 1 ? '50%' : '100%'}`"></div>
      </div>
      <span class="text-xs text-gray-500" x-text="step === 1 ? 'Workload & Efficiency' : 'Reflections & Suggestions'"></span>
    </div>

    <form action="../actions/submit_admin_weekly_survey.php" method="POST" class="space-y-5">
      <input type="hidden" name="survey_type" value="admin_weekly_survey">
      <input type="hidden" name="skip_survey" id="skipSurveyFlag" value="0">

      <!-- Section 1 -->
      <div x-show="step === 1" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            How manageable was your workload this week?
          </label>
          <select name="q1_workload_manageability" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300">
            <option value="5">Very manageable</option>
            <option value="4">Manageable</option>
            <option value="3">Neutral</option>
            <option value="2">Challenging</option>
            <option value="1">Overwhelming</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Did the system help you complete tasks more efficiently?
          </label>
          <select name="q2_system_efficiency" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300">
            <option value="5">Yes, very much</option>
            <option value="4">Yes, somewhat</option>
            <option value="3">Neutral</option>
            <option value="2">Not really</option>
            <option value="1">No, it slowed me down</option>
          </select>
        </div>

        <!-- Actions -->
        <div class="flex justify-between pt-4">
          <button type="button" onclick="document.getElementById('skipSurveyFlag').value = '1'; this.form.submit();" class="text-sm text-gray-500 hover:underline">
            Skip for now
          </button>
          <button type="button" @click="step = 2" class="bg-sky-600 text-white px-4 py-2 rounded hover:bg-sky-700 transition">
            Continue
          </button>
        </div>
      </div>

      <!-- Section 2 -->
      <div x-show="step === 2" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Were there any parts of the system that felt confusing or frustrating?
          </label>
          <textarea name="q3_confusing_parts" rows="3" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" placeholder="Optional..."></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            What’s one improvement or feature you’d love to see next?
          </label>
          <textarea name="q4_feature_request" rows="3" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" placeholder="Optional..."></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            How are you feeling overall?
          </label>
          <textarea name="q5_emotional_state" rows="2" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" placeholder="Optional..."></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-between pt-4">
          <button type="button" @click="step = 1" class="text-sm text-gray-500 hover:underline">
            Back
          </button>
          <div class="flex gap-3">
            <button type="submit" class="bg-sky-600 text-white px-4 py-2 rounded hover:bg-sky-700 transition">
              Submit
            </button>
            <button type="button" onclick="document.getElementById('skipSurveyFlag').value = '1'; this.form.submit();" class="text-sm text-gray-500 hover:underline">
              Skip for now
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>