<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$user = $_SESSION['branch_user'];
$branch_id = $user['branch_id'];
$username = $user['username'] ?? '';
$page_title = 'APB Transactions';

$db = new Database();
$conn = $db->getConnection();

// Fetch branch name
$stmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch_row = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_name = $branch_row ? $branch_row['branch_name'] : '';

// Fetch all APB transactions for this branch (latest first)
$stmt = $conn->prepare("
    SELECT a.*, 
           s.full_name AS staff_name, 
           br.branch_name
    FROM apb a
    LEFT JOIN staff s ON a.staff_id = s.id
    LEFT JOIN branches br ON a.branch_id = br.id
    WHERE a.branch_id = ?
    ORDER BY a.transaction_date DESC, a.created_at DESC, a.id DESC
");
$stmt->execute([$branch_id]);
$apb_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Column names as per your DB
$col_opening   = 'opening_stock';
$col_received  = 'quantity_received';
$col_auto      = 'auto_quantity';
$col_available = 'total_available';
$col_sale      = 'total_sold';
$col_closing   = 'closing_stock';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>APB Transactions</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/bootstrap-icons.css">
    <style>
        .table thead th, .table tbody td { vertical-align: middle; text-align: center; }
        .sidebar {
            height: 100vh;
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
            min-width: 220px;
            position: fixed;
            left: 0;
            top: 0;
            padding-top: 1rem;
        }
        .sidebar .nav-link.active {
            background: #e9ecef;
            font-weight: bold;
        }
        .main-content {
            margin-left: 230px;
        }
        @media (max-width: 991px) {
            .sidebar {
                position: static;
                height: auto;
                width: 100%;
                border-right: none;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Sidebar Navigation -->
    <div class="sidebar d-none d-lg-block">
        <div class="px-3 mb-3">
            <h5 class="mb-1">Branch Panel</h5>
            <div class="small text-muted"><?= htmlspecialchars($branch_name) ?></div>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link" href="../lapu/index.php"><i class="bi bi-phone"></i> LAPU</a>
            <a class="nav-link" href="../sim_cards/index.php"><i class="bi bi-sim"></i> SIM Cards</a>
            <a class="nav-link active" href="index.php"><i class="bi bi-credit-card"></i> APB</a>
            <a class="nav-link" href="../dth/index.php"><i class="bi bi-tv"></i> DTH</a>
            <a class="nav-link" href="../cash_deposit/index.php"><i class="bi bi-bank"></i> Cash Deposits</a>
            <a class="nav-link" href="../staff.php"><i class="bi bi-person-badge"></i> Staff</a>
            <a class="nav-link" href="../services.php"><i class="bi bi-boxes"></i> Services</a>
            <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </div>
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid py-3">
            <div class="mb-4 d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="mb-0">APB Transactions</h3>
                <div>
                    <a href="create.php" class="btn btn-primary me-2">
                        <i class="bi bi-plus-circle"></i> New Transaction
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-center">
                            <thead class="table-light">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Branch Name</th>
                                    <th>Staff Name</th>
                                    <th>Received</th>
                                    <th>Opening Stock</th>
                                    <th>Auto Quantity</th>
                                    <th>Total Available</th>
                                    <th>Total Sold</th>
                                    <th>Closing Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($apb_list as $row): ?>
                                <tr>
                                    <td>
                                        <?= !empty($row['created_at'])
                                            ? date('Y-m-d H:i:s', strtotime($row['created_at']))
                                            : (!empty($row['transaction_date'])
                                                ? date('Y-m-d H:i:s', strtotime($row['transaction_date']))
                                                : '-') ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['branch_name'] ?? '') ?></td>
                                    <td>
                                        <?php
                                            if (!empty($row['staff_name'])) {
                                                echo htmlspecialchars($row['staff_name']);
                                            } elseif (!empty($row['staff_id'])) {
                                                echo "ID #" . htmlspecialchars($row['staff_id']);
                                            } else {
                                                echo "N/A";
                                            }
                                        ?>
                                    </td>
                                    <td><?= number_format((float)($row[$col_received] ?? 0), 0) ?></td>
                                    <td><?= number_format((float)($row[$col_opening] ?? 0), 0) ?></td>
                                    <td><?= number_format((float)($row[$col_auto] ?? 0), 0) ?></td>
                                    <td><?= number_format((float)($row[$col_available] ?? 0), 0) ?></td>
                                    <td><?= number_format((float)($row[$col_sale] ?? 0), 0) ?></td>
                                    <td><?= number_format((float)($row[$col_closing] ?? 0), 0) ?></td>
                                    <td>
                                        <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info mb-1">
                                            View
                                        </a>
                                        <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary mb-1">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($apb_list)): ?>
                                <tr>
                                    <td colspan="10" class="text-muted">No APB transactions found for this branch.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <span class="text-muted">Total transactions: <?= count($apb_list) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>