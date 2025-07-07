<?php
session_start();
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Validate required fields
    $required_fields = ['full_name', 'email', 'mobile', 'username', 'password', 'branch_id', 'role', 'joining_date', 'status'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("$field is required");
        }
    }

    // Validate email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }

    // Validate mobile number
    if (!preg_match("/^[0-9]{10}$/", $_POST['mobile'])) {
        throw new Exception("Invalid mobile number format");
    }

    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM branch_users WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    if ($stmt->fetch()) {
        throw new Exception("Username already exists");
    }

    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM branch_users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    if ($stmt->fetch()) {
        throw new Exception("Email already exists");
    }

    // Generate staff ID
    $stmt = $db->prepare("SELECT branch_code FROM branches WHERE id = ?");
    $stmt->execute([$_POST['branch_id']]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $staff_id = $branch['branch_code'] . '-' . date('Y') . '-' . 
                str_pad((rand(1, 999)), 3, '0', STR_PAD_LEFT);

    // Start transaction
    $db->beginTransaction();

    // Insert user
    $stmt = $db->prepare("
        INSERT INTO branch_users (
            staff_id, branch_id, full_name, email, mobile, username, password,
            role, joining_date, status, permissions, created_by
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $staff_id,
        $_POST['branch_id'],
        $_POST['full_name'],
        $_POST['email'],
        $_POST['mobile'],
        $_POST['username'],
        password_hash($_POST['password'], PASSWORD_DEFAULT),
        $_POST['role'],
        $_POST['joining_date'],
        $_POST['status'],
        isset($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]',
        $_SESSION['admin_id'] ?? 1
    ]);

    $db->commit();
    
    $_SESSION['success'] = "Branch user created successfully. Staff ID: $staff_id";
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: create.php');
    exit;
}
?>