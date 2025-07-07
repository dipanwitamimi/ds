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
    SELECT * FROM cash_deposits 
    WHERE id = ? AND branch_id = ?
");
$stmt->execute([$transaction_id, $branch_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    $_SESSION['error'] = "Transaction not found or you don't have permission to edit it.";
    header('Location: index.php');
    exit;
}

$page_title = 'Edit Cash Deposit Transaction';

// Fetch branch name
$stmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch_row = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_name = $branch_row ? $branch_row['branch_name'] : "";

// Fetch staff list for this branch
$stmt = $conn->prepare("SELECT id, full_name FROM staff WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch bank accounts for this branch
$stmt = $conn->prepare("SELECT id, bank_name, account_number FROM bank_accounts WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$bank_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deposit_date = isset($_POST['deposit_date']) ? trim($_POST['deposit_date']) : '';
    $bank_account_id = isset($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : 0;
    $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
    $notes_2000 = isset($_POST['notes_2000']) ? intval($_POST['notes_2000']) : 0;
    $notes_500 = isset($_POST['notes_500']) ? intval($_POST['notes_500']) : 0;
    $notes_200 = isset($_POST['notes_200']) ? intval($_POST['notes_200']) : 0;
    $notes_100 = isset($_POST['notes_100']) ? intval($_POST['notes_100']) : 0;
    $notes_50 = isset($_POST['notes_50']) ? intval($_POST['notes_50']) : 0;
    $notes_20 = isset($_POST['notes_20']) ? intval($_POST['notes_20']) : 0;
    $notes_10 = isset($_POST['notes_10']) ? intval($_POST['notes_10']) : 0;
    $notes_5 = isset($_POST['notes_5']) ? intval($_POST['notes_5']) : 0;
    $notes_2 = isset($_POST['notes_2']) ? intval($_POST['notes_2']) : 0;
    $notes_1 = isset($_POST['notes_1']) ? intval($_POST['notes_1']) : 0;

    // Calculate total amount
    $total_amount = ($notes_2000 * 2000) + ($notes_500 * 500) + ($notes_200 * 200) + ($notes_100 * 100) +
                    ($notes_50 * 50) + ($notes_20 * 20) + ($notes_10 * 10) + ($notes_5 * 5) +
                    ($notes_2 * 2) + ($notes_1 * 1);

    if (empty($deposit_date) || $bank_account_id === 0 || $staff_id === 0) {
        $_SESSION['error'] = "Deposit Date, Bank Account, and Staff are required.";
        header('Location: edit.php?id=' . $transaction_id);
        exit;
    }

    // Update transaction in database
    try {
        $stmt = $conn->prepare("
            UPDATE cash_deposits 
            SET deposit_date = :deposit_date,
                bank_account_id = :bank_account_id,
                staff_id = :staff_id,
                notes_2000 = :notes_2000,
                notes_500 = :notes_500,
                notes_200 = :notes_200,
                notes_100 = :notes_100,
                notes_50 = :notes_50,
                notes_20 = :notes_20,
                notes_10 = :notes_10,
                notes_5 = :notes_5,
                notes_2 = :notes_2,
                notes_1 = :notes_1,
                total_amount = :total_amount
            WHERE id = :id AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':deposit_date' => $deposit_date,
            ':bank_account_id' => $bank_account_id,
            ':staff_id' => $staff_id,
            ':notes_2000' => $notes_2000,
            ':notes_500' => $notes_500,
            ':notes_200' => $notes_200,
            ':notes_100' => $notes_100,
            ':notes_50' => $notes_50,
            ':notes_20' => $notes_20,
            ':notes_10' => $notes_10,
            ':notes_5' => $notes_5,
            ':notes_2' => $notes_2,
            ':notes_1' => $notes_1,
            ':total_amount' => $total_amount,
            ':id' => $transaction_id,
            ':branch_id' => $branch_id,
        ]);

        $_SESSION['success'] = "Cash deposit transaction updated successfully.";
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
    <style>
        .form-label { font-weight: 500; }
        .uniform-box {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: .4rem;
            padding: .5rem .8rem;
            margin-bottom: .6rem;
            min-height: 38px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .uniform-box input, .uniform-box select {
            border-radius: .32rem;
            font-size: .97rem;
            padding: .25rem .5rem;
            height: 1.9rem;
        }
        .denomination-group {
            margin: auto;
        }
        .denomination-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.6rem;
            text-align: center;
        }
        .denomination-row {
            display: flex;
            gap: 1rem;
            margin-bottom: .5rem;
        }
        .denomination-box {
            flex: 1 1 0;
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border-radius: .32rem;
            padding: .42rem .6rem;
            border: 1px solid #dee2e6;
            min-width: 0;
        }
        .denomination-box label {
            min-width: 60px;
            margin-bottom: 0;
            font-size: .96rem;
        }
        .denomination-box input {
            width: 70px;
            margin-left: 0.5rem;
            text-align: right;
        }
        .total-box {
            font-size: 1.07rem;
            font-weight: bold;
            color: #236AA4;
            background: #e9f7fd;
            padding: .5rem .7rem;
            border-radius: .32rem;
            border: 1.5px solid #b6e0f7;
            margin-top: .7rem;
            text-align: right;
        }
        @media (max-width: 800px) {
            .container.main-content { max-width: 99vw !important; }
            .denomination-row { flex-direction: column; gap: 0.2rem;}
            .denomination-box input { width: 100%; }
        }
    </style>
    <script>
    function calculateTotal() {
        const notes = [2000, 500, 200, 100, 50, 20, 10, 5, 2, 1];
        let total = 0;
        notes.forEach(function(note) {
            let qty = parseInt(document.getElementsByName('notes_' + note)[0].value) || 0;
            total += qty * note;
        });
        document.getElementById('total_amount').textContent = '₹' + total.toLocaleString('en-IN');
    }
    window.addEventListener('DOMContentLoaded', function() {
        const notes = [2000, 500, 200, 100, 50, 20, 10, 5, 2, 1];
        notes.forEach(function(note) {
            document.getElementsByName('notes_' + note)[0].addEventListener('input', calculateTotal);
        });
        calculateTotal();
    });
    </script>
</head>
<body class="bg-light">
    <div class="container py-4 main-content" style="max-width:900px;">
        <h4 class="mb-3"><?php echo $page_title; ?></h4>
        <div class="card mt-2">
            <div class="card-header">
                <h5 class="card-title mb-0">Edit Cash Deposit</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST" autocomplete="off">
                    <div class="row g-2">
                        <div class="col-md-3 col-sm-6 col-12">
                            <div class="uniform-box">
                                <label class="form-label">Branch Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($branch_name); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 col-12">
                            <div class="uniform-box">
                                <label class="form-label">Deposit Date</label>
                                <input type="date" class="form-control" name="deposit_date" value="<?php echo htmlspecialchars($transaction['deposit_date']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 col-12">
                            <div class="uniform-box">
                                <label class="form-label">Staff Name</label>
                                <select name="staff_id" class="form-select" required>
                                    <option value="">Select Staff</option>
                                    <?php foreach($staff_list as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>"<?php if($transaction['staff_id'] == $staff['id']) echo ' selected'; ?>>
                                            <?php echo htmlspecialchars($staff['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 col-12">
                            <div class="uniform-box">
                                <label class="form-label">Bank Name & Account Number</label>
                                <select name="bank_account_id" class="form-select" required>
                                    <option value="">Select Bank Account</option>
                                    <?php foreach($bank_accounts as $bank): ?>
                                        <option value="<?php echo $bank['id']; ?>"<?php if($transaction['bank_account_id'] == $bank['id']) echo ' selected'; ?>>
                                            <?php echo htmlspecialchars($bank['bank_name'] . " / " . $bank['account_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="uniform-box denomination-group mt-2 mb-2">
                        <div class="denomination-title">Denominations (Number of Notes)</div>
                        <?php
                        $notes = [2000, 500, 200, 100, 50, 20, 10, 5, 2, 1];
                        for($i=0; $i<count($notes); $i+=2): ?>
                            <div class="denomination-row">
                                <div class="denomination-box">
                                    <label class="form-label mb-0" for="notes_<?php echo $notes[$i]; ?>">₹<?php echo $notes[$i]; ?></label>
                                    <input type="number" class="form-control" name="notes_<?php echo $notes[$i]; ?>" id="notes_<?php echo $notes[$i]; ?>" min="0" value="<?php echo htmlspecialchars($transaction['notes_' . $notes[$i]]); ?>" oninput="calculateTotal()">
                                </div>
                                <?php if(isset($notes[$i+1])): ?>
                                <div class="denomination-box">
                                    <label class="form-label mb-0" for="notes_<?php echo $notes[$i+1]; ?>">₹<?php echo $notes[$i+1]; ?></label>
                                    <input type="number" class="form-control" name="notes_<?php echo $notes[$i+1]; ?>" id="notes_<?php echo $notes[$i+1]; ?>" min="0" value="<?php echo htmlspecialchars($transaction['notes_' . $notes[$i+1]]); ?>" oninput="calculateTotal()">
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                        <div class="total-box">
                            Total Deposited: <span id="total_amount">₹0</span>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                        <a href="index.php" class="btn btn-secondary btn-sm ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>