<?php
if (!isset($_SESSION['show_trip_completion_survey']) || $_SESSION['show_trip_completion_survey'] !== true) return;
?>

<div id="tripSurveyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm px-4">
  <div x-data="{ step: 1 }" class="bg-white rounded-xl shadow-xl w-full max-w-xl p-6 space-y-6 relative">

    <!-- Header -->
    <div class="text-center space-y-2">
      <h2 class="text-xl font-semibold text-sky-700">How Was Your Trip? ✈️</h2>
      <p class="text-sm text-gray-600">
        We’d love to hear how the portal supported your travel experience. Your feedback helps us improve future trips and support Luzon-based tourism.
      </p>
    </div>

    <!-- Progress Bar -->
    <div class="flex items-center justify-between gap-2 px-2">
      <div class="flex-1 h-2 rounded-full bg-gray-200">
        <div class="h-2 rounded-full bg-sky-500 transition-all duration-300" :style="`width: ${step === 1 ? '50%' : '100%'}`"></div>
      </div>
      <span class="text-xs text-gray-500" x-text="step === 1 ? 'Getting Started' : 'Final Thoughts'"></span>
    </div>

    <form action="../actions/submit_client_trip_completion_survey.php" method="POST" class="space-y-5" novalidate onsubmit="enableTripCompletionSurveyFields()">
      <input type="hidden" name="survey_type" value="trip_complete">

      <!-- Section 1 -->
      <div x-show="step === 1" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            How would you rate your overall experience using the travel portal during your trip?
          </label>
          <select name="q1_overall_experience" :disabled="step !== 1" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300">
            <option value="5">Excellent</option>
            <option value="4">Good</option>
            <option value="3">Neutral</option>
            <option value="2">Poor</option>
            <option value="1">Very poor</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Was it easy to access your itinerary and travel documents through the portal?
          </label>
          <select name="q2_access_ease" :disabled="step !== 1" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300">
            <option value="5">Very easy</option>
            <option value="4">Easy</option>
            <option value="3">Neutral</option>
            <option value="2">Difficult</option>
            <option value="1">Very difficult</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Did the portal help reduce the need for back-and-forth messaging with your travel coordinator?
          </label>
          <select name="q3_coordination_impact" :disabled="step !== 1" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300">
            <option value="5">Yes, significantly</option>
            <option value="4">Yes, somewhat</option>
            <option value="3">Neutral / unsure</option>
            <option value="2">Not really</option>
            <option value="1">No, it added confusion</option>
          </select>
        </div>

        <!-- Actions -->
        <div class="flex justify-between pt-4">
          <button type="button" onclick="document.getElementById('tripSurveyModal').remove()" class="text-sm text-gray-500 hover:underline">
            Skip
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
            Which portal feature did you find most helpful during your trip?
          </label>
          <select name="q4_most_helpful_feature" :disabled="step !== 2" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300">
            <option value="itinerary_viewer">Itinerary viewer</option>
            <option value="document_uploads">Document uploads</option>
            <option value="notifications">Notifications</option>
            <option value="photo_reviews">Photo uploads & reviews</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Was there anything confusing or missing from the portal that you’d like us to improve?
          </label>
          <textarea name="q5_improvement_suggestions" rows="3" :disabled="step !== 2" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" placeholder="Optional..."></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Would you like to share a short review or highlight from your trip?
          </label>
          <textarea name="q6_trip_review" rows="3" :disabled="step !== 2" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring focus:border-sky-300" placeholder="Optional..."></textarea>
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
            <button type="button" onclick="document.getElementById('tripSurveyModal').remove()" class="text-sm text-gray-500 hover:underline">
              Skip
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
  function enableTripCompletionSurveyFields() {
    try {
      var form = document.querySelector('#tripSurveyModal form');
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