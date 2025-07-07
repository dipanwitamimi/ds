<?php
session_start();
require_once '../../config/database.php';

$currentDateTime = "2025-03-12 06:45:08";
$currentUser = "sgpriyom";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new Exception("Invalid staff ID");
    }

    // Get staff details
    $stmt = $db->prepare("
        SELECT bs.*, b.branch_name
        FROM branch_staff bs
        LEFT JOIN branches b ON bs.branch_id = b.id
        WHERE bs.id = ?
    ");
    $stmt->execute([$id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        throw new Exception("Staff not found");
    }

    // Get branches for dropdown
    $stmt = $db->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: index.php');
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
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?>
Current User's Login: <?php echo $currentUser; ?>
branch/staff/edit.php</div>
    </div>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit Staff Details</h5>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <form action="update.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
                    
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Personal Information</h5>
                            
                            <div class="mb-3">
                                <label for="staff_id" class="form-label">Staff ID</label>
                                <input type="text" class="form-control" id="staff_id" 
                                       value="<?php echo htmlspecialchars($staff['staff_id']); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label for="full_name" class="form-label required">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                       value="<?php echo htmlspecialchars($staff['full_name']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label required">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="mobile" class="form-label required">Mobile</label>
                                <input type="tel" class="form-control" id="mobile" name="mobile"
                                       value="<?php echo htmlspecialchars($staff['mobile']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="gender" class="form-label required">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo $staff['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $staff['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo $staff['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="dob" class="form-label required">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob"
                                       value="<?php echo $staff['dob']; ?>" required>
                            </div>
                        </div>

                        <!-- Employment Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Employment Information</h5>

                            <div class="mb-3">
                                <label for="branch_id" class="form-label required">Branch</label>
                                <select class="form-select" id="branch_id" name="branch_id" required>
                                    <option value="">Select Branch</option>
                                    <?php foreach($branches as $branch): ?>
                                        <option value="<?php echo $branch['id']; ?>"
                                            <?php echo $staff['branch_id'] == $branch['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="designation" class="form-label required">Designation</label>
                                <input type="text" class="form-control" id="designation" name="designation"
                                       value="<?php echo htmlspecialchars($staff['designation']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department"
                                       value="<?php echo htmlspecialchars($staff['department']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="joining_date" class="form-label required">Joining Date</label>
                                <input type="date" class="form-control" id="joining_date" name="joining_date"
                                       value="<?php echo $staff['joining_date']; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="salary" class="form-label">Salary</label>
                                <input type="number" class="form-control" id="salary" name="salary"
                                       value="<?php echo $staff['salary']; ?>" step="0.01">
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label required">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $staff['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $staff['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Account Settings -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h5 class="mb-3">Account Settings</h5>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username"
                                       value="<?php echo htmlspecialchars($staff['username']); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password"
                                       minlength="6" placeholder="Leave blank to keep current password">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-x"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>