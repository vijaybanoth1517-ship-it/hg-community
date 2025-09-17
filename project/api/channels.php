<?php
// Prevent any output before JSON response
ob_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Clear any previous output
ob_clean();

// Set proper headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $query = "SELECT c.*, u.username as created_by_name FROM channels c 
                     LEFT JOIN users u ON c.created_by = u.id 
                     ORDER BY c.type, c.name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'channels' => $channels]);
            break;
            
        case 'POST':
            if (!$auth->hasPermission('manage_channels')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            // Get raw POST data
            $input = file_get_contents('php://input');
            if (empty($input)) {
                echo json_encode(['success' => false, 'message' => 'No data received']);
                exit;
            }
            
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['success' => false, 'message' => 'Invalid JSON data: ' . json_last_error_msg()]);
                exit;
            }
            
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $type = trim($data['type'] ?? '');
            $teamName = trim($data['team_name'] ?? '') ?: null;
            
            // Validate required fields
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Channel name is required']);
                exit;
            }
            
            if (empty($type)) {
                echo json_encode(['success' => false, 'message' => 'Channel type is required']);
                exit;
            }
            
            // Validate type
            $validTypes = ['announcement', 'general', 'team', 'technical'];
            if (!in_array($type, $validTypes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid channel type']);
                exit;
            }
            
            // Check if channel name already exists
            $checkQuery = "SELECT COUNT(*) as count FROM channels WHERE name = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$name]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Channel name already exists']);
                exit;
            }
            
            // Insert new channel
            $insertQuery = "INSERT INTO channels (name, description, type, team_name, created_by) VALUES (?, ?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            
            if ($insertStmt->execute([$name, $description, $type, $teamName, $_SESSION['user_id']])) {
                $channelId = $db->lastInsertId();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Channel created successfully',
                    'channel_id' => $channelId
                ]);
            } else {
                $errorInfo = $insertStmt->errorInfo();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . ($errorInfo[2] ?? 'Unknown error')]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>