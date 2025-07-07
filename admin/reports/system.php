<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';

$currentDateTime = date('Y-m-d H:i:s');
$currentUser = $_SESSION['admin_user']['username'] ?? 'admin';

try {
    $db = (new Database())->getConnection();

    // Quick KPIs
    $staffCount = $db->query("SELECT COUNT(*) FROM staff")->fetchColumn();
    $branchUserCount = $db->query("SELECT COUNT(*) FROM branch_users")->fetchColumn();
    $adminCount = $db->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    $branchCount = $db->query("SELECT COUNT(*) FROM branches")->fetchColumn();
    $transactionCount = $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    $lapuCount = $db->query("SELECT COUNT(*) FROM lapu")->fetchColumn();
    $cashDepositCount = $db->query("SELECT COUNT(*) FROM cash_deposits")->fetchColumn();
    $simCardCount = $db->query("SELECT COUNT(*) FROM sim_cards")->fetchColumn();
    $dthCount = $db->query("SELECT COUNT(*) FROM dth")->fetchColumn();

    // Recent activity
    $latestTransaction = $db->query("SELECT MAX(transaction_date) FROM transactions")->fetchColumn();
    $latestLogin = $db->query("SELECT MAX(login_time) FROM admin_login_logs")->fetchColumn();

} catch (Exception $e) {
    $staffCount = $branchUserCount = $adminCount = $branchCount = $transactionCount = $lapuCount = $cashDepositCount = $simCardCount = $dthCount = 0;
    $latestTransaction = $latestLogin = 'N/A';
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Workflow & Performance Report</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .header-info { background: #e9ecef; padding: 10px 0; margin-bottom: 16px; }
        .section-title { background: #f8f9fa; font-size: 1.2rem; font-weight: bold; padding: 0.7rem; }
        .kpi-card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);}
    </style>
</head>
<body>
<div class="header-info p-2 bg-light">
    <div class="container">
        Current Date and Time: <span><?php echo $currentDateTime; ?></span> &nbsp;|&nbsp;
        Current User's Login: <span><?php echo htmlspecialchars($currentUser); ?></span>
    </div>
</div>
<div class="container mt-4">
    <div class="section-title">System Workflow Overview</div>
    <div class="mb-4">
        <ol>
            <li><strong>Staff/Branch Registration:</strong> Admin creates staff and branch users, assigning roles and branches.</li>
            <li><strong>Login & Authentication:</strong> All users (admin, staff, branch) authenticate via login forms. Login attempts/audits are logged.</li>
            <li><strong>Daily Operations:</strong>
                <ul>
                    <li>Staff/branch users manage APB, DTH, LAPU, SIM card, and cash deposit entries.</li>
                    <li>Transactions are logged and linked to bank accounts.</li>
                </ul>
            </li>
            <li><strong>Reporting & Auditing:</strong> Admins and managers view real-time performance, transaction, and user activity reports.</li>
            <li><strong>Performance Tracking:</strong> KPIs are calculated on-the-fly (totals, recent activity, status of users, etc.).</li>
        </ol>
    </div>

    <div class="section-title">Key Modules & Functions</div>
    <div class="mb-4">
        <ul>
            <li><strong>User Management:</strong> Admin, staff, and branch user creation, status control, and login tracking.</li>
            <li><strong>Branch Management:</strong> Creating and maintaining branch records.</li>
            <li><strong>Bank & Transaction Module:</strong> Linking of transactions to bank accounts, tracking credits/debits.</li>
            <li><strong>Service Modules:</strong> APB, DTH, LAPU, SIM cards, and cash deposit management for operational workflow.</li>
            <li><strong>Reporting:</strong> Real-time and historical reporting on users, financials, and activities.</li>
            <li><strong>Audit Logs:</strong> Login attempts and critical changes recorded for all user types.</li>
        </ul>
    </div>

    <div class="section-title">System Performance KPIs</div>
    <div class="row mb-4">
        <div class="col-md-3"><div class="kpi-card"><strong>Staff Users</strong><br><span class="fs-3"><?php echo $staffCount; ?></span></div></div>
        <div class="col-md-3"><div class="kpi-card"><strong>Branch Users</strong><br><span class="fs-3"><?php echo $branchUserCount; ?></span></div></div>
        <div class="col-md-3"><div class="kpi-card"><strong>Admins</strong><br><span class="fs-3"><?php echo $adminCount; ?></span></div></div>
        <div class="col-md-3"><div class="kpi-card"><strong>Branches</strong><br><span class="fs-3"><?php echo $branchCount; ?></span></div></div>
        <div class="col-md-3"><div class="kpi-card"><strong>Transactions</strong><br><span class="fs-3"><?php echo $transactionCount; ?></span></div></div>
        <div class="col-md-3"><div class="kpi-card"><strong>APB Entries</strong><br><span class="fs-3"><?php echo $lapuCount; ?></span></div></div>
        <div class="col-md-3"><div class="kpi-card"><strong>Cash Deposits</strong><br><span class="fs-3"><?php echo $cashDepositCount; ?></span></div></div>
        <div class="col-md-3"><div class="kpi-card"><strong>SIM Cards</strong><br><span class="fs-3"><?php echo $simCardCount; ?></span></div></div>
        <div class="col-md-3"><div class="kpi-card"><strong>DTH Entries</strong><br><span class="fs-3"><?php echo $dthCount; ?></span></div></div>
    </div>

    <div class="section-title">Recent System Activity</div>
    <div class="mb-4">
        <div class="row">
            <div class="col-md-6">
                <div class="alert alert-info">
                    <strong>Latest Transaction Date:</strong> <?php echo htmlspecialchars($latestTransaction ?: 'N/A'); ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-info">
                    <strong>Latest Admin Login:</strong> <?php echo htmlspecialchars($latestLogin ?: 'N/A'); ?>
                </div>
            </div>
        </div>
    </div>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
</div>
</body>
</html>