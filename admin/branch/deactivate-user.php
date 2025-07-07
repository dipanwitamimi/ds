<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/Database.php';

$currentDateTime = "2025-03-12 07:15:24";
$currentUser = "sgpriyom";

// Log the attempt
error_log(sprintf(
    "[%s] User %s attempted to deactivate branch user\n",
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

    // Check if user exists and can be deactivated
    $stmt = $db->prepare("
        SELECT user_id, full_name, status, role 
        FROM branch_users 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    if ($user['status'] === 'inactive') {
        throw new Exception("User is already inactive");
    }

    // Check if this is the last active branch manager
    if ($user['role'] === 'branch_manager') {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM branch_users 
            WHERE role = 'branch_manager' 
            AND status = 'active' 
            AND id != ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] === '0') {
            throw new Exception("Cannot deactivate the last active branch manager");
        }
    }

    // Start transaction
    $db->beginTransaction();

    // Update user status
    $stmt = $db->prepare("
        UPDATE branch_users 
        SET status = 'inactive',
            updated_by = ?,
            updated_at = ?
        WHERE id = ?
    ");

    $stmt->execute([
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
        'deactivate',
        'branch_user',
        $id,
        json_encode([
            'user_id' => $user['user_id'],
            'full_name' => $user['full_name'],
            'deactivated_by' => $currentUser
        ]),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        $currentDateTime
    ]);

    // Send notification email
    $to = $user['email'];
    $subject = "Account Deactivation Notice - " . SITE_NAME;
    $message = "
        Dear {$user['full_name']},

        Your account has been deactivated by the administrator.
        If you believe this is an error, please contact your supervisor.

        User ID: {$user['user_id']}
        Deactivation Time: $currentDateTime

        Best regards,
        " . SITE_NAME . " Team
    ";
    $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];

    mail($to, $subject, $message, $headers);

    // Commit transaction
    $db->commit();

    // Log success
    error_log(sprintf(
        "[%s] User %s successfully deactivated branch user %s\n",
        $currentDateTime,
        $currentUser,
        $user['user_id']
    ));

    $_SESSION['success'] = "User has been deactivated successfully";

} catch (Exception $e) {
    // Rollback transaction if active
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }

    // Log error
    error_log(sprintf(
        "[%s] Error deactivating branch user by %s: %s\n",
        $currentDateTime,
        $currentUser,
        $e->getMessage()
    ));

    $_SESSION['error'] = $e->getMessage();
}

header('Location: users.php');
exit;
?>