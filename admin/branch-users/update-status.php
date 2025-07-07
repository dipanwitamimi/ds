<?php
session_start();
require_once '../../config/database.php';

$currentDateTime = "2025-03-12 07:03:44";
$currentUser = "sgpriyom";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Validate parameters
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_GET, 'status');
    
    if (!$id || !in_array($status, ['active', 'inactive'])) {
        throw new Exception("Invalid parameters");
    }

    // Update user status
    $stmt = $db->prepare("
        UPDATE branch_users 
        SET status = ?, 
            updated_by = ?, 
            updated_at = NOW() 
        WHERE id = ?
    ");
    
    $stmt->execute([
        $status, 
        $_SESSION['admin_id'] ?? 1,
        $id
    ]);

    if ($stmt->rowCount() > 0) {
        // Log the action
        $stmt = $db->prepare("
            INSERT INTO branch_user_activity_logs 
            (user_id, action, details, created_by) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $id,
            'status_update',
            "Status changed to $status",
            $_SESSION['admin_id'] ?? 1
        ]);

        $_SESSION['success'] = "User status updated successfully";
    } else {
        $_SESSION['error'] = "User not found";
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header('Location: index.php');
exit;
?>