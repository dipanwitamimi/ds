<?php
session_start();
require_once '../config/config.php';
require_once '../config/Database.php';

date_default_timezone_set('UTC');

if (!isset($_SESSION['branch_user'])) {
    $_SESSION['error'] = "Please login to continue";
    header('Location: login.php');
    exit;
}
$user = $_SESSION['branch_user'];

// Branch info
$branch_defaults = [
    'id' => 0, 'branch_name' => '', 'branch_code' => '', 'address' => '', 'contact_number' => '', 'email' => ''
];
try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$user['branch_id']]);
    if ($stmt->rowCount() > 0) {
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        $branch = array_merge($branch_defaults, $branch);
    } else {
        $branch = $branch_defaults;
    }
} catch (Exception $e) {
    $branch = $branch_defaults;
}

// Staff for filter and for mapping staff_id to name
$staff_list = [];
$staff_map = [];
try {
    $stmt = $db->prepare("SELECT id, full_name FROM staff WHERE branch_id = ?");
    $stmt->execute([$branch['id']]);
    $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($staff_list as $s) {
        $staff_map[$s['id']] = $s['full_name'];
    }
} catch (Exception $e) {}

// Filters
$filter_from_date = $_GET['from_date'] ?? '';
$filter_to_date   = $_GET['to_date'] ?? '';
$filter_staff     = $_GET['staff_id'] ?? '';
$filter_service   = $_GET['service_type'] ?? '';

// Service table definitions
$service_tables = [
    [
        'table'=>'lapu',
        'type'=>'lapu',
        'amount_field'=>'auto_amount',
        'bank_fields'=>false
    ],
    [
        'table'=>'sim_cards',
        'type'=>'sim_cards',
        'amount_field'=>'total_sold',
        'bank_fields'=>false
    ],
    [
        'table'=>'apb',
        'type'=>'apb',
        'amount_field'=>'total_sold',
        'bank_fields'=>false
    ],
    [
        'table'=>'dth',
        'type'=>'dth',
        'amount_field'=>'auto_amount',
        'bank_fields'=>false
    ],
    [
        'table'=>'cash_deposits',
        'type'=>'cash_deposit',
        'amount_field'=>'total_amount',
        'bank_fields'=>true
    ],
];

function tableExists($db, $table) {
    try {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        return ($result && $result->rowCount() > 0);
    } catch (Exception $e) { return false; }
}
function tableHasStaffId($db, $table) {
    try {
        $cols = $db->query("SHOW COLUMNS FROM `$table` LIKE 'staff_id'");
        return ($cols && $cols->rowCount() > 0);
    } catch (Exception $e) { return false; }
}

$transactions = [];
foreach ($service_tables as $svc) {
    if ($filter_service && $filter_service !== $svc['type']) continue;
    if (!tableExists($db, $svc['table'])) continue;
    $hasStaffId = tableHasStaffId($db, $svc['table']);

    $where = [];
    $params = [];
    $where[] = "t.branch_id = ?";
    $params[] = $branch['id'];

    if ($filter_from_date && $filter_to_date) {
        $where[] = "DATE(t.created_at) BETWEEN ? AND ?";
        $params[] = $filter_from_date;
        $params[] = $filter_to_date;
    } elseif ($filter_from_date) {
        $where[] = "DATE(t.created_at) >= ?";
        $params[] = $filter_from_date;
    } elseif ($filter_to_date) {
        $where[] = "DATE(t.created_at) <= ?";
        $params[] = $filter_to_date;
    }

    if ($filter_staff && $hasStaffId) {
        $where[] = "t.staff_id = ?";
        $params[] = $filter_staff;
    }

    $bankSelect = $svc['bank_fields']
        ? "ba.bank_name as bank_account_name, ba.account_number as bank_account_number,"
        : "NULL as bank_account_name, NULL as bank_account_number,";

    $bankJoin = $svc['bank_fields']
        ? "LEFT JOIN bank_accounts ba ON t.bank_account_id = ba.id"
        : "";

    $select_staff_id = $hasStaffId ? "t.staff_id as staff_id," : "NULL as staff_id,";
    // Do NOT join staff table here, we will map after fetch for robustness

    $sql = "SELECT 
                t.id, t.created_at, '{$svc['type']}' as service_type,
                t.{$svc['amount_field']} as amount,
                $bankSelect
                $select_staff_id
                b.branch_name
            FROM {$svc['table']} t
            $bankJoin
            LEFT JOIN branches b ON t.branch_id = b.id
            WHERE ".implode(' AND ', $where)."
            ORDER BY t.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map staff_id to staff name
    foreach ($rows as &$r) {
        if (isset($r['staff_id']) && $r['staff_id'] && isset($staff_map[$r['staff_id']])) {
            $r['staff_name'] = $staff_map[$r['staff_id']];
        } elseif (isset($r['staff_id']) && $r['staff_id']) {
            $r['staff_name'] = "ID#" . $r['staff_id']; // fallback: unknown staff ID
        } else {
            $r['staff_name'] = "N/A";
        }
    }
    unset($r);

    $transactions = array_merge($transactions, $rows);
}
usort($transactions, function($a,$b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});
function getServiceBadgeClass($service) {
    return match($service) {
        'lapu' => 'primary',
        'sim_cards' => 'success',
        'apb' => 'info',
        'dth' => 'warning',
        'cash_deposit' => 'danger',
        default => 'secondary'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transactions - <?php echo htmlspecialchars($branch['branch_name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        .filter-form .form-select, .filter-form .form-control { min-width: 120px; }
        .print-btn { float: right; margin-left: 1rem;}
        .table-responsive {margin-top: 1rem;}
        @media print {
            .noprint, .noprint * { display: none !important; }
            .printarea { margin: 0; }
        }
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
<body>
    <div class="sidebar d-none d-lg-block">
        <div class="px-3 mb-3">
            <h5 class="mb-1">Branch Panel</h5>
            <div class="small text-muted"><?php echo htmlspecialchars($branch['branch_name']); ?></div>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='dashboard.php') echo ' active'; ?>" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='transactions.php') echo ' active'; ?>" href="transactions.php"><i class="bi bi-list-columns"></i> Transactions</a>
            <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='staff.php') echo ' active'; ?>" href="staff.php"><i class="bi bi-person-badge"></i> Staff</a>
            <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='services.php') echo ' active'; ?>" href="services.php"><i class="bi bi-boxes"></i> Services</a>
            <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </div>
    <div class="main-content">
    <div class="container-fluid py-3">
        <div class="row align-items-center mb-3">
            <div class="col-12 col-md-6">
                <h3 class="mb-0">
                    <?php echo htmlspecialchars($branch['branch_name']); ?> Branch
                    <small class="text-muted fs-6">(<?php echo htmlspecialchars($branch['branch_code']); ?>)</small>
                </h3>
                <div class="text-secondary"><?php echo htmlspecialchars($branch['address']); ?></div>
            </div>
            <div class="col-12 col-md-6 text-md-end noprint">
                <button class="btn btn-outline-secondary print-btn" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>

        <form class="row g-2 align-items-end filter-form noprint" method="get">
            <div class="col-auto">
                <label for="from_date" class="form-label mb-0">From Date</label>
                <input type="date" name="from_date" id="from_date" class="form-control" value="<?php echo htmlspecialchars($filter_from_date); ?>">
            </div>
            <div class="col-auto">
                <label for="to_date" class="form-label mb-0">To Date</label>
                <input type="date" name="to_date" id="to_date" class="form-control" value="<?php echo htmlspecialchars($filter_to_date); ?>">
            </div>
            <div class="col-auto">
                <label for="staff_id" class="form-label mb-0">Staff</label>
                <select name="staff_id" id="staff_id" class="form-select">
                    <option value="">All Staff</option>
                    <?php foreach($staff_list as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>" <?php if($filter_staff == $staff['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($staff['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label for="service_type" class="form-label mb-0">Service</label>
                <select name="service_type" id="service_type" class="form-select">
                    <option value="">All</option>
                    <option value="lapu" <?php if($filter_service == 'lapu') echo 'selected'; ?>>LAPU</option>
                    <option value="sim_cards" <?php if($filter_service == 'sim_cards') echo 'selected'; ?>>SIM Card</option>
                    <option value="apb" <?php if($filter_service == 'apb') echo 'selected'; ?>>APB</option>
                    <option value="dth" <?php if($filter_service == 'dth') echo 'selected'; ?>>DTH</option>
                    <option value="cash_deposit" <?php if($filter_service == 'cash_deposit') echo 'selected'; ?>>Cash Deposit</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="transactions.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive printarea">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Branch</th>
                        <th>Staff Name</th>
                        <th>Service</th>
                        <th>Amount/Qty</th>
                        <th>Bank Name</th>
                        <th>Account Number</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">No transactions found for selected filter.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach($transactions as $row): ?>
                    <tr>
                        <td><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
                        <td><?php echo date('H:i:s', strtotime($row['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo getServiceBadgeClass($row['service_type']); ?>">
                                <?php echo strtoupper(str_replace('_', ' ', $row['service_type'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            if (in_array($row['service_type'], ['lapu', 'dth', 'cash_deposit'])) {
                                echo "₹" . number_format($row['amount'], 2);
                            } else {
                                echo number_format($row['amount']);
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            // Only show for cash deposit
                            echo ($row['service_type'] === "cash_deposit" && !is_null($row['bank_account_name'])) ? htmlspecialchars($row['bank_account_name']) : '';
                            ?>
                        </td>
                        <td>
                            <?php
                            // Only show for cash deposit
                            echo ($row['service_type'] === "cash_deposit" && !is_null($row['bank_account_number'])) ? htmlspecialchars($row['bank_account_number']) : '';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>