<script>
  document.addEventListener('alpine:init', () => {
    Alpine.data('fileViewer', () => ({
      showFileViewer: false,
      fileViewerUrl: '',
      isLoading: false,
      copied: false,

      openFileViewer(filePath) {
        // Optional type check (PDF, image only)
        if (!/\.(pdf|jpg|jpeg|png)$/i.test(filePath)) {
          alert('Unsupported file format');
          return;
        }

        this.isLoading = true;
        this.fileViewerUrl = filePath;
        this.showFileViewer = true;

        // Simulate brief loading indicator
        setTimeout(() => this.isLoading = false, 300);
      },

      closeFileViewer() {
        this.showFileViewer = false;
        this.fileViewerUrl = '';
        this.isLoading = false;
      },

      copyUrlToClipboard() {
        if (!this.fileViewerUrl) return;
        navigator.clipboard.writeText(this.fileViewerUrl).then(() => {
          this.copied = true;
          setTimeout(() => this.copied = false, 1500);
        });
      }
    }));
  });
</script>