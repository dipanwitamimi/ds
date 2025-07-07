<?php
session_start();
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Validate and sanitize input
    $branch_id = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $mobile = filter_input(INPUT_POST, 'mobile', FILTER_SANITIZE_STRING);
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);

    // Validate required fields
    if (!$branch_id || !$username || !$password || !$full_name || !$email || !$mobile || !$role) {
        throw new Exception("All fields are required");
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }

    // Validate mobile (10 digits)
    if (!preg_match("/^[0-9]{10}$/", $mobile)) {
        throw new Exception("Invalid mobile number format");
    }

    // Begin transaction
    $db->beginTransaction();

    // Check for duplicate username
    $stmt = $db->prepare("SELECT id FROM branch_users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        throw new Exception("Username already exists");
    }

    // Check for duplicate email
    $stmt = $db->prepare("SELECT id FROM branch_users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        throw new Exception("Email already exists");
    }

    // Insert user
    $stmt = $db->prepare("
        INSERT INTO branch_users (
            branch_id, username, password, full_name,
            email, mobile, role, status,
            created_by, created_at
        ) VALUES (
            :branch_id, :username, :password, :full_name,
            :email, :mobile, :role, 'active',
            :created_by, NOW()
        )
    ");

    $success = $stmt->execute([
        ':branch_id' => $branch_id,
        ':username' => $username,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':full_name' => $full_name,
        ':email' => $email,
        ':mobile' => $mobile,
        ':role' => $role,
        ':created_by' => $_SESSION['admin_id'] ?? 1
    ]);

    if (!$success) {
        throw new Exception("Failed to create user");
    }

    // Commit transaction
    $db->commit();

    $_SESSION['success'] = "Branch user created successfully";
    header('Location: add.php');
    exit;

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: add.php');
    exit;
}
?>