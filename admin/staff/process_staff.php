<?php
session_start();
require_once '../../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    header('Location: add.php');
    exit;
}

// Debug: Log POST data
error_log("POST Data: " . print_r($_POST, true));

$conn = null;
try {
    $db = new Database();
    $conn = $db->getConnection();

    // Basic validation
    $required_fields = [
        'staff_id',
        'branch_id',
        'full_name',
        'email',
        'mobile',
        'username',
        'password',
        'role',
        'dob',
        'gender',
        'joining_date'
    ];

    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }

    // Sanitize and store all form data
    $data = [
        'staff_id' => trim($_POST['staff_id']),
        'branch_id' => (int)$_POST['branch_id'],
        'full_name' => trim($_POST['full_name']),
        'email' => trim($_POST['email']),
        'mobile' => trim($_POST['mobile']),
        'username' => trim($_POST['username']),
        'password' => $_POST['password'],
        'role' => $_POST['role'],
        'department' => !empty($_POST['department']) ? $_POST['department'] : null,
        'dob' => $_POST['dob'],
        'gender' => $_POST['gender'],
        'joining_date' => $_POST['joining_date'],
        'address' => !empty($_POST['address']) ? trim($_POST['address']) : null
    ];

    // Debug: Log sanitized data
    error_log("Sanitized Data: " . print_r($data, true));

    // Begin transaction
    $conn->beginTransaction();

    // Insert staff data with explicit fields
    $sql = "INSERT INTO staff (
        staff_id,
        branch_id,
        full_name,
        email,
        mobile,
        username,
        password,
        role,
        department,
        dob,
        gender,
        joining_date,
        address,
        status,
        created_by,
        created_at
    ) VALUES (
        :staff_id,
        :branch_id,
        :full_name,
        :email,
        :mobile,
        :username,
        :password,
        :role,
        :department,
        :dob,
        :gender,
        :joining_date,
        :address,
        'active',
        :created_by,
        NOW()
    )";

    $stmt = $conn->prepare($sql);
    
    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

    // Debug: Log SQL
    error_log("SQL Query: " . $sql);

    // Execute with explicit parameter binding
    $success = $stmt->execute([
        ':staff_id' => $data['staff_id'],
        ':branch_id' => $data['branch_id'],
        ':full_name' => $data['full_name'],
        ':email' => $data['email'],
        ':mobile' => $data['mobile'],
        ':username' => $data['username'],
        ':password' => $hashed_password,
        ':role' => $data['role'],
        ':department' => $data['department'],
        ':dob' => $data['dob'],
        ':gender' => $data['gender'],
        ':joining_date' => $data['joining_date'],
        ':address' => $data['address'],
        ':created_by' => $_SESSION['admin_id'] ?? 1
    ]);

    if (!$success) {
        // Debug: Log SQL error
        error_log("SQL Error: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Failed to insert staff data: " . print_r($stmt->errorInfo()[2], true));
    }

    $staff_id = $conn->lastInsertId();

    // Handle profile photo if uploaded
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $upload_dir = '../../uploads/staff/profile_photos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $new_filename = 'STAFF_' . $staff_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
            // Update staff record with photo
            $stmt = $conn->prepare("UPDATE staff SET profile_photo = ? WHERE id = ?");
            $stmt->execute([$new_filename, $staff_id]);
        }
    }

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Staff member added successfully!";
    error_log("Staff added successfully with ID: " . $staff_id);
    
    header('Location: ../staff.php');
    exit;

} catch (Exception $e) {
    error_log("Error in process_staff.php: " . $e->getMessage());
    
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: add.php');
    exit;
}
?>