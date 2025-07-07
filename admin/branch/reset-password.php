<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/Database.php';

$currentDateTime = "2025-03-12 07:15:24";
$currentUser = "sgpriyom";

// Log the attempt
error_log(sprintf(
    "[%s] User %s attempted to reset user password\n",
    $currentDateTime,
    $currentUser
));

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Validate user ID
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new Exception("Invalid user ID");
    }

    // Check if user exists
    $stmt = $db->prepare("
        SELECT user_id, full_name, email, status 
        FROM branch_users 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    if ($user['status'] !== 'active') {
        throw new Exception("Cannot reset password for inactive user");
    }

    // Generate temporary password
    $temp_password = generateSecurePassword();
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

    // Start transaction
    $db->beginTransaction();

    // Update password
    $stmt = $db->prepare("
        UPDATE branch_users 
        SET password = ?,
            password_reset_required = 1,
            updated_by = ?,
            updated_at = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $hashed_password,
        $_SESSION['admin_id'] ?? 1,
        $currentDateTime,
        $id
    ]);

    // Terminate all active sessions for this user
    $stmt = $db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
    $stmt->execute([$id]);

    // Log the action
    $stmt = $db->prepare("
        INSERT INTO activity_logs (
            user_id,
            action,
            entity_type,
            entity_id,
            details,
            ip_address,
            user_agent,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_SESSION['admin_id'] ?? 1,
        'password_reset',
        'branch_user',
        $id,
        json_encode([
            'user_id' => $user['user_id'],
            'reset_by' => $currentUser
        ]),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        $currentDateTime
    ]);

    // Send password reset email
    $to = $user['email'];
    $subject = "Password Reset - " . SITE_NAME;
    $message = "
        Dear {$user['full_name']},

        Your password has been reset by the administrator.

        Your temporary password is: $temp_password

        Please log in and change your password immediately.
        This temporary password will expire in 24 hours.

        User ID: {$user['user_id']}
        Reset Time: $currentDateTime

        Best regards,
        " . SITE_NAME . " Team
    ";
    $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];

    mail($to, $subject, $message, $headers);

    // Commit transaction
    $db->commit();

    // Log success
    error_log(sprintf(
        "[%s] User %s successfully reset password for user %s\n",
        $currentDateTime,
        $currentUser,
        $user['user_id']
    ));

    $_SESSION['success'] = "Password reset successfully. Temporary password has been sent to user's email.";

} catch (Exception $e) {
    // Rollback transaction if active
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }

    // Log error
    error_log(sprintf(
        "[%s] Error resetting password by %s: %s\n",
        $currentDateTime,
        $currentUser,
        $e->getMessage()
    ));

    $_SESSION['error'] = $e->getMessage();
}

header('Location: users.php');
exit;

function generateSecurePassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*()';
    
    $all = $uppercase . $lowercase . $numbers . $special;
    $password = '';
    
    // Ensure at least one character from each set
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    // Fill remaining length with random characters
    for ($i = strlen($password); $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }
    
    // Shuffle the password
    return str_shuffle($password);
}
?>