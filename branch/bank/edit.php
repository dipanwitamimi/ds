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
    $_SESSION['error'] = "Bank account not found or you don't have permission to edit it.";
    header('Location: index.php');
    exit;
}

$page_title = 'Edit Bank Account';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_name = isset($_POST['bank_name']) ? trim($_POST['bank_name']) : '';
    $account_number = isset($_POST['account_number']) ? trim($_POST['account_number']) : '';
    $opening_balance = isset($_POST['opening_balance']) ? floatval($_POST['opening_balance']) : 0.00;
    $current_balance = isset($_POST['current_balance']) ? floatval($_POST['current_balance']) : 0.00;

    if (empty($bank_name) || empty($account_number)) {
        $_SESSION['error'] = "Bank Name and Account Number are required.";
        header('Location: edit.php?id=' . $account_id);
        exit;
    }

    // Update bank account in database
    try {
        $stmt = $conn->prepare("
            UPDATE bank_accounts 
            SET bank_name = :bank_name,
                account_number = :account_number,
                opening_balance = :opening_balance,
                current_balance = :current_balance
            WHERE id = :id AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':bank_name' => $bank_name,
            ':account_number' => $account_number,
            ':opening_balance' => $opening_balance,
            ':current_balance' => $current_balance,
            ':id' => $account_id,
            ':branch_id' => $branch_id,
        ]);

        $_SESSION['success'] = "Bank account updated successfully.";
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to update bank account: " . $e->getMessage();
        header('Location: edit.php?id=' . $account_id);
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
                <h5 class="card-title mb-0">Edit Bank Account</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="mb-3">
                        <label for="bank_name" class="form-label">Bank Name</label>
                        <input type="text" id="bank_name" name="bank_name" class="form-control" value="<?php echo htmlspecialchars($account['bank_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="account_number" class="form-label">Account Number</label>
                        <input type="text" id="account_number" name="account_number" class="form-control" value="<?php echo htmlspecialchars($account['account_number']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="opening_balance" class="form-label">Opening Balance</label>
                        <input type="number" id="opening_balance" name="opening_balance" class="form-control" step="0.01" value="<?php echo htmlspecialchars($account['opening_balance']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="current_balance" class="form-label">Current Balance</label>
                        <input type="number" id="current_balance" name="current_balance" class="form-control" step="0.01" value="<?php echo htmlspecialchars($account['current_balance']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>