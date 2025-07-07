<?php
session_start();
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['staff_id'])) {
    header('Location: ../staff.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $staff_id = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Get staff details first
    $stmt = $conn->prepare("SELECT full_name, profile_photo FROM staff WHERE id = ?");
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch();
    
    if (!$staff) {
        throw new Exception("Staff member not found");
    }
    
    // Delete profile photo if exists
    if ($staff['profile_photo']) {
        $photo_path = '../../uploads/staff/profile_photos/' . $staff['profile_photo'];
        if (file_exists($photo_path)) {
            unlink($photo_path);
        }
    }
    
    // Soft delete by updating status
    $stmt = $conn->prepare("
        UPDATE staff 
        SET status = 'inactive',
            updated_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$_SESSION['admin_id'], $staff_id]);
    
    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (
            user_id, action_type,
            entity_type, entity_id,
            details, created_at
        ) VALUES (
            ?, 'delete',
            'staff', ?,
            ?, NOW()
        )
    ");
    
    $stmt->execute([
        $_SESSION['admin_id'],
        $staff_id,
        "Deactivated staff member: " . $staff['full_name']
    ]);
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success'] = "Staff member has been deactivated successfully";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header('Location: ../staff.php');
exit;
?>