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
    
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    
    if (!$id) {
        throw new Exception('Invalid staff ID');
    }
    
    $stmt = $db->prepare("DELETE FROM branch_staff WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Staff member deleted successfully";
    } else {
        $_SESSION['error'] = "Staff member not found";
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header('Location: index.php');
exit;
?>