<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distributor Management</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }
        .header-info {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-wrap;
        }
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo-container img {
            max-width: 150px;
            height: auto;
        }
        .login-options {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        .login-option {
            text-decoration: none;
            padding: 15px 25px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s ease;
            color: #333;
        }
        .login-option:hover {
            border-color: #0d6efd;
            background: #f8f9fa;
            color: #0d6efd;
        }
        .login-option i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
        .login-option.active {
            border-color: #0d6efd;
            background: #e7f1ff;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    

    <!-- Main Content -->
    <div class="container">
        <div class="login-container">
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="Company Logo">
            </div>
            <div class="login-header">
                <h2>Distributor Management</h2>
            </div>
            
            <!-- Login Options -->
            <div class="login-options">
                <a href="admin/login.php" class="login-option">
                    <i class="bi bi-shield-lock"></i>
                    <div>Admin Login</div>
                </a>
                <a href="branch/login.php" class="login-option">
                    <i class="bi bi-shop"></i>
                    <div>Branch Login</div>
                </a>
            </div>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger text-center">
                    <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <small class="text-muted">Please select your login type</small>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>