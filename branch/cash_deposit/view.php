<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

checkBranchAuth();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Transaction ID is required.";
    header('Location: index.php');
    exit;
}

$transaction_id = intval($_GET['id']);
$db = new Database();
$conn = $db->getConnection();
$branch_id = $_SESSION['branch_user']['branch_id'];

// Fetch transaction details with branch, staff, and bank info
$stmt = $conn->prepare("
    SELECT cd.*, 
           b.branch_name, 
           s.full_name AS staff_name, 
           ba.bank_name, 
           ba.account_number
      FROM cash_deposits cd
 LEFT JOIN branches b ON cd.branch_id = b.id
 LEFT JOIN staff s ON cd.staff_id = s.id
 LEFT JOIN bank_accounts ba ON cd.bank_account_id = ba.id
     WHERE cd.id = ? AND cd.branch_id = ?
");
$stmt->execute([$transaction_id, $branch_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    $_SESSION['error'] = "Transaction not found or you don't have permission to view it.";
    header('Location: index.php');
    exit;
}

$page_title = 'View Cash Deposit Transaction';
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
                <h5 class="card-title mb-0">Transaction Details</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">Transaction ID:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($transaction['id']); ?></dd>

                    <dt class="col-sm-4">Branch Name:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($transaction['branch_name']); ?></dd>

                    <dt class="col-sm-4">Staff Name:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($transaction['staff_name']); ?></dd>

                    <dt class="col-sm-4">Deposit Date:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($transaction['deposit_date']); ?></dd>

                    <dt class="col-sm-4">Bank Name & Account Number:</dt>
                    <dd class="col-sm-8">
                        <?php
                        if ($transaction['bank_name'] && $transaction['account_number']) {
                            echo htmlspecialchars($transaction['bank_name'] . " / " . $transaction['account_number']);
                        } else {
                            echo "-";
                        }
                        ?>
                    </dd>

                    <?php
                    // Display the note denominations
                    $notes = [2000, 500, 200, 100, 50, 20, 10, 5, 2, 1];
                    foreach ($notes as $note): ?>
                        <dt class="col-sm-4">Notes <?php echo $note; ?>:</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($transaction['notes_' . $note]); ?></dd>
                    <?php endforeach; ?>

                    <dt class="col-sm-4">Total Amount:</dt>
                    <dd class="col-sm-8">₹<?php echo number_format($transaction['total_amount'], 2); ?></dd>

                    <dt class="col-sm-4">Created At:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($transaction['created_at']); ?></dd>
                </dl>
                <a href="index.php" class="btn btn-secondary">Back to Transactions</a>
            </div>
        </div>
    </div>
</body>
</html>