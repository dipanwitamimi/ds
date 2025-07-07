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
    
    // Validate input
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new Exception("Invalid staff ID");
    }

    // Build update query
    $updateFields = [
        'full_name' => filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING),
        'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
        'mobile' => filter_input(INPUT_POST, 'mobile', FILTER_SANITIZE_STRING),
        'gender' => filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING),
        'dob' => $_POST['dob'],
        'branch_id' => filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT),
        'designation' => filter_input(INPUT_POST, 'designation', FILTER_SANITIZE_STRING),
        'department' => filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING),
        'joining_date' => $_POST['joining_date'],
        'salary' => filter_input(INPUT_POST, 'salary', FILTER_VALIDATE_FLOAT),
        'status' => filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING)
    ];

    // Validate required fields
    $requiredFields = ['full_name', 'email', 'mobile', 'gender', 'dob', 'branch_id', 'designation', 'joining_date', 'status'];
    foreach ($requiredFields as $field) {
        if (empty($updateFields[$field])) {
            throw new Exception("$field is required");
        }
    }

    // Start transaction
    $db->beginTransaction();

    // Update password if provided
    if (!empty($_POST['new_password'])) {
        $updateFields['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    }

    // Build SQL query
    $sql = "UPDATE branch_staff SET ";
    $params = [];
    foreach ($updateFields as $field => $value) {
        $sql .= "$field = ?, ";
        $params[] = $value;
    }
    $sql .= "updated_by = ?, updated_at = NOW() WHERE id = ?";
    $params[] = $_SESSION['admin_id'] ?? 1;
    $params[] = $id;

    // Remove trailing comma and space
    $sql = str_replace(", WHERE", " WHERE", $sql);

    // Execute update
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // Commit transaction
    $db->commit();

    $_SESSION['success'] = "Staff details updated successfully";

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header('Location: edit.php?id=' . $id);
exit;
?>