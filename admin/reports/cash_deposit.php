<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';

$currentDateTime = date('Y-m-d H:i:s');
$currentUser = $_SESSION['admin_user']['username'] ?? 'subhanmimi';

// Filters
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? '';
$branch_id = $_GET['branch_id'] ?? 'All';
$staff_id = $_GET['staff_id'] ?? 'All';

// Build WHERE clause
$where = [];
$params = [];

$where[] = "DATE(cd.deposit_date) >= :today";
$params[':today'] = date('Y-m-d'); // Only current and future

if ($start_date) {
    $where[] = "DATE(cd.deposit_date) >= :start_date";
    $params[':start_date'] = $start_date;
}
if ($end_date) {
    $where[] = "DATE(cd.deposit_date) <= :end_date";
    $params[':end_date'] = $end_date;
}
if ($branch_id !== 'All' && $branch_id !== '') {
    $where[] = "cd.branch_id = :branch_id";
    $params[':branch_id'] = $branch_id;
}
if ($staff_id !== 'All' && $staff_id !== '') {
    $where[] = "cd.staff_id = :staff_id";
    $params[':staff_id'] = $staff_id;
}
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

try {
    $db = (new Database())->getConnection();
    $branches = $db->query("SELECT id, branch_name FROM branches ORDER BY branch_name")->fetchAll(PDO::FETCH_ASSOC);
    $staff = $db->query("SELECT id, full_name FROM staff ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

    // Main report query
    $sql = "
        SELECT 
            cd.id AS cash_id,
            cd.deposit_date,
            b.branch_name,
            s.full_name AS staff_name,
            ba.bank_name,
            ba.account_number,
            cd.notes_2000,
            cd.notes_500,
            cd.notes_200,
            cd.notes_100,
            cd.notes_50,
            cd.notes_20,
            cd.notes_10,
            cd.notes_5,
            cd.notes_2,
            cd.notes_1,
            cd.total_amount,
            cd.created_at
        FROM cash_deposits cd
        LEFT JOIN staff s ON cd.staff_id = s.id
        LEFT JOIN branches b ON cd.branch_id = b.id
        LEFT JOIN bank_accounts ba ON cd.bank_account_id = ba.id
        $whereSQL
        ORDER BY cd.deposit_date ASC, cd.id ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cash = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($cash);
    $sum_amount = 0;
    foreach ($cash as $row) $sum_amount += (float)$row['total_amount'];

} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    $cash = [];
    $branches = [];
    $staff = [];
    $total = 0;
    $sum_amount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Deposit Activities Report</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stats-card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
        .header-info { background: #e9ecef; padding: 10px 0; margin-bottom: 16px; }
        .sidebar-nav {
            width: 220px;
            min-height: 100vh;
            background: #295998;
            color: #fff;
            position: fixed;
            left: 0; top: 0; bottom: 0;
            z-index: 100;
            padding-top: 64px;
        }
        .sidebar-nav .nav-link {
            color: #eaf1fc;
            font-weight: 500;
            padding: 12px 24px;
            display: flex;
            align-items: center;
        }
        .sidebar-nav .nav-link.active, .sidebar-nav .nav-link:hover {
            background: #eaf1fc;
            color: #295998;
        }
        .main-content { margin-left: 230px; }
        @media (max-width: 991px) { .sidebar-nav { position: static; width: 100%; min-height: auto; padding-top: 0; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <!-- Sidebar Navigation Panel -->
    <nav class="sidebar-nav d-none d-lg-block">
        <div class="text-center mb-4">
            <img src="../../assets/img/logo.png" alt="Logo" style="max-width:120px;">
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="staff.php"><i class="bi bi-people-fill me-2"></i>Staff Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="branch.php"><i class="bi bi-diagram-3 me-2"></i>Branch Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="bank.php"><i class="bi bi-bank me-2"></i>Bank Transactions</a></li>
            <li class="nav-item"><a class="nav-link active" href="cash_deposit.php"><i class="bi bi-cash-coin me-2"></i>Cash Deposits</a></li>
            <li class="nav-item"><a class="nav-link" href="lapu.php"><i class="bi bi-phone me-2"></i>LAPU Activity</a></li>
            <li class="nav-item"><a class="nav-link" href="sim.php"><i class="bi bi-sim me-2"></i>SIM Card Activity</a></li>
            <li class="nav-item"><a class="nav-link" href="apb.php"><i class="bi bi-archive me-2"></i>APB Activity</a></li>
            <li class="nav-item"><a class="nav-link" href="dth.php"><i class="bi bi-tv me-2"></i>DTH Activity</a></li>
            <li class="nav-item"><a class="nav-link" href="transaction.php"><i class="bi bi-bar-chart-fill me-2"></i>All Transactions</a></li>
            <li class="nav-item"><a class="nav-link" href="../../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
    </nav>

    <div class="main-content">
        <!-- Header Info -->
        <div class="header-info">
            <div class="container-fluid">
                Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <span id="current-datetime"><?php echo $currentDateTime; ?></span>
                &nbsp; | &nbsp;
                Current User's Login: <span id="current-user"><?php echo htmlspecialchars($currentUser); ?></span>
            </div>
        </div>
        <div class="container-fluid mt-4">
            <h3 class="mb-3"><i class="bi bi-cash-coin me-2"></i>Cash Deposit Activities & Transactions (Current & Future)</h3>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Cash Deposits by Branch & Staff</h5>
                    <div>
                        <button class="btn btn-success me-2" onclick="exportTableToExcel('cashTable', 'cash_report.xlsx')">
                            <i class="bi bi-file-earmark-excel"></i> Export Excel
                        </button>
                        <button class="btn btn-info" onclick="printElement('reportContent')">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                    </div>
                </div>
                <div class="card-body" id="reportContent">
                    <!-- Filters -->
                    <form method="get" class="row mb-3">
                        <div class="col-md-2">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Branch</label>
                            <select class="form-select" name="branch_id" onchange="this.form.submit()">
                                <option value="All">All Branches</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo $b['id']; ?>" <?php if ($branch_id == $b['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($b['branch_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Staff</label>
                            <select class="form-select" name="staff_id" onchange="this.form.submit()">
                                <option value="All">All Staff</option>
                                <?php foreach ($staff as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php if ($staff_id == $s['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($s['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mt-4">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>

                    <!-- Summary Stats -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stats-card text-center">
                                <h6>Total Cash Deposit Activities</h6>
                                <h3><?php echo $total; ?></h3>
                                <small class="text-success">Listed below</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card text-center">
                                <h6>Total Amount Deposited</h6>
                                <h3>₹<?php echo number_format($sum_amount,2); ?></h3>
                                <small class="text-success">Sum of all deposits</small>
                            </div>
                        </div>
                    </div>

                    <!-- Cash Deposit Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="cashTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Branch</th>
                                    <th>Staff</th>
                                    <th>Bank</th>
                                    <th>Account No.</th>
                                    <th>₹2000</th>
                                    <th>₹500</th>
                                    <th>₹200</th>
                                    <th>₹100</th>
                                    <th>₹50</th>
                                    <th>₹20</th>
                                    <th>₹10</th>
                                    <th>₹5</th>
                                    <th>₹2</th>
                                    <th>₹1</th>
                                    <th>Total Amount</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cash as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['cash_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['deposit_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['bank_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                                    <td><?php echo (int)$row['notes_2000']; ?></td>
                                    <td><?php echo (int)$row['notes_500']; ?></td>
                                    <td><?php echo (int)$row['notes_200']; ?></td>
                                    <td><?php echo (int)$row['notes_100']; ?></td>
                                    <td><?php echo (int)$row['notes_50']; ?></td>
                                    <td><?php echo (int)$row['notes_20']; ?></td>
                                    <td><?php echo (int)$row['notes_10']; ?></td>
                                    <td><?php echo (int)$row['notes_5']; ?></td>
                                    <td><?php echo (int)$row['notes_2']; ?></td>
                                    <td><?php echo (int)$row['notes_1']; ?></td>
                                    <td>₹<?php echo number_format($row['total_amount'],2); ?></td>
                                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($cash)): ?>
                                    <tr><td colspan="18" class="text-center text-muted">No Cash Deposit activities found for current/future filter.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Scripts -->
    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    // Excel export (simple)
    function exportTableToExcel(tableID, filename = ''){
        var downloadLink;
        var dataType = 'application/vnd.ms-excel';
        var tableSelect = document.getElementById(tableID);
        var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
        filename = filename ? filename : 'cash_report.xls';
        downloadLink = document.createElement("a");
        document.body.appendChild(downloadLink);
        if(navigator.msSaveOrOpenBlob){
            var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
            navigator.msSaveOrOpenBlob(blob, filename);
        }else{
            downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
            downloadLink.download = filename;
            downloadLink.click();
        }
    }
    function printElement(elId) {
        var content = document.getElementById(elId).innerHTML;
        var mywindow = window.open('', 'Print', 'height=600,width=900');
        mywindow.document.write('<html><head><title>Print</title>');
        mywindow.document.write('<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">');
        mywindow.document.write('<style>body{font-family:Segoe UI,sans-serif;padding:20px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:8px;}</style>');
        mywindow.document.write('</head><body>');
        mywindow.document.write(content);
        mywindow.document.write('</body></html>');
        mywindow.document.close();
        mywindow.focus();
        setTimeout(function(){ mywindow.print(); mywindow.close(); }, 600);
        return true;
    }
    </script>
</body>
</html>