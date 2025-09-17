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
        if (isset($_GET['online'])) {
            // Get online users (active in last 5 minutes)
            $query = "SELECT id, username, role, avatar, status 
                     FROM users 
                     WHERE last_active > DATE_SUB(NOW(), INTERVAL 5 MINUTE) 
                     AND status != 'banned' 
                     ORDER BY role, username";
        } else {
            // Get all users
            $query = "SELECT id, username, email, phone, role, status, avatar, created_at, last_active 
                     FROM users 
                     ORDER BY role, username";
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'users' => $users]);
        break;
        
    case 'PUT':
        if (!$auth->hasPermission('moderate_users')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['user_id'];
        $action = $data['action'];
        
        $allowedActions = ['ban', 'unban', 'mute', 'unmute', 'restrict', 'unrestrict'];
        if (!in_array($action, $allowedActions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
        }
        
        $statusMap = [
            'ban' => 'banned',
            'unban' => 'active',
            'mute' => 'muted',
            'unmute' => 'active',
            'restrict' => 'restricted',
            'unrestrict' => 'active'
        ];
        
        $newStatus = $statusMap[$action];
        
        $updateQuery = "UPDATE users SET status = :status WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':status', $newStatus);
        $updateStmt->bindParam(':id', $userId);
        
        if ($updateStmt->execute()) {
            echo json_encode(['success' => true, 'message' => ucfirst($action) . ' successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to ' . $action . ' user']);
        }
        break;
}
?>