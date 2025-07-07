<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$user = $_SESSION['branch_user'];
$branch_id = $user['branch_id'];
$username = $user['username'] ?? '';
$page_title = 'New DTH Issue to Staff';

$db = new Database();
$conn = $db->getConnection();

// Fetch staff list for dropdown (only for this branch)
$stmt = $conn->prepare("SELECT id, full_name FROM staff WHERE branch_id = ? ORDER BY full_name ASC");
$stmt->execute([$branch_id]);
$staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get yesterday's closing_amount as opening_balance
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
$stmt = $conn->prepare("SELECT closing_amount FROM dth WHERE branch_id = ? AND DATE(transaction_date) <= ? ORDER BY transaction_date DESC, id DESC LIMIT 1");
$stmt->execute([$branch_id, $yesterday]);
$opening_balance = $stmt->fetchColumn();
$opening_balance = $opening_balance !== false ? (float)$opening_balance : 0.00;

// Sum all today's DTH receipts for this branch (to handle multiple entries in one day)
$stmt = $conn->prepare("SELECT 
    SUM(amount_received) as total_received,
    SUM(auto_amount) as total_auto
    FROM dth 
    WHERE branch_id = ? AND DATE(transaction_date) = ?");
$stmt->execute([$branch_id, $today]);
$today_totals = $stmt->fetch(PDO::FETCH_ASSOC);

$received = $today_totals && $today_totals['total_received'] !== null
    ? (float)$today_totals['total_received'] : 0.00;
$auto = $today_totals && $today_totals['total_auto'] !== null
    ? (float)$today_totals['total_auto'] : 0.00;
$total_available = $opening_balance + $received + $auto;

// Subtract already allocated to staff for today
$stmt = $conn->prepare("SELECT IFNULL(SUM(quantity),0) FROM dth_staff_allocations WHERE branch_id = ? AND allocation_date = ?");
$stmt->execute([$branch_id, $today]);
$already_allocated = (float)$stmt->fetchColumn();

$remaining_to_allocate = $total_available - $already_allocated;

// Default values for repopulating form on error or load
$default = [
    'staff_id' => '',
    'allocation_date' => $today,
    'quantity' => '',
    'notes' => ''
];
foreach ($default as $k => $v) {
    if (!isset($_POST[$k])) $_POST[$k] = $v;
}

// Error messages
$error_msg = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'insufficient_stock') {
        $error_msg = 'Insufficient stock available for the requested allocation.';
        if (isset($_GET['available'])) {
            $error_msg .= ' Available to allocate: ' . number_format((float)$_GET['available'], 2) . '.';
        }
    } elseif ($_GET['error'] === 'invalid_input') {
        $error_msg = 'Please select staff and enter a valid quantity.';
    } else {
        $error_msg = 'An error occurred. Please check your input.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New DTH Issue to Staff</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/bootstrap-icons.css">
    <style>
        .form-label { font-weight: 500; }
        .card { max-width: 500px; margin: 0 auto; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="mb-3 text-center">
            <h2>DTH: Issue to Staff</h2>
            <div class="small text-muted"><?= htmlspecialchars($username) ?> (Branch <?= htmlspecialchars($branch_id) ?>)</div>
        </div>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>
        <div class="card shadow">
            <div class="card-body">
                <form action="process_issue.php" method="post" autocomplete="off" id="dthIssueForm">
                    <div class="mb-3">
                        <label class="form-label" for="allocation_date">Date</label>
                        <input type="date" id="allocation_date" name="allocation_date" class="form-control" value="<?= htmlspecialchars($_POST['allocation_date']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="staff_id">Select Staff</label>
                        <select id="staff_id" name="staff_id" class="form-select" required>
                            <option value="">Select Staff</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?= $staff['id'] ?>" <?= $_POST['staff_id']==$staff['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($staff['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch Total Available DTH Stock</label>
                        <input type="number" class="form-control" value="<?= number_format($total_available, 2, '.', '') ?>" readonly>
                        <div class="form-text">Already allocated today: <?= number_format($already_allocated, 2, '.', '') ?></div>
                        <?php if ($total_available == 0): ?>
                            <div class="alert alert-warning mt-2 mb-0 py-1 small">No DTH stock available. Please ensure today's DTH transaction is entered.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="quantity">Quantity to Allocate</label>
                        <input type="number" min="0.01" max="<?= max(0, number_format($remaining_to_allocate, 2, '.', '')) ?>" id="quantity" name="quantity" class="form-control" value="<?= htmlspecialchars($_POST['quantity']) ?>" required step="0.01" <?= $remaining_to_allocate <= 0 ? 'disabled' : '' ?>>
                        <div class="form-text">Max you can allocate: <?= number_format($remaining_to_allocate, 2, '.', '') ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"><?= htmlspecialchars($_POST['notes']) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100" <?= $remaining_to_allocate <= 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-check-circle"></i> Issue to Staff
                    </button>
                </form>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-link">Back to DTH Dashboard</a>
        </div>
    </div>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('quantity').addEventListener('input', function() {
        var avail = <?= json_encode($remaining_to_allocate) ?>;
        var val = parseFloat(this.value) || 0;
        if(val > avail) this.value = avail;
        if(val < 0) this.value = 0;
    });
    </script>
</body>
</html>