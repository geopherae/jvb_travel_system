<?php
if (!isset($_SESSION['show_survey_modal']) || $_SESSION['show_survey_modal'] !== true) return;
?>

<div id="surveyModal" x-data="{ step: 1 }" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm px-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-xl p-6 space-y-6 relative">

    <!-- Header -->
    <div class="text-center space-y-2">
      <h2 class="text-xl font-semibold text-sky-700">Welcome to Your Admin Dashboard 🧭</h2>
      <p class="text-sm text-gray-600">
        Before you explore, we’d love to understand your expectations. Your insights help us measure how well the system supports agency operations over time.
      </p>
    </div>

    <!-- Progress Bar -->
    <div class="flex items-center justify-between gap-2 px-2">
      <div class="flex-1 h-2 rounded-full bg-gray-200">
        <div class="h-2 rounded-full bg-sky-500 transition-all duration-300" :style="`width: ${step === 1 ? '50%' : '100%'}`"></div>
      </div>
      <span class="text-xs text-gray-500" x-text="step === 1 ? 'Expectations' : 'Confidence & Goals'"></span>
    </div>

    <form action="../actions/submit_admin_first_time_survey.php" method="POST" class="space-y-5">
      <input type="hidden" name="csrf_token_modal" value="<?= $_SESSION['csrf_token_modal'] ?? '' ?>">
      <input type="hidden" name="survey_type" value="first_login">

      <!-- Section 1 -->
      <div x-show="step === 1" x-transition class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            How useful do you expect this system to be for managing itineraries, documents, and client coordination?
          </label>
          <select name="q1_expected_usefulness" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300">
            <option value="5">Extremely useful</option>
            <option value="4">Useful</option>
            <option value="3">Neutral</option>
            <option value="2">Not very useful</option>
            <option value="1">Not useful at all</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            From your initial impression, how easy do you think it will be to learn and navigate this system?
          </label>
          <select name="q2_expected_ease" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300">
            <option value="5">Very easy to learn and navigate</option>
            <option value="4">Fairly easy</option>
            <option value="3">Neutral / unsure</option>
            <option value="2">Somewhat difficult</option>
            <option value="1">Very difficult to understand</option>
          </select>
        </div>

        <div class="flex justify-between pt-4">
          <button type="button" onclick="document.getElementById('surveyModal').remove()" class="text-sm text-gray-500 hover:underline">
            Skip
          </button>
          <button type="button" @click="step = 2" class="bg-sky-600 text-white px-4 py-2 rounded hover:bg-sky-700 transition">
            Continue
          </button>
        </div>
      </div>

      <!-- Section 2 -->
      <div x-show="step === 2" x-transition class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            What part of your workflow do you hope this system will improve the most?
          </label>
          <select name="q3_expected_workflow_focus" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300">
            <option value="itinerary_planning">Itinerary planning</option>
            <option value="document_handling">Document handling</option>
            <option value="client_coordination">Client coordination</option>
            <option value="template_reuse">Reusing trip templates</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            How confident are you that this system will reduce reliance on messaging apps and repetitive coordination?
          </label>
          <select name="q4_expected_coordination_improvement" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300">
            <option value="5">Very confident</option>
            <option value="4">Confident</option>
            <option value="3">Neutral</option>
            <option value="2">Unsure</option>
            <option value="1">Not confident</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            What are you hoping this system will help you do better?
          </label>
          <textarea name="q5_admin_expectations" rows="3" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" placeholder="Optional..."></textarea>
        </div>

        <div class="flex justify-between pt-4">
          <button type="button" @click="step = 1" class="text-sm text-gray-500 hover:underline">
            Back
          </button>
          <div class="flex gap-3">
            <button type="submit" class="bg-sky-600 text-white px-4 py-2 rounded hover:bg-sky-700 transition">
              Submit
            </button>
            <button type="button" onclick="document.getElementById('surveyModal').remove()" class="text-sm text-gray-500 hover:underline">
              Skip
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>