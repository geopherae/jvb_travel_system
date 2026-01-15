// ═══════════════════════════════════════════════════════════════════════════
// Enhanced Notification Poller (v2.0)
// ═══════════════════════════════════════════════════════════════════════════
// Lightweight fallback notification system for browsers that don't support
// WebSocket connections. Works in conjunction with includes/notifications.js
// ═══════════════════════════════════════════════════════════════════════════

const toastedIds = new Set(); // 🧠 Prevent duplicate toasts per session

async function pollNotifications() {
  try {
    const res = await fetch('/actions/fetch_notifications.php');
    const data = await res.json();

    if (!Array.isArray(data)) return;

    const unreadActive = data.filter(n => n.status === 'unread' && !n.dismissed);

    for (const note of unreadActive) {
      if (toastedIds.has(note.id)) continue;

      // ✅ Show toast with rich formatting
      if (window.$store && $store.toast) {
        const metadata = note.metadata || {};
        const title = metadata.title || 'Notification';
        
        $store.toast = {
          show: true,
          level: note.priority === 'urgent' ? 'error' : note.priority === 'high' ? 'warning' : 'success',
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

      // 🔄 Auto-refresh for document status changes
      if (note.event_type === 'document_approved' || note.event_type === 'document_rejected') {
        if (window.location.pathname.includes('client_dashboard')) {
          console.log('🔄 Document status changed, reloading page...');
          setTimeout(() => window.location.reload(), 2000);
        }
      }
    }
  } catch (err) {
    console.error('🔁 Notification polling failed:', err);
  }
}

// 🔄 Start polling every 3 seconds (increased frequency for real-time feel)
setInterval(pollNotifications, 3000);

// Initial poll on page load
pollNotifications();