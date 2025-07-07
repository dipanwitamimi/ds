<?php
session_start();
require_once '../../config/database.php';

$currentDateTime = "2025-03-11 23:12:35";
$currentUser = "sgpriyom";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get active branches
    $stmt = $db->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Branch User</title>
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
branch/users/add.php</div>
    </div>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Add Branch User</h4>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Users
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

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form id="addUserForm" action="process_user.php" method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">User Information</h5>
                            
                            <div class="mb-3">
                                <label for="branch_id" class="form-label required">Branch</label>
                                <select class="form-select" id="branch_id" name="branch_id" required>
                                    <option value="">Select Branch</option>
                                    <?php foreach($branches as $branch): ?>
                                        <option value="<?php echo $branch['id']; ?>">
                                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a branch</div>
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label required">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required
                                       pattern="[a-zA-Z0-9_]{5,}" title="Username must be at least 5 characters long and can only contain letters, numbers, and underscores">
                                <div class="invalid-feedback">Please enter a valid username (minimum 5 characters)</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label required">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required
                                           pattern=".{6,}" title="Password must be at least 6 characters long">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Password must be at least 6 characters long</div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label required">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" required>
                                <div class="invalid-feedback">Passwords do not match</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5 class="mb-3">Personal Information</h5>

                            <div class="mb-3">
                                <label for="full_name" class="form-label required">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                                <div class="invalid-feedback">Please enter full name</div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label required">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>

                            <div class="mb-3">
                                <label for="mobile" class="form-label required">Mobile Number</label>
                                <input type="tel" class="form-control" id="mobile" name="mobile" required
                                       pattern="[0-9]{10}" title="Please enter a valid 10-digit mobile number">
                                <div class="invalid-feedback">Please enter a valid 10-digit mobile number</div>
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
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Add User
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle password visibility
            $('#togglePassword').click(function() {
                const password = $('#password');
                const icon = $(this).find('i');
                
                if (password.attr('type') === 'password') {
                    password.attr('type', 'text');
                    icon.removeClass('bi-eye').addClass('bi-eye-slash');
                } else {
                    password.attr('type', 'password');
                    icon.removeClass('bi-eye-slash').addClass('bi-eye');
                }
            });

            // Form validation
            const form = document.getElementById('addUserForm');
            
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                // Check if passwords match
                const password = $('#password').val();
                const confirmPassword = $('#confirm_password').val();
                
                if (password !== confirmPassword) {
                    event.preventDefault();
                    $('#confirm_password')[0].setCustomValidity('Passwords do not match');
                } else {
                    $('#confirm_password')[0].setCustomValidity('');
                }

                form.classList.add('was-validated');
            }, false);

            // Reset custom validity on input
            $('#confirm_password').on('input', function() {
                this.setCustomValidity('');
            });
        });
    </script>
</body>
</html>