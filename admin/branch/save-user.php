<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/Database.php';

$currentDateTime = "2025-03-12 08:07:31";
$currentUser = "sgpriyom";

try {
    $database = new Database();
    $db = $database->getConnection();

    // Validate required fields
    if (empty($_POST['username']) || empty($_POST['password']) || empty($_POST['branch_id'])) {
        throw new Exception("Username, password and branch are required fields");
    }

    // Basic sanitization
    $data = [
        'username' => trim($_POST['username']),
        'password' => $_POST['password'],
        'full_name' => trim($_POST['full_name'] ?? null),
        'email' => trim($_POST['email'] ?? null),
        'mobile' => trim($_POST['mobile'] ?? null),
        'branch_id' => (int)$_POST['branch_id'],
        'role' => $_POST['role'] ?? 'staff',
        'address' => trim($_POST['address'] ?? null)
    ];

    // Validate email if provided
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }

    // Validate mobile if provided
    if (!empty($data['mobile']) && !preg_match("/^[0-9]{10}$/", $data['mobile'])) {
        throw new Exception("Mobile number must be 10 digits");
    }

    // Check for duplicate username
    $stmt = $db->prepare("SELECT id FROM branch_users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        throw new Exception("Username already exists");
    }

    // Start transaction
    $db->beginTransaction();

    // Insert the user
    $stmt = $db->prepare("
        INSERT INTO branch_users (
            branch_id,
            username,
            password,
            full_name,
            email,
            mobile,
            role,
            status,
            address,
            created_by,
            created_at,
            updated_at
        ) VALUES (
            :branch_id,
            :username,
            :password,
            :full_name,
            :email,
            :mobile,
            :role,
            'active',
            :address,
            :created_by,
            :created_at,
            :updated_at
        )
    ");

    $result = $stmt->execute([
        ':branch_id' => $data['branch_id'],
        ':username' => $data['username'],
        ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
        ':full_name' => $data['full_name'],
        ':email' => $data['email'],
        ':mobile' => $data['mobile'],
        ':role' => $data['role'],
        ':address' => $data['address'],
        ':created_by' => $_SESSION['admin_id'] ?? 1,
        ':created_at' => $currentDateTime,
        ':updated_at' => $currentDateTime
    ]);

    if (!$result) {
        throw new Exception("Failed to create user account");
    }

    // Commit the transaction
    $db->commit();

    // Log success
    error_log("[{$currentDateTime}] New branch user {$data['username']} created successfully by {$currentUser}");

    $_SESSION['success'] = "Branch user created successfully";
    header('Location: users.php');
    exit;

} catch (Exception $e) {
    // Rollback transaction if active
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }

    // Log error with details
    error_log(sprintf(
        "[%s] Error creating branch user: %s\nPOST Data: %s\n",
        $currentDateTime,
        $e->getMessage(),
        print_r($_POST, true)
    ));

    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST; // Save form data for repopulation
    header('Location: add-user.php');
    exit;
}
?>