// Message polling setup

let currentController = null;
let pollingInterval = null;
let pollingIntervalMs = 2000;
let previewRefreshInterval = null;
let lastPreviewRefresh = 0;
let navIndicatorInterval = null;
const PREVIEW_REFRESH_MS = 5000;
const NAV_INDICATOR_CHECK_MS = 5000; // Check Messages navbutton every 5 seconds

async function pollMessages() {
    const maxRetries = 10;
    let retries = 0;

    const findMessageApp = () => {
        const messageAppElement = document.querySelector('[x-data^="messageApp"]');
        return messageAppElement ? Alpine.$data(messageAppElement) : null;
    };

    let messageApp = findMessageApp();
    while (!messageApp && retries < maxRetries) {
        await new Promise(resolve => setTimeout(resolve, 500));
        messageApp = findMessageApp();
        retries++;
        console.log(`Attempt ${retries}/${maxRetries} to find messageApp`);
    }

    if (!messageApp) {
        console.error('Message poller: messageApp Alpine data not found after retries');
        return;
    }

    if (!messageApp.recipientId || !messageApp.userId || !messageApp.userType || !messageApp.recipientType || messageApp.isFetching) {
        return;
    }

    // Prevent overlapping requests
    if (currentController) {
        try { currentController.abort(); } catch (e) {}
    }
    currentController = new AbortController();
    messageApp.isFetching = true;
    try {
        const lastMsg = Array.isArray(messageApp.messages) && messageApp.messages.length > 0
            ? messageApp.messages[messageApp.messages.length - 1]
            : null;
        const sinceId = lastMsg && typeof lastMsg.id === 'number' ? String(lastMsg.id) : '';
        const formattedSince = !sinceId && messageApp.lastFetched
            ? new Date(messageApp.lastFetched).toISOString().slice(0, 19).replace('T', ' ')
            : '';
        const params = new URLSearchParams({
            user_id: messageApp.userId,
            user_type: messageApp.userType,
            recipient_id: messageApp.recipientId,
            recipient_type: messageApp.recipientType,
            thread_id: messageApp.threadId || '',
            since: formattedSince,
            since_id: sinceId
        });

        const res = await fetch(`../api/messages/fetch.php?${params}`, {
            cache: 'no-store',
            signal: currentController.signal
        });
        if (!res.ok) {
            const errorText = await res.text();
            console.error('Polling failed:', res.status, errorText);
            throw new Error(`Polling error: ${res.status}`);
        }

        const newMessages = await res.json();
        if (Array.isArray(newMessages)) {
            const validMessages = newMessages.filter(
                msg => msg && msg.id && typeof msg.id === 'number' && !messageApp.seenMessageIds.has(msg.id)
            );
            if (validMessages.length > 0) {
                messageApp.messages = [...(messageApp.messages || []), ...validMessages];
                validMessages.forEach(msg => messageApp.seenMessageIds.add(msg.id));
                messageApp.lastFetched = validMessages[validMessages.length - 1].created_at;
                // Reset interval on activity when visible
                if (!document.hidden) {
                    pollingIntervalMs = 2000;
                    restartPollingInterval();
                }
                
                // Refresh message previews for sidebar
                if (typeof messageApp.fetchMessagePreviews === 'function') {
                    messageApp.fetchMessagePreviews();
                }
                
                // Update unread indicator after messages are marked as read
                updateSidebarUnreadIndicator();
                
                // Only auto-scroll if user is near bottom (within 100px)
                const container = document.querySelector('.message-list-container, [x-ref="messageList"]');
                if (container) {
                    const isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
                    if (isNearBottom) {
                        messageApp.scrollToBottom();
                    }
                }
            }
        } else {
            console.error('Invalid response format:', newMessages);
            messageApp.messages = messageApp.messages || [];
        }
    } catch (err) {
        console.error('Polling failed:', err.message, {
            userId: messageApp.userId,
            userType: messageApp.userType,
            recipientId: messageApp.recipientId,
            recipientType: messageApp.recipientType,
            lastFetched: messageApp.lastFetched
        });
        // Exponential backoff with jitter on errors
        const jitter = Math.floor(Math.random() * 500);
        pollingIntervalMs = Math.min(pollingIntervalMs * 2, 30000) + jitter;
        restartPollingInterval();
    } finally {
        messageApp.isLoading = false;
        messageApp.isFetching = false;
        currentController = null;
    }
}

function updateSidebarUnreadIndicator() {
    // Check if we have unread messages and update the sidebar red dot
    fetch('../api/messages/unread_count.php', {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        // Find the Messages link in sidebar - it contains 'messages.php' in the href
        const messagesLinks = document.querySelectorAll('a[href*="messages.php"]');
        
        messagesLinks.forEach(link => {
            const redDot = link.querySelector('span.bg-red-500');
            
            if (data.has_unread) {
                // Add red dot if not present
                if (!redDot) {
                    const span = document.createElement('span');
                    span.className = 'absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full';
                    link.classList.add('relative');
                    link.appendChild(span);
                }
            } else {
                // Remove red dot if present
                if (redDot) {
                    redDot.remove();
                }
            }
        });
    })
    .catch(err => console.debug('Error updating unread indicator:', err));
}

function startNavIndicatorCheck() {
    if (navIndicatorInterval) {
        clearInterval(navIndicatorInterval);
    }
    navIndicatorInterval = setInterval(updateSidebarUnreadIndicator, NAV_INDICATOR_CHECK_MS);
}

function stopNavIndicatorCheck() {
    if (navIndicatorInterval) {
        clearInterval(navIndicatorInterval);
        navIndicatorInterval = null;
    }
}
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    pollingInterval = setInterval(pollMessages, pollingIntervalMs);
}

function startPreviewRefresh() {
    if (previewRefreshInterval) {
        clearInterval(previewRefreshInterval);
    }
    previewRefreshInterval = setInterval(async () => {
        const messageAppElement = document.querySelector('[x-data^="messageApp"]');
        const messageApp = messageAppElement ? Alpine.$data(messageAppElement) : null;
        if (messageApp && typeof messageApp.fetchMessagePreviews === 'function') {
            try {
                await messageApp.fetchMessagePreviews();
            } catch (err) {
                console.error('Error refreshing message previews:', err);
            }
        }
        // Update sidebar unread indicator for Messages navbutton
        updateSidebarUnreadIndicator();
    }, PREVIEW_REFRESH_MS);
}

function startPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    pollingIntervalMs = 2000;
    pollMessages();
    restartPollingInterval();
    startPreviewRefresh();
    startNavIndicatorCheck();
    // Update sidebar indicator immediately
    updateSidebarUnreadIndicator();
    console.log('Message polling started');
}

function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
    if (previewRefreshInterval) {
        clearInterval(previewRefreshInterval);
        previewRefreshInterval = null;
    }
    stopNavIndicatorCheck();
    console.log('Message polling stopped');
}

// Handle page visibility to pause/resume polling
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        stopPolling();
        pollingIntervalMs = 5000;
        restartPollingInterval();
    } else {
        stopPolling();
        startPolling();
    }
});

document.addEventListener('alpine:init', () => {
    console.log('Starting message poller from alpine:init');
    startPolling();
});

// Ensure polling starts even if alpine:init fires late
document.addEventListener('DOMContentLoaded', () => {
    if (window.Alpine && !window.pollingStarted) {
        window.pollingStarted = true;
        console.log('Starting message poller from DOMContentLoaded');
        startPolling();
    }
});

// Clean up on page unload
window.addEventListener('beforeunload', () => {
    stopPolling();
    if (currentController) {
        try { currentController.abort(); } catch (e) {}
        currentController = null;
    }
});