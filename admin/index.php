<?php
session_start();

// Include configuration files
require_once('../config/config.php');
require_once('../config/database.php');

$currentDateTime = date('Y-m-d H:i:s');
$currentUser = 'sgpriyom';

// Initialize variables
$summaryData = [
    'branches' => ['total' => 0, 'active' => 0],
    'staff' => ['total' => 0, 'active' => 0],
    'transactions' => ['total_credit' => 0, 'total_debit' => 0],
    'bank_accounts' => ['total_balance' => 0]
];

// Database connection
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get branches count
    $result = $db->query("SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active 
        FROM branches");
    if ($result) {
        $summaryData['branches'] = $result->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get staff count
    $result = $db->query("SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active 
        FROM staff");
    if ($result) {
        $summaryData['staff'] = $result->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get today's transactions
    $result = $db->query("SELECT 
        COALESCE(SUM(credit), 0) as total_credit, 
        COALESCE(SUM(debit), 0) as total_debit 
        FROM transactions 
        WHERE DATE(transaction_date) = CURDATE()");
    if ($result) {
        $summaryData['transactions'] = $result->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get bank accounts total
    $result = $db->query("SELECT 
        COALESCE(SUM(current_balance), 0) as total_balance 
        FROM bank_accounts");
    if ($result) {
        $summaryData['bank_accounts'] = $result->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <style>
        .header-info {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-line;
        }
        .stats-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-card h6 {
            color: #6c757d;
            margin-bottom: 10px;
        }
        .stats-card h3 {
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .quick-actions {
            margin-top: 30px;
        }
        .quick-actions .btn {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <span id="current-datetime"><?php echo $currentDateTime; ?></span>
Current User's Login: <span id="current-user"><?php echo $currentUser; ?></span>
<?php if (isset($error)) echo "\nError: " . htmlspecialchars($error); ?></div>
    </div>

    <!-- Dashboard Content -->
    <div class="container mt-4">
        <!-- Quick Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Branch</h6>
                    <h3><?php echo $summaryData['branches']['active'] ?? 0; ?>/<?php echo $summaryData['branches']['total'] ?? 0; ?></h3>
                    <small class="text-success">Active Branch</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Staff Members</h6>
                    <h3><?php echo $summaryData['staff']['active'] ?? 0; ?>/<?php echo $summaryData['staff']['total'] ?? 0; ?></h3>
                    <small class="text-success">Active Staff</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Today's Transactions</h6>
                    <h3>₹<?php echo number_format(($summaryData['transactions']['total_credit'] ?? 0) - ($summaryData['transactions']['total_debit'] ?? 0), 2); ?></h3>
                    <small class="text-muted">Net Transaction Amount</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Total Bank Balance</h6>
                    <h3>₹<?php echo number_format($summaryData['bank_accounts']['total_balance'] ?? 0, 2); ?></h3>
                    <small class="text-muted">Across all accounts</small>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row quick-actions">
            <div class="col-12">
                <h5 class="mb-3">Quick Actions</h5>
            </div>
            <div class="col-md-3">
                <a href="staff/add.php" class="btn btn-primary w-100">
                    <i class="bi bi-person-plus"></i> Add New Staff
                </a>
            </div>
            <div class="col-md-3">
                <a href="branch/add.php" class="btn btn-success w-100">
                    <i class="bi bi-building-add"></i> Add New Branch
                </a>
            </div>
            <div class="col-md-3">
                <a href="transactions/add.php" class="btn btn-info w-100">
                    <i class="bi bi-currency-exchange"></i> New Transaction
                </a>
            </div>
            <div class="col-md-3">
                <a href="reports/generate.php" class="btn btn-secondary w-100">
                    <i class="bi bi-file-earmark-text"></i> Generate Report
                </a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/custom.js"></script>
    <script>
        // Update current time every second
        setInterval(function() {
            document.getElementById('current-datetime').textContent = 
                new Date().toISOString().slice(0, 19).replace('T', ' ');
        }, 1000);
    </script>
</body>
</html>