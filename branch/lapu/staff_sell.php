<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$user = $_SESSION['branch_user'];
$branch_id = $user['branch_id'];
$username = $user['username'] ?? '';

$db = new Database();
$conn = $db->getConnection();

// Get staff list for this branch
$stmt = $conn->prepare("SELECT id, full_name FROM staff WHERE branch_id = ? ORDER BY full_name ASC");
$stmt->execute([$branch_id]);
$staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$receive = $opening = $total_balance = $sell_amount = $actual_value = $closing = 0.00;
$staff_id = '';
$sell_date = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = intval($_POST['staff_id'] ?? 0);
    $sell_date = $_POST['sell_date'] ?? date('Y-m-d');
    $sell_amount = floatval($_POST['sell'] ?? 0);

    if ($staff_id <= 0 || $sell_amount < 0) {
        $message = '<div class="alert alert-danger">Please select staff and enter a valid sell amount.</div>';
    } else {
        // Get total quantity issued to staff up to and including sell_date
        $stmt = $conn->prepare("SELECT IFNULL(SUM(quantity),0) FROM lapu_staff_allocations WHERE branch_id = ? AND staff_id = ? AND allocation_date <= ?");
        $stmt->execute([$branch_id, $staff_id, $sell_date]);
        $receive = floatval($stmt->fetchColumn());

        // Get staff's last closing up to before this date
        $stmt = $conn->prepare("SELECT closing FROM staff_lapu_sales WHERE branch_id = ? AND staff_id = ? AND sell_date < ? ORDER BY sell_date DESC, id DESC LIMIT 1");
        $stmt->execute([$branch_id, $staff_id, $sell_date]);
        $last_closing = $stmt->fetchColumn();
        $opening = $last_closing !== false ? floatval($last_closing) : 0.00;

        $total_balance = $receive + $opening;

        $actual_value = $sell_amount + ($sell_amount * 2.9 / 100);
        if ($actual_value > $total_balance) {
            $message = '<div class="alert alert-danger">Sell amount (including 2.9%) cannot exceed total available balance.</div>';
        } else {
            $closing = $total_balance - $actual_value;
            // Insert staff sale
            $stmt = $conn->prepare("INSERT INTO staff_lapu_sales (branch_id, staff_id, sell_date, receive, opening, total_balance, sell, actual_value, closing) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$branch_id, $staff_id, $sell_date, $receive, $opening, $total_balance, $sell_amount, $actual_value, $closing]);
            $message = '<div class="alert alert-success">Sale recorded! Staff: ' . htmlspecialchars($staff_id) . ', Sold: ' . number_format($sell_amount, 2) . ', Closing: ' . number_format($closing, 2) . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Sell</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/bootstrap-icons.css">
    <style>.card { max-width: 520px; margin: 0 auto; }</style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="mb-3 text-center">
            <h2>Staff Sell (LAPU)</h2>
        </div>
        <?= $message ?>
        <div class="card shadow">
            <div class="card-body">
                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label" for="sell_date">Date</label>
                        <input type="date" id="sell_date" name="sell_date" class="form-control" value="<?= htmlspecialchars($sell_date) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="staff_id">Select Staff</label>
                        <select id="staff_id" name="staff_id" class="form-select" required>
                            <option value="">Select Staff</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?= $staff['id'] ?>" <?= ($staff_id == $staff['id'])?'selected':'' ?>>
                                    <?= htmlspecialchars($staff['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Display computed values if form submitted -->
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $staff_id): ?>
                    <div class="mb-3">
                        <label class="form-label">Receive (Issued by Branch)</label>
                        <input type="number" class="form-control" value="<?= number_format($receive, 2) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Opening Balance (Staff's last closing)</label>
                        <input type="number" class="form-control" value="<?= number_format($opening, 2) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Balance</label>
                        <input type="number" class="form-control" value="<?= number_format($total_balance, 2) ?>" readonly>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label" for="sell">Sell (input amount)</label>
                        <input type="number" step="0.01" min="0" id="sell" name="sell" class="form-control" value="<?= htmlspecialchars($_POST['sell'] ?? '') ?>" required>
                        <div class="form-text">Actual value = Sell + 2.9% of Sell. Sell cannot exceed total balance.</div>
                    </div>
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $staff_id): ?>
                    <div class="mb-3">
                        <label class="form-label">Actual Value (Sell + 2.9%)</label>
                        <input type="number" class="form-control" value="<?= number_format($actual_value, 2) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Closing</label>
                        <input type="number" class="form-control" value="<?= number_format($closing, 2) ?>" readonly>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-circle"></i> Record Sale
                    </button>
                </form>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-link">Back to LAPU Dashboard</a>
        </div>
    </div>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>