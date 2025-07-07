<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$user = $_SESSION['branch_user'];
$branch_id = $user['branch_id'];
$username = $user['username'] ?? '';
$page_title = 'All DTH Transactions';

$db = new Database();
$conn = $db->getConnection();

// Fetch branch name
$stmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch_row = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_name = $branch_row ? $branch_row['branch_name'] : '';

// Date filter logic
$filter_date = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : '';

// Fetch DTH transactions (all if no date, else filtered)
if ($filter_date) {
    $stmt = $conn->prepare("
        SELECT d.*, 
               s.full_name AS staff_name, 
               bacc.bank_name, 
               bacc.account_number, 
               br.branch_name
        FROM dth d
        LEFT JOIN staff s ON d.staff_id = s.id
        LEFT JOIN bank_accounts bacc ON d.bank_account_id = bacc.id
        LEFT JOIN branches br ON d.branch_id = br.id
        WHERE d.branch_id = ? AND DATE(d.transaction_date) = ?
        ORDER BY d.transaction_date DESC, d.created_at DESC
    ");
    $stmt->execute([$branch_id, $filter_date]);
    $dth_txns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $heading_date = " for " . htmlspecialchars($filter_date);
} else {
    $stmt = $conn->prepare("
        SELECT d.*, 
               s.full_name AS staff_name, 
               bacc.bank_name, 
               bacc.account_number, 
               br.branch_name
        FROM dth d
        LEFT JOIN staff s ON d.staff_id = s.id
        LEFT JOIN bank_accounts bacc ON d.bank_account_id = bacc.id
        LEFT JOIN branches br ON d.branch_id = br.id
        WHERE d.branch_id = ?
        ORDER BY d.transaction_date DESC, d.created_at DESC
    ");
    $stmt->execute([$branch_id]);
    $dth_txns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $heading_date = "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All DTH Transactions</title>
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
            <a class="nav-link" href="index.php"><i class="bi bi-tv"></i> DTH</a>
            <a class="nav-link active" href="transactionss.php"><i class="bi bi-list"></i> DTH Transactions</a>
            <a class="nav-link" href="create.php"><i class="bi bi-plus-circle"></i> New Transaction</a>
            <a class="nav-link" href="../sim_cards/index.php"><i class="bi bi-sim"></i> SIM Cards</a>
            <a class="nav-link" href="../apb/index.php"><i class="bi bi-credit-card"></i> APB</a>
            <a class="nav-link" href="../lapu/index.php"><i class="bi bi-phone"></i> LAPU</a>
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
            <!-- Date Filter -->
            <div class="row mb-3">
                <div class="col-12 col-md-6">
                    <form class="d-flex align-items-center gap-2" method="get" action="transactionss.php">
                        <label for="filter_date" class="form-label mb-0">Show Transactions for: </label>
                        <input type="date" id="filter_date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>" max="<?= date('Y-m-d') ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        <?php if ($filter_date): ?>
                            <a href="transactionss.php" class="btn btn-link btn-sm">All Dates</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <!-- DTH Transactions Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">DTH Transactions<?= $heading_date ?></h5>
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
                                    <th>Total Sale</th>
                                    <th>Closing Balance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dth_txns as $row): ?>
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
                                    <td>₹<?= number_format((float)($row['amount_received'] ?? 0), 2) ?></td>
                                    <td>₹<?= number_format((float)($row['opening_balance'] ?? 0), 2) ?></td>
                                    <td>₹<?= number_format((float)($row['auto_amount'] ?? 0), 2) ?></td>
                                    <td>₹<?= number_format((float)($row['total_available_fund'] ?? 0), 2) ?></td>
                                    <td>₹<?= number_format((float)($row['total_spent'] ?? 0), 2) ?></td>
                                    <td>₹<?= number_format((float)($row['closing_amount'] ?? 0), 2) ?></td>
                                    <td>
                                        <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                            View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($dth_txns)): ?>
                                <tr>
                                    <td colspan="11" class="text-muted">No DTH transactions found for this branch<?= $filter_date ? ' on this day' : '' ?>.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <a href="index.php" class="btn btn-link">Back to DTH Dashboard</a>
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