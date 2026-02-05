// Sidebar unread message indicator - runs on all pages
(function() {
    let updateInterval = null;
    const CHECK_INTERVAL_MS = 10000; // Check every 10 seconds

    function updateSidebarUnreadIndicator() {
        fetch('../api/messages/unread_count.php', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then(data => {
            // Find the Messages link in sidebar - matches both admin and client
            const messagesLinks = document.querySelectorAll('a[href*="messages.php"], a[href*="messages_client.php"]');
            
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
        .catch(err => {
            console.debug('Sidebar unread indicator error:', err);
        });
    }

    function startChecking() {
        // Check immediately on load
        updateSidebarUnreadIndicator();
        
        // Then check periodically
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        updateInterval = setInterval(updateSidebarUnreadIndicator, CHECK_INTERVAL_MS);
    }

    function stopChecking() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }

    // Start checking when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startChecking);
    } else {
        startChecking();
    }

    // Handle page visibility changes
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopChecking();
        } else {
            startChecking();
        }
    });

    // Clean up on page unload
    window.addEventListener('beforeunload', stopChecking);
})();
