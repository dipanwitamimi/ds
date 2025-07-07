<?php
session_start();
require_once '../config/config.php';
require_once '../config/Database.php';

date_default_timezone_set('UTC');
$currentDateTime = date('Y-m-d H:i:s');

// Check if user is logged in
if (!isset($_SESSION['branch_user'])) {
    $_SESSION['error'] = "Please login to continue";
    header('Location: login.php');
    exit;
}
$user = $_SESSION['branch_user'];

// Branch info
$branch_defaults = [
    'id' => 0,
    'branch_name' => '',
    'branch_code' => '',
    'address1' => '',
    'contact_number' => '',
    'email' => ''
];
try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$user['branch_id']]);
    $branch = $stmt->rowCount() > 0
        ? array_merge($branch_defaults, $stmt->fetch(PDO::FETCH_ASSOC))
        : $branch_defaults;
} catch (Exception $e) {
    $branch = $branch_defaults;
}

// Helper: check table exists
function tableExists($db, $table) {
    try {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        return ($result && $result->rowCount() > 0);
    } catch (Exception $e) { return false; }
}

// Returns opening, purchase, sale, available, closing for today for a branch
function getStatusBlock($db, $table, $fields_map, $branch_id, $date_field = 'transaction_date') {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // Opening = most recent closing_balance before today (<= yesterday)
    $sql = "SELECT {$fields_map['closing_balance']} FROM $table 
            WHERE branch_id=? AND DATE($date_field) <= ?
            ORDER BY $date_field DESC, id DESC LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$branch_id, $yesterday]);
    $prev = $stmt->fetchColumn();
    $opening_balance = $prev !== false ? (float)$prev : 0;

    // New Purchase and Sale for today for this branch
    $sql = "SELECT 
        SUM({$fields_map['new_purchase']}) as new_purchase,
        SUM(" . (isset($fields_map['total_sale']) ? $fields_map['total_sale'] : '0') . ") as total_sale
        FROM $table WHERE branch_id = ? AND DATE($date_field) = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$branch_id, $today]);
    $sum = $stmt->fetch(PDO::FETCH_ASSOC);

    $new_purchase = $sum['new_purchase'] ?? 0;
    $total_sale = $sum['total_sale'] ?? 0;

    $total_available = $opening_balance + $new_purchase - $total_sale;
    $closing_balance = $total_available;

    return [
        'opening_balance' => $opening_balance,
        'new_purchase' => $new_purchase,
        'total_sale' => $total_sale,
        'total_available' => $total_available,
        'closing_balance' => $closing_balance,
    ];
}

// LAPU
$lapuStatus = getStatusBlock($db, "lapu", [
    'closing_balance' => 'closing_amount',
    'new_purchase' => 'cash_received',
    'total_sale' => 'total_spent'
], $branch['id'], 'transaction_date');
// APB
$apbStatus = getStatusBlock($db, "apb", [
    'closing_balance' => 'closing_stock',
    'new_purchase' => 'quantity_received',
    'total_sale' => 'total_sold'
], $branch['id'], 'transaction_date');
// SIM
$simStatus = getStatusBlock($db, "sim_cards", [
    'closing_balance' => 'closing_stock',
    'new_purchase' => 'quantity_received',
    'total_sale' => 'total_sold'
], $branch['id'], 'transaction_date');

// DTH - use sale as sum of staff_dth_sales.actual_value for today (to match DTH index & stats)
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
// Opening balance for DTH
$stmt = $db->prepare("SELECT closing_amount FROM dth WHERE branch_id=? AND DATE(transaction_date) <= ? ORDER BY transaction_date DESC, id DESC LIMIT 1");
$stmt->execute([$branch['id'], $yesterday]);
$dth_opening_balance = $stmt->fetchColumn();
$dth_opening_balance = $dth_opening_balance !== false ? (float)$dth_opening_balance : 0.00;
// Amount received and auto for today
$stmt = $db->prepare("SELECT SUM(amount_received) as received, SUM(auto_amount) as auto FROM dth WHERE branch_id=? AND DATE(transaction_date)=?");
$stmt->execute([$branch['id'], $today]);
$dth_today = $stmt->fetch(PDO::FETCH_ASSOC);
$dth_received = $dth_today && $dth_today['received'] !== null ? (float)$dth_today['received'] : 0.00;
$dth_auto = $dth_today && $dth_today['auto'] !== null ? (float)$dth_today['auto'] : 0.00;
$dth_total_available = $dth_opening_balance + $dth_received + $dth_auto;
// DTH "sale" is sum of staff_dth_sales.actual_value for today
$stmt = $db->prepare("SELECT IFNULL(SUM(actual_value),0) FROM staff_dth_sales WHERE branch_id=? AND sell_date=?");
$stmt->execute([$branch['id'], $today]);
$dth_total_sale = (float)$stmt->fetchColumn();
$dth_closing_balance = $dth_total_available - $dth_total_sale;
$dthStatus = [
    'opening_balance' => $dth_opening_balance,
    'new_purchase' => $dth_received,
    'total_sale' => $dth_total_sale,
    'total_available' => $dth_total_available,
    'closing_balance' => $dth_closing_balance,
];

// Cash Deposit for today
$cashDepositTotal = 0;
$cashDepositCount = 0;
$recentCashDeposits = [];
if (tableExists($db, "cash_deposits")) {
    // Today total and count
    $stmt = $db->prepare("SELECT COUNT(id) as cnt, COALESCE(SUM(total_amount),0) as amt FROM cash_deposits WHERE branch_id=? AND DATE(created_at)=?");
    $stmt->execute([$branch['id'], $today]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $cashDepositTotal = $row['amt'] ?? 0;
    $cashDepositCount = $row['cnt'] ?? 0;

    // Recent deposits (last 10)
    $sql = "SELECT 
                cd.id, cd.created_at, cd.deposit_date, cd.total_amount, 
                b.branch_name, 
                s.full_name AS staff_name, 
                ba.bank_name, ba.account_number, ba.current_balance
            FROM cash_deposits cd
            LEFT JOIN branches b ON cd.branch_id = b.id
            LEFT JOIN staff s ON cd.staff_id = s.id
            LEFT JOIN bank_accounts ba ON cd.bank_account_id = ba.id
            WHERE cd.branch_id = ?
            ORDER BY cd.created_at DESC
            LIMIT 10";
    $stmt = $db->prepare($sql);
    $stmt->execute([$branch['id']]);
    $recentCashDeposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Recent Transactions (LAPU, DTH, SIM, APB)
$recent_transactions = [];
$limit = 7;
$recent_queries = [
    [
        'table' => 'lapu',
        'service_type' => 'lapu',
        'amount_field' => 'auto_amount',
        'staff_field' => 'staff_id',
        'has_staff' => true
    ],
    [
        'table' => 'dth',
        'service_type' => 'dth',
        'amount_field' => 'auto_amount',
        'staff_field' => 'staff_id',
        'has_staff' => true
    ],
    [
        'table' => 'sim_cards',
        'service_type' => 'sim_cards',
        'amount_field' => 'total_sold',
        'staff_field' => 'staff_id',
        'has_staff' => true
    ],
    [
        'table' => 'apb',
        'service_type' => 'apb',
        'amount_field' => 'total_sold',
        'staff_field' => 'staff_id',
        'has_staff' => true
    ]
];
foreach ($recent_queries as $rq) {
    if (!tableExists($db, $rq['table'])) continue;
    $sql = "SELECT t.id, t.created_at, '{$rq['service_type']}' as service_type, t.{$rq['amount_field']} as amount, 
                s.full_name as staff_name, b.branch_name
            FROM {$rq['table']} t
            LEFT JOIN staff s ON t.{$rq['staff_field']} = s.id
            LEFT JOIN branches b ON t.branch_id = b.id
            WHERE t.branch_id = ? AND DATE(t.created_at) = ?
            ORDER BY t.created_at DESC LIMIT $limit";
    $stmt = $db->prepare($sql);
    $stmt->execute([$branch['id'], date('Y-m-d')]);
    $recent_transactions = array_merge($recent_transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
usort($recent_transactions, function($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});
$recent_transactions = array_slice($recent_transactions, 0, 15);

function getServiceBadgeClass($service) {
    switch ($service) {
        case 'lapu': return 'primary';
        case 'sim_cards': return 'success';
        case 'apb': return 'info';
        case 'dth': return 'warning';
        case 'cash_deposit': return 'danger';
        default: return 'secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Branch Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --header-height: 64px; --nav-height: 50px; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .main-header { background: #003366; border-bottom: 2px solid #00509e; box-shadow: 0 2px 4px rgba(0,0,0,0.04); padding: 0.5rem 0; position: fixed; top: 0; left: 0; right: 0; z-index: 1030; color: #fff; }
        .header-container { display: flex; justify-content: space-between; align-items: center; }
        .branch-info { flex: 1; padding-right: 2rem; }
        .datetime-display { background: #e3e7ef; padding: 0.5rem 1rem; border-radius: 4px; font-size: 0.97rem; line-height: 1.2; text-align: center; margin-right: 1rem; color: #003366; }
        .user-info { display: flex; align-items: center; gap: 1rem; padding: 0.5rem 1rem; background: #e3e7ef; color: #003366; border-radius: 4px; }
        .main-content { margin-top: calc(var(--header-height) + var(--nav-height) + 12px); padding: 2rem 0 1rem 0; }
        .table-cash-deposit thead th { background: #e9f7fd; }
        .table-cash-deposit td, .table-cash-deposit th { vertical-align: middle; }
        .nav-quick { margin-bottom: 1.5rem; }
        .nav-quick .nav-link { color:#003366; font-weight:500; }
        .nav-quick .nav-link.active { background:#e3e7ef; color:#0d6efd; }
        .service-card { background: #fff; border-radius: 9px; padding: 1.3rem 1.3rem 1rem 1.3rem; height: 100%; border: 1px solid #e0e0e0; transition: transform 0.2s, box-shadow 0.2s; cursor:pointer; }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 5px 18px rgba(0,0,0,0.09); }
        .service-card .stats-value { font-size:1.5rem; font-weight:700; color:#212529; }
        /* Spacing between cards */
        .dash-card-row .col-12 { margin-bottom: 1.2rem; }
        @media (max-width: 991.98px) {
            .main-content { padding: 0.7rem 0 1rem 0; }
            .service-card { padding: 0.9rem 0.9rem 0.7rem 0.9rem; }
        }
        @media (max-width: 767.98px) {
            .main-header, .header-container, .nav-quick {
                flex-direction: column;
                align-items: flex-start;
            }
            .main-header { padding: 0.7rem 0; }
        }
        @media (max-width: 575.98px) {
            .main-header { font-size: 96%; }
            .branch-info { padding-right: 0; }
            .main-content { padding: 0.4rem 0 0.5rem 0; }
            .service-card { padding: 0.5rem 0.5rem 0.5rem 0.5rem; }
            .header-container { gap: 0.5rem; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="container-fluid">
            <div class="header-container">
                <div class="branch-info d-flex align-items-center">
                    <img src="../assets/images/logo.png" alt="Logo" height="42" class="me-3">
                    <div>
                        <span class="fw-bold fs-4"><?php echo htmlspecialchars($branch['branch_name']); ?></span>
                        <small class="text-info ms-2 fs-6">(<?php echo htmlspecialchars($branch['branch_code']); ?>)</small><br>
                        <span class="small"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($branch['address1']); ?></span>
                    </div>
                </div>
                <div class="datetime-display">
                    <div class="date">
                        <?php echo date('d M Y', strtotime($currentDateTime)); ?>
                    </div>
                    <div class="time" id="currentTime">
                        <?php echo date('H:i:s', strtotime($currentDateTime)); ?> UTC
                    </div>
                </div>
                <div class="user-info">
                    <div>
                        <span class="fw-bold"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></span>
                        <br>
                        <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                    </div>
                    <div class="dropdown ms-2">
                        <button class="btn btn-link text-dark p-0" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle fs-4"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- Quick Nav -->
    <nav class="nav nav-pills nav-quick container-fluid mt-5">
        <a class="nav-link" href="dashboard.php">Dashboard</a>
        <a class="nav-link" href="lapu/">LAPU</a>
        <a class="nav-link" href="dth/">DTH</a>
        <a class="nav-link" href="sim_cards/">SIM Cards</a>
        <a class="nav-link" href="apb/">APB</a>
        <a class="nav-link" href="cash_deposit/">Cash Deposit</a>
        <a class="nav-link" href="users/">Users</a>
        <a class="nav-link" href="staff/">Staff</a>
        <a class="nav-link" href="bank_transactions/">Bank Transactions</a>
        <a class="nav-link" href="bank/">Bank</a>
        <a class="nav-link" href="transactions.php">All Transactions</a>
    </nav>
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Service Statistics Overview with clickable cards -->
            <div class="row g-4 mb-3 dash-card-row">
                <!-- LAPU Card -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="service-card border-primary" onclick="window.location='lapu/';" title="Go to LAPU">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-phone service-icon text-primary me-2"></i>
                                <h5 class="mb-0">LAPU</h5>
                            </div>
                            <span class="badge bg-primary">Today</span>
                        </div>
                        <div class="stats-value"><?php echo number_format($lapuStatus['new_purchase'], 2); ?> received</div>
                        <small class="text-muted"><?php echo number_format($lapuStatus['total_sale'],2); ?> spent | Opening: <?php echo number_format($lapuStatus['opening_balance'],2); ?></small>
                    </div>
                </div>
                <!-- SIM Cards -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="service-card border-success" onclick="window.location='sim_cards/';" title="Go to SIM Cards">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-sim service-icon text-success me-2"></i>
                                <h5 class="mb-0">SIM Cards</h5>
                            </div>
                            <span class="badge bg-success">Today</span>
                        </div>
                        <div class="stats-value"><?php echo number_format($simStatus['new_purchase']); ?> received</div>
                        <small class="text-muted"><?php echo number_format($simStatus['total_sale']); ?> sold | Opening: <?php echo number_format($simStatus['opening_balance']); ?></small>
                    </div>
                </div>
                <!-- APB -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="service-card border-info" onclick="window.location='apb/';" title="Go to APB">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-bank service-icon text-info me-2"></i>
                                <h5 class="mb-0">APB</h5>
                            </div>
                            <span class="badge bg-info">Today</span>
                        </div>
                        <div class="stats-value"><?php echo number_format($apbStatus['new_purchase']); ?> received</div>
                        <small class="text-muted"><?php echo number_format($apbStatus['total_sale']); ?> sold | Opening: <?php echo number_format($apbStatus['opening_balance']); ?></small>
                    </div>
                </div>
                <!-- DTH Card -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="service-card border-warning" onclick="window.location='dth/';" title="Go to DTH">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-broadcast service-icon text-warning me-2"></i>
                                <h5 class="mb-0">DTH</h5>
                            </div>
                            <span class="badge bg-warning">Today</span>
                        </div>
                        <div class="stats-value"><?php echo number_format($dthStatus['new_purchase'], 2); ?> received</div>
                        <small class="text-muted"><?php echo number_format($dthStatus['total_sale'],2); ?> spent | Opening: <?php echo number_format($dthStatus['opening_balance'],2); ?></small>
                    </div>
                </div>
            </div>
            <!-- Separate row for Cash Deposit card and extra gap -->
            <div class="row g-4 mb-4">
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="service-card border-danger" onclick="window.location='cash_deposit/';" title="Go to Cash Deposit">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-cash-stack service-icon text-danger me-2"></i>
                                <h5 class="mb-0">Cash Deposit</h5>
                            </div>
                            <span class="badge bg-danger">Today</span>
                        </div>
                        <div class="stats-value">₹<?php echo number_format($cashDepositTotal, 2); ?></div>
                        <small class="text-muted"><?php echo number_format($cashDepositCount); ?> deposits</small>
                    </div>
                </div>
            </div>
            <!-- Cash Deposit Table -->
            <div class="card mb-4">
                <div class="card-header bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Cash Deposits</h5>
                    <a href="cash_deposit/" class="btn btn-sm btn-danger">New Deposit</a>
                </div>
                <div class="card-body px-0 pb-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-cash-deposit">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Branch Name</th>
                                    <th>Staff Name</th>
                                    <th>Bank Name & Account No.</th>
                                    <th>Total Cash Deposit</th>
                                    <th>Current Bank Balance</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentCashDeposits)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No cash deposit records found.</td>
                                </tr>
                                <?php endif; ?>
                                <?php foreach($recentCashDeposits as $cd): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y H:i', strtotime($cd['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($cd['branch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($cd['staff_name']); ?></td>
                                    <td><?php echo htmlspecialchars($cd['bank_name'] . " / " . $cd['account_number']); ?></td>
                                    <td>₹<?php echo number_format($cd['total_amount'], 2); ?></td>
                                    <td><?php echo is_numeric($cd['current_balance']) ? '₹' . number_format($cd['current_balance'], 2) : 'N/A'; ?></td>
                                    <td class="text-end">
                                        <a href="cash_deposit/view.php?id=<?php echo $cd['id']; ?>" class="btn btn-sm btn-outline-danger" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="cash_deposit/edit.php?id=<?php echo $cd['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Recent Transactions Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Transactions (All Services)</h5>
                    <a href="transactions.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body px-0 pb-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Service</th>
                                    <th>Amount/Qty</th>
                                    <th>Staff</th>
                                    <th>Branch</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_transactions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No transactions found for today.</td>
                                </tr>
                                <?php else: foreach($recent_transactions as $trans): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($trans['created_at'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($trans['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getServiceBadgeClass($trans['service_type']); ?>">
                                            <?php echo strtoupper(str_replace('_', ' ', $trans['service_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo in_array($trans['service_type'], ['lapu','dth']) ? "₹" . number_format($trans['amount'], 2) : number_format($trans['amount']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($trans['staff_name']); ?></td>
                                    <td><?php echo htmlspecialchars($trans['branch_name']); ?></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateTime() {
            const now = new Date();
            let hours = String(now.getUTCHours()).padStart(2, '0');
            let minutes = String(now.getUTCMinutes()).padStart(2, '0');
            let seconds = String(now.getUTCSeconds()).padStart(2, '0');
            document.getElementById('currentTime').textContent = `${hours}:${minutes}:${seconds} UTC`;
        }
        setInterval(updateTime, 1000);
    </script>
</body>
</html>