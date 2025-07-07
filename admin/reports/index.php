<?php
session_start();
require_once '../../includes/auth.php'; // Ensure user is authenticated
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Panel</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .report-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .nav-link.active {
            font-weight: bold;
            color: #0d6efd !important;
        }
        .sidebar {
            min-height: 100vh;
            border-right: 1px solid #dee2e6;
            background: #fff;
        }
        .sidebar .nav-link {
            color: #333;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: #e9ecef;
            color: #0d6efd !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation Panel -->
            <nav class="col-md-2 d-none d-md-block sidebar p-0">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="../../admin/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="../../admin/branch/">
                                <i class="bi bi-diagram-3"></i> Branches
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="../../admin/staff/">
                                <i class="bi bi-people"></i> Staff
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="../../admin/transactions/">
                                <i class="bi bi-cash-stack"></i> Transactions
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link active" href="../../admin/reports/">
                                <i class="bi bi-bar-chart-line"></i> Reports
                            </a>
                        </li>
                    </ul>
                    <hr>
                    <div class="px-3">
                        <small class="text-muted">Logged in as:<br><strong><?= htmlspecialchars($_SESSION['admin_user']['username'] ?? 'Admin') ?></strong></small>
                    </div>
                </div>
            </nav>
            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-4">
                <div class="container mt-4">
                    <h1 class="mb-4">Reports</h1>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card report-card p-3 text-center">
                                <h5>Staff Report</h5>
                                <p>View and analyze staff-related data.</p>
                                <a href="staff.php" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card report-card p-3 text-center">
                                <h5>Bank Report</h5>
                                <p>View bank account transactions and summaries.</p>
                                <a href="bank.php" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card report-card p-3 text-center">
                                <h5>LAPU Report</h5>
                                <p>Analyze LAPU-related operations.</p>
                                <a href="lapu.php" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card report-card p-3 text-center">
                                <h5>SIM Report</h5>
                                <p>View and manage SIM card details.</p>
                                <a href="sim.php" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card report-card p-3 text-center">
                                <h5>APB Report</h5>
                                <p>Track APB-related operations.</p>
                                <a href="apb.php" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card report-card p-3 text-center">
                                <h5>DTH Report</h5>
                                <p>Analyze DTH transactions and data.</p>
                                <a href="dth.php" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card report-card p-3 text-center">
                                <h5>Cash Deposit Report</h5>
                                <p>View cash deposit details and summaries.</p>
                                <a href="cash_deposit.php" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card report-card p-3 text-center">
                                <h5>User Report</h5>
                                <p>View application users, login history, and role assignments.</p>
                                <a href="user.php" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card report-card p-3 text-center">
                                <h5>System Report</h5>
                                <p>System health, usage stats, and application environment details.</p>
                                <a href="system.php" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <!-- Optionally add Bootstrap JS and Bootstrap Icons CDN if not already included globally -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</body>
</html>