<?php
session_start();
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($username, $password) {
        $query = "SELECT id, username, email, password, role, status FROM users WHERE username = :username OR email = :username";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row['status'] == 'banned') {
                return ['success' => false, 'message' => 'Your account has been banned.'];
            }
            
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];
                
                // Update last active
                $updateQuery = "UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE id = :id";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $row['id']);
                $updateStmt->execute();
                
                // Log login activity
                $this->logActivity($row['id'], null, 'user_login', 'User logged in');
                
                return ['success' => true, 'user' => $row];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid credentials.'];
    }
    
    public function register($username, $email, $phone, $password, $inviteCode = null) {
        // Check if invite code is valid
        if ($inviteCode) {
            $inviteQuery = "SELECT id, role FROM invites WHERE invite_code = :code AND expires_at > NOW() AND used_at IS NULL";
            $inviteStmt = $this->db->prepare($inviteQuery);
            $inviteStmt->bindParam(':code', $inviteCode);
            $inviteStmt->execute();
            
            if ($inviteStmt->rowCount() == 0) {
                return ['success' => false, 'message' => 'Invalid or expired invite code.'];
            }
            
            $invite = $inviteStmt->fetch(PDO::FETCH_ASSOC);
            $role = $invite['role'];
        } else {
            $role = 'member';
        }
        
        // Check if username or email already exists
        $checkQuery = "SELECT id FROM users WHERE username = :username OR email = :email";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':username', $username);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Username or email already exists.'];
        }
        
        // Create user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insertQuery = "INSERT INTO users (username, email, phone, password, role) VALUES (:username, :email, :phone, :password, :role)";
        $insertStmt = $this->db->prepare($insertQuery);
        $insertStmt->bindParam(':username', $username);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->bindParam(':phone', $phone);
        $insertStmt->bindParam(':password', $hashedPassword);
        $insertStmt->bindParam(':role', $role);
        
        if ($insertStmt->execute()) {
            $userId = $this->db->lastInsertId();
            
            // Mark invite as used
            if ($inviteCode) {
                $updateInviteQuery = "UPDATE invites SET used_at = NOW(), used_by = :user_id WHERE invite_code = :code";
                $updateInviteStmt = $this->db->prepare($updateInviteQuery);
                $updateInviteStmt->bindParam(':user_id', $userId);
                $updateInviteStmt->bindParam(':code', $inviteCode);
                $updateInviteStmt->execute();
            }
            
            // Log registration
            $this->logActivity($userId, null, 'user_register', "User registered with role: $role");
            
            return ['success' => true, 'message' => 'Account created successfully.'];
        }
        
        return ['success' => false, 'message' => 'Registration failed.'];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function logout() {
        if ($this->isLoggedIn()) {
            $this->logActivity($_SESSION['user_id'], null, 'user_logout', 'User logged out');
        }
        session_destroy();
        return true;
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $query = "SELECT * FROM users WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getUserPermissions($userId = null) {
        if (!$userId && !$this->isLoggedIn()) {
            return [];
        }
        
        $userId = $userId ?: $_SESSION['user_id'];
        
        $query = "SELECT rp.permission 
                 FROM users u 
                 JOIN roles r ON u.role = r.name 
                 JOIN role_permissions rp ON r.id = rp.role_id 
                 WHERE u.id = ? AND rp.granted = TRUE";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId]);
        
        $permissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[] = $row['permission'];
        }
        
        return $permissions;
    }
    
    public function canPostInChannel($channelId, $userId = null) {
        if (!$userId && !$this->isLoggedIn()) {
            return false;
        }
        
        $userId = $userId ?: $_SESSION['user_id'];
        
        // Get user role
        $userQuery = "SELECT role FROM users WHERE id = ?";
        $userStmt = $this->db->prepare($userQuery);
        $userStmt->execute([$userId]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) return false;
        
        // Check channel ACL
        $aclQuery = "SELECT can_post FROM channel_acl WHERE channel_id = ? AND role_name = ?";
        $aclStmt = $this->db->prepare($aclQuery);
        $aclStmt->execute([$channelId, $userData['role']]);
        $aclData = $aclStmt->fetch(PDO::FETCH_ASSOC);
        
        return $aclData ? (bool)$aclData['can_post'] : false;
    }
    
    public function canReadChannel($channelId, $userId = null) {
        if (!$userId && !$this->isLoggedIn()) {
            return false;
        }
        
        $userId = $userId ?: $_SESSION['user_id'];
        
        // Get user role
        $userQuery = "SELECT role FROM users WHERE id = ?";
        $userStmt = $this->db->prepare($userQuery);
        $userStmt->execute([$userId]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) return false;
        
        // Check channel ACL
        $aclQuery = "SELECT can_read FROM channel_acl WHERE channel_id = ? AND role_name = ?";
        $aclStmt = $this->db->prepare($aclQuery);
        $aclStmt->execute([$channelId, $userData['role']]);
        $aclData = $aclStmt->fetch(PDO::FETCH_ASSOC);
        
        return $aclData ? (bool)$aclData['can_read'] : true; // Default to true for backward compatibility
    }
    
    public function hasPermission($permission, $channelId = null) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $permissions = $this->getUserPermissions();
        return in_array($permission, $permissions);
    }
    
    public function logActivity($actorId, $targetId, $action, $details, $ipAddress = null) {
        try {
            $ipAddress = $ipAddress ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            $query = "INSERT INTO audit_logs (actor_id, target_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$actorId, $targetId, $action, $details, $ipAddress]);
        } catch (Exception $e) {
            // Log error but don't break the main flow
            error_log("Audit log error: " . $e->getMessage());
        }
    }
    
    public function getRoleLevel($role) {
        $query = "SELECT level FROM roles WHERE name = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$role]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['level'] : 0;
    }
    
    public function canManageUser($actorId, $targetId) {
        if ($actorId == $targetId) return false; // Can't manage yourself
        
        $actorQuery = "SELECT role FROM users WHERE id = ?";
        $actorStmt = $this->db->prepare($actorQuery);
        $actorStmt->execute([$actorId]);
        $actorData = $actorStmt->fetch(PDO::FETCH_ASSOC);
        
        $targetQuery = "SELECT role FROM users WHERE id = ?";
        $targetStmt = $this->db->prepare($targetQuery);
        $targetStmt->execute([$targetId]);
        $targetData = $targetStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$actorData || !$targetData) return false;
        
        $actorLevel = $this->getRoleLevel($actorData['role']);
        $targetLevel = $this->getRoleLevel($targetData['role']);
        
        // Can only manage users with lower role level
        if ($actorLevel > $targetLevel) {
            return true;
        }
        
        // Special case: trusted_member can manage members only
        if ($actorData['role'] === 'trusted_member' && $targetData['role'] === 'member') {
            return true;
        }
        
        return false;
    }
}
?>