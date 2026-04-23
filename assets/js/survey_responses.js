/**
 * Survey Responses Viewer
 * Loads and displays survey responses from user_survey_status table
 */

document.addEventListener('DOMContentLoaded', () => {
  const surveyContainer = document.getElementById('surveyResponsesContainer');
  const sortSelect = document.getElementById('surveySortSelect');
  const roleSelect = document.getElementById('surveyRoleSelect');
  const surveyTypeSelect = document.getElementById('surveyTypeSelect');

  if (!surveyContainer) return;

  // Update survey type options based on role
  function updateSurveyTypeOptions(role) {
    const options = [{ value: 'all', text: 'All Types' }];
    
    if (role === 'client') {
      options.push(
        { value: 'first_login', text: 'First Login' },
        { value: 'status_confirmed', text: 'Confirmed' },
        { value: 'trip_complete', text: 'Trip Completed' }
      );
    } else if (role === 'admin') {
      options.push(
        { value: 'first_login', text: 'First Login' },
        { value: 'admin_weekly_survey', text: 'Weekly Survey' }
      );
    } else {
      // For 'all', show all possible types
      options.push(
        { value: 'first_login', text: 'First Login' },
        { value: 'status_confirmed', text: 'Confirmed' },
        { value: 'trip_complete', text: 'Trip Completed' },
        { value: 'admin_weekly_survey', text: 'Weekly Survey' }
      );
    }
    
    surveyTypeSelect.innerHTML = options.map(opt => 
      `<option value="${opt.value}">${opt.text}</option>`
    ).join('');
  }

  // Fetch survey responses
  function loadSurveyResponses(sort = 'recent', role = 'all', surveyType = 'all') {
    fetch(`../components/survey_responses_data.php?sort=${sort}&role=${role}&survey_type=${surveyType}`)
      .then(res => res.json())
      .then(data => {
        if (data.surveys && data.surveys.length > 0) {
          surveyContainer.innerHTML = data.surveys.map(survey => createSurveyCard(survey)).join('');
        } else {
          surveyContainer.innerHTML = `
            <div class="col-span-full text-center py-12">
              <p class="text-gray-500 text-lg">📭 No survey responses yet</p>
              <p class="text-gray-400 text-sm mt-2">Survey responses will appear here as users complete surveys.</p>
            </div>
          `;
        }
      })
      .catch(error => {
        console.error('Error loading surveys:', error);
        surveyContainer.innerHTML = `
          <div class="col-span-full text-center py-12">
            <p class="text-red-500">⚠️ Error loading survey responses</p>
            <p class="text-gray-400 text-sm mt-2">Please try again later.</p>
          </div>
        `;
      });
  }

  // Create survey card HTML
  function createSurveyCard(survey) {
    const surveyBadges = {
      'first_login': '<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-semibold">First Login</span>',
      'status_confirmed': '<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-semibold">Confirmation</span>',
      'trip_complete': '<span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs font-semibold">Trip Complete</span>',
      'admin_weekly_survey': '<span class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs font-semibold">Weekly Admin</span>'
    };

    let badge = '';
    if (survey.survey_type === 'first_login') {
      const isAdmin = survey.user_role === 'admin';
      const badgeText = isAdmin ? 'Admin First Login' : 'First Login';
      const badgeClass = isAdmin ? 'bg-indigo-100 text-indigo-700' : 'bg-blue-100 text-blue-700';
      badge = `<span class="px-2 py-1 ${badgeClass} rounded text-xs font-semibold">${badgeText}</span>`;
    } else {
      badge = surveyBadges[survey.survey_type] || '<span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs font-semibold">Survey</span>';
    }

    let responseHtml = '';
    if (survey.response_data && survey.response_data.responses) {
      const responses = survey.response_data.responses;
      for (const [key, value] of Object.entries(responses)) {
        if (value && value !== '') {
          const label = key.replace(/_/g, ' ').replace(/^q\d+_/, '');
          responseHtml += `
            <div class="flex justify-between items-center text-xs py-1 border-b border-gray-100">
              <span class="text-gray-600">${escapeHtml(label)}</span>
              <span class="font-medium text-gray-700">${escapeHtml(value)}</span>
            </div>
          `;
        }
      }
    }

    const userLabel = survey.user_role === 'admin' ? 'Admin' : 'Client';

    return `
      <div class="bg-white rounded-lg shadow p-4 border border-gray-200 hover:shadow-md transition">
        <div class="flex items-start justify-between mb-3">
          <div>
            ${badge}
            <p class="text-sm text-gray-700 font-medium mt-2">
              ${userLabel} #${survey.user_id}
            </p>
          </div>
          <span class="text-xs text-gray-400">${formatDate(survey.created_at)}</span>
        </div>
        
        <div class="space-y-1 text-xs">
          ${responseHtml || '<p class="text-gray-400 italic">No responses</p>'}
        </div>

        ${survey.completed_at ? `
          <div class="mt-3 pt-3 border-t border-gray-100">
            <span class="text-xs text-green-600 font-semibold">✓ Completed</span>
            <p class="text-xs text-gray-500">${formatDate(survey.completed_at)}</p>
          </div>
        ` : `
          <div class="mt-3 pt-3 border-t border-gray-100">
            <span class="text-xs text-yellow-600 font-semibold">⏳ Pending</span>
          </div>
        `}
      </div>
    `;
  }

  // Helper function to escape HTML
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }

  // Format date
  function formatDate(dateStr) {
    try {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: '2-digit' }) + ' ' + 
             date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    } catch {
      return dateStr;
    }
  }

  // Load surveys on init
  updateSurveyTypeOptions('all');
  loadSurveyResponses();

  // Handle sort change
  sortSelect?.addEventListener('change', (e) => {
    const currentRole = roleSelect?.value || 'all';
    const currentType = surveyTypeSelect?.value || 'all';
    loadSurveyResponses(e.target.value, currentRole, currentType);
  });

  // Handle role change
  roleSelect?.addEventListener('change', (e) => {
    const newRole = e.target.value;
    updateSurveyTypeOptions(newRole);
    const currentSort = sortSelect?.value || 'recent';
    // Reset survey type to 'all' when role changes
    surveyTypeSelect.value = 'all';
    loadSurveyResponses(currentSort, newRole, 'all');
  });

  // Handle survey type change
  surveyTypeSelect?.addEventListener('change', (e) => {
    const currentSort = sortSelect?.value || 'recent';
    const currentRole = roleSelect?.value || 'all';
    loadSurveyResponses(currentSort, currentRole, e.target.value);
  });
});
