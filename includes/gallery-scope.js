function galleryScope() {
  return {
    selectedPhoto: null,
    isAdmin: true, // Inject dynamically via PHP if needed
    days: [],      // Injected from PHP
    confirmDeletePhoto: false,

    updateStatus(status) {
      if (!this.selectedPhoto || this.selectedPhoto.status === status) return;

      fetch(`../actions/update_photo_status.php?id=${this.selectedPhoto.id}&status=${status}`)
        .then(res => {
          if (!res.ok) throw new Error('Failed to update status');
          return res.json();
        })
        .then(data => {
          if (data.success) {
            this.selectedPhoto.status = status;
            this.selectedPhoto.status_class = this.getStatusClass(status);

            // ✅ Optional: show toast
            window.dispatchEvent(new CustomEvent('toast', {
              detail: { status: 'photo_status_updated' }
            }));

            // ✅ Optional: refresh photo modal or gallery
            this.selectedPhoto = null;
          } else {
            throw new Error(data.message || 'Unknown error');
          }
        })
        .catch(err => {
          console.error('Photo status update failed:', err);
          window.dispatchEvent(new CustomEvent('toast', {
            detail: { status: 'photo_status_failed' }
          }));
        });
    },

    deletePhoto(photoId) {
      fetch(`../actions/update_photo_status.php?id=${photoId}&action=delete`)
        .then(res => {
          if (!res.ok) throw new Error('Failed to delete photo');
          return res.json();
        })
        .then(data => {
          if (data.success) {
            // ✅ Show success toast
            window.dispatchEvent(new CustomEvent('toast', {
              detail: { status: 'photo_deleted' }
            }));

            // ✅ Refresh gallery or reload
            location.reload();
          } else {
            throw new Error(data.message || 'Unknown error');
          }
        })
        .catch(err => {
          console.error('Photo deletion failed:', err);
          window.dispatchEvent(new CustomEvent('toast', {
            detail: { status: 'photo_delete_failed' }
          }));
        });
    },

    getStatusClass(status) {
      const map = {
        'Pending': 'bg-yellow-100 text-yellow-700 border border-yellow-300',
        'Rejected': 'bg-red-100 text-red-700 border border-red-300',
        'Approved': 'bg-emerald-100 text-emerald-700 border border-emerald-300'
      };
      return map[status] || 'bg-gray-100 text-gray-600 border border-gray-300';
    }
  };
}