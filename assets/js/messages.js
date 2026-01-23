document.addEventListener('alpine:init', () => {
    Alpine.data('messageApp', () => {
        const initData = window.initialData || {};

        return {
            // Initial state from PHP
            isAdmin: initData.isAdmin ?? false,
            isClient: initData.isClient ?? false,
            userId: initData.userId ?? null,
            userType: initData.userType ?? '',
            recipientType: initData.recipientType ?? 'client', // default to client
            recipientId: initData.initialRecipientId ?? null,
            threadId: initData.initialThreadId ?? null,
            clients: initData.clients || [],
            admins: initData.admins || [],

            // Runtime state
            messagePreviews: {},
            messages: [],
            newMessage: '',
            searchQuery: '',
            isLoading: false,
            lastFetched: null,
            isFetching: false,
            seenMessageIds: new Set(),
            fetchTimeout: null,

            init() {
                // Load last selected recipient from localStorage
                const savedRecipient = localStorage.getItem('messageRecipient');
                if (savedRecipient) {
                    const parsed = JSON.parse(savedRecipient);
                    if (parsed.id && parsed.type) {
                        this.recipientId = parsed.id;
                        this.recipientType = parsed.type;
                    }
                }

                console.debug('[messageApp] Initialized with data:', {
                    userId: this.userId,
                    userType: this.userType,
                    recipientId: this.recipientId,
                    threadId: this.threadId,
                    clientCount: this.clients.length
                });

                // Watch for recipient changes
                this.$watch('recipientId', (newVal, oldVal) => {
                    if (newVal && newVal !== oldVal) {
                        console.debug('[messageApp] Switching recipient:', { from: oldVal, to: newVal });
                        this.messages = [];
                        this.seenMessageIds.clear();
                        this.lastFetched = null;
                        this.threadId = null;

                        // Save to localStorage
                        localStorage.setItem('messageRecipient', JSON.stringify({ id: newVal, type: this.recipientType }));

                        this.debounceFetchInitialMessages();
                    }
                });

                // Load previews and start polling
                this.fetchMessagePreviews();
            },

            debounceFetchInitialMessages() {
                if (this.fetchTimeout) clearTimeout(this.fetchTimeout);
                this.fetchTimeout = setTimeout(() => this.fetchInitialMessages(), 300);
            },

            async fetchInitialMessages() {
                if (this.isFetching || !this.recipientId) return;

                this.isFetching = true;
                this.isLoading = true;

                try {
                    const params = new URLSearchParams({
                        user_id: this.userId,
                        user_type: this.userType,
                        recipient_id: this.recipientId,
                        recipient_type: this.recipientType,
                        thread_id: this.threadId || ''
                    });

                    const response = await fetch(`../api/messages/fetch.php?${params}`);
                    const text = await response.text();

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const data = JSON.parse(text);

                    if (Array.isArray(data)) {
                        // Enrich messages with avatars
                        const enriched = data.map(msg => ({
                            ...msg,
                            sender_photo: msg.sender_photo || this.getAvatarUrl(msg)
                        }));

                        this.messages = enriched;
                        this.seenMessageIds = new Set(enriched.map(m => m.id));

                        if (enriched.length > 0) {
                            this.threadId = enriched[0].thread_id;
                            this.lastFetched = enriched[enriched.length - 1].created_at;
                        }

                        this.$nextTick(() => this.scrollToBottom());
                    } else {
                        console.warn('[messageApp] Unexpected response format:', data);
                        this.messages = [];
                    }
                } catch (err) {
                    console.error('[messageApp] Failed to fetch messages:', err);
                    alert('Failed to load messages. Please try again.');
                } finally {
                    this.isLoading = false;
                    this.isFetching = false;
                }
            },

            async sendMessage() {
                const text = this.newMessage.trim();
                if (!text || !this.recipientId) {
                    return;
                }

                this.isLoading = true;

                try {
                    const payload = {
                        user_id: this.userId,
                        user_type: this.userType,
                        recipient_id: this.recipientId,
                        recipient_type: this.recipientType,
                        message_text: text,
                        thread_id: this.threadId || null
                    };

                    const response = await fetch('../api/messages/send.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (!response.ok) {
                        throw new Error('Failed to send message');
                    }

                    const result = await response.json();
                    if (result.id) {
                        // Add the message to the list
                        this.messages.push(result);
                        this.scrollToBottom();
                        
                        // Refresh message previews to update the sidebar
                        this.fetchMessagePreviews();
                    }

                    this.newMessage = '';
                    console.debug('[messageApp] Message sent via REST:', payload);
                } catch (err) {
                    console.error('[messageApp] Failed to send message:', err);
                    alert('Failed to send message. Check your connection.');
                } finally {
                    this.isLoading = false;
                }
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    const container = this.$el.querySelector('#messageContainer');
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                        // Optional: smooth scroll
                        // container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                    }
                });
            },

            get canSendMessage() {
                return this.newMessage.trim().length > 0 &&
                       this.recipientId;
            },

            get filteredClients() {
                const query = this.searchQuery.trim().toLowerCase();
                let clientList = query ? this.clients.filter(client => {
                    const name = (client.full_name || '').toLowerCase();
                    return name.includes(query);
                }) : this.clients;

                // Sort: assigned clients first, then others
                return clientList.sort((a, b) => {
                    const aAssigned = a.assigned_admin_id === this.userId;
                    const bAssigned = b.assigned_admin_id === this.userId;
                    
                    if (aAssigned && !bAssigned) return -1;
                    if (!aAssigned && bAssigned) return 1;
                    return 0; // Keep original order within each group
                });
            },

            get filteredAdmins() {
                const query = this.searchQuery.trim().toLowerCase();
                return query ? this.admins.filter(admin => {
                    const name = (admin.full_name || '').toLowerCase();
                    return name.includes(query);
                }) : this.admins;
            },

            get allRecipients() {
                // Combine clients and admins with type indicator for the UI
                const clientsWithType = this.filteredClients.map(c => ({ ...c, recipientType: 'client' }));
                const adminsWithType = this.filteredAdmins.map(a => ({ ...a, recipientType: 'admin' }));
                return [...clientsWithType, ...adminsWithType];
            },

            get myAssignedClientsCount() {
                return this.filteredClients.filter(c => c.assigned_admin_id === this.userId).length;
            },

            getLastMessagePreview(recipientId, recipientType) {
                const preview = this.messagePreviews[recipientId];
                
                if (!preview) {
                    console.debug('[messageApp] No preview for recipient', recipientId);
                    return 'No messages yet';
                }
                
                if (!preview.message_text) {
                    console.debug('[messageApp] Empty message text for recipient', recipientId);
                    return 'No messages yet';
                }

                const prefix = preview.sent_by_me ? 'You: ' : '';
                let text = preview.message_text || '';
                
                // Handle images in message text (check for image markers or JSON with image data)
                if (text.trim().startsWith('{') || text.includes('[Image]') || text.includes('image')) {
                    try {
                        const parsed = JSON.parse(text);
                        if (parsed.type === 'image' || parsed.image) {
                            return prefix + '[Image]';
                        }
                    } catch (e) {
                        // Not JSON, check for image markers
                        if (text.includes('[Image]')) {
                            return prefix + '[Image]';
                        }
                    }
                }
                
                const maxLength = 40;
                const fullText = prefix + text;
                return fullText.length > maxLength
                    ? fullText.substring(0, maxLength) + '...'
                    : fullText;
            },

            getLastMessageTime(recipientId) {
                const preview = this.messagePreviews[recipientId];
                if (!preview?.created_at) return '';
                
                const date = new Date(preview.created_at);
                const now = new Date();
                const diffMs = now - date;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);
                
                if (diffMins < 1) return 'Now';
                if (diffMins < 60) return `${diffMins}m`;
                if (diffHours < 24) return `${diffHours}h`;
                if (diffDays === 1) return 'Yesterday';
                if (diffDays < 7) return `${diffDays}d`;
                
                // For older messages, show the actual date
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            },

            isAssignedToMe(clientId) {
                const client = this.clients.find(c => c.id === clientId);
                return client?.assigned_admin_id === this.userId;
            },

            async fetchMessagePreviews() {
                if (!this.userId || !this.userType) {
                    console.warn('[messageApp] Cannot fetch previews: missing userId or userType');
                    return;
                }

                try {
                    const params = new URLSearchParams({
                        user_id: this.userId,
                        user_type: this.userType
                    });

                    console.debug('[messageApp] Fetching message previews:', {
                        userId: this.userId,
                        userType: this.userType,
                        url: `../api/messages/preview.php?${params}`
                    });

                    const response = await fetch(`../api/messages/preview.php?${params}`);
                    if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);

                    const data = await response.json();

                    console.debug('[messageApp] Received preview data:', data);

                    if (Array.isArray(data)) {
                        this.messagePreviews = data.reduce((acc, p) => {
                            acc[p.recipient_id] = {
                                thread_id: p.thread_id,
                                recipient_id: p.recipient_id,
                                recipient_type: p.recipient_type,
                                message_text: p.message_text || '',
                                created_at: p.created_at,
                                recipient_name: p.recipient_name || 'Unknown',
                                sent_by_me: p.sent_by_me || false
                            };
                            return acc;
                        }, {});
                        console.debug('[messageApp] Updated messagePreviews:', this.messagePreviews);
                    } else {
                        console.warn('[messageApp] Unexpected response format (not array):', data);
                    }
                } catch (err) {
                    console.error('[messageApp] Failed to load message previews:', err);
                }
            },

            getAvatarUrl(msg) {
                if (!msg?.sender_id || !msg?.sender_type) {
                    return '../images/default_client_profile.png';
                }

                if (msg.sender_type === 'admin') {
                    const admin = this.admins.find(a => a.id === msg.sender_id);
                    return admin?.admin_photo
                        ? `../uploads/admin_photo/${encodeURIComponent(admin.admin_photo)}`
                        : '../images/default_client_profile.png';
                }

                const client = this.clients.find(c => c.id === msg.sender_id);
                return client?.client_profile_photo
                    ? `../uploads/client_profiles/${encodeURIComponent(client.client_profile_photo)}`
                    : '../images/default_client_profile.png';
            },

            getRecipientDetails(id, type) {
                if (!id || !type) return null;

                if (type === 'client') {
                    const client = this.clients.find(c => c.id === Number(id));
                    if (!client) return null;

                    return {
                        name: client.full_name || 'Unknown Client',
                        avatar: client.client_profile_photo
                            ? `../uploads/client_profiles/${encodeURIComponent(client.client_profile_photo)}`
                            : '../images/default_client_profile.png',
                        status: client.status || ''
                    };
                }

                if (type === 'admin') {
                    const admin = this.admins.find(a => a.id === Number(id));
                    if (!admin) {
                        return { name: 'Travel Agent', avatar: '../images/default_client_profile.png', status: '' };
                    }

                    return {
                        name: `${admin.first_name || ''} ${admin.last_name || ''}`.trim() || 'Travel Agent',
                        avatar: admin.admin_photo
                            ? `../uploads/admin_photo/${encodeURIComponent(admin.admin_photo)}`
                            : '../images/default_client_profile.png',
                        status: ''
                    };
                }

                return null;
            }
        };
    });
});