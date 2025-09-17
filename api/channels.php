@@ .. @@
 <?php
-// Prevent any output before JSON response
-ob_start();
-
 require_once __DIR__ . '/../includes/auth.php';
 require_once __DIR__ . '/../config/database.php';
 
-// Clear any previous output
-ob_clean();
-
 // Set proper headers
 header('Content-Type: application/json');
 header('Access-Control-Allow-Origin: *');
 header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
 header('Access-Control-Allow-Headers: Content-Type, Authorization');