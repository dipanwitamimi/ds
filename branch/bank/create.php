<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$page_title = 'Add New Bank Account';
$db = new Database();
$conn = $db->getConnection();
$branch_id = $_SESSION['branch_user']['branch_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_name = isset($_POST['bank_name']) ? trim($_POST['bank_name']) : '';
    $account_number = isset($_POST['account_number']) ? trim($_POST['account_number']) : '';
    $opening_balance = isset($_POST['opening_balance']) ? floatval($_POST['opening_balance']) : 0.00;

    if (empty($bank_name) || empty($account_number)) {
        $_SESSION['error'] = "Bank Name and Account Number are required.";
        header('Location: create.php');
        exit;
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO bank_accounts 
            (branch_id, bank_name, account_number, opening_balance, current_balance, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $branch_id, $bank_name, $account_number, $opening_balance, $opening_balance
        ]);

        $_SESSION['success'] = "Bank account added successfully.";
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to add bank account: " . $e->getMessage();
        header('Location: create.php');
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
                <h5 class="card-title mb-0">Create Bank Account</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="mb-3">
                        <label for="bank_name" class="form-label">Bank Name</label>
                        <input type="text" id="bank_name" name="bank_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="account_number" class="form-label">Account Number</label>
                        <input type="text" id="account_number" name="account_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="opening_balance" class="form-label">Opening Balance</label>
                        <input type="number" id="opening_balance" name="opening_balance" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Add Account</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>