<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/Database.php';

$currentDateTime = "2025-03-12 07:12:44";
$currentUser = "sgpriyom";

// Log the attempt
error_log(sprintf(
    "[%s] User %s attempted to update branch user\n",
    $currentDateTime,
    $currentUser
));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    header('Location: users.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Validate user ID
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new Exception("Invalid user ID");
    }

    // Check if user exists
    $stmt = $db->prepare("SELECT user_id, email FROM branch_users WHERE id = ?");
    $stmt->execute([$id]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing_user) {
        throw new Exception("User not found");
    }

    // Validate required fields
    $required_fields = [
        'full_name' => 'Full Name',
        'email' => 'Email',
        'mobile' => 'Mobile Number',
        'branch_id' => 'Branch',
        'role' => 'Role',
        'status' => 'Status'
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

    // Check email uniqueness (if changed)
    if ($data['email'] !== $existing_user['email']) {
        $stmt = $db->prepare("SELECT id FROM branch_users WHERE email = ? AND id != ?");
        $stmt->execute([$data['email'], $id]);
        if ($stmt->fetch()) {
            throw new Exception("Email already exists");
        }
    }

    // Validate mobile number
    if (!preg_match("/^[0-9]{10}$/", $data['mobile'])) {
        throw new Exception("Invalid mobile number format");
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

    // Validate status
    if (!in_array($data['status'], ['active', 'inactive'])) {
        throw new Exception("Invalid status");
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

    // Update user
    $stmt = $db->prepare("
        UPDATE branch_users SET
            full_name = ?,
            email = ?,
            mobile = ?,
            address = ?,
            branch_id = ?,
            role = ?,
            permissions = ?,
            status = ?,
            updated_by = ?,
            updated_at = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $data['full_name'],
        $data['email'],
        $data['mobile'],
        $_POST['address'] ?? '',
        $data['branch_id'],
        $data['role'],
        json_encode($permissions),
        $data['status'],
        $_SESSION['admin_id'] ?? 1,
        $currentDateTime,
        $id
    ]);

    // Log the change
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
        'update',
        'branch_user',
        $id,
        json_encode([
            'user_id' => $existing_user['user_id'],
            'updated_fields' => array_keys($data),
            'updated_by' => $currentUser
        ]),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        $currentDateTime
    ]);

    // If status changed to inactive, log all active sessions out
    if ($data['status'] === 'inactive') {
        $stmt = $db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$id]);
    }

    // Commit transaction
    $db->commit();

    // Log success
    error_log(sprintf(
        "[%s] User %s successfully updated branch user %s\n",
        $currentDateTime,
        $currentUser,
        $existing_user['user_id']
    ));

    $_SESSION['success'] = "User updated successfully";
    header('Location: users.php');
    exit;

} catch (Exception $e) {
    // Rollback transaction if active
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }

    // Log error
    error_log(sprintf(
        "[%s] Error updating branch user by %s: %s\n",
        $currentDateTime,
        $currentUser,
        $e->getMessage()
    ));

    $_SESSION['error'] = $e->getMessage();
    header('Location: edit-user.php?id=' . ($id ?? ''));
    exit;
}
?>