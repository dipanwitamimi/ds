<?php
session_start();
$currentDateTime = "2025-03-11 22:09:43";
$currentUser = "sgpriyom";

require_once '../../config/database.php';

// Get staff details
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $staff_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$staff_id) {
        throw new Exception("Invalid staff ID");
    }
    
    $stmt = $conn->prepare("
        SELECT s.*, b.branch_name 
        FROM staff s
        LEFT JOIN branches b ON s.branch_id = b.id
        WHERE s.id = ?
    ");
    
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch();
    
    if (!$staff) {
        throw new Exception("Staff not found");
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: ../staff.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff - <?php echo htmlspecialchars($staff['full_name']); ?></title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        .header-info {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-line;
        }
        .required:after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        .profile-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?>
Current User's Login: <?php echo $currentUser; ?>
admin/staff/edit.php</div>
    </div>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Edit Staff - <?php echo htmlspecialchars($staff['full_name']); ?></h4>
                <a href="../staff.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form id="editStaffForm" method="POST" action="process_edit.php" enctype="multipart/form-data">
                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                    
                    <div class="row">
                        <!-- Profile Photo -->
                        <div class="col-md-12 text-center mb-4">
                            <img src="<?php echo $staff['profile_photo'] ? '../../uploads/staff/profile_photos/' . $staff['profile_photo'] : '../../assets/images/default-avatar.png'; ?>" 
                                 class="profile-preview" id="profilePreview" alt="Profile Photo">
                            <div>
                                <label class="btn btn-outline-primary">
                                    Change Photo
                                    <input type="file" name="profile_photo" id="profilePhoto" style="display: none;" accept="image/*">
                                </label>
                            </div>
                        </div>

                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Personal Information</h5>
                            
                            <div class="mb-3">
                                <label class="form-label required">Full Name</label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($staff['full_name']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Mobile Number</label>
                                <input type="tel" class="form-control" name="mobile" value="<?php echo htmlspecialchars($staff['mobile']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Date of Birth</label>
                                <input type="date" class="form-control" name="dob" value="<?php echo $staff['dob']; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="male" <?php echo $staff['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $staff['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo $staff['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Employment Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Employment Information</h5>

                            <div class="mb-3">
                                <label class="form-label">Staff ID</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($staff['staff_id']); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Branch</label>
                                <select class="form-select" name="branch_id" required>
                                    <?php
                                    $branches = $conn->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name")->fetchAll();
                                    foreach ($branches as $branch) {
                                        $selected = $branch['id'] === $staff['branch_id'] ? 'selected' : '';
                                        echo "<option value='{$branch['id']}' {$selected}>{$branch['branch_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Role</label>
                                <select class="form-select" name="role" required>
                                    <option value="manager" <?php echo $staff['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                    <option value="supervisor" <?php echo $staff['role'] === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                    <option value="staff" <?php echo $staff['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department">
                                    <option value="sales" <?php echo $staff['department'] === 'sales' ? 'selected' : ''; ?>>Sales</option>
                                    <option value="operations" <?php echo $staff['department'] === 'operations' ? 'selected' : ''; ?>>Operations</option>
                                    <option value="accounts" <?php echo $staff['department'] === 'accounts' ? 'selected' : ''; ?>>Accounts</option>
                                    <option value="admin" <?php echo $staff['department'] === 'admin' ? 'selected' : ''; ?>>Administration</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo $staff['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $staff['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="col-md-12 mt-3">
                            <h5 class="mb-3">Additional Information</h5>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($staff['address']); ?></textarea>
                            </div>
                        </div>

                        <!-- Password Reset -->
                        <div class="col-md-12 mt-3">
                            <h5 class="mb-3">Change Password</h5>
                            <div class="alert alert-info">
                                Leave password fields empty if you don't want to change the password.
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="password">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            <a href="../staff.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/jquery.validate.min.js"></script>
<script src="../../assets/js/jquery.min.js"></script>
<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/jquery.validate.min.js"></script>
<script src="../../assets/js/select2.min.js"></script>
<script src="../../assets/js/moment.min.js"></script>
<script src="../../assets/js/bootstrap-datepicker.min.js"></script>
<script src="../../assets/js/sweetalert2.min.js"></script>
<script src="../../assets/js/xlsx.full.min.js"></script>
<script src="../../assets/js/pdfmake.min.js"></script>
<script src="../../assets/js/vfs_fonts.js"></script>
<script src="../../assets/js/chart.min.js"></script>
<script src="js/staff.js"></script>
<script src="js/staff-view.js"></script>
    <script>
        $(document).ready(function() {
            // Preview profile photo
            $("#profilePhoto").change(function() {
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#profilePreview').attr('src', e.target.result);
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });

            // Form validation
            $("#editStaffForm").validate({
                rules: {
                    full_name: {
                        required: true,
                        minlength: 3
                    },
                    email: {
                        required: true,
                        email: true
                    },
                    mobile: {
                        required: true,
                        minlength: 10
                    },
                    confirm_password: {
                        equalTo: "[name='password']"
                    }
                },
                messages: {
                    confirm_password: {
                        equalTo: "Passwords do not match!"
                    }
                },
                errorElement: 'span',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.mb-3').append(error);
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass('is-invalid');
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).removeClass('is-invalid');
                }
            });
        });
    </script>
</body>
</html>