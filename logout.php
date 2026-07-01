<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Log logout
    $log_query = "INSERT INTO audit_logs (user_id, action, ip_address) 
                  VALUES (:user_id, 'logout', :ip)";
    $log_stmt = $db->prepare($log_query);
    $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
    
    // Clear session
    session_unset();
    session_destroy();
}

redirect('index.php');
?>