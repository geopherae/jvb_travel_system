document.addEventListener('alpine:init', () => {
    Alpine.data('messageApp', () => {
        const initData = window.initialData || {};

        return {
            // Initial state from PHP
            isAdmin: initData.isAdmin ?? false,
            isClient: initData.isClient ?? false,
            userId: initData.userId ?? null,
            userType: initData.userType ?? '',
            recipientType: initData.recipientType ?? '',
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
            ws: null,
            wsUrl: 'ws://localhost:8080', // Update for production host/port
            enableWebSocket: true,
            wsRetryCount: 0,
            maxWsRetries: 3,

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

                // Load previews and connect if recipient already selected
                this.fetchMessagePreviews();

                if (this.recipientId && this.enableWebSocket) {
                    this.connectWebSocket();
                }

                // Clean up WebSocket on page unload
                window.addEventListener('beforeunload', () => {
                    if (this.ws) this.ws.close();
                });
            },

            connectWebSocket() {
                if (!this.enableWebSocket) {
                    console.debug('[messageApp] WebSocket disabled, using polling only');
                    return;
                }

                // Validate required data
                if (!this.userId || !this.userType || !this.recipientId || !this.recipientType) {
                    console.warn('[messageApp] Cannot connect WebSocket: missing required data');
                    return;
                }

                // Stop retrying if we've exceeded limits
                if (this.wsRetryCount >= this.maxWsRetries) {
                    console.warn('[messageApp] WebSocket retries exceeded; falling back to polling');
                    this.enableWebSocket = false;
                    return;
                }

                // Reuse open connection
                if (this.ws?.readyState === WebSocket.OPEN) {
                    this.subscribeToRecipient();
                    this.debounceFetchInitialMessages();
                    return;
                }

                // Avoid duplicate connections
                if (this.ws?.readyState === WebSocket.CONNECTING) {
                    console.debug('[messageApp] WebSocket already connecting...');
                    return;
                }

                this.isLoading = true;
                this.wsRetryCount++;

                const wsUrl = this.wsUrl;
                console.debug(`[messageApp] Connecting to WebSocket: ${wsUrl}`);

                this.ws = new WebSocket(wsUrl);

                this.ws.onopen = () => {
                    console.debug('[messageApp] WebSocket connected');
                    this.wsRetryCount = 0;
                    this.subscribeToRecipient();
                    this.debounceFetchInitialMessages();
                };

                this.ws.onmessage = (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        console.debug('[messageApp] WS Message:', data);

                        if (data.error) {
                            console.error('[messageApp] WS Server Error:', data.error);
                            return;
                        }

                        if (data.action === 'new_message' && data.message) {
                            const msg = data.message;

                            if (!msg.id || this.seenMessageIds.has(msg.id)) return;

                            // Enrich message with avatar
                            msg.sender_photo = msg.sender_photo || this.getAvatarUrl(msg);

                            this.messages.push(msg);
                            this.seenMessageIds.add(msg.id);
                            this.threadId = msg.thread_id;
                            this.lastFetched = msg.created_at;

                            // Update preview for sidebar
                            this.messagePreviews[msg.recipient_id] = {
                                thread_id: msg.thread_id,
                                recipient_id: msg.recipient_id,
                                recipient_type: msg.recipient_type,
                                message_text: msg.message_text,
                                created_at: msg.created_at,
                                recipient_name: msg.sender_name || 'Unknown'
                            };

                            this.scrollToBottom();
                        }
                    } catch (err) {
                        console.error('[messageApp] Failed to parse WS message:', err);
                    }
                };

                this.ws.onclose = (event) => {
                    console.debug('[messageApp] WebSocket closed', event.code, event.reason);
                    this.ws = null;
                    this.isLoading = false;

                    if (this.wsRetryCount < this.maxWsRetries) {
                        const delay = Math.min(1000 * Math.pow(2, this.wsRetryCount), 30000);
                        console.debug(`[messageApp] Reconnecting in ${delay}ms... (attempt ${this.wsRetryCount + 1})`);
                        setTimeout(() => this.connectWebSocket(), delay);
                    } else {
                        console.warn('[messageApp] Max WebSocket retries reached; using polling only');
                        this.enableWebSocket = false;
                    }
                };

                this.ws.onerror = (error) => {
                    console.error('[messageApp] WebSocket error:', error);
                };
            },

            subscribeToRecipient() {
                if (!this.ws || this.ws.readyState !== WebSocket.OPEN || !this.recipientId) {
                    if (this.recipientId) {
                        setTimeout(() => this.subscribeToRecipient(), 500);
                    }
                    return;
                }

                const payload = {
                    action: 'subscribe',
                    user_id: this.userId,
                    user_type: this.userType,
                    recipient_id: this.recipientId,
                    recipient_type: this.recipientType,
                    thread_id: this.threadId || ''
                };

                this.ws.send(JSON.stringify(payload));
                console.debug('[messageApp] Subscribed to thread:', payload);
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

                    const response = await fetch(`/jvb_travel_system/api/messages/fetch.php?${params}`);
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

                    const response = await fetch('/jvb_travel_system/api/messages/send.php', {
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

            isAssignedToMe(clientId) {
                const client = this.clients.find(c => c.id === clientId);
                return client?.assigned_admin_id === this.userId;
            },

            get myAssignedClientsCount() {
                return this.filteredClients.filter(c => c.assigned_admin_id === this.userId).length;
            },

            getLastMessagePreview(clientId) {
                const preview = this.messagePreviews[clientId];
                if (!preview?.message_text) return 'No messages yet';

                const prefix = preview.sent_by_me ? 'You: ' : '';
                const text = preview.message_text;
                const maxLength = 40;
                
                const fullText = prefix + text;
                return fullText.length > maxLength
                    ? fullText.substring(0, maxLength) + '...'
                    : fullText;
            },

            async fetchMessagePreviews() {
                if (!this.userId || !this.userType) return;

                try {
                    const params = new URLSearchParams({
                        user_id: this.userId,
                        user_type: this.userType
                    });

                    const response = await fetch(`/jvb_travel_system/api/messages/preview.php?${params}`);
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);

                    const data = await response.json();

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
                        ? `../Uploads/admin_photo/${encodeURIComponent(admin.admin_photo)}`
                        : '../images/default_client_profile.png';
                }

                const client = this.clients.find(c => c.id === msg.sender_id);
                return client?.client_profile_photo
                    ? `../Uploads/client_profiles/${encodeURIComponent(client.client_profile_photo)}`
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
                            ? `../Uploads/client_profiles/${encodeURIComponent(client.client_profile_photo)}`
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
                            ? `../Uploads/admin_photo/${encodeURIComponent(admin.admin_photo)}`
                            : '../images/default_client_profile.png',
                        status: ''
                    };
                }

                return null;
            }
        };
    });
});