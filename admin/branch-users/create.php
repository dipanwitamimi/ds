<?php
session_start();
require_once '../../config/database.php';

$currentDateTime = "2025-03-12 06:55:10";
$currentUser = "sgpriyom";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get branches for dropdown
    $stmt = $db->query("SELECT id, branch_name, branch_code FROM branches WHERE status = 'active' ORDER BY branch_name");
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
    <title>Create Branch User</title>
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
admin/branch-users/create.php</div>
    </div>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Create Branch User</h5>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="store.php" method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <!-- User Information -->
                        <div class="col-md-6">
                            <h6 class="mb-3">User Information</h6>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label required">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                                <div class="invalid-feedback">Please enter full name</div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label required">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>

                            <div class="mb-3">
                                <label for="mobile" class="form-label required">Mobile Number</label>
                                <input type="tel" class="form-control" id="mobile" name="mobile" 
                                       pattern="[0-9]{10}" required>
                                <div class="invalid-feedback">Please enter a valid 10-digit mobile number</div>
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label required">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       pattern="[a-zA-Z0-9_]{5,}" required>
                                <div class="invalid-feedback">Username must be at least 5 characters (letters, numbers, underscore)</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label required">Password</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       minlength="6" required>
                                <div class="invalid-feedback">Password must be at least 6 characters</div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label required">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password" required>
                                <div class="invalid-feedback">Passwords do not match</div>
                            </div>
                        </div>

                        <!-- Branch & Role Information -->
                        <div class="col-md-6">
                            <h6 class="mb-3">Branch & Role Information</h6>

                            <div class="mb-3">
                                <label for="branch_id" class="form-label required">Branch</label>
                                <select class="form-select" id="branch_id" name="branch_id" required>
                                    <option value="">Select Branch</option>
                                    <?php foreach($branches as $branch): ?>
                                        <option value="<?php echo $branch['id']; ?>">
                                            <?php echo htmlspecialchars($branch['branch_name']); ?> 
                                            (<?php echo htmlspecialchars($branch['branch_code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a branch</div>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label required">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="branch_admin">Branch Admin</option>
                                    <option value="manager">Manager</option>
                                    <option value="staff">Staff</option>
                                </select>
                                <div class="invalid-feedback">Please select a role</div>
                            </div>

                            <div class="mb-3">
                                <label for="joining_date" class="form-label required">Joining Date</label>
                                <input type="date" class="form-control" id="joining_date" name="joining_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Please select joining date</div>
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label required">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <div class="invalid-feedback">Please select status</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Permissions</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="can_manage_staff" 
                                           name="permissions[]" value="manage_staff">
                                    <label class="form-check-label" for="can_manage_staff">
                                        Can Manage Staff
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="can_manage_inventory" 
                                           name="permissions[]" value="manage_inventory">
                                    <label class="form-check-label" for="can_manage_inventory">
                                        Can Manage Inventory
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="can_manage_transactions" 
                                           name="permissions[]" value="manage_transactions">
                                    <label class="form-check-label" for="can_manage_transactions">
                                        Can Manage Transactions
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Create User
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
            
            // Fetch all forms we want to apply validation styles to
            var forms = document.querySelectorAll('.needs-validation');
            
            // Loop over them and prevent submission
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    // Check if passwords match
                    var password = document.getElementById('password');
                    var confirm = document.getElementById('confirm_password');
                    
                    if (password.value !== confirm.value) {
                        confirm.setCustomValidity('Passwords do not match');
                        event.preventDefault();
                        event.stopPropagation();
                    } else {
                        confirm.setCustomValidity('');
                    }

                    form.classList.add('was-validated');
                }, false);

                // Real-time password match validation
                var password = document.getElementById('password');
                var confirm = document.getElementById('confirm_password');
                
                function validatePassword(){
                    if(password.value !== confirm.value) {
                        confirm.setCustomValidity('Passwords do not match');
                    } else {
                        confirm.setCustomValidity('');
                    }
                }

                password.onchange = validatePassword;
                confirm.onkeyup = validatePassword;
            });
        })();

        // Role-based permission management
        document.getElementById('role').addEventListener('change', function() {
            var permissions = document.getElementsByName('permissions[]');
            
            // Reset all permissions
            permissions.forEach(function(permission) {
                permission.checked = false;
                permission.disabled = false;
            });

            // Set permissions based on role
            switch(this.value) {
                case 'branch_admin':
                    permissions.forEach(function(permission) {
                        permission.checked = true;
                    });
                    break;
                case 'manager':
                    document.getElementById('can_manage_staff').checked = true;
                    document.getElementById('can_manage_inventory').checked = true;
                    break;
                case 'staff':
                    permissions.forEach(function(permission) {
                        permission.disabled = true;
                    });
                    break;
            }
        });
    </script>
</body>
</html>