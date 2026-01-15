// Survey & Disclaimer Modal Handlers

document.addEventListener('DOMContentLoaded', function () {
  const disclaimerBtn = document.querySelector('.close-disclaimer');
  const surveyWrapper = document.getElementById('surveyWrapper');
  const weeklySurveyWrapper = document.getElementById('weeklySurveyWrapper');

  if (disclaimerBtn) {
    disclaimerBtn.addEventListener('click', function () {
      setTimeout(() => {
        if (surveyWrapper) surveyWrapper.style.display = 'flex';
        if (weeklySurveyWrapper) weeklySurveyWrapper.style.display = 'flex';
      }, 2000);
    });
  }
});
