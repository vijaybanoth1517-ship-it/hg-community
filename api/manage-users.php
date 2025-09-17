@@ .. @@
 <?php
 require_once __DIR__ . '/../includes/auth.php';
 require_once __DIR__ . '/../config/database.php';
 
 header('Content-Type: application/json');
+header('Access-Control-Allow-Origin: *');
+header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
+header('Access-Control-Allow-Headers: Content-Type, Authorization');
+
+// Handle preflight OPTIONS request
+if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
+    http_response_code(200);
+    exit();
+}
 
 $auth = new Auth();
 if (!$auth->isLoggedIn()) {
     http_response_code(401);
     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
     exit;
 }
 
-$database = new Database();
-$db = $database->getConnection();
+try {
+    $database = new Database();
+    $db = $database->getConnection();
+    
+    if (!$db) {
+        throw new Exception("Database connection failed");
+    }
+} catch (Exception $e) {
+    http_response_code(500);
+    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
+    exit;
+}