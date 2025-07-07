<?php
session_start();
require_once '../config/config.php';
require_once '../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE username=? AND status='active' LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // If password is stored hashed
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_user'] = [
            'id' => $admin['id'],
            'username' => $admin['username']
        ];
        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['error'] = "Invalid username or password";
        header('Location: login.php');
        exit;
    }
} else {
    header('Location: login.php');
    exit;
}
?>