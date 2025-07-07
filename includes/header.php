<?php
if (!defined('SITE_NAME')) {
    exit('Direct script access denied.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>



    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? SITE_NAME; ?></title>
    <link href="<?php echo SITE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        .main-header {
            background: #2c3e50;
            padding: 1rem 0;
            color: white;
        }
        .main-header .navbar-brand {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .main-header .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.5rem 1rem;
        }
        .main-header .nav-link:hover {
            color: white;
        }
        .main-header .user-info {
            color: rgba(255,255,255,0.8);
        }
        .nav-pills .nav-link.active {
            background-color: #34495e;
        }
    </style>
	
</head>
<body>
<script>
        // Auto logout when page is closed or user navigates away
        window.addEventListener('beforeunload', function() {
            navigator.sendBeacon('auto_logout.php');
        });

        // Auto logout after inactivity
        let inactivityTime = function () {
            let time;
            const logoutTime = 30 * 60 * 1000; // 30 minutes in milliseconds

            window.onload = resetTimer;
            document.onmousemove = resetTimer;
            document.onkeypress = resetTimer;
            document.onscroll = resetTimer;
            document.onclick = resetTimer;

            function logout() {
                window.location.href = 'logout.php?reason=inactivity';
            }

            function resetTimer() {
                clearTimeout(time);
                time = setTimeout(logout, logoutTime);
            }
        };
        inactivityTime();
    </script>
</head>
<body>
    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h1 class="navbar-brand mb-0">Distributor Management</h1>
                    <nav class="ms-4">
                        <ul class="nav nav-pills">
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($currentPage === 'branches') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>../admin/branch/">
                                    <i class="bi bi-building"></i> Branch
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($currentPage === 'staff') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>../admin/staff/">
                                    <i class="bi bi-people"></i> Staff
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($currentPage === 'transactions') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>../admin/transactions/">
                                    <i class="bi bi-cash-stack"></i> Transactions
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($currentPage === 'reports') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>../admin/reports/">
                                    <i class="bi bi-file-text"></i> Reports
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <div class="d-flex align-items-center">
                    <span class="user-info me-3">
                        <i class="bi bi-person-circle"></i>
                        <?php echo $currentUser; ?>
                    </span>
                    <a href="<?php echo SITE_URL; ?>logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
				
            </div>
        </div>
    </header>