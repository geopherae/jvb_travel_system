// notification-poller.js

const toastedIds = new Set(); // 🧠 Prevent duplicate toasts per session

async function pollNotifications() {
  try {
    const res = await fetch('/actions/fetch_notifications.php');
    const data = await res.json();

    if (!Array.isArray(data)) return;

    const unreadActive = data.filter(n => n.status === 'unread' && !n.dismissed);

    for (const note of unreadActive) {
      if (toastedIds.has(note.id)) continue;

      // ✅ Show toast
      if (window.$store && $store.toast) {
        $store.toast = {
          show: true,
          level: note.priority || 'success',
          message: `${note.icon || '🔔'} ${note.message}`
        };
      }

      toastedIds.add(note.id);

      // ✅ Mark as read after toast
      fetch('/actions/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(note.id)}`
      });
    }
  } catch (err) {
    console.error('🔁 Notification polling failed:', err);
  }
}

// 🔄 Start polling every 10 seconds
setInterval(pollNotifications, 10000);