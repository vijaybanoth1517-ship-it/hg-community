class SettingsManager {
    constructor() {
        this.settings = {};
        this.init();
    }
    
    init() {
        this.loadSettings();
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        document.getElementById('save-settings').addEventListener('click', () => {
            this.saveSettings();
        });
    }
    
    async loadSettings() {
        try {
            const response = await fetch('api/settings.php');
            const data = await response.json();
            
            if (data.success) {
                this.settings = data.settings;
                this.renderSettings();
            }
        } catch (error) {
            console.error('Error loading settings:', error);
        }
    }
    
    renderSettings() {
        // Set checkbox states based on loaded settings
        const modsCanInvite = this.getSetting('mods_can_invite', false);
        const membersCanPost = this.getSetting('members_can_post', true);
        
        document.getElementById('mods-can-invite').checked = modsCanInvite;
        document.getElementById('members-can-post').checked = membersCanPost;
    }
    
    getSetting(name, defaultValue) {
        const setting = this.settings.find(s => s.name === name);
        return setting ? setting.value === 'true' : defaultValue;
    }
    
    async saveSettings() {
        const modsCanInvite = document.getElementById('mods-can-invite').checked;
        const membersCanPost = document.getElementById('members-can-post').checked;
        
        try {
            await this.updateSetting('mods_can_invite', modsCanInvite);
            await this.updateSetting('members_can_post', membersCanPost);
            
            this.showNotification('Settings saved successfully', 'success');
        } catch (error) {
            console.error('Error saving settings:', error);
            this.showNotification('Error saving settings', 'error');
        }
    }
    
    async updateSetting(name, value) {
        const response = await fetch('api/settings.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                setting: name,
                value: value.toString()
            })
        });
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message);
        }
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

const settingsManager = new SettingsManager();