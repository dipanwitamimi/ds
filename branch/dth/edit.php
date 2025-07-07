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

// Fetch transaction details
$stmt = $conn->prepare("
    SELECT * FROM dth 
    WHERE id = ? AND branch_id = ?
");
$stmt->execute([$transaction_id, $branch_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    $_SESSION['error'] = "Transaction not found or you don't have permission to edit it.";
    header('Location: index.php');
    exit;
}

$page_title = 'Edit DTH Transaction';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_date = isset($_POST['transaction_date']) ? trim($_POST['transaction_date']) : '';
    $amount_received = isset($_POST['amount_received']) ? floatval($_POST['amount_received']) : 0.00;
    $opening_balance = isset($_POST['opening_balance']) ? floatval($_POST['opening_balance']) : 0.00;
    $auto_amount = isset($_POST['auto_amount']) ? floatval($_POST['auto_amount']) : 0.00;
    $total_spent = isset($_POST['total_spent']) ? floatval($_POST['total_spent']) : 0.00;

    if (empty($transaction_date) || $amount_received < 0 || $opening_balance < 0 || $auto_amount < 0 || $total_spent < 0) {
        $_SESSION['error'] = "All fields are required and must be valid.";
        header('Location: edit.php?id=' . $transaction_id);
        exit;
    }

    // Calculations
    $total_available_fund = $amount_received + $opening_balance + $auto_amount;
    $closing_amount = $total_available_fund - $total_spent;

    // Update transaction in database
    try {
        $stmt = $conn->prepare("
            UPDATE dth 
            SET transaction_date = :transaction_date,
                amount_received = :amount_received,
                opening_balance = :opening_balance,
                auto_amount = :auto_amount,
                total_available_fund = :total_available_fund,
                total_spent = :total_spent,
                closing_amount = :closing_amount
            WHERE id = :id AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':transaction_date' => $transaction_date,
            ':amount_received' => $amount_received,
            ':opening_balance' => $opening_balance,
            ':auto_amount' => $auto_amount,
            ':total_available_fund' => $total_available_fund,
            ':total_spent' => $total_spent,
            ':closing_amount' => $closing_amount,
            ':id' => $transaction_id,
            ':branch_id' => $branch_id,
        ]);

        $_SESSION['success'] = "Transaction updated successfully.";
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to update transaction: " . $e->getMessage();
        header('Location: edit.php?id=' . $transaction_id);
        exit;
    }
}
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
                <h5 class="card-title mb-0">Edit Transaction</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Transaction Date</label>
                            <input type="date" class="form-control" name="transaction_date" value="<?php echo htmlspecialchars($transaction['transaction_date']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount Received</label>
                            <input type="number" step="0.01" class="form-control" name="amount_received" value="<?php echo htmlspecialchars($transaction['amount_received']); ?>" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Opening Balance</label>
                            <input type="number" step="0.01" class="form-control" name="opening_balance" value="<?php echo htmlspecialchars($transaction['opening_balance']); ?>" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Auto Amount</label>
                            <input type="number" step="0.01" class="form-control" name="auto_amount" value="<?php echo htmlspecialchars($transaction['auto_amount']); ?>" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Spent</label>
                            <input type="number" step="0.01" class="form-control" name="total_spent" value="<?php echo htmlspecialchars($transaction['total_spent']); ?>" min="0" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>