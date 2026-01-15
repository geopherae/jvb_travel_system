function documentsTable() {
    return {
        deleteFileLoading: false,
        modals: {
            upload: false,
            viewer: false,
        },
        toast: {
            visible: false,
            message: '',
        },
        confirmAction: {
            visible: false,
            type: '',
            documentId: null,
            reason: '',
        },
        pendingDocumentUpdate: null,
        fileViewer: {
            id: null,
            path: '',
            name: '',
            type: '',
            mimeType: '',
            status: '',
            adminComments: '',
            uploadedAt: '',
            approvedAt: '',
            updatedBy: '',
            zoom: 1,
        },
        submitDocumentChangesLoading: false,
        hasFileChanged: false,

        // Open file viewer modal
        openFileModal(id, path, name, type, mimeType, status, adminComments, uploadedAt, approvedAt, updatedBy) {
            this.fileViewer = {
                id,
                path,
                name,
                type,
                mimeType,
                status,
                adminComments,
                uploadedAt,
                approvedAt,
                updatedBy,
                zoom: 1,
            };
            this.modals.viewer = true;
            this.hasFileChanged = false;
        },

        // Close file viewer modal
        closeFileModal() {
            this.modals.viewer = false;
        },

        // Save changes to document (approve/reject/status/comments)
        submitDocumentChanges() {
            // If status is being set to Rejected and no reason yet, show modal
            if (
                this.fileViewer.status === 'Rejected' &&
                !this.fileViewer.adminComments.trim()
            ) {
                // Store the current update so we can resume after reason is entered
                this.pendingDocumentUpdate = { ...this.fileViewer };
                this.confirmAction = {
                    visible: true,
                    type: 'reject',
                    documentId: this.fileViewer.id,
                    reason: ''
                };
                return;
            }

            this.submitDocumentChangesLoading = true;

            fetch('../actions/update_client_document.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    id: this.fileViewer.id,
                    file_name: this.fileViewer.name,
                    document_type: this.fileViewer.type,
                    document_status: this.fileViewer.status,
                    admin_comments: this.fileViewer.adminComments
                })
            })
            .then(async response => {
                const data = await response.json();
                if (data.success) {
                    this.toast.message = 'Document changes saved!';
                    this.toast.visible = true;
                    this.modals.viewer = false;
                    setTimeout(() => {
                        this.toast.visible = false;
                        fetch('../components/documents-table.php?client_id=<?= htmlspecialchars($client_id) ?>')
                            .then(res => res.text())
                            .then(html => {
                                // Create a temporary DOM to extract the inner table
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = html;
                                const newContent = tempDiv.querySelector('#documents-table-content');
                                if (newContent) {
                                    document.getElementById('documents-table-content').innerHTML = newContent.innerHTML;
                                } else {
                                    document.getElementById('documents-table-content').innerHTML = html;
                                }
                            });
                    }, 1500);
                } else {
                    this.toast.message = data.error || 'Update failed.';
                    this.toast.visible = true;
                    setTimeout(() => this.toast.visible = false, 2000);
                }
            })
            .catch(() => {
                this.toast.message = 'Network error.';
                this.toast.visible = true;
                setTimeout(() => this.toast.visible = false, 2000);
            })
            .finally(() => {
                this.submitDocumentChangesLoading = false;
            });
        },

        // Open confirmation modal for approve/reject/delete
        confirmAndOpenModal() {
            // Handle rejection from File Viewer "Save Changes"
            if (this.confirmAction.type === 'reject' && this.pendingDocumentUpdate) {
                // Set the adminComments to the entered reason
                this.fileViewer.adminComments = this.confirmAction.reason;
                this.confirmAction.visible = false;
                this.confirmAction.reason = '';
                this.pendingDocumentUpdate = null;
                // Now actually submit the changes
                this.submitDocumentChanges();
                return;
            }

            // Handle delete from File Viewer
            if (this.confirmAction.type === 'delete') {
                this.confirmAction.visible = false;
                this.deleteFileConfirmed(this.confirmAction.documentId);
                return;
            }

            // Approve/Reject from table actions
            let endpoint = this.confirmAction.type === 'approve'
                ? '../actions/approve_document.php'
                : '../actions/reject_document.php';

            let body = `id=${encodeURIComponent(this.confirmAction.documentId)}`;
            if (this.confirmAction.type === 'reject') {
                body += `&reason=${encodeURIComponent(this.confirmAction.reason)}`;
            }

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(async response => {
                const text = await response.text();
                this.toast.message = text || 'An error occurred.';
                this.toast.visible = true;
                this.confirmAction.visible = false;
                this.confirmAction.reason = '';
                setTimeout(() => {
                    this.toast.visible = false;
                    window.location.reload();
                }, 1800);
            })
            .catch(() => {
                this.toast.message = 'Network error.';
                this.toast.visible = true;
                setTimeout(() => this.toast.visible = false, 1800);
            });
        },

        // Show confirmation modal for delete
        deleteFile() {
            this.confirmAction = {
                visible: true,
                type: 'delete',
                documentId: this.fileViewer.id,
                reason: ''
            };
        },

deleteFileConfirmed(documentId) {
  this.deleteFileLoading = true;

  fetch('../components/delete_document.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: documentId })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      this.modals.viewer = false;
      window.location.reload(); // ✅ Toast will appear via status_alert.php
    } else {
      // ✅ Fallback only if backend fails before setting session
      console.warn('Backend failed to set toast status:', data.message);
      window.location.reload(); // Still reload to trigger any session-based fallback
    }
  })
  .catch(err => {
    console.error('Delete request failed:', err);
    window.location.reload(); // ✅ Let backend/session handle the toast
  })
  .finally(() => {
    this.deleteFileLoading = false;
  });
}
    }
}