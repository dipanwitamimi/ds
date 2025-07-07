<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$user = $_SESSION['branch_user'];
$branch_id = $user['branch_id'];
$username = $user['username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: create.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Validate and sanitize input
$transaction_date = $_POST['transaction_date'] ?? '';
$staff_id = $_POST['staff_id'] ?? null;
$bank_account_id = $_POST['bank_account_id'] ?? null;
$amount_received = floatval($_POST['amount_received'] ?? 0);
$auto_amount = floatval($_POST['auto_amount'] ?? 0);
$total_spent = floatval($_POST['total_spent'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if (!$transaction_date || !$staff_id || !$bank_account_id) {
    // Incomplete data, redirect back with error
    $_SESSION['error'] = 'Please fill all required fields.';
    header('Location: create.php');
    exit();
}

// Get last transaction's closing amount for this branch (for opening_balance & total_available_fund)
$stmt = $conn->prepare("SELECT closing_amount FROM dth WHERE branch_id = ? ORDER BY created_at DESC, id DESC LIMIT 1");
$stmt->execute([$branch_id]);
$last_closing = $stmt->fetchColumn();
$opening_balance = $last_closing !== false ? floatval($last_closing) : 0.00;

// Calculate total_available_fund
$total_available_fund = $opening_balance + $amount_received + $auto_amount;

// The closing amount is: opening_balance + amount_received + auto_amount - total_spent
if (isset($_POST['closing_amount']) && $_POST['closing_amount'] !== '') {
    $closing_amount = floatval($_POST['closing_amount']);
} else {
    $closing_amount = $total_available_fund - $total_spent;
}

// Insert the new DTH transaction
$stmt = $conn->prepare("
    INSERT INTO dth (
        branch_id, staff_id, bank_account_id, transaction_date, 
        amount_received, opening_balance, auto_amount, total_spent, 
        total_available_fund, closing_amount, notes, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
    )
");
$ok = $stmt->execute([
    $branch_id,
    $staff_id,
    $bank_account_id,
    $transaction_date,
    $amount_received,
    $opening_balance,
    $auto_amount,
    $total_spent,
    $total_available_fund,
    $closing_amount,
    $notes
]);

if ($ok) {
    $_SESSION['success'] = 'DTH transaction added successfully.';
    header('Location: index.php');
    exit();
} else {
    $_SESSION['error'] = 'Failed to add DTH transaction. Please try again.';
    header('Location: create.php');
    exit();
}
?>