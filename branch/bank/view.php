<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

checkBranchAuth();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Bank Account ID is required.";
    header('Location: index.php');
    exit;
}

$account_id = intval($_GET['id']);
$db = new Database();
$conn = $db->getConnection();
$branch_id = $_SESSION['branch_user']['branch_id'];

// Fetch bank account details
$stmt = $conn->prepare("
    SELECT * FROM bank_accounts WHERE id = ? AND branch_id = ?
");
$stmt->execute([$account_id, $branch_id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    $_SESSION['error'] = "Bank account not found or you don't have permission to view it.";
    header('Location: index.php');
    exit;
}

$page_title = 'View Bank Account';
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
                <h5 class="card-title mb-0">Bank Account Details</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">Bank Name:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($account['bank_name']); ?></dd>

                    <dt class="col-sm-4">Account Number:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($account['account_number']); ?></dd>

                    <dt class="col-sm-4">Opening Balance:</dt>
                    <dd class="col-sm-8"><?php echo number_format($account['opening_balance'], 2); ?></dd>

                    <dt class="col-sm-4">Current Balance:</dt>
                    <dd class="col-sm-8"><?php echo number_format($account['current_balance'], 2); ?></dd>

                    <dt class="col-sm-4">Created At:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($account['created_at']); ?></dd>
                </dl>
                <a href="index.php" class="btn btn-secondary">Back to Accounts</a>
            </div>
        </div>
    </div>
</body>
</html>