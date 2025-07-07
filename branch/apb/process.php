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
$transaction_date   = $_POST['transaction_date'] ?? '';
$staff_id           = $_POST['staff_id'] ?? null;
$bank_account_id    = $_POST['bank_account_id'] ?? null;
$quantity_received  = intval($_POST['quantity_received'] ?? 0);
$auto_quantity      = intval($_POST['auto_quantity'] ?? 0);
$total_sold         = intval($_POST['total_sold'] ?? 0);

if (!$transaction_date || !$staff_id) {
    // Incomplete data, redirect back with error
    $_SESSION['error'] = 'Please fill all required fields.';
    header('Location: create.php');
    exit();
}

// Get last transaction's closing_stock for this branch (for opening_stock)
$stmt = $conn->prepare("SELECT closing_stock FROM apb WHERE branch_id = ? ORDER BY created_at DESC, id DESC LIMIT 1");
$stmt->execute([$branch_id]);
$last_closing = $stmt->fetchColumn();
$opening_stock = $last_closing !== false ? intval($last_closing) : 0;

// Calculate total_available
$total_available = $opening_stock + $quantity_received + $auto_quantity;

// Calculate closing_stock
if (isset($_POST['closing_stock']) && $_POST['closing_stock'] !== '') {
    $closing_stock = intval($_POST['closing_stock']);
} else {
    $closing_stock = $total_available - $total_sold;
}

// Insert the new APB transaction
$stmt = $conn->prepare("
    INSERT INTO apb (
        branch_id, staff_id, bank_account_id, transaction_date,
        quantity_received, auto_quantity, opening_stock, total_available,
        total_sold, closing_stock, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
    )
");
$ok = $stmt->execute([
    $branch_id,
    $staff_id,
    $bank_account_id,
    $transaction_date,
    $quantity_received,
    $auto_quantity,
    $opening_stock,
    $total_available,
    $total_sold,
    $closing_stock
]);

if ($ok) {
    $_SESSION['success'] = 'APB transaction added successfully.';
    header('Location: index.php');
    exit();
} else {
    $_SESSION['error'] = 'Failed to add APB transaction. Please try again.';
    header('Location: create.php');
    exit();
}