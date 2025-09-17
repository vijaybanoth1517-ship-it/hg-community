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
$currentUser = $auth->getCurrentUser();

switch ($method) {
    case 'GET':
        $userId = $_GET['user_id'] ?? $currentUser['id'];
        
        $query = "SELECT id, username, email, phone, avatar, bio, display_name, social_links, timezone, created_at FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $user['social_links'] = json_decode($user['social_links'] ?? '{}', true);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['user_id'] ?? $currentUser['id'];
        
        if ($userId != $currentUser['id'] && !$auth->hasPermission('manage_users')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot edit other users']);
            exit;
        }
        
        $updateFields = [];
        $params = [];
        
        if (isset($data['display_name'])) {
            $updateFields[] = "display_name = ?";
            $params[] = $data['display_name'];
        }
        if (isset($data['bio'])) {
            $updateFields[] = "bio = ?";
            $params[] = $data['bio'];
        }
        if (isset($data['email'])) {
            $updateFields[] = "email = ?";
            $params[] = $data['email'];
        }
        if (isset($data['phone'])) {
            $updateFields[] = "phone = ?";
            $params[] = $data['phone'];
        }
        if (isset($data['social_links'])) {
            $updateFields[] = "social_links = ?";
            $params[] = json_encode($data['social_links']);
        }
        if (isset($data['timezone'])) {
            $updateFields[] = "timezone = ?";
            $params[] = $data['timezone'];
        }
        
        if (!empty($updateFields)) {
            $params[] = $userId;
            $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $updateStmt = $db->prepare($updateQuery);
            
            if ($updateStmt->execute($params)) {
                $auth->logActivity($currentUser['id'], $userId, 'profile_updated', 'Profile information updated');
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
        }
        break;
        
    case 'POST':
        if (isset($_FILES['avatar'])) {
            $uploadDir = '../uploads/avatars/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = $currentUser['id'] . '_' . time() . '_' . $_FILES['avatar']['name'];
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filePath)) {
                $avatarUrl = 'uploads/avatars/' . $fileName;
                
                $updateQuery = "UPDATE users SET avatar = ? WHERE id = ?";
                $updateStmt = $db->prepare($updateQuery);
                
                if ($updateStmt->execute([$avatarUrl, $currentUser['id']])) {
                    $auth->logActivity($currentUser['id'], null, 'avatar_updated', 'Profile picture updated');
                    echo json_encode(['success' => true, 'avatar_url' => $avatarUrl]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update avatar']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            }
        }
        break;
}
?>