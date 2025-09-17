<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (!$auth->hasPermission('manage_users')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $query = "SELECT u.id, u.username, u.email, u.role, u.status, u.created_at, u.last_active 
                 FROM users u ORDER BY u.role, u.username";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'users' => $users]);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'];
        $targetId = $data['user_id'];
        $currentUser = $auth->getCurrentUser();
        
        if (!$auth->canManageUser($currentUser['id'], $targetId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot manage this user']);
            exit;
        }
        
        switch ($action) {
            case 'remove':
                if ($currentUser['role'] === 'trusted_member') {
                    $targetQuery = "SELECT role FROM users WHERE id = ?";
                    $targetStmt = $db->prepare($targetQuery);
                    $targetStmt->execute([$targetId]);
                    $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($targetUser['role'] !== 'member') {
                        echo json_encode(['success' => false, 'message' => 'Can only remove members']);
                        exit;
                    }
                }
                
                $deleteQuery = "DELETE FROM users WHERE id = ?";
                $deleteStmt = $db->prepare($deleteQuery);
                if ($deleteStmt->execute([$targetId])) {
                    $auth->logActivity($currentUser['id'], $targetId, 'user_removed', 'User removed from system');
                    echo json_encode(['success' => true, 'message' => 'User removed successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to remove user']);
                }
                break;
                
            case 'change_role':
                if (!$auth->hasPermission('manage_roles')) {
                    echo json_encode(['success' => false, 'message' => 'Cannot change roles']);
                    exit;
                }
                
                $newRole = $data['new_role'];
                $updateQuery = "UPDATE users SET role = ? WHERE id = ?";
                $updateStmt = $db->prepare($updateQuery);
                if ($updateStmt->execute([$newRole, $targetId])) {
                    $auth->logActivity($currentUser['id'], $targetId, 'role_changed', "Role changed to: $newRole");
                    echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update role']);
                }
                break;
        }
        break;
}
?>