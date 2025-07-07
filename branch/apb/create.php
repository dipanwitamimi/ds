<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$user = $_SESSION['branch_user'];
$branch_id = $user['branch_id'];
$username = $user['username'] ?? '';
$page_title = 'New APB Issue to Staff';

$db = new Database();
$conn = $db->getConnection();

// Fetch staff list for dropdown (only for this branch)
$stmt = $conn->prepare("SELECT id, full_name FROM staff WHERE branch_id = ? ORDER BY full_name ASC");
$stmt->execute([$branch_id]);
$staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get yesterday's closing stock as opening
$yesterday = date('Y-m-d', strtotime('-1 day'));
$stmt = $conn->prepare("SELECT closing_stock FROM apb WHERE branch_id = ? AND DATE(transaction_date) <= ? ORDER BY transaction_date DESC, id DESC LIMIT 1");
$stmt->execute([$branch_id, $yesterday]);
$opening_stock = $stmt->fetchColumn();
$opening_stock = $opening_stock !== false ? (int)$opening_stock : 0;

// Today's APB transaction for this branch (if any)
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM apb WHERE branch_id = ? AND DATE(transaction_date) = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$branch_id, $today]);
$today_apb = $stmt->fetch(PDO::FETCH_ASSOC);

// Use today's transaction values or default to 0
$received = $today_apb ? (int)$today_apb['quantity_received'] : 0;
$auto = $today_apb ? (int)$today_apb['auto_quantity'] : 0;
$total_available = $opening_stock + $received + $auto;

// Default values for repopulating form on error
$default = [
    'staff_id' => '',
    'allocation_date' => $today,
    'quantity' => '',
    'notes' => ''
];
foreach ($default as $k => $v) {
    if (!isset($_POST[$k])) $_POST[$k] = $v;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New APB Issue to Staff</title>
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
            <h2>APB: Issue to Staff</h2>
            <div class="small text-muted"><?= htmlspecialchars($username) ?> (Branch <?= htmlspecialchars($branch_id) ?>)</div>
        </div>
        <div class="card shadow">
            <div class="card-body">
                <form action="process_issue.php" method="post" autocomplete="off" id="apbIssueForm">
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
                        <label class="form-label">Branch Total Available APB Stock</label>
                        <input type="number" class="form-control" value="<?= number_format($total_available, 0) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="quantity">Quantity to Allocate</label>
                        <input type="number" min="0" max="<?= $total_available ?>" id="quantity" name="quantity" class="form-control" value="<?= htmlspecialchars($_POST['quantity']) ?>" required>
                        <div class="form-text">Max: <?= number_format($total_available, 0) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"><?= htmlspecialchars($_POST['notes']) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-circle"></i> Issue to Staff
                    </button>
                </form>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-link">Back to APB Dashboard</a>
        </div>
    </div>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('quantity').addEventListener('input', function() {
        var avail = <?= $total_available ?>;
        var val = parseInt(this.value) || 0;
        if(val > avail) this.value = avail;
        if(val < 0) this.value = 0;
    });
    </script>
</body>
</html>