<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/Database.php';

$currentDateTime = "2025-03-12 07:10:58";
$currentUser = "sgpriyom";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user ID from URL
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new Exception("Invalid user ID");
    }

    // Get user details
    $stmt = $db->prepare("
        SELECT bu.*, b.branch_name, b.branch_code
        FROM branch_users bu
        LEFT JOIN branches b ON bu.branch_id = b.id
        WHERE bu.id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    // Get all active branches
    $stmt = $db->query("SELECT id, branch_name, branch_code FROM branches WHERE status = 'active' ORDER BY branch_name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: users.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Branch User</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
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
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?>
Current User's Login: <?php echo $currentUser; ?>
admin/branch/edit-user.php</div>
    </div>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit Branch User</h5>
                <a href="users.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Users
                </a>
            </div>
            <div class="card-body">
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- User Profile Overview -->
                    <div class="col-md-3">
                        <div class="text-center mb-4">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                            </div>
                            <h6><?php echo htmlspecialchars($user['full_name']); ?></h6>
                            <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                        <div class="list-group mb-4">
                            <div class="list-group-item">
                                <small class="text-muted">User ID</small><br>
                                <?php echo htmlspecialchars($user['user_id']); ?>
                            </div>
                            <div class="list-group-item">
                                <small class="text-muted">Created On</small><br>
                                <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
                            </div>
                            <div class="list-group-item">
                                <small class="text-muted">Last Login</small><br>
                                <?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Form -->
                    <div class="col-md-9">
                        <form action="update-user.php" method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            
                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-md-6">
                                    <h6 class="mb-3">Personal Information</h6>
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label required">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name"
                                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label required">Email</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="mobile" class="form-label required">Mobile Number</label>
                                        <input type="tel" class="form-control" id="mobile" name="mobile"
                                               value="<?php echo htmlspecialchars($user['mobile']); ?>"
                                               pattern="[0-9]{10}" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"
                                        ><?php echo htmlspecialchars($user['address']); ?></textarea>
                                    </div>
                                </div>

                                <!-- Account Information -->
                                <div class="col-md-6">
                                    <h6 class="mb-3">Account Information</h6>

                                    <div class="mb-3">
                                        <label for="branch_id" class="form-label required">Branch</label>
                                        <select class="form-select" id="branch_id" name="branch_id" required>
                                            <?php foreach($branches as $branch): ?>
                                                <option value="<?php echo $branch['id']; ?>"
                                                    <?php echo $user['branch_id'] == $branch['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($branch['branch_name'] . 
                                                          ' (' . $branch['branch_code'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="role" class="form-label required">Role</label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="branch_manager" <?php echo $user['role'] === 'branch_manager' ? 'selected' : ''; ?>>
                                                Branch Manager
                                            </option>
                                            <option value="cashier" <?php echo $user['role'] === 'cashier' ? 'selected' : ''; ?>>
                                                Cashier
                                            </option>
                                            <option value="operator" <?php echo $user['role'] === 'operator' ? 'selected' : ''; ?>>
                                                Operator
                                            </option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="status" class="form-label required">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>
                                                Active
                                            </option>
                                            <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>
                                                Inactive
                                            </option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Permissions</label>
                                        <?php 
                                        $permissions = json_decode($user['permissions'], true) ?? [];
                                        ?>
                                        <div class="form-check mb-2">
                                            <input type="checkbox" class="form-check-input" id="perm_cash" 
                                                   name="permissions[]" value="manage_cash"
                                                   <?php echo in_array('manage_cash', $permissions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="perm_cash">Manage Cash</label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input type="checkbox" class="form-check-input" id="perm_transactions" 
                                                   name="permissions[]" value="manage_transactions"
                                                   <?php echo in_array('manage_transactions', $permissions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="perm_transactions">Manage Transactions</label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input type="checkbox" class="form-check-input" id="perm_reports" 
                                                   name="permissions[]" value="view_reports"
                                                   <?php echo in_array('view_reports', $permissions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="perm_reports">View Reports</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="perm_staff" 
                                                   name="permissions[]" value="manage_staff"
                                                   <?php echo in_array('manage_staff', $permissions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="perm_staff">Manage Staff</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Changes
                                </button>
                                <a href="users.php" class="btn btn-secondary">
                                    <i class="bi bi-x"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
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

            // Role-based permission management
            document.getElementById('role').addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('input[name="permissions[]"]');
                
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = false;
                });

                switch(this.value) {
                    case 'branch_manager':
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = true;
                        });
                        break;
                    case 'cashier':
                        document.getElementById('perm_cash').checked = true;
                        document.getElementById('perm_transactions').checked = true;
                        break;
                    case 'operator':
                        document.getElementById('perm_transactions').checked = true;
                        break;
                }
            });
        })();
    </script>
</body>
</html>