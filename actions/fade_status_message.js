document.addEventListener("DOMContentLoaded", () => {
  const el = document.getElementById("statusMessage");
  if (el) {
    setTimeout(() => {
      el.classList.add("opacity-0", "transition-opacity", "duration-500");
      setTimeout(() => el.remove(), 600);
    }, 2000);
  }
});