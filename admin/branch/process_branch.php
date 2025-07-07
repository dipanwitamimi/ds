<?php
session_start();
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Sanitize and validate input
    $branch_code = filter_input(INPUT_POST, 'branch_code', FILTER_SANITIZE_STRING);
    $branch_name = filter_input(INPUT_POST, 'branch_name', FILTER_SANITIZE_STRING);
    $branch_type = filter_input(INPUT_POST, 'branch_type', FILTER_SANITIZE_STRING);
    $opening_date = filter_input(INPUT_POST, 'opening_date', FILTER_SANITIZE_STRING);
    $address1 = filter_input(INPUT_POST, 'address1', FILTER_SANITIZE_STRING);
    $address2 = filter_input(INPUT_POST, 'address2', FILTER_SANITIZE_STRING);
    $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING);
    $postal_code = filter_input(INPUT_POST, 'postal_code', FILTER_SANITIZE_STRING);
    $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $manager_name = filter_input(INPUT_POST, 'manager_name', FILTER_SANITIZE_STRING);
    $working_hours = filter_input(INPUT_POST, 'working_hours', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

    // Check if branch code already exists
    $stmt = $conn->prepare("SELECT id FROM branches WHERE branch_code = ?");
    $stmt->execute([$branch_code]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Branch code already exists!";
        header('Location: add.php');
        exit;
    }

    // Insert branch data
    $stmt = $conn->prepare("
        INSERT INTO branches (
            branch_code, branch_name, branch_type, opening_date,
            address1, address2, city, postal_code,
            contact_number, email, manager_name,
            working_hours, notes, status,
            created_by, created_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, 'active',
            ?, NOW()
        )
    ");

    $stmt->execute([
        $branch_code, $branch_name, $branch_type, $opening_date,
        $address1, $address2, $city, $postal_code,
        $contact_number, $email, $manager_name,
        $working_hours, $notes,
        $_SESSION['admin_id']
    ]);

    $branch_id = $conn->lastInsertId();

    // Create default branch login credentials
    $default_password = password_hash($branch_code . '@123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO branch_users (
            branch_id, username, password,
            role, status, created_by,
            created_at
        ) VALUES (
            ?, ?, ?,
            'manager', 'active', ?,
            NOW()
        )
    ");

    $stmt->execute([
        $branch_id,
        strtolower($branch_code),
        $default_password,
        $_SESSION['admin_id']
    ]);

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (
            user_id, action_type,
            entity_type, entity_id,
            details, created_at
        ) VALUES (
            ?, 'create',
            'branch', ?,
            ?, NOW()
        )
    ");

    $stmt->execute([
        $_SESSION['admin_id'],
        $branch_id,
        "Created new branch: $branch_name"
    ]);

    $_SESSION['success'] = "Branch added successfully! Default login credentials have been created.";
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: add.php');
    exit;
}
?>