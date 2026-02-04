/**
 * Visa Processing Global Alpine Stores & Data Functions
 * Parallels tour_packages_global_scope.js structure for consistency
 * 
 * Usage:
 * - Include: <script src="../includes/visa_global_scope.js"></script>
 * - Access via: Alpine.store('visaApplications'), Alpine.data('visaFormData'), etc.
 */

document.addEventListener('alpine:init', () => {
  console.log('Alpine initializing visa stores...');

  /* --------------------
     Visa Application Store
  -------------------- */
  Alpine.store('visaApplications', {
    currentApplication: null,
    selectedApplicationId: null,
    loading: false,
    error: null,

    setCurrentApplication(app) {
      this.currentApplication = app;
      this.selectedApplicationId = app?.id || null;
    },

    clearApplication() {
      this.currentApplication = null;
      this.selectedApplicationId = null;
    },

    setError(message) {
      this.error = message;
      console.error('[visaApplications] Error:', message);
    },

    clearError() {
      this.error = null;
    }
  });

  /* --------------------
     Visa Modal Store (similar to editTourModal)
  -------------------- */
  Alpine.store('visaModal', {
    isOpen: false,
    applicationId: null,
    applicationData: {},
    tab: 'details',

    open(id) {
      this.isOpen = true;
      this.applicationId = id;
      console.log('[visaModal] Opening with applicationId:', id);
    },

    close() {
      this.isOpen = false;
      this.applicationId = null;
      this.applicationData = {};
      this.tab = 'details';
      console.log('[visaModal] Closed');
    },

    setTab(tabName) {
      this.tab = tabName;
    },

    setData(data) {
      this.applicationData = data;
    }
  });

  /* --------------------
     Visa Documents Modal
  -------------------- */
  Alpine.store('visaDocumentsModal', {
    isOpen: false,
    applicationId: null,
    requirementId: null,
    documents: [],

    open(appId, reqId) {
      this.isOpen = true;
      this.applicationId = appId;
      this.requirementId = reqId;
      console.log('[visaDocumentsModal] Opened for app:', appId, 'requirement:', reqId);
    },

    close() {
      this.isOpen = false;
      this.applicationId = null;
      this.requirementId = null;
      this.documents = [];
    },

    setDocuments(docs) {
      this.documents = docs;
    },

    addDocument(doc) {
      this.documents.push(doc);
    }
  });

  /* --------------------
     Visa Requirement Checklist Store
  -------------------- */
  Alpine.store('visaRequirements', {
    primaryRequirements: [],
    conditionalRequirements: [],
    companionRequirements: {},
    applicantStatus: null,

    setRequirements(primary, conditional) {
      this.primaryRequirements = primary || [];
      this.conditionalRequirements = conditional || [];
    },

    setApplicantStatus(status) {
      this.applicantStatus = status;
    },

    getApplicableRequirements() {
      let applicable = [...this.primaryRequirements];
      
      // Add conditional requirements matching applicant status
      this.conditionalRequirements.forEach(group => {
        if (group.applicant_status === this.applicantStatus) {
          applicable = applicable.concat(group.requirements || []);
        }
      });

      return applicable;
    },

    setCompanionRequirements(companionId, requirements) {
      this.companionRequirements[companionId] = requirements;
    }
  });

  /* --------------------
     Visa Companions Store
  -------------------- */
  Alpine.store('visaCompanions', {
    companions: [],
    selectedCompanionId: null,

    addCompanion(companion) {
      this.companions.push(companion);
    },

    removeCompanion(companionId) {
      this.companions = this.companions.filter(c => c.id !== companionId);
    },

    updateCompanion(companionId, data) {
      const companion = this.companions.find(c => c.id === companionId);
      if (companion) {
        Object.assign(companion, data);
      }
    },

    setCompanions(companions) {
      this.companions = companions || [];
    },

    selectCompanion(companionId) {
      this.selectedCompanionId = companionId;
    },

    clearSelection() {
      this.selectedCompanionId = null;
    },

    getCompanion(companionId) {
      return this.companions.find(c => c.id === companionId);
    }
  });

  /* --------------------
     Visa Approval Workflow Store
  -------------------- */
  Alpine.store('visaApprovalWorkflow', {
    submissions: [],
    selectedSubmissionId: null,
    approvalInProgress: false,

    setSubmissions(submissions) {
      this.submissions = submissions || [];
    },

    selectSubmission(submissionId) {
      this.selectedSubmissionId = submissionId;
    },

    updateSubmissionStatus(submissionId, newStatus) {
      const submission = this.submissions.find(s => s.id === submissionId);
      if (submission) {
        submission.status = newStatus;
      }
    },

    setApprovalProgress(inProgress) {
      this.approvalInProgress = inProgress;
    },

    getSubmissionsByStatus(status) {
      return this.submissions.filter(s => s.status === status);
    }
  });

  /* --------------------
     Dropdown Store (for requirement cards)
  -------------------- */
  Alpine.store('dropdownVisa', {
    active: null,
    open(id) {
      this.active = id;
    },
    close() {
      this.active = null;
    },
    isOpen(id) {
      return this.active === id;
    }
  });
});

/* --------------------
   Form Data Factory Functions
-------------------- */

/**
 * Visa Application Form Data
 */
window.visaApplicationFormData = function (application = {}) {
  return {
    // Application Details
    applicationId: application.id || null,
    clientId: application.client_id || null,
    visaPackageId: application.visa_package_id || null,
    visaTypeSelected: application.visa_type_selected || '',
    applicantStatus: application.applicant_status || '',
    status: application.status || 'draft',

    // Form State
    errors: {},
    isSubmitting: false,
    successMessage: '',

    // Methods
    resetForm() {
      this.visaTypeSelected = '';
      this.applicantStatus = '';
      this.errors = {};
    },

    addError(field, message) {
      this.errors[field] = message;
    },

    clearErrors() {
      this.errors = {};
    },

    isValidated() {
      this.clearErrors();
      
      if (!this.visaPackageId) {
        this.addError('visaPackageId', 'Visa package is required');
      }
      
      if (!this.visaTypeSelected) {
        this.addError('visaTypeSelected', 'Visa type is required');
      }

      if (!this.applicantStatus) {
        this.addError('applicantStatus', 'Applicant status is required');
      }

      return Object.keys(this.errors).length === 0;
    }
  };
};

/**
 * Visa Requirement Submission Form Data
 */
window.visaRequirementFormData = function (requirement = {}) {
  return {
    // Requirement Details
    requirementId: requirement.id || '',
    requirementName: requirement.name || '',
    applicationId: null,
    companionId: null,
    file: null,
    fileName: '',
    filePreview: null,

    // Form State
    uploading: false,
    uploadProgress: 0,
    errors: {},
    successMessage: '',

    // Methods
    handleFileSelection(event) {
      const file = event.target.files?.[0];
      if (file) {
        this.file = file;
        this.fileName = file.name;
        this.filePreview = URL.createObjectURL(file);
      }
    },

    clearFile() {
      this.file = null;
      this.fileName = '';
      this.filePreview = null;
    },

    isValidated() {
      this.errors = {};
      
      if (!this.file) {
        this.errors.file = 'File is required';
      }

      if (!this.applicationId) {
        this.errors.applicationId = 'Application ID is required';
      }

      return Object.keys(this.errors).length === 0;
    }
  };
};

/**
 * Visa Companion Data Factory
 */
window.visaCompanionFormData = function (companion = {}) {
  return {
    // Companion Details
    id: companion.id || null,
    fullName: companion.full_name || '',
    relationship: companion.relationship || '',
    email: companion.email || '',
    phoneNumber: companion.phone_number || '',
    applicantStatus: companion.applicant_status || '',

    // Form State
    errors: {},
    isEditing: false,

    // Methods
    resetForm() {
      this.fullName = '';
      this.relationship = '';
      this.email = '';
      this.phoneNumber = '';
      this.applicantStatus = '';
      this.errors = {};
    },

    isValidated() {
      this.errors = {};
      
      if (!this.fullName) {
        this.errors.fullName = 'Full name is required';
      }

      if (!this.relationship) {
        this.errors.relationship = 'Relationship is required';
      }

      if (!this.applicantStatus) {
        this.errors.applicantStatus = 'Applicant status is required';
      }

      // Email validation if provided
      if (this.email && !this.isValidEmail(this.email)) {
        this.errors.email = 'Invalid email format';
      }

      return Object.keys(this.errors).length === 0;
    },

    isValidEmail(email) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
  };
};

/**
 * Toast/Notification Helper
 */
if (typeof window.showToast !== 'function') {
  window.showToast = function (message, type = 'success') {
    const toastContainer = document.getElementById('toast-container') || (() => {
      const container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'fixed bottom-4 right-4 z-50 space-y-2';
      document.body.appendChild(container);
      return container;
    })();

    const typeClasses = {
      success: 'bg-green-100 text-green-800 border border-green-300',
      error: 'bg-red-100 text-red-800 border border-red-300',
      warning: 'bg-yellow-100 text-yellow-800 border border-yellow-300',
      info: 'bg-blue-100 text-blue-800 border border-blue-300'
    };

    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg border ${typeClasses[type] || typeClasses.info}`;
    toast.textContent = message;
    toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s ease-in-out';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  };
}

window.visaShowToast = function (message, type = 'success') {
  window.showToast(message, type);
};

console.log('[visa_global_scope.js] Loaded successfully');
