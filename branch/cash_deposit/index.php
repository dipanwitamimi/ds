<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$user = $_SESSION['branch_user'];
$page_title = 'Cash Deposit Transactions';

$db = new Database();
$conn = $db->getConnection();
$branch_id = $user['branch_id'];

// Fetch Cash Deposit transactions
$stmt = $conn->prepare("
    SELECT cd.*, 
           cd.bank_account_id AS account_identifier -- Replace with actual identifier column in bank_accounts table if necessary
    FROM cash_deposits cd
    WHERE cd.branch_id = ? 
    ORDER BY cd.deposit_date DESC, cd.created_at DESC
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
    <div class="container-fluid py-4">
        <h4><?php echo $page_title; ?></h4>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Cash Deposit Transactions</h5>
                <a href="create.php" class="btn btn-primary float-end">
                    <i class="bi bi-plus-circle"></i> Add New Transaction
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($transactions)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Deposit Date</th>
                                <th>Bank Account</th>
                                <th>Total Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $index => $transaction): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($transaction['deposit_date']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['account_identifier'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($transaction['total_amount'], 2); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $transaction['id']; ?>" class="btn btn-info">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="edit.php?id=<?php echo $transaction['id']; ?>" class="btn btn-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No cash deposit transactions found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>