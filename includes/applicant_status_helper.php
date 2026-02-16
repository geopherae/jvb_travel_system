<?php
/**
 * Helper function to convert applicant status labels to the required JSON format
 * Uses a predefined mapping to ensure consistent option values across the system
 * 
 * @param array $statusLabels Array of status labels from the frontend
 * @return string JSON encoded array with option and label fields
 * 
 * Example:
 * Input: ["Employed", "Self-Employed", "Student"]
 * Output: [
 *   {"option": "employed", "label": "Employed"},
 *   {"option": "self_employed", "label": "Self-Employed"},
 *   {"option": "student", "label": "Student"}
 * ]
 */
function convertApplicantStatusToJson(array $statusLabels) {
  // Predefined mapping of labels to standardized option values
  $statusMapping = [
    'Employed' => 'employed',
    'Self-Employed' => 'self_employed',
    'Business Owner' => 'business_owner',
    'Corporation' => 'corporation',
    'Student' => 'student',
    'Senior Citizen/Retired' => 'senior_citizen_retired',
    'Married' => 'married',
    'Widowed' => 'widowed',
    'Visiting Family/Friend' => 'visiting_family_friend',
    'None of the above' => 'none'
  ];
  
  $result = [];
  
  foreach ($statusLabels as $label) {
    $label = trim($label);
    if (empty($label)) continue;
    
    // Use predefined option value if available, otherwise generate from label
    if (isset($statusMapping[$label])) {
      $option = $statusMapping[$label];
    } else {
      // Fallback: generate option value from label for any custom statuses
      $option = strtolower($label);
      $option = str_replace(['/', ' ', '(', ')', '-'], '_', $option);
      $option = preg_replace('/[^a-z0-9_]/', '', $option);
      $option = preg_replace('/_+/', '_', $option);
      $option = trim($option, '_');
    }
    
    $result[] = [
      'option' => $option,
      'label' => $label
    ];
  }
  
  return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>