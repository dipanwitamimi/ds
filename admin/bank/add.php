<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';

// --- Include your header here ---
require_once '../../includes/header.php';

$message = "";
$addedAccount = null;

// Fetch branches for dropdown
$branches = [];
try {
    $db = (new Database())->getConnection();
    $stmt = $db->query("SELECT id, branch_name FROM branches ORDER BY branch_name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If fetching branches fails, $branches will remain empty
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branchId = $_POST['branch_id'] ?? null;
    $bankName = trim($_POST['bank_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $openingBalance = $_POST['opening_balance'] ?? 0.00;

    // Validate input
    if (empty($branchId) || empty($bankName) || empty($accountNumber)) {
        $message = "All fields (except Opening Balance) are required.";
    } else {
        try {
            // Save bank account details to the database
            $stmt = $db->prepare("
                INSERT INTO bank_accounts (branch_id, bank_name, account_number, opening_balance, current_balance, created_at)
                VALUES (:branch_id, :bank_name, :account_number, :opening_balance, :current_balance, NOW())
            ");
            $stmt->bindParam(':branch_id', $branchId);
            $stmt->bindParam(':bank_name', $bankName);
            $stmt->bindParam(':account_number', $accountNumber);
            $stmt->bindParam(':opening_balance', $openingBalance);
            $stmt->bindParam(':current_balance', $openingBalance); // Initialize current balance with opening balance

            if ($stmt->execute()) {
                $message = "Bank account added successfully.";
                // Fetch all bank accounts to show in the list
                $stmt = $db->query("
                    SELECT ba.*, b.branch_name
                    FROM bank_accounts ba
                    LEFT JOIN branches b ON ba.branch_id = b.id
                    ORDER BY ba.created_at DESC
                ");
                $allAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $message = "Failed to add bank account.";
                $allAccounts = [];
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $allAccounts = [];
        }
    }
} else {
    // On page load or GET, fetch all accounts too
    try {
        $db = (new Database())->getConnection();
        $stmt = $db->query("
            SELECT ba.*, b.branch_name
            FROM bank_accounts ba
            LEFT JOIN branches b ON ba.branch_id = b.id
            ORDER BY ba.created_at DESC
        ");
        $allAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $allAccounts = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Bank Account</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Add Bank Account</h1>
        <?php if (!empty($message)) : ?>
            <div class="alert <?php echo strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="branch_id" class="form-label">Branch</label>
                <select name="branch_id" id="branch_id" class="form-control" required>
                    <option value="">-- Select Branch --</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?php echo $br['id']; ?>" <?php if (isset($branchId) && $branchId == $br['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($br['branch_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="bank_name" class="form-label">Bank Name</label>
                <input type="text" name="bank_name" id="bank_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['bank_name'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label for="account_number" class="form-label">Account Number</label>
                <input type="text" name="account_number" id="account_number" class="form-control" required value="<?php echo htmlspecialchars($_POST['account_number'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label for="opening_balance" class="form-label">Opening Balance</label>
                <input type="number" name="opening_balance" id="opening_balance" class="form-control" step="0.01" value="<?php echo htmlspecialchars($_POST['opening_balance'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Add Bank Account</button>
        </form>

        <hr class="my-4">

        <h2>All Bank Accounts</h2>
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Branch</th>
                        <th>Bank Name</th>
                        <th>Account Number</th>
                        <th>Opening Balance (₹)</th>
                        <th>Current Balance (₹)</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($allAccounts)): ?>
                    <?php foreach ($allAccounts as $idx => $acc): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><?php echo htmlspecialchars($acc['branch_name']); ?></td>
                            <td><?php echo htmlspecialchars($acc['bank_name']); ?></td>
                            <td><?php echo htmlspecialchars($acc['account_number']); ?></td>
                            <td><?php echo number_format($acc['opening_balance'], 2); ?></td>
                            <td><?php echo number_format($acc['current_balance'], 2); ?></td>
                            <td><?php echo htmlspecialchars($acc['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center">No bank accounts found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>