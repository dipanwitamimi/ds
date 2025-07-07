<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/Database.php';

$currentDateTime = "2025-03-12 08:07:31";
$currentUser = "sgpriyom";

// Get branches for dropdown
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $branches = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Branch User - <?php echo SITE_NAME; ?></title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Add New Branch User</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['error'];
                                unset($_SESSION['error']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <form action="save-user.php" method="post" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required 
                                       value="<?php echo $_SESSION['form_data']['username'] ?? ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                       value="<?php echo $_SESSION['form_data']['full_name'] ?? ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo $_SESSION['form_data']['email'] ?? ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="mobile" class="form-label">Mobile Number</label>
                                <input type="text" class="form-control" id="mobile" name="mobile"
                                       pattern="[0-9]{10}"
                                       value="<?php echo $_SESSION['form_data']['mobile'] ?? ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="branch_id" class="form-label">Branch *</label>
                                <select class="form-select" id="branch_id" name="branch_id" required>
                                    <option value="">Select Branch</option>
                                    <?php foreach($branches as $branch): ?>
                                        <option value="<?php echo $branch['id']; ?>"
                                            <?php echo (isset($_SESSION['form_data']['branch_id']) && 
                                                $_SESSION['form_data']['branch_id'] == $branch['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label">Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="staff" <?php echo (isset($_SESSION['form_data']['role']) && 
                                        $_SESSION['form_data']['role'] == 'staff') ? 'selected' : ''; ?>>Staff</option>
                                    <option value="manager" <?php echo (isset($_SESSION['form_data']['role']) && 
                                        $_SESSION['form_data']['role'] == 'manager') ? 'selected' : ''; ?>>Manager</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php 
                                    echo $_SESSION['form_data']['address'] ?? ''; 
                                ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required
                                       minlength="6">
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirm_password" required
                                       minlength="6">
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="users.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Create User</button>
                            </div>
                        </form>
                        <?php unset($_SESSION['form_data']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('confirm_password').addEventListener('input', function() {
            if (this.value !== document.getElementById('password').value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>