<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$user = $_SESSION['branch_user'];
$branch_id = $user['branch_id'];
$username = $user['username'] ?? '';
$page_title = 'All SIM Card Transactions';

$db = new Database();
$conn = $db->getConnection();

// Fetch branch name
$stmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch_row = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_name = $branch_row ? $branch_row['branch_name'] : '';

// Date filter logic
$filter_date = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : '';

// Fetch SIM Card transactions (all if no date, else filtered)
if ($filter_date) {
    $stmt = $conn->prepare("
        SELECT sc.*, 
               s.full_name AS staff_name, 
               bacc.bank_name, 
               bacc.account_number, 
               br.branch_name
        FROM sim_cards sc
        LEFT JOIN staff s ON sc.staff_id = s.id
        LEFT JOIN bank_accounts bacc ON sc.bank_account_id = bacc.id
        LEFT JOIN branches br ON sc.branch_id = br.id
        WHERE sc.branch_id = ? AND DATE(sc.transaction_date) = ?
        ORDER BY sc.transaction_date DESC, sc.created_at DESC, sc.id DESC
    ");
    $stmt->execute([$branch_id, $filter_date]);
    $sim_txns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $heading_date = " for " . htmlspecialchars($filter_date);
} else {
    $stmt = $conn->prepare("
        SELECT sc.*, 
               s.full_name AS staff_name, 
               bacc.bank_name, 
               bacc.account_number, 
               br.branch_name
        FROM sim_cards sc
        LEFT JOIN staff s ON sc.staff_id = s.id
        LEFT JOIN bank_accounts bacc ON sc.bank_account_id = bacc.id
        LEFT JOIN branches br ON sc.branch_id = br.id
        WHERE sc.branch_id = ?
        ORDER BY sc.transaction_date DESC, sc.created_at DESC, sc.id DESC
    ");
    $stmt->execute([$branch_id]);
    $sim_txns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $heading_date = "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All SIM Card Transactions</title>
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
            <a class="nav-link" href="index.php"><i class="bi bi-sim"></i> SIM Cards</a>
            <a class="nav-link active" href="transactions.php"><i class="bi bi-list"></i> SIM Card Transactions</a>
            <a class="nav-link" href="create.php"><i class="bi bi-plus-circle"></i> New Transaction</a>
            <a class="nav-link" href="../lapu/index.php"><i class="bi bi-phone"></i> LAPU</a>
            <a class="nav-link" href="../apb/index.php"><i class="bi bi-credit-card"></i> APB</a>
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
                    <form class="d-flex align-items-center gap-2" method="get" action="transactions.php">
                        <label for="filter_date" class="form-label mb-0">Show Transactions for: </label>
                        <input type="date" id="filter_date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>" max="<?= date('Y-m-d') ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        <?php if ($filter_date): ?>
                            <a href="transactions.php" class="btn btn-link btn-sm">All Dates</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <!-- SIM Card Transactions Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">SIM Card Transactions<?= $heading_date ?></h5>
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
                                <?php foreach ($sim_txns as $row): ?>
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
                                    <td><?= number_format((float)$row['quantity_received'], 0) ?></td>
                                    <td><?= number_format((float)$row['opening_stock'], 0) ?></td>
                                    <td><?= number_format((float)$row['auto_quantity'], 0) ?></td>
                                    <td><?= number_format((float)$row['total_available'], 0) ?></td>
                                    <td><?= number_format((float)$row['total_sold'], 0) ?></td>
                                    <td><?= number_format((float)$row['closing_stock'], 0) ?></td>
                                    <td>
                                        <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                            View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($sim_txns)): ?>
                                <tr>
                                    <td colspan="11" class="text-muted">No SIM card transactions found for this branch<?= $filter_date ? ' on this day' : '' ?>.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <a href="index.php" class="btn btn-link">Back to SIM Cards Dashboard</a>
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