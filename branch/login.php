<?php
session_start();
require_once '../config/database.php';

// Get list of branches
try {
    $database = new Database();
    $db = $database->getConnection();
    $query = "SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name";
    $result = $db->query($query);
    $branches = $result->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $branches = [];
    error_log("Error fetching branches: " . $e->getMessage());
}

$currentDateTime = date('Y-m-d H:i:s');
$currentUser = isset($_SESSION['admin_user']['username']) ? $_SESSION['admin_user']['username'] : 'mimisubha';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Login - Distributor Management</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #e0e7ff, #f8fafc 85%);
            min-height: 100vh;
            font-family: 'Segoe UI', sans-serif;
        }
        .header-info {
            padding: 10px 0;
            font-family: monospace;
            font-size: .95rem;
            background: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
            color: #555;
        }
        .login-container {
            max-width: 380px;
            margin: 60px auto 0 auto;
            padding: 32px 26px 24px 26px;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 3px 20px rgba(60,90,130,0.08);
        }
        .logo-container {
            text-align: center;
            margin-bottom: 12px;
        }
        .logo-container img {
            max-width: 110px;
            max-height: 90px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 18px;
        }
        .form-label { font-weight: 500; }
        .form-select, .form-control {
            padding: 0.6rem 0.9rem;
            font-size: 1rem;
            border-radius: 0.5rem;
            background: #f8fafc;
        }
        .input-group-text {
            background: #f1f5f9;
            border: none;
        }
        .btn-primary {
            padding: .55rem 0;
            font-size: 1.05rem;
            border-radius: .4rem;
        }
        .btn-outline-secondary {
            font-size: .98rem;
        }
        .alert { font-size: .95rem; }
        .branch-select-container {
            margin-bottom: .85rem;
        }
        .input-group { border-radius: .5rem; }
        .input-group input { border-left: none; }
        .input-group .form-control:focus { box-shadow: none; }
        @media (max-width: 480px) {
            .login-container { padding: 14px 6px; }
        }
    </style>
</head>
<body>
    <div class="header-info">
        <div class="container">
            <div class="row">
                <div class="col-12 col-md-7">
                    Current Date and Time: <b><?= $currentDateTime ?></b>
                </div>
                <div class="col-12 col-md-5 text-md-end">
                    Current User's Login: <b><?= htmlspecialchars($currentUser) ?></b>
                </div>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="login-container mt-4">
            <div class="logo-container">
                <img src="../assets/images/logo.png" alt="Company Logo" loading="lazy">
            </div>
            <div class="login-header">
                <h4 class="mb-1" style="font-weight:600;letter-spacing:.5px;">Branch Login</h4>
            </div>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <form action="auth.php" method="POST" id="loginForm" autocomplete="off">
                <div class="branch-select-container mb-2">
                    <label for="branch" class="form-label">Select Branch</label>
                    <select class="form-select" id="branch" name="branch_id" required>
                        <option value="">Choose branch...</option>
                        <?php foreach($branches as $branch): ?>
                            <option value="<?= htmlspecialchars($branch['id']) ?>">
                                <?= htmlspecialchars($branch['branch_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required autocomplete="off" placeholder="Username">
                    </div>
                </div>
                <div class="mb-2">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="d-grid gap-2 mt-3 mb-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </button>
                </div>
                <div class="d-grid">
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house"></i> Home
                    </a>
                </div>
            </form>
        </div>
    </div>
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    $(function() {
        $('#togglePassword').click(function() {
            const input = $('#password');
            const icon = $(this).find('i');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('bi-eye').addClass('bi-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('bi-eye-slash').addClass('bi-eye');
            }
        });
        $('#loginForm').on('submit', function(e) {
            if (!$('#branch').val() || !$('#username').val() || !$('#password').val()) {
                e.preventDefault();
                alert('Please fill in all fields');
            }
        });
    });
    </script>
</body>
</html>