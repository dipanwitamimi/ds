<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/Database.php';

$currentDateTime = "2025-03-12 07:23:15";
$currentUser = "sgpriyom";

// Log the attempt
error_log(sprintf(
    "[%s] User %s attempted to create new branch user\n",
    $currentDateTime,
    $currentUser
));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    header('Location: add-user.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Validate required fields
    $required_fields = [
        'full_name' => 'Full Name',
        'email' => 'Email',
        'mobile' => 'Mobile Number',
        'branch_id' => 'Branch',
        'role' => 'Role',
        'username' => 'Username',
        'password' => 'Password'
    ];

    $data = [];
    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            throw new Exception("$label is required");
        }
        $data[$field] = trim($_POST[$field]);
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }

    // Validate mobile number (10 digits)
    if (!preg_match("/^[0-9]{10}$/", $data['mobile'])) {
        throw new Exception("Invalid mobile number format");
    }

    // Validate username format
    if (!preg_match("/^[a-zA-Z0-9_]{5,}$/", $data['username'])) {
        throw new Exception("Username must be at least 5 characters and contain only letters, numbers, and underscore");
    }

    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM branch_users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        throw new Exception("Username already exists");
    }

    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM branch_users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        throw new Exception("Email already exists");
    }

    // Validate branch exists
    $stmt = $db->prepare("SELECT id FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$data['branch_id']]);
    if (!$stmt->fetch()) {
        throw new Exception("Invalid branch selected");
    }

    // Validate role
    $valid_roles = ['branch_manager', 'cashier', 'operator'];
    if (!in_array($data['role'], $valid_roles)) {
        throw new Exception("Invalid role selected");
    }

    // Process permissions
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    $valid_permissions = [
        'manage_cash',
        'manage_transactions',
        'view_reports',
        'manage_staff'
    ];
    $permissions = array_intersect($permissions, $valid_permissions);

    // Start transaction
    $db->beginTransaction();

    // Insert user
    $stmt = $db->prepare("
        INSERT INTO branch_users (
            username,
            full_name,
            email,
            mobile,
            password,
            branch_id,
            role,
            permissions,
            address,
            created_by,
            created_at,
            status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active'
        )
    ");

    $stmt->execute([
        $data['username'],
        $data['full_name'],
        $data['email'],
        $data['mobile'],
        password_hash($data['password'], PASSWORD_DEFAULT),
        $data['branch_id'],
        $data['role'],
        json_encode($permissions),
        $_POST['address'] ?? '',
        $_SESSION['admin_id'] ?? 1,
        $currentDateTime
    ]);

    $userId = $db->lastInsertId();

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
        'create',
        'branch_user',
        $userId,
        json_encode([
            'username' => $data['username'],
            'branch_id' => $data['branch_id'],
            'role' => $data['role'],
            'created_by' => $currentUser
        ]),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        $currentDateTime
    ]);

    // Send welcome email
    $to = $data['email'];
    $subject = "Welcome to " . SITE_NAME . " - Your Account Details";
    $message = "
        Dear {$data['full_name']},

        Your account has been created successfully.

        Username: {$data['username']}
        Branch: {$data['branch_id']}
        Role: " . ucwords(str_replace('_', ' ', $data['role'])) . "

        Please login to " . SITE_URL . " using your username and password.
        For security reasons, please change your password after first login.

        Best regards,
        " . SITE_NAME . " Team
    ";
    $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];

    mail($to, $subject, $message, $headers);

    // Commit transaction
    $db->commit();

    // Log success
    error_log(sprintf(
        "[%s] User %s successfully created new branch user %s\n",
        $currentDateTime,
        $currentUser,
        $data['username']
    ));
    
    $_SESSION['success'] = "User created successfully";
    header('Location: users.php');
    exit;

} catch (Exception $e) {
    // Rollback transaction if active
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }

    // Log error
    error_log(sprintf(
        "[%s] Error creating branch user by %s: %s\n",
        $currentDateTime,
        $currentUser,
        $e->getMessage()
    ));

    $_SESSION['error'] = $e->getMessage();
    header('Location: add-user.php');
    exit;
}
?>