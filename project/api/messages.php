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
        $channelId = $_GET['channel_id'] ?? null;
        if (!$channelId) {
            echo json_encode(['success' => false, 'message' => 'Channel ID required']);
            exit;
        }
        
        $query = "SELECT m.*, u.username, u.role, u.avatar 
                 FROM messages m 
                 JOIN users u ON m.user_id = u.id 
                 WHERE m.channel_id = :channel_id 
                 ORDER BY m.created_at DESC 
                 LIMIT 50";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':channel_id', $channelId);
        $stmt->execute();
        $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        break;
        
    case 'POST':
        if (!$auth->hasPermission('send_message')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $channelId = $_POST['channel_id'];
        $content = $_POST['content'];
        $filePath = null;
        $fileType = null;
        
        // Handle file upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $uploadDir = '../uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = time() . '_' . $_FILES['file']['name'];
            $filePath = $uploadDir . $fileName;
            $fileType = $_FILES['file']['type'];
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
                $filePath = 'uploads/' . $fileName;
            } else {
                $filePath = null;
                $fileType = null;
            }
        }
        
        $insertQuery = "INSERT INTO messages (channel_id, user_id, content, file_path, file_type) VALUES (:channel_id, :user_id, :content, :file_path, :file_type)";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':channel_id', $channelId);
        $insertStmt->bindParam(':user_id', $_SESSION['user_id']);
        $insertStmt->bindParam(':content', $content);
        $insertStmt->bindParam(':file_path', $filePath);
        $insertStmt->bindParam(':file_type', $fileType);
        
        if ($insertStmt->execute()) {
            // Get the created message with user info
            $messageId = $db->lastInsertId();
            $selectQuery = "SELECT m.*, u.username, u.role, u.avatar 
                           FROM messages m 
                           JOIN users u ON m.user_id = u.id 
                           WHERE m.id = :id";
            $selectStmt = $db->prepare($selectQuery);
            $selectStmt->bindParam(':id', $messageId);
            $selectStmt->execute();
            $message = $selectStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send message']);
        }
        break;
}
?>