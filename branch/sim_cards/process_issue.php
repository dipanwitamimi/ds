<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

checkBranchAuth();

$user = $_SESSION['branch_user'];
$branch_id = $user['branch_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: create.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$staff_id = intval($_POST['staff_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 0);
$allocation_date = $_POST['allocation_date'] ?? date('Y-m-d');
$notes = trim($_POST['notes'] ?? '');

if ($staff_id <= 0 || $quantity <= 0) {
    header("Location: create.php?error=invalid_input");
    exit;
}

// Get yesterday's closing as opening stock
$yesterday = date('Y-m-d', strtotime('-1 day', strtotime($allocation_date)));
$stmt = $conn->prepare("SELECT closing_stock FROM sim_cards WHERE branch_id = ? AND DATE(transaction_date) <= ? ORDER BY transaction_date DESC, id DESC LIMIT 1");
$stmt->execute([$branch_id, $yesterday]);
$opening_stock = $stmt->fetchColumn();
$opening_stock = $opening_stock !== false ? (int)$opening_stock : 0;

// Get today's sim_cards transaction (for that date)
$stmt = $conn->prepare("SELECT * FROM sim_cards WHERE branch_id = ? AND DATE(transaction_date) = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$branch_id, $allocation_date]);
$today_sim = $stmt->fetch(PDO::FETCH_ASSOC);

$received = $today_sim ? (int)$today_sim['quantity_received'] : 0;
$auto = $today_sim ? (int)$today_sim['auto_quantity'] : 0;
$total_available = $opening_stock + $received + $auto;

// Get today's already allocated
$stmt = $conn->prepare("SELECT IFNULL(SUM(quantity),0) FROM sim_cards_staff_allocations WHERE branch_id = ? AND allocation_date = ?");
$stmt->execute([$branch_id, $allocation_date]);
$already_allocated = (int)$stmt->fetchColumn();

if ($quantity + $already_allocated > $total_available) {
    header("Location: create.php?error=insufficient_stock");
    exit;
}

// Insert allocation
$stmt = $conn->prepare("INSERT INTO sim_cards_staff_allocations (branch_id, staff_id, allocation_date, quantity, notes) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$branch_id, $staff_id, $allocation_date, $quantity, $notes]);

header("Location: index.php?success=allocation");
exit;