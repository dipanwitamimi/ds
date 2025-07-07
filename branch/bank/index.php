<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$user = $_SESSION['branch_user'];
$page_title = 'Bank Accounts';

$db = new Database();
$conn = $db->getConnection();
$branch_id = $user['branch_id'];

// Fetch bank accounts
$stmt = $conn->prepare("
    SELECT * FROM bank_accounts WHERE branch_id = ? ORDER BY created_at DESC
");
$stmt->execute([$branch_id]);
$bank_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <h4><?php echo $page_title; ?></h4>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Bank Accounts</h5>
                <a href="create.php" class="btn btn-primary float-end">
                    <i class="bi bi-plus-circle"></i> Add New Account
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($bank_accounts)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Bank Name</th>
                                <th>Account Number</th>
                                <th>Opening Balance</th>
                                <th>Current Balance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bank_accounts as $index => $account): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($account['bank_name']); ?></td>
                                <td><?php echo htmlspecialchars($account['account_number']); ?></td>
                                <td><?php echo number_format($account['opening_balance'], 2); ?></td>
                                <td><?php echo number_format($account['current_balance'], 2); ?></td>
                                <td>
                                    <a href="view.php?id=<?php echo $account['id']; ?>" class="btn btn-info btn-sm">View</a>
                                    <a href="edit.php?id=<?php echo $account['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No bank accounts found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>