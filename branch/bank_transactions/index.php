<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

checkBranchAuth();
$user = $_SESSION['branch_user'];
$page_title = 'Bank Transactions Management';

$db = new Database();
$conn = $db->getConnection();
$branch_id = $user['branch_id'];
$today = date('Y-m-d');

// Get today's transaction statistics
$stmt = $conn->prepare("
    SELECT 
        SUM(amount) as total_amount,
        COUNT(*) as total_transactions
    FROM bank_transactions 
    WHERE branch_id = ? 
    AND DATE(transaction_date) = ?
");
$stmt->execute([$branch_id, $today]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get transactions
$stmt = $conn->prepare("
    SELECT bt.*, b.name as bank_name 
    FROM bank_transactions bt
    LEFT JOIN banks b ON bt.bank_id = b.id
    WHERE bt.branch_id = ? 
    ORDER BY bt.transaction_date DESC, bt.created_at DESC 
    LIMIT 10
");
$stmt->execute([$branch_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- DateTime Header with exact format -->
    <div class="container-fluid py-3">
        <div class="row">
            <div class="col-md-6">
                <div class="border rounded p-3 bg-white">
                    Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted):<br>
                    <strong id="current-datetime">2025-03-12 20:14:03</strong>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="border rounded p-3 bg-white d-inline-block">
                    Current User's Login:<br>
                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Today's Total Amount</h6>
                        <h3 class="mb-0">₹<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Total Transactions</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['total_transactions'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex gap-2">
                    <a href="create.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> New Transaction
                    </a>
                    <a href="transactions.php" class="btn btn-info">
                        <i class="bi bi-list"></i> View All Transactions
                    </a>
                    <a href="reports.php" class="btn btn-secondary">
                       