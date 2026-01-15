// Message polling setup

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

    messageApp.isFetching = true;
    try {
        const formattedSince = messageApp.lastFetched
            ? new Date(messageApp.lastFetched).toISOString().slice(0, 19).replace('T', ' ')
            : '';
        const params = new URLSearchParams({
            user_id: messageApp.userId,
            user_type: messageApp.userType,
            recipient_id: messageApp.recipientId,
            recipient_type: messageApp.recipientType,
            thread_id: messageApp.threadId || '',
            since: formattedSince
        });

        console.log('Polling fetch with params:', params.toString());
        const res = await fetch(`/jvb_travel_system/api/messages/fetch.php?${params}`);
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
                
                // Refresh message previews for sidebar
                if (typeof messageApp.fetchMessagePreviews === 'function') {
                    messageApp.fetchMessagePreviews();
                }
                
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
    } finally {
        messageApp.isLoading = false;
        messageApp.isFetching = false;
    }
}

// Global polling reference
let pollingInterval = null;

function startPolling() {
    // Stop any existing polling
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    
    // Poll every 2 seconds for faster real-time experience
    pollingInterval = setInterval(pollMessages, 2000);
    console.log('Message polling started (interval: 2s)');
}

function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
        console.log('Message polling stopped');
    }
}

// Handle page visibility to pause/resume polling
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        console.log('Page hidden, pausing message polling');
        stopPolling();
        // Resume polling at slower rate (5s) when hidden
        pollingInterval = setInterval(pollMessages, 5000);
    } else {
        console.log('Page visible, resuming message polling');
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
});