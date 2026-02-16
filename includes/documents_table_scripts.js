function visaDocumentTable(el) {
  const meta = JSON.parse(el.dataset.applicantsMeta || '[]');
  const applicantRequirements = JSON.parse(el.dataset.applicantRequirements || '[]');
  const accessType = el.dataset.accessType || 'individual';
  const isClient = el.dataset.isClient === '1';
  const visaApplicationId = el.dataset.visaApplicationId || null;

      console.log('=== visaDocumentTable init ===');
    console.log('meta:', meta);
    console.log('applicantRequirements:', applicantRequirements);
    console.log('accessType:', accessType);
    console.log('isClient:', isClient);

  return {
    modals: {
      viewer: false,
      addRequirement: false,
      uploadDocument: false,
      uploadActualVisa: false
    },
    selectedRequirementId: '',
    selectedRequirementName: '',
    selectedRequirementDescription: '',
    selectedAddReqFileName: '',
    selectedUploadDocFileName: '',
    actualVisaFileName: '',

    reqTypeOpen: false,
    selectedReqType: 'admin_added', // Default value
    reqTypeDropdownTop: 0,
    reqTypeDropdownLeft: 0,
    reqTypeDropdownWidth: 0,

    toast: {
      visible: false,
      message: '',
      type: 'success' // success, error
    },
    confirmAction: {
      visible: false,
      type: '',
      documentId: null,
      requirementId: null,
      requirementName: '',
      reason: ''
    },
    accessType: accessType,
    isClient: isClient,
    viewer: {
      path: '',
      fileName: '',
      requirement: '',
      mimeType: '',
      status: '',
      adminComments: '',
      uploadedAt: '',
      approvedAt: '',
      updatedBy: '',
      submissionId: '',
      zoom: 1,
      documentType: 'requirement' // 'requirement' or 'actual_visa'
    },
    applicantMeta: meta,
    applicantRequirements: applicantRequirements,
    currentIdx: 0,
    hoverRowId: null,
    isFromTopButton: false,
    editableReqName: '',
    editableReqDescription: '',
init() {
  console.log('🔵 init() called');
  try {
    console.log('🔵 calling syncWithApplicantStore...');
    this.syncWithApplicantStore();
    console.log('✓ syncWithApplicantStore completed');
  } catch (error) {
    console.error('✗ Error in syncWithApplicantStore:', error);
  }
  
  // Add file input listeners
  this.$nextTick(() => {
    console.log('🔵 $nextTick callback running');
    try {
      const addReqFileInput = document.getElementById('add_req_file');
      if (addReqFileInput) {
        addReqFileInput.addEventListener('change', (e) => {
          if (e.target.files.length > 0) {
            this.selectedAddReqFileName = e.target.files[0].name;
          } else {
            this.selectedAddReqFileName = '';
          }
        });
      }
      
      const uploadDocFileInput = document.getElementById('upload_document_file');
      if (uploadDocFileInput) {
        uploadDocFileInput.addEventListener('change', (e) => {
          if (e.target.files.length > 0) {
            this.selectedUploadDocFileName = e.target.files[0].name;
          } else {
            this.selectedUploadDocFileName = '';
          }
        });
      }
      console.log('✓ File input listeners added');
    } catch (error) {
      console.error('✗ Error adding file input listeners:', error);
    }
  });
},
syncWithApplicantStore() {
  const store = window.Alpine?.store('applicantSelector');
  if (!store) {
    return;
  }

  if (Array.isArray(this.applicantMeta) && this.applicantMeta.length > 0) {
    store.totalApplicants = this.applicantMeta.length;
    if (!Array.isArray(store.applicants) || store.applicants.length === 0) {
      store.applicants = this.applicantMeta.map(app => ({ name: app.name, relationship: app.relationship }));
    }
  }

  const maxIdx = Math.max((this.applicantMeta.length || 1) - 1, 0);
  const clamp = value => Math.min(Math.max(Number(value) || 0, 0), maxIdx);

  // One-time initial pull from store (handles pre-set values)
  this.currentIdx = clamp(store.currentIdx);

  // Push local changes to store (e.g., from dropdown)
  this.$watch('currentIdx', value => {
    store.currentIdx = clamp(value);
  });

  // Removed store watcher to prevent redundant updates/resets
},
    getCurrentApplicantRequirements() {
      // Get requirements for the currently selected applicant (indexed by currentIdx)
      if (!Array.isArray(this.applicantRequirements) || this.applicantRequirements.length === 0) {
        return [];
      }
      const reqs = this.applicantRequirements[this.currentIdx];
      return Array.isArray(reqs) ? reqs : [];
    },
    openViewer(path, fileName, requirement, mimeType, status, adminComments, uploadedAt = '', approvedAt = '', updatedBy = '', submissionId = '', documentType = 'requirement') {
      this.viewer = {
        path,
        fileName,
        requirement,
        mimeType,
        status,
        adminComments,
        uploadedAt,
        approvedAt,
        updatedBy,
        submissionId,
        zoom: 1,
        documentType: documentType
      };
      this.modals.viewer = true;
    },
    openAddRequirement() {
      // Clear form fields
      this.editableReqName = '';
      this.editableReqDescription = '';
      this.selectedAddReqFileName = '';
      this.modals.addRequirement = true;
      
      // Manually set the companion_id in the form after it renders
      // Use a small delay to ensure the modal DOM is rendered
      this.$nextTick(() => {
        const companionIdInput = document.querySelector('input[name="companion_id"]');
        if (companionIdInput) {
          const currentApplicant = this.applicantMeta[this.currentIdx];
          companionIdInput.value = currentApplicant?.companion_id || '';
        }
      });
    },
    openUploadDocument(reqId, reqName, reqDescription) {
      this.selectedRequirementId = reqId || '';
      this.selectedRequirementName = reqName || '';
      this.selectedRequirementDescription = reqDescription || '';
      this.selectedUploadDocFileName = '';
      this.modals.uploadDocument = true;
    },
    openUploadActualVisa() {
      // Open modal for uploading actual visa document
      this.actualVisaFileName = '';
      this.modals.uploadActualVisa = true;
    },
        toggleReqTypeDropdown() {
      this.reqTypeOpen = !this.reqTypeOpen;
      if (this.reqTypeOpen) {
        this.$nextTick(() => {
          this.positionReqTypeDropdown();
        });
      }
    },
    
    positionReqTypeDropdown() {
      const button = this.$refs.reqTypeButton;
      if (!button) return;
      
      const rect = button.getBoundingClientRect();
      this.reqTypeDropdownTop = rect.bottom + 4;
      this.reqTypeDropdownLeft = rect.left;
      this.reqTypeDropdownWidth = rect.width;
    },
    
    selectReqType(value) {
      this.selectedReqType = value;
      this.reqTypeOpen = false;
    },
    
    getReqTypeDisplayText() {
      if (!this.selectedReqType) return 'Select requirement type';
      if (this.selectedReqType === 'admin_added') return 'Custom Requirement (Other)';
      if (this.selectedReqType === 'primary') return 'Primary';
      return this.selectedReqType;
    },
    
    openUpload(reqId, reqName) {
      // Deprecated - kept for backwards compatibility
      this.openUploadDocument(reqId, reqName, '');
    },
    openUpload(reqId, reqName) {
      // Deprecated - kept for backwards compatibility
      this.openUploadDocument(reqId, reqName, '');
    },
    openDeleteRequirementModal(reqId, reqName) {
      // Show confirmation modal for removing requirement
      this.confirmAction = {
        visible: true,
        type: 'delete_requirement',
        requirementId: reqId,
        requirementName: reqName,
        reason: ''
      };
    },
    onRequirementSelected(event) {
      // This is no longer needed since we separated the modals
    },
    resetRequirementSelection() {
      this.selectedRequirementId = '';
      this.selectedRequirementName = '';
      this.selectedRequirementDescription = '';
    },
    closeViewer() {
      this.modals.viewer = false;
      this.viewer.zoom = 1;
    },
    async deleteDocument() {
      // Show styled confirmation modal instead of native confirm
      // Check document type to determine delete action type
      const deleteType = this.viewer.documentType === 'actual_visa' ? 'delete_actual_visa' : 'delete';
      this.confirmAction = {
        visible: true,
        type: deleteType,
        documentId: this.viewer.submissionId,
        reason: ''
      };
    },
    async deleteDocumentConfirmed(submissionId, documentType = 'requirement') {
      try {
        const formData = new FormData();
        
        // Choose action URL and parameter based on document type
        let actionUrl, paramName;
        if (documentType === 'actual_visa') {
          actionUrl = '../actions/delete_actual_visa_document.php';
          paramName = 'document_id';
        } else {
          actionUrl = '../actions/delete_visa_document.php';
          paramName = 'submission_id';
        }
        formData.append(paramName, submissionId);

        const response = await fetch(actionUrl, {
          method: 'POST',
          body: formData
        });

        const text = await response.text();
        let result;
        try {
          result = JSON.parse(text);
        } catch (e) {
          console.error('Invalid JSON response:', text);
          throw new Error('Server returned invalid response');
        }

        this.confirmAction.visible = false;
        this.closeViewer();
        
        if (result.success) {
            // Reload immediately to show toast from session
            window.location.reload();
        } else {
          this.toast.message = result.message || 'Failed to delete document.';
          this.toast.visible = true;
          setTimeout(() => this.toast.visible = false, 2000);
        }
      } catch (error) {
        console.error('Error deleting document:', error);
        this.confirmAction.visible = false;
        this.toast.message = 'An error occurred while deleting the document.';
        this.toast.visible = true;
        setTimeout(() => this.toast.visible = false, 2000);
      }
    },
    async deleteRequirementConfirmed(requirementId) {
      try {
        const formData = new FormData();
        formData.append('requirement_id', requirementId);
        formData.append('visa_application_id', visaApplicationId);
        
        // Get companion_id from current applicant (null for lead, ID for companions)
        const currentApplicant = this.applicantMeta[this.currentIdx];
        const companionId = currentApplicant?.companion_id || null;
        formData.append('companion_id', companionId || '');

        const response = await fetch('../actions/delete_visa_requirement.php', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        });

        const text = await response.text();
        let result;
        try {
          result = JSON.parse(text);
        } catch (e) {
          console.error('Invalid JSON response:', text);
          throw new Error('Server returned invalid response');
        }

        this.confirmAction.visible = false;
        
        if (result.success) {
          // Show success toast (emerald green, matching "Approved" status)
          this.toast.type = 'success';
          this.toast.message = result.message || 'Requirement removed successfully.';
          this.toast.visible = true;
          // Reload after toast disappears
          setTimeout(() => {
            this.toast.visible = false;
            window.location.reload();
          }, 2000);
        } else {
          this.toast.type = 'error';
          this.toast.message = result.message || 'Failed to remove requirement.';
          this.toast.visible = true;
          setTimeout(() => this.toast.visible = false, 2000);
        }
      } catch (error) {
        console.error('Error deleting requirement:', error);
        this.confirmAction.visible = false;
        this.toast.message = 'An error occurred while removing the requirement.';
        this.toast.visible = true;
        setTimeout(() => this.toast.visible = false, 2000);
      }
    },
    async saveChanges() {
      try {
        if (!this.viewer.submissionId) {
          alert('Submission ID is required to update document status.');
          return;
        }
        const formData = new FormData();
        formData.append('submission_id', this.viewer.submissionId);
        formData.append('status', this.viewer.status);
        formData.append('admin_comments', this.viewer.adminComments);

        const response = await fetch('../actions/update_visa_document_status.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          // Dispatch toast event based on status
          let toastStatus = 'visa_document_updated';
          if (this.viewer.status === 'Approved') {
            toastStatus = 'visa_document_approved';
          } else if (this.viewer.status === 'Rejected') {
            toastStatus = 'visa_document_rejected';
          }
          
          window.dispatchEvent(new CustomEvent('toast', { 
            detail: { status: toastStatus } 
          }));

          this.closeViewer();
          setTimeout(() => {
            window.location.reload();
          }, 1500);
        } else {
          alert(result.message || 'Failed to update document status.');
        }
      } catch (error) {
        console.error('Error saving changes:', error);
        alert('An error occurred while saving changes.');
      }
    },
    async handleAddRequirement(event) {
      const form = event.target;
      const formData = new FormData(form);
      
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        
        // Log response details for debugging
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (parseError) {
          console.error('JSON parse error:', parseError);
          console.error('Response text was:', responseText);
          throw new Error('Server returned invalid response: ' + responseText.substring(0, 100));
        }
        
        if (result.success) {
          this.modals.addRequirement = false;
          this.toast.message = result.message || 'Requirement added successfully!';
          this.toast.visible = true;
          
          setTimeout(() => {
            this.toast.visible = false;
            location.reload();
          }, 2000);
        } else {
          alert('Failed to add requirement: ' + (result.message || 'Unknown error'));
        }
      } catch (error) {
        console.error('Error adding requirement:', error);
        alert('An error occurred while adding the requirement.');
      }
    },
    async handleUploadDocument(event) {
      const form = event.target;
      const formData = new FormData(form);
      
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        
        const result = await response.json();
        
        if (result.success) {
          this.modals.uploadDocument = false;
          this.toast.message = result.message || 'Document uploaded successfully!';
          this.toast.visible = true;
          
          setTimeout(() => {
            this.toast.visible = false;
            location.reload();
          }, 2000);
        } else {
          alert('Upload failed: ' + (result.message || 'Unknown error'));
        }
      } catch (error) {
        console.error('Upload error:', error);
        alert('An error occurred while uploading the document.');
      }
    },
    async handleUploadActualVisa(event) {
      const form = event.target;
      const formData = new FormData(form);
      
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        
        const result = await response.json();
        
        if (result.success) {
          this.modals.uploadActualVisa = false;
          this.toast.message = result.message || 'Actual visa document uploaded successfully!';
          this.toast.type = 'success';
          this.toast.visible = true;
          
          setTimeout(() => {
            this.toast.visible = false;
            location.reload();
          }, 2000);
        } else {
          alert('Upload failed: ' + (result.message || 'Unknown error'));
        }
      } catch (error) {
        console.error('Upload actual visa error:', error);
        alert('An error occurred while uploading the actual visa document.');
      }
    },
    async handleUpload(event) {
      // Deprecated - kept for backwards compatibility
      // Redirect to appropriate handler based on context
      if (this.modals.uploadDocument) {
        this.handleUploadDocument(event);
      } else if (this.modals.addRequirement) {
        this.handleAddRequirement(event);
      }
    }
  }
}