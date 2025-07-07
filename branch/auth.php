<?php
session_start();
require_once '../config/config.php';
require_once '../config/Database.php';

$currentDateTime = "2025-03-12 08:24:26";
$currentUser = "sgpriyom";

try {
    // Validate input
    if (empty($_POST['branch_id']) || empty($_POST['username']) || empty($_POST['password'])) {
        throw new Exception("All fields are required");
    }

    // Sanitize input
    $branch_id = (int)$_POST['branch_id'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $database = new Database();
    $db = $database->getConnection();

    // First verify if branch exists and is active
    $stmt = $db->prepare("SELECT id, branch_name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        throw new Exception("Invalid branch selected");
    }

    // Get user details
    $stmt = $db->prepare("
        SELECT 
            id,
            username,
            password,
            full_name,
            role,
            status,
            branch_id
        FROM branch_users 
        WHERE username = ? 
        AND branch_id = ?
        AND status = 'active'
    ");
    
    $stmt->execute([$username, $branch_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify user exists and password matches
    if (!$user || !password_verify($password, $user['password'])) {
        throw new Exception("Invalid username or password");
    }

    // Start transaction for logging
    $db->beginTransaction();

    // Update last login time
    $stmt = $db->prepare("UPDATE branch_users SET last_login = ? WHERE id = ?");
    $stmt->execute([$currentDateTime, $user['id']]);

    // Log the login activity
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
        ) VALUES (
            :user_id,
            'login',
            'branch_user',
            :entity_id,
            :details,
            :ip_address,
            :user_agent,
            :created_at
        )
    ");

    $stmt->execute([
        ':user_id' => $user['id'],
        ':entity_id' => $user['id'],
        ':details' => json_encode([
            'branch_id' => $branch_id,
            'branch_name' => $branch['branch_name'],
            'role' => $user['role']
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'],
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'],
        ':created_at' => $currentDateTime
    ]);

    // Commit transaction
    $db->commit();

    // Set session variables
    $_SESSION['branch_user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'branch_id' => $user['branch_id'],
        'branch_name' => $branch['branch_name']
    ];

    // Log successful login
    error_log("[{$currentDateTime}] Successful branch login: {$user['username']} at branch {$branch['branch_name']}");

    // Redirect to dashboard
    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    // Rollback if transaction is active
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    // Log error
    error_log(sprintf(
        "[%s] Branch login error: %s\nPOST Data: %s\n",
        $currentDateTime,
        $e->getMessage(),
        print_r($_POST, true)
    ));

    $_SESSION['error'] = "System error. Please try again later.";
    header('Location: login.php');
    exit;
}
?>