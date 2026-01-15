function photoGalleryComponent(daysData) {
  return {
    // 📅 Gallery Data
    days: daysData,

    // 📷 Selected Photo for Modal
    selectedPhoto: null,

    // 📤 Upload Modal State
    uploadDay: null,
    uploadFile: null,
    uploadPreview: null,
    uploadCaption: "",
    uploadPackageId: null,

    

    // 📷 Handle file input
    handleFileUpload(event) {
      const file = event.target.files[0];
      if (!file) return;
      this.uploadFile = file;
      this.uploadPreview = URL.createObjectURL(file);
    },

    // 💾 Save client-side photo edits
    async savePhotoDetails() {
      if (!this.selectedPhoto) return;

      const payload = {
        photo_id: this.selectedPhoto.id,
        caption: this.selectedPhoto.caption,
        assigned_package_id: this.selectedPhoto.assigned_package_id,
        tags: []
      };

      try {
        const res = await fetch("../actions/update_photo_details.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (res.ok && json.success) {
          this.selectedPhoto = null;
          await this.refreshGallery();
        } else {
          alert("Server error: " + (json.error || "Unknown issue"));
        }
      } catch (err) {
        console.error("Error saving photo details:", err);
        alert("Network error while saving changes.");
      }
    },

    // 🚀 Submit new photo upload
    async submitUpload() {
      const formData = new FormData();
      formData.append("photo", this.uploadFile);
      formData.append("day", this.uploadDay);
      formData.append("caption", this.uploadCaption);
      // scope removed
      formData.append("assigned_package_id", this.uploadPackageId);

      try {
        const res = await fetch("../actions/process_client_upload_trip_photo.php", {
          method: "POST",
          body: formData
        });

        if (res.ok) {
          this.resetUploadState();
          await this.refreshGallery();
        } else {
          alert("Upload failed.");
        }
      } catch (err) {
        console.error("Error uploading photo:", err);
        alert("Network error while uploading.");
      }
    },

    // 🔄 Refresh gallery data
    async refreshGallery() {
      try {
        const res = await fetch("../actions/client_trip_gallery_data.php");
        const json = await res.json();

        if (!json || !Array.isArray(json.days)) {
          console.warn("Gallery response malformed:", json);
          this.days = [];
          return;
        }

        this.days = json.days;
        console.log("Gallery days:", this.days);
      } catch (err) {
        console.error("Gallery refresh failed:", err);
        this.days = [];
      }
    },

    // 🧼 Reset upload modal state
    resetUploadState() {
      this.uploadPreview = null;
      this.uploadFile = null;
      this.uploadCaption = "";
      this.uploadPackageId = null;
    }
  };
}