<?php
session_start();

// Function to log the logout activity
function logLogout($userId, $logoutType) {
    try {
        require_once 'config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO user_logout_logs (
                user_id, 
                logout_time, 
                logout_type, 
                ip_address, 
                user_agent
            ) VALUES (?, NOW(), ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $logoutType,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (Exception $e) {
        error_log("Auto-logout logging failed: " . $e->getMessage());
    }
}

// Get user ID before destroying session
$userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;

if ($userId) {
    // Log the automatic logout
    logLogout($userId, 'auto');
    
    // Clear session data
    session_unset();
    session_destroy();
    
    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
}
?>