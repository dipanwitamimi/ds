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

// Fetch transaction details
$stmt = $conn->prepare("
    SELECT * FROM lapu 
    WHERE id = ? AND branch_id = ?
");
$stmt->execute([$transaction_id, $branch_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    $_SESSION['error'] = "Transaction not found or you don't have permission to edit it.";
    header('Location: transactions.php');
    exit;
}

$page_title = 'Edit LAPU Transaction';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cash_received = isset($_POST['cash_received']) ? floatval($_POST['cash_received']) : 0;
    $auto_amount = isset($_POST['auto_amount']) ? floatval($_POST['auto_amount']) : 0;
    $opening_balance = isset($_POST['opening_balance']) ? floatval($_POST['opening_balance']) : 0;
    $total_spent = isset($_POST['total_spent']) ? floatval($_POST['total_spent']) : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($cash_received <= 0 || $auto_amount <= 0 || $opening_balance <= 0 || $total_spent <= 0) {
        $_SESSION['error'] = "All numeric fields must be greater than zero.";
        header('Location: edit.php?id=' . $transaction_id);
        exit;
    }

    // Calculate totals
    $total_available_fund = $cash_received + $opening_balance + $auto_amount;
    $closing_amount = $total_available_fund - $total_spent;

    // Update transaction in database
    try {
        $stmt = $conn->prepare("
            UPDATE lapu 
            SET 
                cash_received = :cash_received,
                opening_balance = :opening_balance,
                auto_amount = :auto_amount,
                total_spent = :total_spent,
                total_available_fund = :total_available_fund,
                closing_amount = :closing_amount,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':cash_received' => $cash_received,
            ':opening_balance' => $opening_balance,
            ':auto_amount' => $auto_amount,
            ':total_spent' => $total_spent,
            ':total_available_fund' => $total_available_fund,
            ':closing_amount' => $closing_amount,
            ':notes' => $notes,
            ':id' => $transaction_id,
            ':branch_id' => $branch_id,
        ]);

        $_SESSION['success'] = "Transaction updated successfully.";
        header('Location: transactions.php');
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
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
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
                            <label class="form-label">Cash Received</label>
                            <input type="number" step="0.01" class="form-control" name="cash_received" value="<?php echo htmlspecialchars($transaction['cash_received']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Auto Amount</label>
                            <input type="number" step="0.01" class="form-control" name="auto_amount" value="<?php echo htmlspecialchars($transaction['auto_amount']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Opening Balance</label>
                            <input type="number" step="0.01" class="form-control" name="opening_balance" value="<?php echo htmlspecialchars($transaction['opening_balance']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Spent</label>
                            <input type="number" step="0.01" class="form-control" name="total_spent" value="<?php echo htmlspecialchars($transaction['total_spent']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($transaction['notes']); ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Update Transaction</button>
                            <a href="transactions.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>