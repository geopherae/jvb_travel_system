/**
 * Global Message Received Toast Poller
 * ═══════════════════════════════════════════════════════════════════════════
 * Runs on ALL pages (except messaging pages) and shows toast when messages arrive
 * Batches multiple simultaneous messages to show only once
 * Persists shown messages in sessionStorage to prevent re-showing on reload
 * ═══════════════════════════════════════════════════════════════════════════
 */

let toastedMessageIds = new Set(); // Prevent duplicate toasts in memory
let globalMessagePollingInterval = null;
let pendingToastMessages = []; // Batch messages
let toastBatchTimer = null;

/**
 * Initialize sessionStorage for tracking shown toasts across page reloads
 */
function initSessionStorage() {
  if (!sessionStorage.getItem('shownMessageIds')) {
    sessionStorage.setItem('shownMessageIds', JSON.stringify([]));
  }
}

/**
 * Load previously shown message IDs from sessionStorage
 */
function loadShownMessageIds() {
  try {
    const stored = sessionStorage.getItem('shownMessageIds');
    const ids = stored ? JSON.parse(stored) : [];
    ids.forEach(id => toastedMessageIds.add(id));
  } catch (e) {
    console.debug('Error loading shownMessageIds from sessionStorage');
  }
}

/**
 * Save shown message IDs to sessionStorage
 */
function saveShownMessageIds() {
  try {
    sessionStorage.setItem('shownMessageIds', JSON.stringify(Array.from(toastedMessageIds)));
  } catch (e) {
    console.debug('Error saving shownMessageIds to sessionStorage');
  }
}

/**
 * Don't show toasts on messaging pages (but show on visa dashboard)
 */
function isOnMessagingPage() {
  const pathname = window.location.pathname;
  return pathname.includes('messages.php') || 
         pathname.includes('messages_client.php');
}

/**
 * Truncate text to 3 lines max
 */
function truncateToThreeLines(text, maxCharsPerLine = 60) {
  if (!text) return '';
  const lines = text.split('\n');
  const truncated = lines.slice(0, 3).join('\n');
  
  // If single line is too long, break it
  if (truncated.length > maxCharsPerLine * 3) {
    return truncated.substring(0, maxCharsPerLine * 3) + '...';
  }
  return truncated;
}

/**
 * Show batched toast for messages
 */
function showBatchedToast() {
  if (pendingToastMessages.length === 0) return;
  
  // Only show first message in batch, but note there might be more
  const firstMsg = pendingToastMessages[0];
  const senderName = firstMsg.sender_name || 'Someone';
  const messagePreview = truncateToThreeLines(firstMsg.message_text);
  const moreCount = pendingToastMessages.length - 1;
  const adminPhoto = firstMsg.admin_photo;
  const clientPhoto = firstMsg.client_photo;
  const companionPhoto = firstMsg.companion_photo;
  const createdAt = firstMsg.created_at ? new Date(firstMsg.created_at) : null;
  const createdAtLabel = createdAt && !Number.isNaN(createdAt.getTime())
    ? createdAt.toLocaleString([], { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' })
    : '';
  
  // Create custom toast HTML without emoji
  const toastContainer = document.getElementById('message-toast-container') || (() => {
    const container = document.createElement('div');
    container.id = 'message-toast-container';
    container.className = 'fixed bottom-16 right-10 z-50 flex flex-col gap-2';
    document.body.appendChild(container);
    return container;
  })();
  
  const toastEl = document.createElement('div');
  toastEl.className = 'bg-sky-100 border border-sky-400 text-slate-800 px-4 py-3 rounded-lg shadow-lg w-full min-w-[300px] max-w-[420px] animate-fade-in';
  
  // Build avatar HTML - try admin, then client, then companion, then default image
const avatarHtml = adminPhoto
    ? `<img src="../uploads/admin_photo/${adminPhoto}" alt="${senderName}" class="w-10 h-10 border-2 border-white rounded-full object-cover">`
    : clientPhoto
        ? `<img src="../uploads/client_profiles/${clientPhoto}" alt="${senderName}" class="w-10 h-10 border-2 border-white rounded-full object-cover">`
        : companionPhoto
            ? `<img src="../uploads/client_profiles/${companionPhoto}" alt="${senderName}" class="w-10 h-10 border-2 border-white rounded-full object-cover">`
            : `<img src="../images/default_client_profile.png" alt="Default" class="w-10 h-10 border-2 border-white rounded-full object-cover">`;
  
  let html = `<div class="flex items-start justify-between gap-3">
    <div class="flex items-start gap-3 flex-1 min-w-0">
      <div class="flex-shrink-0">
        ${avatarHtml}
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-[10px] text-slate-500 font-semibold mb-1">NEW MESSAGE</div>
        <div class="text-sky-800 font-bold text-sm">${senderName} <span class="text-sky-800 font-bold text-xs" >says:</span></div>
        <div class="text-sm whitespace-pre-wrap break-words line-clamp-2">${messagePreview}</div>
        ${moreCount > 0 ? `<div class="text-xs mt-2 text-slate-400">+${moreCount} more message${moreCount > 1 ? 's' : ''}</div>` : ''}
        ${createdAtLabel ? `<div class="text-[11px] mt-2 text-sky-800 text-right">${createdAtLabel}</div>` : ''}
      </div>
    </div>
    <button class="flex-shrink-0 text-slate-400 hover:text-slate-600 transition-colors" title="Close">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
      </svg>
    </button>
  </div>`;
  
  toastEl.innerHTML = html;
  
  // Close button functionality
  const closeBtn = toastEl.querySelector('button');
  closeBtn.addEventListener('click', () => {
    toastEl.style.opacity = '0';
    toastEl.style.transition = 'opacity 0.3s ease-out';
    setTimeout(() => toastEl.remove(), 300);
  });
  
  toastContainer.appendChild(toastEl);
  
  // Play message alert sound
  const audioElement = new Audio('../assets/alert_message.mp3');
  audioElement.volume = 0.2; // Set volume to 50%
  audioElement.play().catch(err => {
    console.debug('Audio play failed:', err);
  });
  
  // Auto-remove after 15 seconds
  setTimeout(() => {
    if (toastEl.parentElement) {
      toastEl.style.opacity = '0';
      toastEl.style.transition = 'opacity 0.3s ease-out';
      setTimeout(() => toastEl.remove(), 300);
    }
  }, 15000);
  
  // Clear batch
  pendingToastMessages = [];
  toastBatchTimer = null;
}

/**
 * Poll for new messages across all conversations
 */
async function pollNewMessagesGlobally() {
  try {
    // Skip if on messaging page
    if (isOnMessagingPage()) return;
    
    // Skip if page is hidden
    if (document.hidden) return;
    
    const res = await fetch('../api/messages/check_new_messages.php', {
      method: 'GET',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    
    if (!res.ok) return;
    
    const data = await res.json();
    if (!Array.isArray(data) || data.length === 0) return;
    
    // Check for new messages not yet toasted
    data.forEach(msg => {
      if (msg.id && !toastedMessageIds.has(msg.id)) {
        toastedMessageIds.add(msg.id);
        pendingToastMessages.push(msg);
      }
    });
    
    // Save to sessionStorage to persist across page reloads
    saveShownMessageIds();
    
    // If we have pending messages, show them in 500ms (batches rapid messages)
    if (pendingToastMessages.length > 0) {
      if (toastBatchTimer) clearTimeout(toastBatchTimer);
      toastBatchTimer = setTimeout(showBatchedToast, 500);
    }
    
  } catch (err) {
    // Silently fail - JSON parse error means auth/redirect, not a real error
    console.debug('[message_received_toast_poller] Skipping - likely auth issue or not logged in');
  }
}

/**
 * Start polling when DOM is ready
 */
function startGlobalMessagePolling() {
  // Only start if not already running
  if (globalMessagePollingInterval) return;
  
  // Skip on messaging pages
  if (isOnMessagingPage()) return;
  
  // Initialize sessionStorage and load previously shown messages
  initSessionStorage();
  loadShownMessageIds();
  
  // Check every 5 seconds for new messages
  globalMessagePollingInterval = setInterval(pollNewMessagesGlobally, 5000);
  
  // Initial check on startup
  pollNewMessagesGlobally();
}

/**
 * Stop polling
 */
function stopGlobalMessagePolling() {
  if (globalMessagePollingInterval) {
    clearInterval(globalMessagePollingInterval);
    globalMessagePollingInterval = null;
  }
  if (toastBatchTimer) {
    clearTimeout(toastBatchTimer);
    toastBatchTimer = null;
  }
}

// Auto-start when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startGlobalMessagePolling);
} else {
  startGlobalMessagePolling();
}

// Cleanup on page unload
window.addEventListener('beforeunload', stopGlobalMessagePolling);

// Resume polling when page regains focus
document.addEventListener('visibilitychange', () => {
  if (!document.hidden && !globalMessagePollingInterval && !isOnMessagingPage()) {
    startGlobalMessagePolling();
  }
});
