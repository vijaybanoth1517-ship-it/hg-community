@@ .. @@
 <?php
+error_reporting(0);
+ini_set('display_errors', 0);
+
 require_once 'includes/auth.php';
 
 $auth = new Auth();
 
 if (!$auth->isLoggedIn()) {
     header('Location: login.php');
     exit;
 }
 
 $user = $auth->getCurrentUser();