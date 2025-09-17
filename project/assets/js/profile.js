class ProfileManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.loadProfile();
    }
    
    setupEventListeners() {
        document.getElementById('profile-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveProfile();
        });
        
        document.querySelector('.avatar-container').addEventListener('click', () => {
            document.getElementById('avatar-input').click();
        });
        
        document.getElementById('avatar-input').addEventListener('change', (e) => {
            this.handleAvatarUpload(e);
        });
    }
    
    async loadProfile() {
        try {
            const response = await fetch(`api/profile.php?user_id=${currentUser.id}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateForm(data.user);
            }
        } catch (error) {
            console.error('Error loading profile:', error);
        }
    }
    
    populateForm(user) {
        document.getElementById('display-name').value = user.display_name || user.username;
        document.getElementById('email').value = user.email || '';
        document.getElementById('phone').value = user.phone || '';
        document.getElementById('bio').value = user.bio || '';
        document.getElementById('timezone').value = user.timezone || 'UTC';
        
        if (user.avatar) {
            document.getElementById('profile-avatar').src = user.avatar;
        }
    }
    
    async saveProfile() {
        const formData = new FormData(document.getElementById('profile-form'));
        const profileData = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch('api/profile.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(profileData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Profile updated successfully', 'success');
                // Update header avatar if changed
                this.updateHeaderAvatar();
            } else {
                this.showNotification('Failed to update profile: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error saving profile:', error);
            this.showNotification('Error saving profile', 'error');
        }
    }
    
    async handleAvatarUpload(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        if (file.size > 5 * 1024 * 1024) {
            this.showNotification('File size must be less than 5MB', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('avatar', file);
        
        try {
            const response = await fetch('api/profile.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('profile-avatar').src = data.avatar_url;
                this.showNotification('Profile picture updated', 'success');
                this.updateHeaderAvatar();
            } else {
                this.showNotification('Failed to upload avatar: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error uploading avatar:', error);
            this.showNotification('Error uploading avatar', 'error');
        }
    }
    
    updateHeaderAvatar() {
        // Update avatar in main chat if user returns there
        const avatarSrc = document.getElementById('profile-avatar').src;
        localStorage.setItem('updated_avatar', avatarSrc);
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 3000);
    }
}

const profileManager = new ProfileManager();