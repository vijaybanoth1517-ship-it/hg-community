class UserManager {
    constructor() {
        this.users = [];
        this.init();
    }
    
    init() {
        this.loadUsers();
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        document.getElementById('refresh-users').addEventListener('click', () => {
            this.loadUsers();
        });
    }
    
    async loadUsers() {
        try {
            const response = await fetch('api/manage-users.php');
            const data = await response.json();
            
            if (data.success) {
                this.users = data.users;
                this.renderUsers();
            } else {
                this.showNotification('Failed to load users: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.showNotification('Error loading users', 'error');
        }
    }
    
    renderUsers() {
        const tbody = document.getElementById('users-tbody');
        tbody.innerHTML = '';
        
        this.users.forEach(user => {
            const row = this.createUserRow(user);
            tbody.appendChild(row);
        });
    }
    
    createUserRow(user) {
        const row = document.createElement('tr');
        
        const canManage = this.canManageUser(user);
        const actionsHtml = canManage ? `
            <button class="action-btn action-remove" onclick="userManager.removeUser(${user.id})">
                Remove
            </button>
        ` : '<span style="color: #949ba4;">No actions</span>';
        
        row.innerHTML = `
            <td>${user.username}</td>
            <td>${user.email}</td>
            <td><span class="role-badge role-${user.role}">${user.role.replace('_', ' ')}</span></td>
            <td><span class="status-${user.status}">${user.status}</span></td>
            <td>${new Date(user.last_active).toLocaleDateString()}</td>
            <td>${actionsHtml}</td>
        `;
        
        return row;
    }
    
    canManageUser(user) {
        if (currentUser.role === 'owner') return user.role !== 'owner' || user.id !== currentUser.id;
        if (currentUser.role === 'co_admin') return !['owner', 'co_admin'].includes(user.role);
        if (currentUser.role === 'trusted_member') return user.role === 'member';
        return false;
    }
    
    async removeUser(userId) {
        if (!confirm('Are you sure you want to remove this user?')) return;
        
        try {
            const response = await fetch('api/manage-users.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'remove',
                    user_id: userId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('User removed successfully', 'success');
                this.loadUsers();
            } else {
                this.showNotification('Failed to remove user: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error removing user:', error);
            this.showNotification('Error removing user', 'error');
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

const userManager = new UserManager();