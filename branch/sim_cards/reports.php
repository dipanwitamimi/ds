<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

checkBranchAuth();
$user = $_SESSION['branch_user'];
$branch_id = $user['branch_id'];
$page_title = 'SIM Card Reports';

$db = new Database();
$conn = $db->getConnection();

// Fetch branch name for sidebar & heading
$stmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch_row = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_name = $branch_row ? $branch_row['branch_name'] : '';

// Staff map for filter dropdown
$staff_map = [];
$stmt = $conn->prepare("SELECT id, full_name FROM staff WHERE branch_id = ?");
$stmt->execute([$branch_id]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $staff_map[$s['id']] = $s['full_name'];
}

// Bank map for filter dropdown
$bank_map = [];
$stmt = $conn->prepare("SELECT id, bank_name, account_number FROM bank_accounts WHERE branch_id = ?");
$stmt->execute([$branch_id]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $bank_map[$b['id']] = [
        'bank_name' => $b['bank_name'],
        'account_number' => $b['account_number'],
    ];
}

// Handle filters
$from_date = isset($_GET['from_date']) && $_GET['from_date'] ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) && $_GET['to_date'] ? $_GET['to_date'] : date('Y-m-d');
$staff_id = isset($_GET['staff_id']) && $_GET['staff_id'] ? intval($_GET['staff_id']) : '';
$bank_account_id = isset($_GET['bank_account_id']) && $_GET['bank_account_id'] ? intval($_GET['bank_account_id']) : '';

// Build query
$where = ["sc.branch_id = ?"];
$params = [$branch_id];

if ($from_date && $to_date) {
    $where[] = "DATE(sc.transaction_date) BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
}
if ($staff_id) {
    $where[] = "sc.staff_id = ?";
    $params[] = $staff_id;
}
if ($bank_account_id) {
    $where[] = "sc.bank_account_id = ?";
    $params[] = $bank_account_id;
}

$sql = "
    SELECT sc.*, b.branch_name, s.full_name as staff_name, ba.bank_name, ba.account_number
    FROM sim_cards sc
    LEFT JOIN branches b ON sc.branch_id = b.id
    LEFT JOIN staff s ON sc.staff_id = s.id
    LEFT JOIN bank_accounts ba ON sc.bank_account_id = ba.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY sc.transaction_date DESC, sc.created_at DESC, sc.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totals = [
    'opening_stock' => 0,
    'quantity_received' => 0,
    'auto_quantity' => 0,
    'total_available' => 0,
    'total_sold' => 0,
    'closing_stock' => 0,
];
foreach ($transactions as $row) {
    $totals['opening_stock']      += $row['opening_stock'];
    $totals['quantity_received']  += $row['quantity_received'];
    $totals['auto_quantity']      += $row['auto_quantity'];
    $totals['total_available']    += $row['total_available'];
    $totals['total_sold']         += $row['total_sold'];
    $totals['closing_stock']      += $row['closing_stock'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
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
        @media print {
            .sidebar, .noprint, .sidebar * { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .print-heading { display: block !important; }
        }
        .print-heading {
            display: none;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation Panel -->
    <div class="sidebar d-none d-lg-block">
        <div class="px-3 mb-3">
            <h5 class="mb-1">Branch Panel</h5>
            <div class="small text-muted"><?php echo htmlspecialchars($branch_name); ?></div>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link" href="index.php"><i class="bi bi-sim"></i> SIM Cards</a>
            <a class="nav-link" href="transactions.php"><i class="bi bi-list"></i> SIM Card Transactions</a>
            <a class="nav-link" href="create.php"><i class="bi bi-plus-circle"></i> New Transaction</a>
            <a class="nav-link" href="../lapu/index.php"><i class="bi bi-phone"></i> LAPU</a>
            <a class="nav-link" href="../apb/index.php"><i class="bi bi-credit-card"></i> APB</a>
            <a class="nav-link" href="../dth/index.php"><i class="bi bi-tv"></i> DTH</a>
            <a class="nav-link" href="../cash_deposits/index.php"><i class="bi bi-bank"></i> Cash Deposits</a>
            <a class="nav-link" href="../staff.php"><i class="bi bi-person-badge"></i> Staff</a>
            <a class="nav-link" href="../services.php"><i class="bi bi-boxes"></i> Services</a>
            <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </div>
    <div class="main-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-3 noprint">
            <h4 class="mb-0"><?php echo $page_title; ?></h4>
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>
        <div class="print-heading">
            <h3>SIM Cards Division</h3>
            <div>Branch: <strong><?php echo htmlspecialchars($branch_name); ?></strong></div>
            <div>Date Range: <strong><?php echo htmlspecialchars($from_date); ?></strong> to <strong><?php echo htmlspecialchars($to_date); ?></strong></div>
            <hr>
        </div>
        <div class="card mb-4 noprint">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="get">
                    <div class="col-auto">
                        <label for="from_date" class="form-label mb-0">From</label>
                        <input type="date" class="form-control" name="from_date" id="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
                    </div>
                    <div class="col-auto">
                        <label for="to_date" class="form-label mb-0">To</label>
                        <input type="date" class="form-control" name="to_date" id="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
                    </div>
                    <div class="col-auto">
                        <label for="staff_id" class="form-label mb-0">Staff</label>
                        <select class="form-select" name="staff_id" id="staff_id">
                            <option value="">All</option>
                            <?php foreach($staff_map as $sid => $sname): ?>
                                <option value="<?php echo $sid; ?>" <?php if($staff_id == $sid) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($sname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="bank_account_id" class="form-label mb-0">Bank Account</label>
                        <select class="form-select" name="bank_account_id" id="bank_account_id">
                            <option value="">All</option>
                            <?php foreach($bank_map as $bid => $b): ?>
                                <option value="<?php echo $bid; ?>" <?php if($bank_account_id == $bid) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($b['bank_name'] . " / " . $b['account_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="reports.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        <?php if (!empty($transactions)): ?>
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Branch Name</th>
                        <th>Staff Name</th>
                        <th>Bank Name/Account</th>
                        <th>Quantity Received</th>
                        <th>Opening Stock</th>
                        <th>Auto Quantity</th>
                        <th>Total Available</th>
                        <th>Total Sold</th>
                        <th>Closing Stock</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($transactions as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['transaction_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['staff_name'] ?: ($row['staff_id'] ? "ID#".$row['staff_id'] : "N/A")); ?></td>
                        <td>
                            <?php
                            if (!empty($row['bank_name']) && !empty($row['account_number'])) {
                                echo htmlspecialchars($row['bank_name']) . " / " . htmlspecialchars($row['account_number']);
                            } elseif (!empty($row['bank_account_id'])) {
                                echo "ID#" . htmlspecialchars($row['bank_account_id']);
                            } else {
                                echo "N/A";
                            }
                            ?>
                        </td>
                        <td><?php echo number_format($row['quantity_received'], 0); ?></td>
                        <td><?php echo number_format($row['opening_stock'], 0); ?></td>
                        <td><?php echo number_format($row['auto_quantity'], 0); ?></td>
                        <td><?php echo number_format($row['total_available'], 0); ?></td>
                        <td><?php echo number_format($row['total_sold'], 0); ?></td>
                        <td><?php echo number_format($row['closing_stock'], 0); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($row['notes'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="4" class="text-end">Totals:</th>
                        <th><?php echo number_format($totals['quantity_received'], 0); ?></th>
                        <th><?php echo number_format($totals['opening_stock'], 0); ?></th>
                        <th><?php echo number_format($totals['auto_quantity'], 0); ?></th>
                        <th><?php echo number_format($totals['total_available'], 0); ?></th>
                        <th><?php echo number_format($totals['total_sold'], 0); ?></th>
                        <th><?php echo number_format($totals['closing_stock'], 0); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
            <div class="alert alert-info">No transactions found for the selected filter.</div>
        <?php endif; ?>
    </div>
    </div>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show print heading only on print
        window.addEventListener("beforeprint", function() {
            var heading = document.querySelector('.print-heading');
            if (heading) heading.style.display = 'block';
        });
        window.addEventListener("afterprint", function() {
            var heading = document.querySelector('.print-heading');
            if (heading) heading.style.display = 'none';
        });
    </script>
</body>
</html>