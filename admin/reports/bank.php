<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';

// Set current user and time
$currentDateTime = date('Y-m-d H:i:s');
$currentUser = $_SESSION['admin_user']['username'] ?? 'sgpriyom';

// Get filter values from GET
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$bank_name = $_GET['bank_name'] ?? 'All';
$account_number = $_GET['account_number'] ?? 'All';

// DEFAULT: Show today if no filter selected
if (!$start_date && !$end_date) {
    $start_date = $end_date = date('Y-m-d');
}

// Build filter SQL
$where = [];
$params = [];

if ($start_date) {
    $where[] = "DATE(t.transaction_date) >= :start_date";
    $params[':start_date'] = $start_date;
}
if ($end_date) {
    $where[] = "DATE(t.transaction_date) <= :end_date";
    $params[':end_date'] = $end_date;
}
if ($bank_name !== 'All' && $bank_name !== '') {
    $where[] = "ba.bank_name = :bank_name";
    $params[':bank_name'] = $bank_name;
}
if ($account_number !== 'All' && $account_number !== '') {
    $where[] = "ba.account_number = :account_number";
    $params[':account_number'] = $account_number;
}
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch bank names and account numbers for dropdowns
try {
    $db = (new Database())->getConnection();
    $banks = $db->query("SELECT DISTINCT bank_name FROM bank_accounts ORDER BY bank_name")->fetchAll(PDO::FETCH_COLUMN);
    $accounts = $db->query("SELECT DISTINCT account_number FROM bank_accounts ORDER BY account_number")->fetchAll(PDO::FETCH_COLUMN);

    // Fetch transactions
    $sql = "
        SELECT 
            t.id AS transaction_id,
            t.transaction_date,
            ba.bank_name,
            ba.account_number,
            t.credit,
            t.debit,
            t.remarks
        FROM transactions t
        LEFT JOIN bank_accounts ba ON t.bank_account_id = ba.id
        $whereSQL
        ORDER BY t.transaction_date DESC, t.id DESC
        LIMIT 100
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary
    $totalAmount = 0;
    $totalCredit = 0;
    $totalDebit = 0;
    $totalCount = count($transactions);
    foreach ($transactions as $row) {
        $totalCredit += (float)$row['credit'];
        $totalDebit += (float)$row['debit'];
        $totalAmount += (float)$row['credit'] - (float)$row['debit'];
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    $transactions = [];
    $banks = [];
    $accounts = [];
    $totalAmount = 0;
    $totalCredit = 0;
    $totalDebit = 0;
    $totalCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Account & Transaction Reports</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
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
        .main-content {
            margin-left: 230px;
        }
        @media (max-width: 991px) {
            .sidebar-nav { position: static; width: 100%; min-height: auto; padding-top: 0; }
            .main-content { margin-left: 0; }
        }
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
            <li class="nav-item"><a class="nav-link active" href="bank.php"><i class="bi bi-bank me-2"></i>Bank Transactions</a></li>
            <li class="nav-item"><a class="nav-link" href="cash.php"><i class="bi bi-cash-coin me-2"></i>Cash Deposits</a></li>
            <li class="nav-item"><a class="nav-link" href="lapu.php"><i class="bi bi-phone me-2"></i>LAPU Transactions</a></li>
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

        <!-- Bank Account & Transactions Report Content -->
        <div class="container-fluid mt-4">
            <h3 class="mb-3"><i class="bi bi-bank me-2"></i>Bank Account & Transaction Report</h3>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Bank Transactions</h5>
                    <div>
                        <button class="btn btn-success me-2" onclick="exportTableToExcel('bankTable', 'bank_report.xlsx')">
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
                            <label class="form-label">Bank</label>
                            <select class="form-select" name="bank_name" onchange="this.form.submit()">
                                <option value="All">All Banks</option>
                                <?php foreach ($banks as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>" <?php if ($bank_name == $b) echo 'selected'; ?>><?php echo htmlspecialchars($b); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Account No.</label>
                            <select class="form-select" name="account_number" onchange="this.form.submit()">
                                <option value="All">All Accounts</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?php echo htmlspecialchars($a); ?>" <?php if ($account_number == $a) echo 'selected'; ?>><?php echo htmlspecialchars($a); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mt-4">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <h6>Total Transactions</h6>
                                <h3><?php echo $totalCount; ?></h3>
                                <small class="text-success">Displayed in table</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <h6>Total Credit</h6>
                                <h3>₹<?php echo number_format($totalCredit, 2); ?></h3>
                                <small class="text-success">Sum of credit</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <h6>Total Debit</h6>
                                <h3>₹<?php echo number_format($totalDebit, 2); ?></h3>
                                <small class="text-danger">Sum of debit</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <h6>Net Amount (Credit - Debit)</h6>
                                <h3>₹<?php echo number_format($totalAmount, 2); ?></h3>
                                <small class="text-muted">Net in filtered data</small>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Transactions Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="bankTable">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Date</th>
                                    <th>Bank</th>
                                    <th>Account No.</th>
                                    <th>Credit</th>
                                    <th>Debit</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['transaction_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['transaction_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['bank_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                                    <td>₹<?php echo number_format($row['credit'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['debit'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['remarks']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($transactions)): ?>
                                    <tr><td colspan="7" class="text-center text-muted">No transactions found for current filter.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script>
    // Excel export (simple)
    function exportTableToExcel(tableID, filename = ''){
        var downloadLink;
        var dataType = 'application/vnd.ms-excel';
        var tableSelect = document.getElementById(tableID);
        var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
        filename = filename ? filename : 'bank_report.xls';
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
    // Print support
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