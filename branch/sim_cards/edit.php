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
    SELECT * FROM sim_cards 
    WHERE id = ? AND branch_id = ?
");
$stmt->execute([$transaction_id, $branch_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    $_SESSION['error'] = "Transaction not found or you don't have permission to edit it.";
    header('Location: index.php');
    exit;
}

$page_title = 'Edit SIM Card Transaction';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_date = isset($_POST['transaction_date']) ? trim($_POST['transaction_date']) : '';
    $quantity_received = isset($_POST['quantity_received']) ? intval($_POST['quantity_received']) : 0;
    $opening_stock = isset($_POST['opening_stock']) ? intval($_POST['opening_stock']) : 0;
    $auto_quantity = isset($_POST['auto_quantity']) ? intval($_POST['auto_quantity']) : 0;
    $total_sold = isset($_POST['total_sold']) ? intval($_POST['total_sold']) : 0;

    if (empty($transaction_date) || $quantity_received < 0 || $opening_stock < 0 || $auto_quantity < 0 || $total_sold < 0) {
        $_SESSION['error'] = "All fields are required and must be valid.";
        header('Location: edit.php?id=' . $transaction_id);
        exit;
    }

    // Calculations
    $total_available = $quantity_received + $opening_stock + $auto_quantity;
    $closing_stock = $total_available - $total_sold;

    // Update transaction in database
    try {
        $stmt = $conn->prepare("
            UPDATE sim_cards 
            SET transaction_date = :transaction_date,
                quantity_received = :quantity_received,
                opening_stock = :opening_stock,
                auto_quantity = :auto_quantity,
                total_available = :total_available,
                total_sold = :total_sold,
                closing_stock = :closing_stock
            WHERE id = :id AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':transaction_date' => $transaction_date,
            ':quantity_received' => $quantity_received,
            ':opening_stock' => $opening_stock,
            ':auto_quantity' => $auto_quantity,
            ':total_available' => $total_available,
            ':total_sold' => $total_sold,
            ':closing_stock' => $closing_stock,
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
                            <label class="form-label">Quantity Received</label>
                            <input type="number" class="form-control" name="quantity_received" value="<?php echo htmlspecialchars($transaction['quantity_received']); ?>" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Opening Stock</label>
                            <input type="number" class="form-control" name="opening_stock" value="<?php echo htmlspecialchars($transaction['opening_stock']); ?>" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Auto Quantity</label>
                            <input type="number" class="form-control" name="auto_quantity" value="<?php echo htmlspecialchars($transaction['auto_quantity']); ?>" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Sold</label>
                            <input type="number" class="form-control" name="total_sold" value="<?php echo htmlspecialchars($transaction['total_sold']); ?>" min="0" required>
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