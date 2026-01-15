const Checklist = {
  templateId: 1, // or dynamically set
  clientId: window.CLIENT_ID, // set via PHP or session
  checklist: [],
  currentIndex: 0,

  async loadChecklist() {
    try {
      const res = await fetch(`../actions/get_checklist.php?client_id=${this.clientId}&template_id=${this.templateId}`);
      const json = await res.json();
      this.checklist = json;
      this.currentIndex = this.checklist.findIndex(item => !item.is_completed);
    } catch (err) {
      console.error("Failed to load checklist:", err);
    }
  },

  async markCompleted(statusKey) {
    try {
      const res = await fetch(`../actions/update_checklist_progress.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          client_id: this.clientId,
          template_id: this.templateId,
          status_key: statusKey
        })
      });

      if (res.ok) {
        await this.loadChecklist();
        this.animateSwipe();
      } else {
        alert("Failed to update progress.");
      }
    } catch (err) {
      console.error("Error updating checklist:", err);
    }
  },

  animateSwipe() {
    // Optional: trigger CSS animation or Alpine state change
    console.log("Swipe to next task");
  }
};

document.addEventListener("DOMContentLoaded", () => {
  Checklist.loadChecklist();
});