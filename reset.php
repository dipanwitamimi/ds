<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/config.php';
require_once 'config/database.php';

$currentDateTime = date('Y-m-d H:i:s');
$currentUser = $_SESSION['admin_user']['username'] ?? 'admin';

$db = (new Database())->getConnection();
$success = $error = "";

// Handle password reset POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_type'], $_POST['user_id'], $_POST['new_password'])) {
    $user_type = $_POST['user_type'];
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $table = $id_field = '';
    if ($user_type === 'admin') {
        $table = 'admin_users';
        $id_field = 'id';
    } elseif ($user_type === 'branch') {
        $table = 'branch_users';
        $id_field = 'id';
    } elseif ($user_type === 'staff') {
        $table = 'staff';
        $id_field = 'id';
    }

    if ($table && $id_field && $user_id > 0) {
        $stmt = $db->prepare("UPDATE `$table` SET password = :password WHERE $id_field = :id");
        if ($stmt->execute([':password' => $hashed_password, ':id' => $user_id])) {
            $success = "Password reset successfully!";
        } else {
            $error = "Failed to reset password.";
        }
    } else {
        $error = "Invalid user type or ID.";
    }
}

// Fetch branches for lookup
$branches = [];
foreach ($db->query("SELECT id, branch_name FROM branches") as $b) {
    $branches[$b['id']] = $b['branch_name'];
}
$admin_users = $db->query("SELECT id, username, full_name, email, role, status, created_at FROM admin_users")->fetchAll(PDO::FETCH_ASSOC);
$branch_users = $db->query("SELECT id, username, full_name, email, role, status, branch_id, created_at FROM branch_users")->fetchAll(PDO::FETCH_ASSOC);
$staff_users = $db->query("SELECT id, staff_id, username, full_name, email, role, status, branch_id, created_at FROM staff")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset User Passwords</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .header-info { background: #e9ecef; padding: 10px 0; margin-bottom: 16px; }
        .reset-btn { padding: 2px 8px; }
    </style>
</head>
<body>
<div class="header-info p-2 bg-light">
    <div class="container">
        Current Date and Time: <span><?= htmlspecialchars($currentDateTime) ?></span> &nbsp;|&nbsp;
        Current User's Login: <span><?= htmlspecialchars($currentUser) ?></span>
    </div>
</div>
<div class="container mt-4">
    <h3>All Users (Admin, Branch, Staff) — Password Reset Authority</h3>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <h4>Admin Users</h4>
    <table class="table table-bordered">
        <thead>
            <tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Reset</th></tr>
        </thead>
        <tbody>
        <?php foreach ($admin_users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['id'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['username'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['role'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['status'] ?? '') ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="user_type" value="admin">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?? '' ?>">
                        <input type="password" name="new_password" placeholder="New Password" required minlength="6">
                        <button type="submit" class="btn btn-sm btn-warning">Reset</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; if(empty($admin_users)): ?>
            <tr><td colspan="7" class="text-center text-muted">No admin users.</td></tr>
        <?php endif ?>
        </tbody>
    </table>

    <h4>Branch Users</h4>
    <table class="table table-bordered">
        <thead>
            <tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Branch</th><th>Reset</th></tr>
        </thead>
        <tbody>
        <?php foreach ($branch_users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['id'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['username'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['role'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['status'] ?? '') ?></td>
                <td><?= htmlspecialchars($branches[$u['branch_id']] ?? $u['branch_id'] ?? '') ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="user_type" value="branch">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?? '' ?>">
                        <input type="password" name="new_password" placeholder="New Password" required minlength="6">
                        <button type="submit" class="btn btn-sm btn-warning">Reset</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; if(empty($branch_users)): ?>
            <tr><td colspan="8" class="text-center text-muted">No branch users.</td></tr>
        <?php endif ?>
        </tbody>
    </table>

    <h4>Staff Users</h4>
    <table class="table table-bordered">
        <thead>
            <tr><th>ID</th><th>Staff ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Branch</th><th>Reset</th></tr>
        </thead>
        <tbody>
        <?php foreach ($staff_users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['id'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['staff_id'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['username'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['role'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['status'] ?? '') ?></td>
                <td><?= htmlspecialchars($branches[$u['branch_id']] ?? $u['branch_id'] ?? '') ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="user_type" value="staff">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?? '' ?>">
                        <input type="password" name="new_password" placeholder="New Password" required minlength="6">
                        <button type="submit" class="btn btn-sm btn-warning">Reset</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; if(empty($staff_users)): ?>
            <tr><td colspan="9" class="text-center text-muted">No staff users.</td></tr>
        <?php endif ?>
        </tbody>
    </table>
</div>
</body>
</html>