<?php
// Script to create missing tables for HG Community
// Run this once: http://localhost/hg-community/setup-missing-tables.php

require_once 'config/database.php';

echo "<h2>HG Community - Creating Missing Tables</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p style='color: green;'>✅ Connected to database successfully!</p>";
    
    // Create missing tables
    $tables = [
        "roles" => "CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            description TEXT,
            level INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "role_permissions" => "CREATE TABLE IF NOT EXISTS role_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_id INT,
            permission VARCHAR(100) NOT NULL,
            granted BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            UNIQUE KEY unique_role_permission (role_id, permission)
        )",
        
        "settings" => "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) UNIQUE NOT NULL,
            value TEXT,
            category VARCHAR(50) DEFAULT 'general',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "audit_logs" => "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_id INT,
            target_id INT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (actor_id) REFERENCES users(id),
            FOREIGN KEY (target_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    ];
    
    echo "<h3>Creating Missing Tables:</h3>";
    foreach ($tables as $tableName => $sql) {
        try {
            $db->exec($sql);
            echo "<p style='color: green;'>✅ Table '$tableName' created successfully</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error creating table '$tableName': " . $e->getMessage() . "</p>";
        }
    }
    
    // Insert default roles
    echo "<h3>Setting up Default Roles:</h3>";
    $defaultRoles = [
        ['admin', 'Administrator', 'Full system administrator with all permissions', 100],
        ['moderator', 'Moderator', 'Can moderate messages and manage channels', 70],
        ['trusted_member', 'Trusted Member', 'Can invite users and manage members', 50],
        ['member', 'Member', 'Default participant with basic permissions', 30],
        ['guest', 'Guest', 'Can read specified channels but cannot post', 10]
    ];
    
    foreach ($defaultRoles as $role) {
        $checkQuery = "SELECT COUNT(*) as count FROM roles WHERE name = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$role[0]]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if (!$exists) {
            $insertQuery = "INSERT INTO roles (name, display_name, description, level) VALUES (?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->execute($role);
            echo "<p style='color: green;'>✅ Role '{$role[1]}' created</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ Role '{$role[1]}' already exists</p>";
        }
    }
    
    // Set up role permissions
    echo "<h3>Setting up Role Permissions:</h3>";
    $rolePermissions = [
        'admin' => [
            'manage_users', 'manage_roles', 'manage_channels', 'manage_settings',
            'create_invites', 'delete_messages', 'ban_users', 'send_message',
            'moderate_users'
        ],
        'moderator' => [
            'delete_messages', 'mute_users', 'send_message', 'moderate_users'
        ],
        'trusted_member' => [
            'create_invites', 'manage_members', 'send_message'
        ],
        'member' => [
            'send_message'
        ],
        'guest' => [
            'read_channels'
        ]
    ];
    
    foreach ($rolePermissions as $roleName => $permissions) {
        // Get role ID
        $roleQuery = "SELECT id FROM roles WHERE name = ?";
        $roleStmt = $db->prepare($roleQuery);
        $roleStmt->execute([$roleName]);
        $roleData = $roleStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($roleData) {
            $roleId = $roleData['id'];
            
            foreach ($permissions as $permission) {
                $checkPermQuery = "SELECT COUNT(*) as count FROM role_permissions WHERE role_id = ? AND permission = ?";
                $checkPermStmt = $db->prepare($checkPermQuery);
                $checkPermStmt->execute([$roleId, $permission]);
                $permExists = $checkPermStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                
                if (!$permExists) {
                    $insertPermQuery = "INSERT INTO role_permissions (role_id, permission) VALUES (?, ?)";
                    $insertPermStmt = $db->prepare($insertPermQuery);
                    $insertPermStmt->execute([$roleId, $permission]);
                }
            }
            echo "<p style='color: green;'>✅ Permissions set for role '$roleName'</p>";
        }
    }
    
    // Insert default settings
    echo "<h3>Setting up Default Settings:</h3>";
    $defaultSettings = [
        ['mods_can_invite', 'true', 'permissions', 'Allow moderators to create invites'],
        ['members_can_post', 'true', 'permissions', 'Allow members to post in general channels'],
        ['max_file_size', '10485760', 'uploads', 'Maximum file upload size in bytes (10MB)'],
        ['allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,mp4,mp3', 'uploads', 'Allowed file extensions']
    ];
    
    foreach ($defaultSettings as $setting) {
        $checkQuery = "SELECT COUNT(*) as count FROM settings WHERE name = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$setting[0]]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if (!$exists) {
            $insertQuery = "INSERT INTO settings (name, value, category, description) VALUES (?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->execute($setting);
            echo "<p style='color: green;'>✅ Setting '{$setting[0]}' created</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ Setting '{$setting[0]}' already exists</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3>Database Setup Complete! 🎉</h3>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Delete this file for security</li>";
    echo "<li><a href='index.php'>Go back to HG Community</a></li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Setup Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
h2, h3 { color: #333; }
ol, ul { margin-left: 20px; }
a { color: #667eea; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>