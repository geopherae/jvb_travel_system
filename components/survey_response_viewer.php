<?php
/**
 * Survey Response Viewer
 * 
 * Renders survey responses in a readable, formatted way
 * Used in audit logs and admin dashboards
 * 
 * @param string $payloadJson - JSON payload containing survey data
 * @return string - Formatted HTML for display
 */

function formatSurveyResponse($payloadJson) {
    if (!$payloadJson) {
        return '<em class="text-gray-500">No survey data</em>';
    }

    $decoded = json_decode($payloadJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '<em class="text-red-500">Invalid JSON</em>';
    }

    $surveyType = $decoded['survey_type'] ?? 'unknown';
    $responses = $decoded['responses'] ?? [];
    $submittedAt = $decoded['submitted_at'] ?? null;

    // Define survey-specific labels and mappings
    $surveyLabels = [
        'first_login' => [
            'usefulness' => 'Expected Usefulness',
            'ease_of_use' => 'Expected Ease of Use',
            'upload_confidence' => 'Upload Confidence',
            'expectations' => 'Expectations',
            'feature_interest' => 'Feature Interest',
            'q1_expected_usefulness' => 'Expected Usefulness',
            'q2_expected_ease' => 'Expected Ease of Use',
            'q3_upload_confidence' => 'Upload Confidence',
            'q4_task_simplicity' => 'Task Simplicity',
            'q5_feature_interest' => 'Feature Interest'
        ],
        'status_confirmed' => [
            'q1_perceived_usefulness' => 'Perceived Usefulness',
            'q2_ease_of_use' => 'Ease of Use',
            'q3_trust_security' => 'Trust & Security',
            'q4_satisfaction_process' => 'Satisfaction with Process',
            'q5_behavioral_intention' => 'Behavioral Intention'
        ],
        'trip_complete' => [
            'q1_trip_usefulness' => 'Trip Experience Usefulness',
            'q2_ease_of_use' => 'Ease of Use',
            'q3_recommendation_likelihood' => 'Recommendation Likelihood',
            'q4_overall_satisfaction' => 'Overall Satisfaction',
            'q5_future_engagement' => 'Future Engagement'
        ],
        'admin_weekly_survey' => [
            'q1_workload' => 'Weekly Workload',
            'q2_stress_level' => 'Stress Level',
            'q3_feature_request' => 'Feature Request',
            'q4_team_feedback' => 'Team Feedback',
            'q5_system_satisfaction' => 'System Satisfaction'
        ]
    ];

    // Get labels for this survey type
    $labels = $surveyLabels[$surveyType] ?? [];

    // Scale labels for Likert-type responses
    $scaleMappings = [
        'yes' => 'Yes',
        'no' => 'No',
        '5' => '5 (Excellent)',
        '4' => '4 (Good)',
        '3' => '3 (Neutral)',
        '2' => '2 (Poor)',
        '1' => '1 (Very Poor)',
    ];

    // Additional specific mappings
    $specificMappings = [
        'perceived_usefulness' => [
            '5' => '5 - Extremely helpful',
            '4' => '4 - Helpful',
            '3' => '3 - Neutral',
            '2' => '2 - Not very helpful',
            '1' => '1 - Not helpful at all'
        ],
        'ease_of_use' => [
            '5' => '5 - Very easy',
            '4' => '4 - Fairly easy',
            '3' => '3 - Neutral / unsure',
            '2' => '2 - Somewhat difficult',
            '1' => '1 - Very difficult'
        ],
        'trust_security' => [
            '5' => '5 - Very confident',
            '4' => '4 - Confident',
            '3' => '3 - Neutral / unsure',
            '2' => '2 - Not very confident',
            '1' => '1 - Not confident at all'
        ],
        'satisfaction_process' => [
            '5' => '5 - Very satisfied',
            '4' => '4 - Satisfied',
            '3' => '3 - Neutral',
            '2' => '2 - Dissatisfied',
            '1' => '1 - Very dissatisfied'
        ],
        'behavioral_intention' => [
            '5' => '5 - Very likely',
            '4' => '4 - Likely',
            '3' => '3 - Neutral',
            '2' => '2 - Unlikely',
            '1' => '1 - Very unlikely'
        ],
        'feature_interest' => [
            '5' => '5 - Extremely interested',
            '4' => '4 - Interested',
            '3' => '3 - Neutral',
            '2' => '2 - Not very interested',
            '1' => '1 - Not interested at all'
        ],
        'upload_confidence' => [
            '5' => '5 - Very confident',
            '4' => '4 - Confident',
            '3' => '3 - Neutral / unsure',
            '2' => '2 - Not very confident',
            '1' => '1 - Not confident at all'
        ]
    ];

    // Build HTML
    $html = '<div class="survey-response-container bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">';
    
    // Survey type badge
    $surveyTypeBadges = [
        'first_login' => '<span class="inline-block px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">First Login</span>',
        'status_confirmed' => '<span class="inline-block px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Status Confirmed</span>',
        'trip_complete' => '<span class="inline-block px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">Trip Complete</span>',
        'admin_weekly_survey' => '<span class="inline-block px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-semibold">Weekly Admin</span>'
    ];

    $badge = $surveyTypeBadges[$surveyType] ?? "<span class=\"inline-block px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold\">Survey</span>";
    
    $html .= '<div class="flex items-center justify-between mb-3">';
    $html .= '<div>' . $badge . '</div>';
    if ($submittedAt) {
        $html .= '<span class="text-xs text-gray-600">Submitted: ' . htmlspecialchars($submittedAt) . '</span>';
    }
    $html .= '</div>';

    // Responses table
    if (!empty($responses)) {
        $html .= '<div class="space-y-2">';
        
        foreach ($responses as $key => $value) {
            // Skip empty values
            if (empty($value) || $value === '') {
                continue;
            }

            // Get readable label
            $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
            
            // Get readable value
            $readableValue = $value;
            
            // Check for specific mapping first
            if (isset($specificMappings[$key][$value])) {
                $readableValue = $specificMappings[$key][$value];
            } elseif (isset($scaleMappings[$value])) {
                $readableValue = $scaleMappings[$value];
            }

            $html .= '<div class="flex items-center justify-between text-xs border-b border-blue-100 py-1.5">';
            $html .= '<span class="font-medium text-gray-700">' . htmlspecialchars($label) . ':</span>';
            $html .= '<span class="text-gray-600">' . htmlspecialchars($readableValue) . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>';
    } else {
        $html .= '<em class="text-gray-500 text-xs">No responses recorded</em>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Get survey type display name
 */
function getSurveyTypeDisplayName($surveyType) {
    return match($surveyType) {
        'first_login' => 'First Login Survey',
        'status_confirmed' => 'Booking Confirmation Survey',
        'trip_complete' => 'Trip Completion Survey',
        'admin_weekly_survey' => 'Weekly Admin Survey',
        default => ucfirst(str_replace('_', ' ', $surveyType))
    };
}
?>
