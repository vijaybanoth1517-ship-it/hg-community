class CommunityApp {
    constructor() {
        this.currentChannelId = null;
        this.channels = [];
        this.messages = [];
        this.onlineUsers = [];
        this.messageUpdateInterval = null;
        this.usersUpdateInterval = null;
        
        this.init();
    }
    
    init() {
        this.loadChannels();
        this.loadOnlineUsers();
        this.setupEventListeners();
        this.startAutoRefresh();
    }
    
    setupEventListeners() {
        // Message form
        document.getElementById('message-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
        
        // File input
        document.getElementById('file-btn').addEventListener('click', () => {
            document.getElementById('file-input').click();
        });
        
        document.getElementById('file-input').addEventListener('change', (e) => {
            this.handleFileSelect(e);
        });
        
        // Upload file button
        document.getElementById('upload-file-btn').addEventListener('click', () => {
            document.getElementById('file-input').click();
        });
        
        // Logout
        document.getElementById('logout-btn').addEventListener('click', () => {
            this.logout();
        });
        
        // Modal controls
        this.setupModalControls();
        
        // Auto-scroll message input
        document.getElementById('message-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('message-form').dispatchEvent(new Event('submit'));
            }
        });
    }
    
    logout() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'api/auth.php?action=logout';
        }
    }
    
    setupModalControls() {
        // Create channel modal
        const createChannelBtn = document.getElementById('create-channel-btn');
        const createChannelModal = document.getElementById('create-channel-modal');
        const createChannelForm = document.getElementById('create-channel-form');
        
        if (createChannelBtn) {
            createChannelBtn.addEventListener('click', () => {
                createChannelModal.style.display = 'block';
            });
            
            createChannelForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createChannel();
            });
        }
        
        // Create invite modal
        const createInviteBtn = document.getElementById('create-invite-btn');
        const createInviteModal = document.getElementById('create-invite-modal');
        const createInviteForm = document.getElementById('create-invite-form');
        
        if (createInviteBtn) {
            createInviteBtn.addEventListener('click', () => {
                createInviteModal.style.display = 'block';
                document.getElementById('invite-result').style.display = 'none';
            });
            
            createInviteForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createInvite();
            });
        }
        
        // Channel type change
        const channelTypeSelect = document.getElementById('channel-type');
        if (channelTypeSelect) {
            channelTypeSelect.addEventListener('change', (e) => {
                const teamNameGroup = document.getElementById('team-name-group');
                if (e.target.value === 'team') {
                    teamNameGroup.style.display = 'block';
                    document.getElementById('team-name').required = true;
                } else {
                    teamNameGroup.style.display = 'none';
                    document.getElementById('team-name').required = false;
                }
            });
        }
        
        // Close modals
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.addEventListener('click', (e) => {
                e.target.closest('.modal').style.display = 'none';
            });
        });
        
        // Click outside modal to close
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
    }
    
    async loadChannels() {
        try {
            const response = await fetch('api/channels.php');
            const data = await response.json();
            
            if (data.success) {
                this.channels = data.channels;
                this.renderChannels();
            } else {
                console.error('Failed to load channels:', data.message);
            }
        } catch (error) {
            console.error('Error loading channels:', error);
        }
    }
    
    renderChannels() {
        const containers = {
            'announcement': document.getElementById('announcement-channels'),
            'team': document.getElementById('team-channels'),
            'technical': document.getElementById('technical-channels'),
            'general': document.getElementById('general-channels')
        };
        
        // Clear containers
        Object.values(containers).forEach(container => {
            if (container) container.innerHTML = '';
        });
        
        this.channels.forEach(channel => {
            const channelElement = this.createChannelElement(channel);
            const container = containers[channel.type];
            if (container) {
                container.appendChild(channelElement);
            }
        });
    }
    
    createChannelElement(channel) {
        const channelEl = document.createElement('div');
        channelEl.className = 'channel-item';
        channelEl.dataset.channelId = channel.id;
        
        let icon = '💬';
        switch (channel.type) {
            case 'announcement': icon = '📢'; break;
            case 'team': icon = '👥'; break;
            case 'technical': icon = '💻'; break;
            case 'general': icon = '💬'; break;
        }
        
        channelEl.innerHTML = `
            <span class="channel-icon">${icon}</span>
            <span class="channel-name">${channel.name}</span>
        `;
        
        channelEl.addEventListener('click', () => {
            this.selectChannel(channel.id, channel.name);
        });
        
        return channelEl;
    }
    
    selectChannel(channelId, channelName) {
        // Update UI
        document.querySelectorAll('.channel-item').forEach(item => {
            item.classList.remove('active');
        });
        
        document.querySelector(`[data-channel-id="${channelId}"]`).classList.add('active');
        document.getElementById('current-channel').textContent = channelName;
        document.getElementById('current-channel-id').value = channelId;
        document.querySelector('.message-input-container').style.display = 'block';
        
        // Load messages
        this.currentChannelId = channelId;
        this.loadMessages();
    }
    
    async loadMessages() {
        if (!this.currentChannelId) return;
        
        try {
            const response = await fetch(`api/messages.php?channel_id=${this.currentChannelId}`);
            const data = await response.json();
            
            if (data.success) {
                this.messages = data.messages;
                this.renderMessages();
            } else {
                console.error('Failed to load messages:', data.message);
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }
    
    renderMessages() {
        const container = document.getElementById('messages-container');
        
        if (this.messages.length === 0) {
            container.innerHTML = `
                <div class="welcome-message">
                    <h2>Welcome to this channel! 👋</h2>
                    <p>Start the conversation by sending a message.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = '';
        
        this.messages.forEach(message => {
            const messageEl = this.createMessageElement(message);
            container.appendChild(messageEl);
        });
        
        // Scroll to bottom
        container.scrollTop = container.scrollHeight;
    }
    
    createMessageElement(message) {
        const messageEl = document.createElement('div');
        messageEl.className = 'message';
        messageEl.dataset.messageId = message.id;
        
        const timestamp = new Date(message.created_at).toLocaleString();
        const avatarSrc = message.avatar || 'assets/images/default-avatar.png';
        
        let fileContent = '';
        if (message.file_path) {
            const fileType = message.file_type?.split('/')[0];
            const fileName = message.file_path.split('/').pop();
            
            switch (fileType) {
                case 'image':
                    fileContent = `
                        <div class="message-file">
                            <img src="${message.file_path}" alt="Image" onclick="window.open('${message.file_path}', '_blank')">
                        </div>
                    `;
                    break;
                case 'video':
                    fileContent = `
                        <div class="message-file">
                            <video controls>
                                <source src="${message.file_path}" type="${message.file_type}">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    `;
                    break;
                case 'audio':
                    fileContent = `
                        <div class="message-file">
                            <audio controls>
                                <source src="${message.file_path}" type="${message.file_type}">
                                Your browser does not support the audio element.
                            </audio>
                        </div>
                    `;
                    break;
                default:
                    fileContent = `
                        <div class="message-file">
                            <div class="file-info">
                                <span class="file-icon">📄</span>
                                <a href="${message.file_path}" target="_blank" style="color: #5865f2;">${fileName}</a>
                            </div>
                        </div>
                    `;
            }
        }
        
        messageEl.innerHTML = `
            <img src="${avatarSrc}" alt="Avatar" class="message-avatar">
            <div class="message-content">
                <div class="message-header">
                    <span class="message-author">${message.username}</span>
                    <span class="role-badge role-${message.role}">${message.role}</span>
                    <span class="message-timestamp">${timestamp}</span>
                </div>
                ${message.content ? `<div class="message-text">${this.formatMessage(message.content)}</div>` : ''}
                ${fileContent}
            </div>
        `;
        
        return messageEl;
    }
    
    formatMessage(content) {
        // Simple message formatting
        return content
            .replace(/\n/g, '<br>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`(.*?)`/g, '<code>$1</code>');
    }
    
    async sendMessage() {
        const form = document.getElementById('message-form');
        const formData = new FormData(form);
        
        if (!formData.get('content') && !formData.get('file').name) {
            return;
        }
        
        try {
            const response = await fetch('api/messages.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Add message to local array and re-render
                this.messages.push(data.message);
                this.renderMessages();
                
                // Clear form
                document.getElementById('message-input').value = '';
                document.getElementById('file-input').value = '';
                document.getElementById('file-preview').style.display = 'none';
            } else {
                alert('Failed to send message: ' + data.message);
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('Error sending message');
        }
    }
    
    handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validate file size (10MB limit)
        if (file.size > 10 * 1024 * 1024) {
            alert('File size must be less than 10MB');
            e.target.value = '';
            return;
        }
        
        // Show preview
        const preview = document.getElementById('file-preview');
        const fileType = file.type.split('/')[0];
        
        let previewContent = '';
        if (fileType === 'image') {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewContent = `<img src="${e.target.result}" alt="Preview">`;
                this.showFilePreview(previewContent, file.name);
            };
            reader.readAsDataURL(file);
        } else {
            previewContent = `<div class="file-info"><span class="file-icon">📄</span></div>`;
            this.showFilePreview(previewContent, file.name);
        }
    }
    
    showFilePreview(content, fileName) {
        const preview = document.getElementById('file-preview');
        preview.innerHTML = `
            ${content}
            <div class="preview-info">
                <div><strong>${fileName}</strong></div>
                <div>Ready to upload</div>
            </div>
            <button type="button" class="remove-file" onclick="app.removeFile()">Remove</button>
        `;
        preview.style.display = 'flex';
    }
    
    removeFile() {
        document.getElementById('file-input').value = '';
        document.getElementById('file-preview').style.display = 'none';
    }
    
    async loadOnlineUsers() {
        try {
            const response = await fetch('api/users.php?online=1');
            const data = await response.json();
            
            if (data.success) {
                this.onlineUsers = data.users;
                this.renderOnlineUsers();
            } else {
                console.error('Failed to load online users:', data.message);
            }
        } catch (error) {
            console.error('Error loading online users:', error);
        }
    }
    
    renderOnlineUsers() {
        const container = document.getElementById('members-list');
        const countElement = document.getElementById('online-count');
        
        countElement.textContent = `${this.onlineUsers.length} online`;
        
        container.innerHTML = '';
        
        this.onlineUsers.forEach(user => {
            const memberEl = this.createMemberElement(user);
            container.appendChild(memberEl);
        });
    }
    
    createMemberElement(user) {
        const memberEl = document.createElement('div');
        memberEl.className = 'member-item';
        memberEl.dataset.userId = user.id;
        
        const avatarSrc = user.avatar || 'assets/images/default-avatar.png';
        
        memberEl.innerHTML = `
            <div class="member-avatar">
                <img src="${avatarSrc}" alt="Avatar">
                <div class="status-indicator online"></div>
            </div>
            <div class="member-info">
                <div class="member-name">${user.username}</div>
                <div class="member-status">${user.role}</div>
            </div>
        `;
        
        return memberEl;
    }
    
    async createChannel() {
        const form = document.getElementById('create-channel-form');
        const formData = new FormData(form);
        
        const channelData = {
            name: formData.get('name'),
            description: formData.get('description'),
            type: formData.get('type'),
            team_name: formData.get('team_name')
        };
        
        // Validate required fields
        if (!channelData.name || !channelData.type) {
            this.showNotification('Channel name and type are required', 'error');
            return;
        }
        
        try {
            const response = await fetch('api/channels.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                body: JSON.stringify(channelData)
            });
            
            // Check if response is ok
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP Error:', response.status, errorText);
                throw new Error(`Server error (${response.status}): ${errorText.substring(0, 100)}`);
            }
            
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response text:', responseText);
                throw new Error('Invalid server response. Check console for details.');
            }
            
            if (data.success) {
                this.showNotification('Channel created successfully!', 'success');
                document.getElementById('create-channel-modal').style.display = 'none';
                form.reset();
                document.getElementById('team-name-group').style.display = 'none';
                this.loadChannels();
            } else {
                this.showNotification('Failed to create channel: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error creating channel:', error);
            this.showNotification('Error creating channel: ' + error.message, 'error');
        }
    }
    
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 3000);
    }
    
    async createInvite() {
        const form = document.getElementById('create-invite-form');
        const formData = new FormData(form);
        
        const inviteData = {
            email: formData.get('email'),
            phone: formData.get('phone'),
            role: formData.get('role'),
            expiry_hours: formData.get('expiry_hours')
        };
        
        try {
            const response = await fetch('api/invites.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(inviteData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('invite-url').value = data.invite_url;
                document.getElementById('invite-code').value = data.invite_code;
                document.getElementById('invite-result').style.display = 'block';
                form.style.display = 'none';
            } else {
                alert('Failed to create invite: ' + data.message);
            }
        } catch (error) {
            console.error('Error creating invite:', error);
            alert('Error creating invite');
        }
    }
    
    startAutoRefresh() {
        // Refresh messages every 5 seconds if channel is selected
        this.messageUpdateInterval = setInterval(() => {
            if (this.currentChannelId) {
                this.loadMessages();
            }
        }, 5000);
        
        // Refresh online users every 30 seconds
        this.usersUpdateInterval = setInterval(() => {
            this.loadOnlineUsers();
        }, 30000);
    }
}

// Utility functions
function copyInviteLink() {
    const inviteUrl = document.getElementById('invite-url');
    inviteUrl.select();
    document.execCommand('copy');
    alert('Invite link copied to clipboard!');
}

// Initialize app
const app = new CommunityApp();

// Make app globally available for onclick handlers
window.app = app;
window.copyInviteLink = copyInviteLink;