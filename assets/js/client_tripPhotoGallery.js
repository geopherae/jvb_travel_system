document.addEventListener('alpine:init', () => {
  Alpine.data('tripPhotoGallery', () => {
    const el = document.querySelector('.trip-photo-gallery');

    return {
      // 📅 Gallery Data
      days: JSON.parse(el.dataset.gallery || '[]'),

      // 📷 Selected Photo
      selectedPhoto: null,

      // 📤 Upload Form State
      uploadDay: null,
      uploadFile: null,
      uploadPreview: null,
      uploadCaption: "",
      uploadPackageId: el.dataset.packageId || null,
      uploadLocationTag: el.dataset.packageName || "",
      clientId: el.dataset.clientId || null,

      // 🧼 UI Feedback
      uploadErrorMessage: "",

      // 🧼 Reset Upload Form
      resetUploadForm() {
        this.uploadDay = null;
        this.uploadFile = null;
        this.uploadPreview = null;
        this.uploadCaption = "";
        this.uploadErrorMessage = "";
        this.uploadLocationTag = el.dataset.packageName || "";
      },


      // 🎨 Status Styling
      getStatusClass(status) {
        return {
          Approved: "bg-emerald-100 text-emerald-700 border border-emerald-300",
          Rejected: "bg-red-100 text-red-700 border border-red-300",
          Pending:  "bg-yellow-100 text-yellow-700 border border-yellow-300"
        }[status] || "bg-gray-100 text-gray-600 border border-gray-300";
      },

      // 📷 Handle File Selection
      handleFileUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        const validTypes = ["image/jpeg", "image/png", "image/webp"];
        if (!validTypes.includes(file.type)) {
          this.uploadErrorMessage = "Please select a valid image file (JPG, PNG, or WebP)";
          return;
        }

        this.uploadFile = file;
        this.uploadPreview = URL.createObjectURL(file);
        this.uploadErrorMessage = "";
      },

      // 💾 Save Caption
      async savePhotoDetails() {
        if (!this.selectedPhoto?.id) return;

        const payload = {
          photo_id: this.selectedPhoto.id,
          caption: this.selectedPhoto.caption?.trim() || "",
          tags: []
        };

        try {
          const res = await fetch("../actions/update_photo_details.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
          });

          const raw = await res.text();
          const data = JSON.parse(raw);

          if (data.success) {
            this.selectedPhoto = null;
            await this.refreshGallery();
          } else {
            this.uploadErrorMessage = data.error || "Failed to save changes";
          }
        } catch (err) {
          console.error("Error saving photo details:", err);
          this.uploadErrorMessage = err.message || "Network error while saving changes.";
        }
      },

      // 🚀 Submit Photo Upload
      async submitUpload() {
        if (!this.uploadFile || !this.uploadDay) {
          this.uploadErrorMessage = "Please select a photo and day";
          return;
        }

        const formData = new FormData();
        formData.append("photo", this.uploadFile);
        formData.append("day", this.uploadDay);
        formData.append("caption", this.uploadCaption.trim());
        // scope removed
        formData.append("location_tag", this.uploadLocationTag.trim());
        formData.append("assigned_package_id", this.uploadPackageId);

        try {
          const res = await fetch("../actions/process_client_upload_trip_photo.php", {
            method: "POST",
            body: formData
          });

          const raw = await res.text();
          const data = JSON.parse(raw);

          if (!res.ok || !data.success) {
            throw new Error(data.error || `Upload failed: ${res.status}`);
          }

          // Show toast immediately after successful upload
          window.dispatchEvent(new CustomEvent("toast", {
            detail: { status: "photo_uploaded" }
          }));

          this.resetUploadForm();
          const refreshed = await this.refreshGallery();

          if (!refreshed) {
            // Show secondary toast if refresh fails
            window.dispatchEvent(new CustomEvent("toast", {
              detail: { status: "gallery_refresh_failed" }
            }));
          }
        } catch (err) {
          console.error("Error uploading photo:", err);
          this.uploadErrorMessage = err.message || "Network error while uploading.";

          window.dispatchEvent(new CustomEvent("toast", {
            detail: { status: "photo_upload_failed" }
          }));
        }
      },

      // 🔄 Refresh Gallery
      async refreshGallery() {
        try {
          const res = await fetch(`../actions/get_trip_photos.php?client_id=${encodeURIComponent(this.clientId)}`, {
            headers: { "Cache-Control": "no-cache" }
          });

          const raw = await res.text();
          const data = JSON.parse(raw);

          if (Array.isArray(data)) {
            this.days = [];
            this.$nextTick(() => {
              this.days = data;
            });
            return true;
          } else {
            console.warn("Gallery data is not an array:", data);
            return false;
          }
        } catch (err) {
          console.error("Gallery refresh failed:", err);
          return false;
        }
      },

      

      // 🧼 Reset Upload State
      resetUploadState() {
        this.uploadPreview = null;
        this.uploadFile = null;
        this.uploadCaption = "";
        this.uploadErrorMessage = "";
      }
    };
  });
});