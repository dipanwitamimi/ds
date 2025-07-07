<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$user = $_SESSION['branch_user'];
$branch_id = $user['branch_id'];
$username = $user['username'] ?? '';
$page_title = 'DTH Management';

$db = new Database();
$conn = $db->getConnection();

// Fetch branch name
$stmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch_row = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_name = $branch_row ? $branch_row['branch_name'] : '';

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Get yesterday's closing as today's opening
$stmt = $conn->prepare("SELECT closing_amount FROM dth WHERE branch_id = ? AND DATE(transaction_date) <= ? ORDER BY transaction_date DESC, id DESC LIMIT 1");
$stmt->execute([$branch_id, $yesterday]);
$opening_balance = $stmt->fetchColumn();
$opening_balance = $opening_balance !== false ? (float)$opening_balance : 0.00;

// Today's DTH transaction (if any)
$stmt = $conn->prepare("
    SELECT d.*, 
           st.full_name AS staff_name, 
           bacc.bank_name, 
           bacc.account_number, 
           br.branch_name
    FROM dth d
    LEFT JOIN staff st ON d.staff_id = st.id
    LEFT JOIN bank_accounts bacc ON d.bank_account_id = bacc.id
    LEFT JOIN branches br ON d.branch_id = br.id
    WHERE d.branch_id = ? AND DATE(d.transaction_date) = ?
    ORDER BY d.created_at DESC
    LIMIT 1
");
$stmt->execute([$branch_id, $today]);
$today_dth = $stmt->fetch(PDO::FETCH_ASSOC);

// Today's allocations to staff (sum)
$stmt = $conn->prepare("SELECT IFNULL(SUM(quantity),0) AS allocated FROM dth_staff_allocations WHERE branch_id = ? AND allocation_date = ?");
$stmt->execute([$branch_id, $today]);
$today_allocated = (float)$stmt->fetchColumn();

// Today's staff sell (sum of all staff actual_value for today)
$stmt = $conn->prepare("SELECT IFNULL(SUM(actual_value),0) AS staff_sale FROM staff_dth_sales WHERE branch_id = ? AND sell_date = ?");
$stmt->execute([$branch_id, $today]);
$staff_sale = (float)$stmt->fetchColumn();

// For stats (use today's transaction if any, else compute from opening)
$amount_received = $today_dth ? (float)$today_dth['amount_received'] : 0.00;
$auto_amount     = $today_dth ? (float)$today_dth['auto_amount'] : 0.00;
$total_available = $today_dth ? (float)$today_dth['total_available_fund'] : ($opening_balance + $amount_received + $auto_amount);
// FIX: Always use staff_sale for total_spent (it matches Actual Value (Sell+2.9%) in staff_dth_sales)
$total_spent     = $staff_sale;
$closing_amount  = $total_available - $total_spent;

$stats = [
    'opening'   => $opening_balance,
    'received'  => $amount_received,
    'auto'      => $auto_amount,
    'available' => $total_available,
    'allocated' => $today_allocated,
    'sale'      => $total_spent,
    'closing'   => $closing_amount,
];

// For transactions table: show today's DTH transaction if any
$recent_dth = $today_dth ? [$today_dth] : [];
$dth_date = $today;

// For Staff Allocations Table: show all allocations for today
$stmt = $conn->prepare("
    SELECT a.*, s.full_name 
    FROM dth_staff_allocations a
    LEFT JOIN staff s ON a.staff_id = s.id
    WHERE a.branch_id = ? AND a.allocation_date = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$branch_id, $today]);
$allocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For Staff Sell Table: show all staff sells for today
$stmt = $conn->prepare("
    SELECT s.full_name, sls.*
    FROM staff_dth_sales sls
    LEFT JOIN staff s ON sls.staff_id = s.id
    WHERE sls.branch_id = ? AND sls.sell_date = ?
    ORDER BY sls.created_at DESC
");
$stmt->execute([$branch_id, $today]);
$sells = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DTH Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/bootstrap-icons.css">
    <style>
        .dth-stats-value { font-size: 1.5rem; font-weight: 700; }
        .dth-stats-label { font-size: .97rem; font-weight: 500; color: #6c757d; }
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
            <a class="nav-link<?= (basename(dirname($_SERVER['PHP_SELF']))=='dashboard') ? ' active' : '' ?>" href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link<?= (basename($_SERVER['PHP_SELF'])=='index.php') ? ' active' : '' ?>" href="index.php"><i class="bi bi-tv"></i> DTH</a>
            <a class="nav-link" href="../lapu/index.php"><i class="bi bi-phone"></i> LAPU</a>
            <a class="nav-link" href="../sim_cards/index.php"><i class="bi bi-sim"></i> SIM Cards</a>
            <a class="nav-link" href="../apb/index.php"><i class="bi bi-credit-card"></i> APB</a>
            <a class="nav-link" href="../cash_deposit/index.php"><i class="bi bi-bank"></i> Cash Deposits</a>
            <a class="nav-link" href="../staff.php"><i class="bi bi-person-badge"></i> Staff</a>
            <a class="nav-link" href="../services.php"><i class="bi bi-boxes"></i> Services</a>
            <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </div>
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid py-3">
            <div class="row align-items-center mb-4">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="border rounded p-3 bg-white d-flex flex-column align-items-center justify-content-center h-100">
                        <span>Current Date and Time (UTC):</span>
                        <span id="current-datetime" class="fw-bold fs-5"><?= date('Y-m-d H:i:s') ?> UTC</span>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="border rounded p-3 bg-white d-flex flex-column align-items-center justify-content-center h-100">
                        <span>Current User's Login:</span>
                        <span class="fw-bold fs-5"><?= htmlspecialchars($username) ?></span>
                    </div>
                </div>
            </div>
            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-6 col-md-2">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <span class="dth-stats-label">Opening Balance</span>
                            <div class="dth-stats-value"><?= number_format($stats['opening'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <span class="dth-stats-label">Amount Received</span>
                            <div class="dth-stats-value"><?= number_format($stats['received'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <span class="dth-stats-label">Auto Amount</span>
                            <div class="dth-stats-value"><?= number_format($stats['auto'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <span class="dth-stats-label">Total Available</span>
                            <div class="dth-stats-value"><?= number_format($stats['available'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <span class="dth-stats-label">Allocated to Staff</span>
                            <div class="dth-stats-value"><?= number_format($stats['allocated'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <span class="dth-stats-label">Total Spent</span>
                            <div class="dth-stats-value"><?= number_format($stats['sale'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2 mt-md-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <span class="dth-stats-label">Closing Amount</span>
                            <div class="dth-stats-value"><?= number_format($stats['closing'], 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
         <!-- Actions -->
<div class="card mb-4">
    <div class="card-body">
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> New Issue to Staff
        </a>
        <a href="staff_sell.php" class="btn btn-warning">
            <i class="bi bi-cart-check"></i> Staff Sell
        </a>
        <a href="transactions.php" class="btn btn-info">
            <i class="bi bi-list"></i> View All Transactions
        </a>
        <a href="reports.php" class="btn btn-secondary">
            <i class="bi bi-file-text"></i> Generate Report
        </a>
    </div>
</div>
            <!-- Recent DTH Transactions Table (today's, if any) -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        Today's DTH Transaction (<?= htmlspecialchars($dth_date) ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-center">
                            <thead class="table-light">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Branch Name</th>
                                    <th>Staff Name</th>
                                    <th>Bank Name/Account</th>
                                    <th>Amount Received</th>
                                    <th>Opening Balance</th>
                                    <th>Auto Amount</th>
                                    <th>Total Available</th>
                                    <th>Allocated to Staff</th>
                                    <th>Total Spent</th>
                                    <th>Closing Amount</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_dth as $row): ?>
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
                                    <td>
                                        <?php
                                            if (!empty($row['bank_name']) && !empty($row['account_number'])) {
                                                echo htmlspecialchars($row['bank_name']) . " / " . htmlspecialchars($row['account_number']);
                                            } elseif (!empty($row['bank_account_id'])) {
                                                echo "ID #" . htmlspecialchars($row['bank_account_id']);
                                            } else {
                                                echo "N/A";
                                            }
                                        ?>
                                    </td>
                                    <td><?= number_format((float)$row['amount_received'], 2) ?></td>
                                    <td><?= number_format((float)$row['opening_balance'], 2) ?></td>
                                    <td><?= number_format((float)$row['auto_amount'], 2) ?></td>
                                    <td><?= number_format((float)$row['total_available_fund'], 2) ?></td>
                                    <td><?= number_format($today_allocated, 2) ?></td>
                                    <td><?= number_format($stats['sale'], 2) ?></td>
                                    <td><?= number_format((float)$row['closing_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($row['notes'] ?? '') ?></td>
                                    <td>
                                        <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                            View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_dth)): ?>
                                <tr>
                                    <td colspan="13" class="text-muted">No DTH transactions found for today.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <a href="transactions.php" class="btn btn-link">Show All</a>
                    </div>
                </div>
            </div>
            <!-- Staff DTH Allocations Table -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Today's DTH Allocations to Staff</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-center mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Staff Name</th>
                                    <th>Allocated Quantity</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($allocs) {
                                    foreach ($allocs as $row) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row['allocation_date']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['full_name'] ?? 'Unknown') . "</td>";
                                        echo "<td>" . number_format($row['quantity'], 2) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['notes']) . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo '<tr><td colspan="4" class="text-muted">No allocations found for today.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Staff DTH Sell Table -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Today's DTH Staff Sales</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-center mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Staff Name</th>
                                    <th>Receive</th>
                                    <th>Opening</th>
                                    <th>Total Balance</th>
                                    <th>Sell</th>
                                    <th>Actual Value (Sell+2.9%)</th>
                                    <th>Closing</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($sells) {
                                    foreach ($sells as $row) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row['sell_date']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['full_name'] ?? 'Unknown') . "</td>";
                                        echo "<td>" . number_format($row['receive'],2) . "</td>";
                                        echo "<td>" . number_format($row['opening'],2) . "</td>";
                                        echo "<td>" . number_format($row['total_balance'],2) . "</td>";
                                        echo "<td>" . number_format($row['sell'],2) . "</td>";
                                        echo "<td>" . number_format($row['actual_value'],2) . "</td>";
                                        echo "<td>" . number_format($row['closing'],2) . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo '<tr><td colspan="8" class="text-muted">No staff sales found for today.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    function updateDateTime() {
        const now = new Date();
        document.getElementById('current-datetime').textContent =
            now.toISOString().replace('T', ' ').split('.')[0] + ' UTC';
    }
    setInterval(updateDateTime, 1000);
    </script>
</body>
</html>