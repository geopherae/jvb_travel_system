document.addEventListener('DOMContentLoaded', () => {
  const stack = document.getElementById('notifications-card-stack');
  const overlay = document.getElementById('notification-overlay');
  const overlayList = document.getElementById('notification-overlay-list');
  const bellBtn = document.getElementById('toggle-notification-overlay');
  const refreshBtn = document.getElementById('refresh-notifications');
  const bellIndicator = document.querySelector('#notification-bell .unread-indicator');

  let cachedNotifications = [];

  // 🧠 Safe JSON parser
  function safeJsonParse(text) {
    try {
      return JSON.parse(text);
    } catch (err) {
      console.error('❌ JSON parse failed:', err, text);
      return null;
    }
  }

  // 🔔 Create notification card
  function createNotificationCard(note) {
    const card = document.createElement('div');
    card.className = 'group flex items-start gap-3 p-2 pl-6 rounded-lg bg-white hover:bg-sky-100 transition-colors relative';
    card.dataset.id = note.id;

    const icon = note.icon || '🔔';
    const time = new Date(note.created_at).toLocaleString();
    const ago = getTimeAgo(note.created_at);
    const isUnread = note.status === 'unread';

    // 🔘 Unread dot
    const unreadDot = isUnread
      ? `<span class="absolute left-2 top-1/2 -translate-y-1/2 w-2 h-2 bg-sky-500 rounded-full" data-unread></span>`
      : '';

    // ❌ Dismiss button
    const dismissBtn = document.createElement('button');
    dismissBtn.className = 'absolute top-2 right-2 text-gray-400 hover:text-gray-600 text-base opacity-0 group-hover:opacity-100 transition-opacity';
    dismissBtn.innerHTML = '&times;';
    dismissBtn.title = 'Dismiss';

    dismissBtn.addEventListener('click', (e) => {
      e.stopPropagation();

      fetch('../actions/dismiss_notification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(note.id)}`
      })
        .then(res => res.text())
        .then(text => {
          const response = safeJsonParse(text);
          if (response?.success) {
            card.remove();
            cachedNotifications = cachedNotifications.filter(n => n.id !== note.id);
            updateBellIndicator();
          } else {
            console.error('Dismiss failed:', response?.error || 'Unknown error');
          }
        })
        .catch(err => {
          console.error('Dismiss request failed:', err);
        });
    });

    // 🧱 Card content
    card.innerHTML = `
      ${unreadDot}
      <div class="flex-shrink-0 mt-1 text-sky-600">${icon}</div>
      <div class="flex flex-col pr-6">
        <p class="text-sm text-gray-800 leading-snug">${note.message}</p>
        <span class="text-xs text-gray-500 mt-1">
          <time title="${time}">${ago}</time>
        </span>
      </div>
    `;

    card.appendChild(dismissBtn);
    card.addEventListener('mouseenter', () => markAsRead(card, note.id));
    card.addEventListener('click', () => markAsRead(card, note.id));

    return card;
  }

  // ✅ Mark notification as read
  function markAsRead(card, id) {
    const dot = card.querySelector('[data-unread]');
    if (!dot) return;

    fetch('../actions/mark_notification_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${encodeURIComponent(id)}`
    })
      .then(() => {
        dot.remove();
        cachedNotifications = cachedNotifications.map(n =>
          n.id === id ? { ...n, status: 'read' } : n
        );
        updateBellIndicator();
      })
      .catch(err => {
        console.error('Failed to mark notification as read:', err);
      });
  }

// 🔁 Render notification list
function renderNotificationList(container, data) {
  if (!container) return;
  container.innerHTML = '';

  // ➕ Add Clear All button at top-right
  const clearBtnWrapper = document.createElement('div');
  clearBtnWrapper.className = 'flex justify-end mb-4';

  const clearBtn = document.createElement('button');
  clearBtn.className = 'text-xs text-sky-600 hover:text-sky-700 px-3 py-1 rounded transition-colors';
  clearBtn.textContent = 'Clear All';
  clearBtn.title = 'Dismiss all notifications';

  clearBtn.addEventListener('click', () => {
    const ids = data.map(n => n.id);
    if (ids.length === 0) return;

    fetch('../actions/dismiss_bulk_notifications.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `ids=${encodeURIComponent(JSON.stringify(ids))}`
    })
      .then(res => res.text())
      .then(text => {
        const response = safeJsonParse(text);
        if (response?.success) {
          container.innerHTML = `
            <div class="flex flex-col items-center justify-center py-8 text-center text-gray-500">
              <div class="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center mb-3">
                <span class="text-sky-500 text-xl">🔔</span>
              </div>
              <p class="text-sm">No notifications yet.</p>
            </div>
          `;
          cachedNotifications = [];
          updateBellIndicator();
        } else {
          console.error('Bulk dismiss failed:', response?.error || 'Unknown error');
        }
      })
      .catch(err => {
        console.error('Bulk dismiss request failed:', err);
      });
  });

  clearBtnWrapper.appendChild(clearBtn);
  container.appendChild(clearBtnWrapper);

  // 🔔 Render notifications
  if (!Array.isArray(data) || data.length === 0) return;

  data.forEach(note => container.appendChild(createNotificationCard(note)));
}


  // 🔄 Fetch notifications
  function fetchNotifications() {
    fetch('../actions/fetch_notifications.php')
      .then(res => res.text())
      .then(text => {
        const data = safeJsonParse(text);
        if (!data) return;

        // 🔄 Check for new document status notifications
        const newDocNotifications = data.filter(n => 
          n.status === 'unread' && 
          (n.event_type === 'document_approved' || n.event_type === 'document_rejected') &&
          !cachedNotifications.some(cached => cached.id === n.id)
        );

        // Auto-reload page if new document status notification (to show updated documents)
        if (newDocNotifications.length > 0 && window.location.pathname.includes('client_dashboard')) {
          console.log('🔄 New document status detected, reloading page...');
          // Mark notifications as read before reloading to prevent infinite loop
          newDocNotifications.forEach(note => {
            fetch('../actions/mark_notification_read.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `id=${encodeURIComponent(note.id)}`
            }).catch(err => console.error('Failed to mark notification as read:', err));
          });
          setTimeout(() => window.location.reload(), 2000);
        }

        cachedNotifications = data;
        renderNotificationList(stack, data);
        renderNotificationList(overlayList, data);
        updateBellIndicator();
      })
      .catch(err => {
        console.error('🔔 Notification fetch error:', err);
        const errorMsg = '<p class="text-sm text-red-500">Failed to load notifications.</p>';
        if (stack) stack.innerHTML = errorMsg;
        if (overlayList) overlayList.innerHTML = errorMsg;
      });
  }

  // 🔔 Update bell indicator
  function updateBellIndicator() {
    const hasUnread = cachedNotifications.some(n => n.status === 'unread');
    if (bellIndicator) {
      bellIndicator.style.display = hasUnread ? 'inline-block' : 'none';
    }
  }

  // 🧠 Close overlay on outside click
  document.addEventListener('click', event => {
    if (!overlay || overlay.classList.contains('hidden')) return;
    if (overlay.contains(event.target) || bellBtn?.contains(event.target)) return;
    overlay.classList.add('hidden');
  });

  // 🚀 Init
  fetchNotifications();
  refreshBtn?.addEventListener('click', fetchNotifications);
  bellBtn?.addEventListener('click', () => overlay?.classList.toggle('hidden'));

  // 🔁 Polling (every 3 seconds for faster updates)
  setInterval(fetchNotifications, 3000);
  setInterval(updateBellIndicator, 2000);
});