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
    if (!$id) {
        throw new Exception("Invalid user ID");
    }

    // Generate temporary password
    $temp_password = generateTempPassword();
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

    // Get user email
    $stmt = $db->prepare("SELECT email, full_name FROM branch_users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    // Update password
    $stmt = $db->prepare("
        UPDATE branch_users 
        SET password = ?,
            password_reset_required = 1,
            updated_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $hashed_password,
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
            'password_reset',
            'Password reset by admin',
            $_SESSION['admin_id'] ?? 1
        ]);

        // Send email with temporary password
        $to = $user['email'];
        $subject = "Password Reset - Your Temporary Password";
        $message = "
            Dear {$user['full_name']},

            Your password has been reset by the administrator.
            
            Your temporary password is: $temp_password
            
            Please log in and change your password immediately.
            
            Best regards,
            System Administrator
        ";
        $headers = "From: noreply@example.com";

        mail($to, $subject, $message, $headers);

        $_SESSION['success'] = "Password reset successfully. Temporary password has been sent to user's email.";
    } else {
        $_SESSION['error'] = "Failed to reset password";
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header('Location: index.php');
exit;

function generateTempPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}
?>