@@ .. @@
     public function getUserPermissions($userId = null) {
         if (!$userId && !$this->isLoggedIn()) {
             return [];
         }
         
         $userId = $userId ?: $_SESSION['user_id'];
         
-        $query = "SELECT rp.permission 
-                 FROM users u 
-                 JOIN roles r ON u.role = r.name 
-                 JOIN role_permissions rp ON r.id = rp.role_id 
-                 WHERE u.id = ? AND rp.granted = TRUE";
-        $stmt = $this->db->prepare($query);
-        $stmt->execute([$userId]);
-        
-        $permissions = [];
-        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
-            $permissions[] = $row['permission'];
+        try {
+            $query = "SELECT rp.permission 
+                     FROM users u 
+                     JOIN roles r ON u.role = r.name 
+                     JOIN role_permissions rp ON r.id = rp.role_id 
+                     WHERE u.id = ? AND rp.granted = TRUE";
+            $stmt = $this->db->prepare($query);
+            $stmt->execute([$userId]);
+            
+            $permissions = [];
+            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
+                $permissions[] = $row['permission'];
+            }
+            
+            return $permissions;
+        } catch (Exception $e) {
+            // Fallback to basic role-based permissions if tables don't exist
+            $userQuery = "SELECT role FROM users WHERE id = ?";
+            $userStmt = $this->db->prepare($userQuery);
+            $userStmt->execute([$userId]);
+            $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
+            
+            if (!$userData) return [];
+            
+            // Basic permissions based on role
+            $rolePermissions = [
+                'admin' => ['manage_users', 'manage_channels', 'manage_settings', 'create_invites', 'send_message', 'moderate_users'],
+                'moderator' => ['send_message', 'moderate_users'],
+                'trusted_member' => ['send_message', 'create_invites'],
+                'member' => ['send_message'],
+                'guest' => []
+            
-        
        try {
            $query = "SELECT level FROM roles WHERE name = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$role]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['level'] : 0;
        } catch (Exception $e) {
            // Fallback role levels if table doesn't exist
            $roleLevels = [
                'admin' => 100,
                'moderator' => 70,
                'trusted_member' => 50,
                'member' => 30,
                'guest' => 10
            ];
            
            return $roleLevels[$role] ?? 0;
        }
     }