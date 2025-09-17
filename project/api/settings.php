<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasPermission('manage_settings')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $query = "SELECT * FROM settings ORDER BY category, name";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'settings' => $settings]);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $setting = $data['setting'];
        $value = $data['value'];
        
        $updateQuery = "UPDATE settings SET value = ? WHERE name = ?";
        $updateStmt = $db->prepare($updateQuery);
        if ($updateStmt->execute([$value, $setting])) {
            $currentUser = $auth->getCurrentUser();
            $auth->logActivity($currentUser['id'], null, 'setting_changed', "Setting '$setting' changed to '$value'");
            echo json_encode(['success' => true, 'message' => 'Setting updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update setting']);
        }
        break;
}
?>