<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

checkBranchAuth();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Transaction ID is required.";
    header('Location: transactions.php');
    exit;
}

$transaction_id = intval($_GET['id']);

$db = new Database();
$conn = $db->getConnection();
$branch_id = $_SESSION['branch_user']['branch_id'];

// Fetch transaction details (with branch, staff, and bank info)
$stmt = $conn->prepare("
    SELECT l.*, 
           b.branch_name,
           s.full_name AS staff_name,
           ba.bank_name,
           ba.account_number
    FROM lapu l
    LEFT JOIN branches b ON l.branch_id = b.id
    LEFT JOIN staff s ON l.staff_id = s.id
    LEFT JOIN bank_accounts ba ON l.bank_account_id = ba.id
    WHERE l.id = ? AND l.branch_id = ?
");
$stmt->execute([$transaction_id, $branch_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    $_SESSION['error'] = "Transaction not found or you don't have permission to view it.";
    header('Location: transactions.php');
    exit;
}

$page_title = 'View LAPU Transaction';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
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
    </style>
</head>
<body class="bg-light">
    <!-- Navigation Panel -->
    <div class="sidebar d-none d-lg-block">
        <div class="px-3 mb-3">
            <h5 class="mb-1">Branch Panel</h5>
            <div class="small text-muted"><?php echo htmlspecialchars($transaction['branch_name'] ?? ''); ?></div>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='dashboard.php') echo ' active'; ?>" href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='index.php') echo ' active'; ?>" href="index.php"><i class="bi bi-phone"></i> LAPU</a>
            <a class="nav-link" href="../sim_cards/index.php"><i class="bi bi-sim"></i> SIM Cards</a>
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
        <h4><?php echo $page_title; ?></h4>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Transaction Details</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Branch Name</th>
                                <th>Staff Name</th>
                                <th>Bank Name/Account</th>
                                <th>Received</th>
                                <th>Opening Balance</th>
                                <th>Auto Amount</th>
                                <th>Total Available</th>
                                <th>Total Sale</th>
                                <th>Closing Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['branch_name']); ?></td>
                                <td>
                                    <?php
                                        if (!empty($transaction['staff_name'])) {
                                            echo htmlspecialchars($transaction['staff_name']);
                                        } elseif (!empty($transaction['staff_id'])) {
                                            echo "ID#" . htmlspecialchars($transaction['staff_id']);
                                        } else {
                                            echo "N/A";
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        if (!empty($transaction['bank_name']) && !empty($transaction['account_number'])) {
                                            echo htmlspecialchars($transaction['bank_name']) . " / " . htmlspecialchars($transaction['account_number']);
                                        } elseif (!empty($transaction['bank_account_id'])) {
                                            echo "ID#" . htmlspecialchars($transaction['bank_account_id']);
                                        } else {
                                            echo "N/A";
                                        }
                                    ?>
                                </td>
                                <td>₹<?php echo number_format($transaction['cash_received'], 2); ?></td>
                                <td>₹<?php echo number_format($transaction['opening_balance'], 2); ?></td>
                                <td>₹<?php echo number_format($transaction['auto_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($transaction['total_available_fund'], 2); ?></td>
                                <td>₹<?php echo number_format($transaction['total_spent'], 2); ?></td>
                                <td>₹<?php echo number_format($transaction['closing_amount'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <strong>Notes:</strong>
                    <div><?php echo nl2br(htmlspecialchars($transaction['notes'])); ?></div>
                </div>
                <a href="transactions.php" class="btn btn-secondary mt-3">Back to Transactions</a>
            </div>
        </div>
    </div>
    </div>
</body>
</html>