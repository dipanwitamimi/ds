<?php
session_start();
if (!isset($_SESSION['admin_user'])) {
    header('Location: login.php');
    exit;
}
require_once '../config/config.php';
require_once '../config/Database.php';

$user = $_SESSION['admin_user'];
$currentUser = $user['username'] ?? 'Admin';
$db = (new Database())->getConnection();
$errorMsg = '';
$successMsg = '';

/* ------- Branch List for Dropdowns ------- */
$branches = [];
try {
    $stmt = $db->query("SELECT id, branch_name FROM branches ORDER BY branch_name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $branches[] = $row;
} catch (PDOException $e) {}

function branchOptions($branches) {
    $opts = "";
    foreach ($branches as $br) {
        $opts .= "<option value=\"{$br['id']}\">" . htmlspecialchars($br['branch_name']) . "</option>";
    }
    return $opts;
}

$products = [
    'lapu' => [
        'label' => 'LAPU', 'icon' => 'wallet2', 'unit' => '₹',
        'db' => 'lapu', 'closing' => 'closing_amount', 'transfer_field' => 'cash_received',
        'proc_table' => 'lapu_supplier_purchases'
    ],
    'apb' => [
        'label' => 'APB', 'icon' => 'cash-stack', 'unit' => '',
        'db' => 'apb', 'closing' => 'closing_stock', 'transfer_field' => 'quantity_received',
        'proc_table' => 'apb_supplier_purchases'
    ],
    'sim' => [
        'label' => 'SIM', 'icon' => 'sim', 'unit' => '',
        'db' => 'sim_cards', 'closing' => 'closing_stock', 'transfer_field' => 'quantity_received',
        'proc_table' => 'sim_supplier_purchases'
    ],
    'dth' => [
        'label' => 'DTH', 'icon' => 'tv', 'unit' => '₹',
        'db' => 'dth', 'closing' => 'closing_amount', 'transfer_field' => 'amount_received',
        'proc_table' => 'dth_supplier_purchases'
    ],
];
// --- Admin Stock & Corporate Stats Calculations ---
function getAdminOpeningStock($db, $table, $closing_field) {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $stmt = $db->query("SELECT id FROM branches");
    $branches = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $opening_stock = 0;
    foreach ($branches as $branch_id) {
        $sql = "SELECT $closing_field FROM $table 
                WHERE branch_id=? AND DATE(transaction_date) <= ?
                ORDER BY transaction_date DESC, id DESC LIMIT 1";
        $stmt2 = $db->prepare($sql);
        $stmt2->execute([$branch_id, $yesterday]);
        $prev = $stmt2->fetchColumn();
        $opening_stock += $prev !== false ? $prev : 0;
    }
    return $opening_stock;
}
function getAdminNewPurchase($db, $product) {
    $today = date('Y-m-d');
    $table = $product . "_supplier_purchases";
    return floatval($db->query("SELECT IFNULL(SUM(quantity),0) FROM $table WHERE purchase_date='$today'")->fetchColumn());
}
function getBalanceTransfers($db, $product, $prod) {
    $today = date('Y-m-d');
    $sql = "SELECT b.branch_name, t.{$prod['transfer_field']} as qty
            FROM {$prod['db']} t LEFT JOIN branches b ON t.branch_id = b.id
            WHERE DATE(t.transaction_date)=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$today]);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    foreach ($list as $row) $total += $row['qty'];
    return ['list' => $list, 'total' => $total];
}
function getStatusBlock($db, $table, $fields_map, $date_field = 'transaction_date') {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $opening_balance = 0;
    $stmt = $db->query("SELECT id FROM branches");
    $branches = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($branches as $branch_id) {
        $sql = "SELECT {$fields_map['closing_balance']} FROM $table 
                WHERE branch_id=? AND DATE($date_field) <= ?
                ORDER BY $date_field DESC, id DESC LIMIT 1";
        $stmt2 = $db->prepare($sql);
        $stmt2->execute([$branch_id, $yesterday]);
        $prev = $stmt2->fetchColumn();
        $opening_balance += $prev !== false ? $prev : 0;
    }
    $sql = "SELECT 
        SUM({$fields_map['new_purchase']}) as new_purchase,
        SUM(" . (isset($fields_map['total_sale']) ? $fields_map['total_sale'] : '0') . ") as total_sale,
        SUM({$fields_map['closing_balance']}) as closing_balance
        FROM $table WHERE DATE($date_field) = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$today]);
    $sum = $stmt->fetch(PDO::FETCH_ASSOC);

    $new_purchase = $sum['new_purchase'] ?? 0;
    $total_sale = $sum['total_sale'] ?? 0;
    $closing_balance = $sum['closing_balance'] ?? 0;

    $total_available = $opening_balance + $new_purchase - $total_sale;

    return [
        'opening_balance' => $opening_balance,
        'new_purchase' => $new_purchase,
        'total_sale' => $total_sale,
        'total_available' => $total_available,
        'closing_balance' => $closing_balance,
    ];
}

$adminOpening = [];
$newPurchase = [];
$totalBalance = [];
$transfers = [];
$totalAvailable = [];
$status = [];
$distributorClosing = [];

foreach ($products as $key => $prod) {
    $adminOpening[$key] = getAdminOpeningStock($db, $prod['db'], $prod['closing']);
    $newPurchase[$key] = getAdminNewPurchase($db, $key);
    $totalBalance[$key] = $adminOpening[$key] + $newPurchase[$key];
    $transfers[$key] = getBalanceTransfers($db, $key, $prod);
    $totalAvailable[$key] = $totalBalance[$key] - $transfers[$key]['total'];
    if ($key == 'lapu' || $key == 'dth') {
        $status[$key] = getStatusBlock($db, $prod['db'], [
            'closing_balance' => $prod['closing'],
            'new_purchase' => $prod['transfer_field'],
            'total_sale' => 'total_spent'
        ]);
    } else {
        $status[$key] = getStatusBlock($db, $prod['db'], [
            'closing_balance' => $prod['closing'],
            'new_purchase' => $prod['transfer_field'],
            'total_sale' => 'total_sold'
        ]);
    }
    // Distributor Closing
    $distAvailable = $status[$key]['opening_balance'] + $newPurchase[$key] - $transfers[$key]['total'];
    $distributorClosing[$key] = $distAvailable - $status[$key]['closing_balance'];
}

// --- POST: handle procurement and branch transfer (with stock validation) ---
function getPrevClosing($db, $table, $branch_id, $field, $date_field = 'transaction_date') {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $sql = "SELECT $field FROM $table WHERE branch_id = ? AND DATE($date_field) <= ? ORDER BY $date_field DESC, id DESC LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$branch_id, $yesterday]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (float)$row[$field] : 0;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $date = date('Y-m-d');
        foreach ($products as $key => $prod) {
            // BALANCE TRANSFER
            if (isset($_POST["add_$key"])) {
                $field = $prod['transfer_field'];
                $amount = floatval($_POST["{$key}_" . ($key == 'lapu' || $key == 'dth' ? 'amount' : 'qty')]);
                $adminAvail = $status[$key]['opening_balance'] + $newPurchase[$key] - $transfers[$key]['total'];
                if ($amount > $adminAvail) {
                    $errorMsg = "Error: Balance transfer exceeds admin available stock ($amount > {$adminAvail})!";
                    break;
                }
                $branch_id_post = intval($_POST["{$key}_branch_id"]);
                $opening = getPrevClosing($db, $prod['db'], $branch_id_post, $prod['closing']);
                if ($key == 'lapu' || $key == 'dth') {
                    $stmt = $db->prepare("INSERT INTO {$prod['db']} (branch_id, transaction_date, {$field}, opening_balance, auto_amount, total_spent, total_available_fund, closing_amount, created_at) VALUES (?, ?, ?, ?, 0, 0, ?, ?, NOW())");
                    $total_available = $opening + $amount;
                    $stmt->execute([$branch_id_post, $date, $amount, $opening, $total_available, $total_available]);
                } else {
                    $stmt = $db->prepare("INSERT INTO {$prod['db']} (branch_id, transaction_date, {$field}, opening_stock, total_available, total_sold, closing_stock, created_at) VALUES (?, ?, ?, ?, ?, 0, ?, NOW())");
                    $total_available = $opening + $amount;
                    $stmt->execute([$branch_id_post, $date, $amount, $opening, $total_available, $total_available]);
                }
                header("Location: dashboard.php?success=$key");
                exit;
            }
            // PROCUREMENT
            if (isset($_POST["add_{$key}_procurement"])) {
                $amount = floatval($_POST["{$key}_procurement_" . ($key == 'lapu' || $key == 'dth' ? 'amount' : 'qty')]);
                $supplier = trim($_POST["{$key}_procurement_supplier"]);
                $stmt = $db->prepare("INSERT INTO {$prod['proc_table']} (purchase_date, quantity, supplier) VALUES (?, ?, ?)");
                $stmt->execute([$date, $amount, $supplier]);
                header("Location: dashboard.php?success={$key}_procure");
                exit;
            }
        }
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}
if (isset($_GET['success'])) {
    $type = htmlspecialchars($_GET['success']);
    $successMsgs = [
        'lapu' => "LAPU balance transfer added!",
        'apb'  => "APB balance transfer added!",
        'sim'  => "SIM balance transfer added!",
        'dth'  => "DTH balance transfer added!",
        'lapu_procure' => "LAPU procurement recorded!",
        'apb_procure'  => "APB procurement recorded!",
        'sim_procure'  => "SIM procurement recorded!",
        'dth_procure'  => "DTH procurement recorded!"
    ];
    $successMsg = $successMsgs[$type] ?? '';
}






/* ------ Recent Cash Deposits ------ */
$sql = "SELECT cd.created_at, b.branch_name, s.full_name AS staff_name, ba.bank_name, ba.account_number, cd.total_amount
    FROM cash_deposits cd
    LEFT JOIN branches b ON cd.branch_id = b.id
    LEFT JOIN staff s ON cd.staff_id = s.id
    LEFT JOIN bank_accounts ba ON cd.bank_account_id = ba.id
    ORDER BY cd.created_at DESC LIMIT 10";
$stmt = $db->query($sql);
$cash_deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?= htmlspecialchars(SITE_NAME) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Modern Bootstrap and Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f5f8fa;
        }
        .navbar {
            background: linear-gradient(90deg, #004e92 0%, #000428 100%);
            box-shadow: 0 2px 12px 0 rgba(44,62,80,0.10);
        }
        .navbar .navbar-brand span {
            font-weight: bold;
            font-size: 1.2rem;
            color: #fff;
            letter-spacing: 2px;
        }
        .navbar .nav-link {
            color: #d5d9e5!important;
            font-weight: 500;
            margin-right: 8px;
        }
        .navbar .nav-link.active, .navbar .nav-link:hover {
            color: #fff!important;
            border-bottom: 2px solid #fff;
        }
        .stat-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 4px 24px 0 rgba(0,0,0,0.08);
            color: #fff;
            position: relative;
            overflow: hidden;
            min-height: 205px;
        }
        .stat-card .icon-bg {
            position: absolute;
            right: 20px; top: 20px;
            opacity: 0.18;
            font-size: 4.5rem;
            pointer-events: none;
        }
        .stat-lapu { background: linear-gradient(135deg,#43cea2 0,#185a9d 100%);}
        .stat-apb  { background: linear-gradient(135deg,#f7971e 0,#ffd200 100%);}
        .stat-sim  { background: linear-gradient(135deg,#ff5858 0,#f09819 100%);}
        .stat-dth  { background: linear-gradient(135deg,#667eea 0,#764ba2 100%);}
        .stat-card h6 { font-size: 1.1rem; font-weight: 700;}
        .stat-card .amount { font-size:1.15rem; font-weight:500; }
        .stat-card .btn-sm { font-size:0.92rem; font-weight:600; }
        .modal-content { border-radius: 1rem; }
        @media (max-width: 991px) {.stat-card{min-height:180px;}}
		
		  /* Macho, powerful card styles */
    .machoman-card {
        border: none;
        border-radius: 18px;
        box-shadow: 0 8px 30px 0 rgba(0,0,0,.15);
        background: #181d23;
        color: #fff;
        min-height: 235px;
        position: relative;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .machoman-card:hover, .machoman-card:focus-within {
        transform: scale(1.03) rotate(-1deg);
        box-shadow: 0 16px 40px 0 rgba(0,0,0,.35);
        z-index: 2;
    }
    .machoman-title {
        font-size: 1.45rem;
        font-weight: 900;
        letter-spacing: 2px;
        margin-bottom: 1.25rem;
        text-shadow: 0 3px 12px #000;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .machoman-card .icon-bg {
        position: absolute;
        right: 18px; top: 17px;
        opacity: 0.12;
        font-size: 5.2rem;
        pointer-events: none;
        filter: drop-shadow(0 0 8px #000);
    }
    .stat-lapu { background: linear-gradient(120deg,#232526 0,#3a7bd5 100%);}
    .stat-apb  { background: linear-gradient(120deg,#42275a 0,#734b6d 100%);}
    .stat-sim  { background: linear-gradient(120deg,#0f2027 0,#2c5364 100%);}
    .stat-dth  { background: linear-gradient(120deg,#e65c00 0,#F9D423 100%);}
    .machoman-card .stat-row {
        font-size: 1.08rem;
        font-weight: 700;
        letter-spacing: 1px;
        padding: 0.28rem 0;
        border-bottom: 1px solid rgba(255,255,255,0.07);
        display: flex;
        justify-content: space-between;
    }
    .machoman-card .stat-row:last-child {border-bottom: none;}
    .machoman-card .amount {
        font-weight: 900;
        font-size: 1.17rem;
        letter-spacing: 1px;
    }
    .machoman-card .btn-macho {
        background: #fff;
        color: #181d23;
        font-weight: 700;
        border-radius: 1.5rem;
        box-shadow: 0 2px 12px #0004;
        letter-spacing: 1px;
        transition: background 0.18s, color 0.18s;
    }
    .machoman-card .btn-macho:hover {
        background: #0d6efd;
        color: #fff;
    }
	
	.powercard {
        border: none;
        border-radius: 26px;
        background: linear-gradient(150deg, #181d23 60%, #24243e 100%);
        color: #fff;
        min-height: 265px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 6px 36px 0 #000c, 0 2px 12px 0 #3334;
        transition: transform .18s cubic-bezier(.4,2.3,.3,1), box-shadow .18s;
        z-index: 1;
    }
    .powercard:before {
        content: '';
        display: block;
        position: absolute;
        left: -80px; top: -80px;
        width: 180px; height: 180px;
        background: radial-gradient(circle,rgba(255,255,255,0.08) 0,rgba(255,255,255,0.01) 90%);
        z-index: 0;
    }
    .powercard:hover, .powercard:focus-within {
        transform: scale(1.03) rotate(-1deg);
        box-shadow: 0 16px 44px 0 #000e, 0 8px 20px #222a;
        z-index: 10;
    }
    .powercard .icon-circle {
        position: absolute;
        top: 24px;
        right: 24px;
        background: rgba(255,255,255,0.09);
        border-radius: 50%;
        width: 64px;
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 35px 0 #0ff8,0 2px 8px #0009;
        font-size: 2.5rem;
        z-index: 1;
        filter: drop-shadow(0 0 12px #0ff8);
        transition: box-shadow .14s;
    }
    .powercard.stat-lapu .icon-circle { background: linear-gradient(135deg,#34e89e 0,#0f3443 100%); color: #fff;}
    .powercard.stat-apb  .icon-circle { background: linear-gradient(135deg,#ffd200 0,#f7971e 100%); color: #7c5c00;}
    .powercard.stat-sim  .icon-circle { background: linear-gradient(135deg,#f7971e 0,#f44336 100%); color: #fff;}
    .powercard.stat-dth  .icon-circle { background: linear-gradient(135deg,#667eea 0,#764ba2 100%); color: #fff;}
    .powercard .machoman-title {
        font-size: 1.35rem;
        font-weight: 900;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-bottom: 1.30rem;
        margin-top: .25rem;
        text-shadow: 0 3px 16px #000b, 0 1px 0 #fff3;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }
    .powercard .stat-row {
        font-size: 1.17rem;
        font-weight: 800;
        letter-spacing: 0.5px;
        padding: 0.25rem 0 0.25rem 0;
        border-bottom: 1px solid rgba(255,255,255,.13);
        display: flex;
        justify-content: space-between;
    }
    .powercard .stat-row:last-child { border-bottom: none; }
    .powercard .amount {
        font-weight: 900;
        font-size: 1.18rem;
        letter-spacing: 1px;
        text-shadow: 0 1px 3px #000b;
    }
    .powercard .btn-macho {
        background: linear-gradient(90deg,#0ff 0,#36f 100%);
        color: #181d23;
        font-weight: 800;
        border-radius: 2rem;
        letter-spacing: 1px;
        margin-top: 1.2rem;
        box-shadow: 0 2px 14px #0ff4, 0 1px 2px #0002;
        border: none;
        transition: background .17s, color .17s;
    }
    .powercard .btn-macho:hover {
        background: linear-gradient(90deg,#36f 0,#0ff 100%);
        color: #fff;
    }
	
	 /* SEXXBOMB Powerful Minimalist Cards */
    .sexxbomb-stats-row { --icon-size: 3.1rem; }
    .sexxbomb-card {
        border: none;
        border-radius: 22px;
        background: #111215;
        color: #fff;
        box-shadow: 0 8px 32px 0 #000b, 0 2px 12px #2227;
        min-height: 220px;
        display: flex; flex-direction: column; align-items: stretch;
        position: relative;
        overflow: visible;
        transition: box-shadow .13s, transform .13s;
    }
    .sexxbomb-card:hover, .sexxbomb-card:focus-within {
        box-shadow: 0 14px 48px 0 #000d, 0 4px 16px #000a;
        transform: translateY(-7px) scale(1.022);
        z-index: 1;
    }
    .sexxbomb-iconbox {
        width: var(--icon-size);
        height: var(--icon-size);
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 2.1rem;
        margin-bottom: 14px;
        margin-top: -18px;
        box-shadow: 0 0 0 3px #fff2, 0 2px 8px #0005;
        background: linear-gradient(135deg, #2FF6FA 10%, #1B1C2B 100%);
    }
    .sexxbomb-card.stat-lapu   .sexxbomb-iconbox { background: linear-gradient(120deg,#2ff6fa 10%,#1b1c2b 100%);}
    .sexxbomb-card.stat-apb    .sexxbomb-iconbox { background: linear-gradient(120deg,#fff200 20%,#c6b200 100%);}
    .sexxbomb-card.stat-sim    .sexxbomb-iconbox { background: linear-gradient(120deg,#ff8e53 0%,#fe6b8b 100%);}
    .sexxbomb-card.stat-dth    .sexxbomb-iconbox { background: linear-gradient(120deg,#76e5ff 0%,#2979ff 100%);}
    .sexxbomb-title {
        font-size: 1.25rem;
        font-weight: 900;
        letter-spacing: 2px;
        margin-bottom: .5rem;
        text-transform: uppercase;
        color: #fff;
        text-shadow: 0 2px 16px #0004, 0 1px 0 #fff2;
    }
    .sexxbomb-stats {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        margin-bottom: .6rem;
    }
    .sexxbomb-row {
        display: flex; justify-content: space-between; align-items: center;
        font-size: 1.05rem;
        font-weight: 700;
        padding: 0.13rem 0;
        border-bottom: 1px dashed #fff1;
    }
    .sexxbomb-row:last-child { border-bottom: none; }
    .sexxbomb-label { color: #b0b3bc; font-weight: 500; letter-spacing: 1.5px;}
    .sexxbomb-amount { font-weight: 900; font-size: 1.1rem; letter-spacing: 1px; }
    .stat-lapu   .sexxbomb-amount { color: #2ff6fa; }
    .stat-apb    .sexxbomb-amount { color: #ffe149; }
    .stat-sim    .sexxbomb-amount { color: #ff8e53; }
    .stat-dth    .sexxbomb-amount { color: #76e5ff; }
    .sexxbomb-card .btn-sexxbomb {
        background: linear-gradient(90deg,#2ff6fa 0,#00b3b3 100%);
        color: #181d23;
        font-weight: 800;
        border-radius: 2rem;
        margin-top: .9rem;
        letter-spacing: 1px;
        box-shadow: 0 2px 12px #2ff6fa33, 0 1px 2px #0002;
        border: none;
        transition: background .16s, color .16s;
    }
    .sexxbomb-card .btn-sexxbomb:hover {
        background: linear-gradient(90deg,#00b3b3 0,#2ff6fa 100%);
        color: #fff;
    }
	
	 .natural-table-card {
        border: none;
        border-radius: 22px;
        background: #fff;
        box-shadow: 0 6px 32px 0 #a1b7e033, 0 1.5px 8px 0 #b1b8bb22;
        overflow: hidden;
    }
    .natural-table-card .card-header {
        background: linear-gradient(90deg, #a1c4fd 0%, #c2e9fb 100%);
        border-bottom: none;
        padding: 1rem 1.5rem;
        border-radius: 22px 22px 0 0;
    }
    .natural-table-card h5 {
        font-weight: 800;
        letter-spacing: 1.5px;
        color: #183153;
        margin-bottom: 0;
    }
    .natural-table-card .table {
        margin-bottom: 0;
        background: transparent;
    }
    .natural-table-card .table thead tr {
        background: #f2f6fa;
    }
    .natural-table-card .table th {
        color: #4a5a6a;
        font-weight: 700;
        background: #f2f6fa;
        border-top: none;
        border-bottom: 2px solid #e0e8f3;
        letter-spacing: .4px;
        font-size: 1.01rem;
        vertical-align: middle;
    }
    .natural-table-card .table td {
        background: #fff;
        border-bottom: 1.5px solid #f2f6fa;
        color: #273145;
        vertical-align: middle;
        font-size: 1.02rem;
        font-weight: 600;
    }
    .natural-table-card .table tr:last-child td {
        border-bottom: none;
    }
    .natural-table-card .table tbody tr {
        transition: background 0.14s;
    }
    .natural-table-card .table tbody tr:hover {
        background: #eaf6ff;
    }
    .natural-table-card .price-cell {
        color: #12744b;
        font-weight: 900;
        font-size: 1.08rem;
        letter-spacing: 1px;
    }
    .natural-table-card .text-muted {
        font-style: italic;
        font-size: 1.01rem;
        color: #a1a9b3 !important;
    }
	
	.dramatic-summary-card {
        border: none;
        border-radius: 24px;
        background: linear-gradient(120deg, #f9fafc 60%, #e0ecff 100%);
        box-shadow: 0 6px 34px 0 #1e2e501c, 0 1.5px 8px 0 #1e2e5022;
        overflow: hidden;
        margin-bottom: 2rem;
        transition: box-shadow .18s, transform .15s;
    }
    .dramatic-summary-card:hover, .dramatic-summary-card:focus-within {
        box-shadow: 0 16px 54px 0 #38517233, 0 4px 18px #38517218;
        transform: scale(1.017);
        z-index: 2;
    }
    .dramatic-summary-card .card-header {
        background: linear-gradient(90deg, #5c7aff 0%, #c2e9fb 100%);
        border-bottom: none;
        padding: 1rem 1.8rem;
        border-radius: 24px 24px 0 0;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 10px #5c7aff22;
    }
    .dramatic-summary-card h6 {
        font-weight: 900;
        letter-spacing: 2px;
        color: #fff;
        margin-bottom: 0;
        text-shadow: 0 2px 16px #38517255;
        font-size: 1.17rem;
        display: flex; align-items: center; gap: 10px;
    }
    .dramatic-summary-card .card-header form label,
    .dramatic-summary-card .card-header form input[type="date"] {
        font-size: 1rem;
        font-weight: 600;
        color: #184068;
    }
    .dramatic-summary-card .card-header form .btn-primary {
        background: linear-gradient(90deg,#5c7aff 0,#2ff6fa 100%);
        border: none;
        font-weight: 700;
        border-radius: 2rem;
        padding-left: 18px; padding-right: 18px;
        letter-spacing: .5px;
        box-shadow: 0 2px 10px #5c7aff33;
    }
    .dramatic-summary-card .table {
        margin-bottom: 0;
        background: transparent;
    }
    .dramatic-summary-card .table thead tr {
        background: #e7efff;
    }
    .dramatic-summary-card .table th {
        color: #36507c;
        font-weight: 800;
        background: #e7efff!important;
        border-top: none;
        border-bottom: 2px solid #d2e3ff;
        letter-spacing: .6px;
        font-size: 1.03rem;
        vertical-align: middle;
    }
    .dramatic-summary-card .table td {
        background: #fff;
        border-bottom: 1.5px solid #e7efff;
        color: #273145;
        vertical-align: middle;
        font-size: 1.06rem;
        font-weight: 600;
        transition: background 0.14s;
    }
    .dramatic-summary-card .table tr:last-child td {
        border-bottom: none;
    }
    .dramatic-summary-card .table tbody tr:hover {
        background: #fffbe6;
        box-shadow: 0 2px 18px #ffd20033;
    }
    .dramatic-summary-card .dramatic-currency {
        color: #5c7aff;
        font-weight: 900;
        font-size: 1.12rem;
        letter-spacing: 1px;
    }
    .dramatic-summary-card .text-muted {
        font-style: italic;
        font-size: 1.01rem;
        color: #a1a9b3 !important;
    }
	 .dramatic-summary-card {
        border: none;
        border-radius: 18px;
        background: linear-gradient(120deg, #f9fafc 60%, #e0ecff 100%);
        box-shadow: 0 6px 22px 0 #1e2e501c, 0 1.5px 8px 0 #1e2e5022;
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .dramatic-summary-card .card-header {
        background: linear-gradient(90deg, #5c7aff 0%, #c2e9fb 100%);
        border-bottom: none;
        padding: .8rem 1.3rem;
        border-radius: 18px 18px 0 0;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 10px #5c7aff22;
    }
    .dramatic-summary-card h6 {
        font-weight: 900;
        letter-spacing: 2px;
        color: #fff;
        margin-bottom: 0;
        text-shadow: 0 2px 16px #38517255;
        font-size: 1.05rem;
        display: flex; align-items: center; gap: 10px;
    }
    .dramatic-summary-card .card-header form label,
    .dramatic-summary-card .card-header form input[type="date"] {
        font-size: .94rem;
        font-weight: 600;
        color: #184068;
    }
    .dramatic-summary-card .card-header form .btn-primary {
        background: linear-gradient(90deg,#5c7aff 0,#2ff6fa 100%);
        border: none;
        font-weight: 700;
        border-radius: 2rem;
        padding-left: 15px; padding-right: 15px;
        letter-spacing: .5px;
        box-shadow: 0 2px 10px #5c7aff33;
        font-size: 0.93rem;
    }
    .dramatic-summary-card .table {
        margin-bottom: 0;
        background: transparent;
    }
    .dramatic-summary-card .table th, .dramatic-summary-card .table td {
        white-space: nowrap;
        padding: 0.38rem 0.44rem !important;
        font-size: 0.98rem;
        border: none;
        vertical-align: middle;
    }
    .dramatic-summary-card .table th {
        color: #36507c;
        font-weight: 800;
        background: #e7efff!important;
        letter-spacing: .6px;
        border-bottom: 2px solid #d2e3ff !important;
    }
    .dramatic-summary-card .table td {
        background: #fff;
        color: #273145;
        font-weight: 700;
        border-bottom: 1px solid #e7efff !important;
        font-size: 0.99rem;
        text-align: right;
    }
    .dramatic-summary-card .table td:first-child,
    .dramatic-summary-card .table th:first-child {
        text-align: left;
    }
    .dramatic-summary-card .table tr:last-child td {
        border-bottom: none !important;
    }
    .dramatic-summary-card .table tbody tr:hover {
        background: #eaf6ff;
    }
    .dramatic-summary-card .dramatic-currency {
        color: #5c7aff;
        font-weight: 900;
    }
    .dramatic-summary-card .text-muted {
        font-style: italic;
        font-size: 0.98rem;
        color: #a1a9b3 !important;
    }
	 .corporate-summary-card {
        border: none;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 4px 18px 0 #a1b7e033, 0 1px 6px 0 #b1b8bb22;
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .corporate-summary-card .card-header {
        background: #f4f7fb;
        border-bottom: 1px solid #e6eaf1;
        padding: .7rem 1.1rem;
        border-radius: 16px 16px 0 0;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 0.7rem;
    }
    .corporate-summary-card h6 {
        font-weight: 800;
        color: #213957;
        margin-bottom: 0;
        font-size: 1.11rem;
        letter-spacing: .5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .corporate-summary-card .card-header form {
        margin-bottom: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .corporate-summary-card .card-header label {
        font-size: .94rem;
        font-weight: 600;
        color: #3b5274;
        margin-bottom: 0;
    }
    .corporate-summary-card .card-header input[type="date"] {
        font-size: .97rem;
        padding: 0.18rem 0.45rem;
        height: 2rem;
        border-radius: .6rem;
        border: 1px solid #dbe5eb;
        background: #f7fafc;
        color: #2a4567;
    }
    .corporate-summary-card .card-header .btn-primary {
        background: #3867d6;
        border: none;
        font-weight: 700;
        border-radius: 1.1rem;
        padding: 0.17rem 0.98rem;
        font-size: 0.93rem;
        box-shadow: 0 1.5px 8px #3867d622;
    }
    .corporate-summary-card .table {
        margin-bottom: 0;
        background: transparent;
    }
    .corporate-summary-card .table th, 
    .corporate-summary-card .table td {
        white-space: nowrap;
        padding: 0.32rem 0.42rem !important;
        font-size: 0.96rem;
        border: none;
        vertical-align: middle;
    }
    .corporate-summary-card .table th {
        color: #36507c;
        font-weight: 700;
        background: #f4f7fb!important;
        border-bottom: 1.5px solid #e6eaf1 !important;
        letter-spacing: .45px;
        text-align: center;
    }
    .corporate-summary-card .table td {
        background: #fff;
        color: #273145;
        font-weight: 600;
        border-bottom: 1px solid #f4f7fb !important;
        font-size: 0.96rem;
        text-align: center;
    }
    .corporate-summary-card .table td:first-child,
    .corporate-summary-card .table th:first-child {
        text-align: left;
    }
    .corporate-summary-card .table tr:last-child td {
        border-bottom: none !important;
    }
    .corporate-summary-card .table tbody tr:hover {
        background: #f0f5fa;
    }
    .corporate-summary-card .corporate-currency {
        color: #3867d6;
        font-weight: 900;
    }
    .corporate-summary-card .text-muted {
        font-style: italic;
        font-size: 0.98rem;
        color: #a1a9b3 !important;
    }
    @media (max-width: 600px) {
        .corporate-summary-card .table th, 
        .corporate-summary-card .table td {
            font-size: 0.88rem;
            padding: 0.22rem 0.16rem !important;
        }
        .corporate-summary-card .card-header { flex-direction: column; align-items: stretch;}
    }
	.corporate-summary-card {
    border: none;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 6px 24px 0 #1e2e5022;
    overflow: hidden;
    margin-bottom: 2rem;
}
.corporate-summary-card .card-header {
    background: #f7faff;
    border-bottom: 1px solid #e8edf3;
    padding: 0.7rem 1.1rem;
    border-radius: 14px 14px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 54px;
}
.corporate-summary-card .card-header h6 {
    font-weight: 700;
    letter-spacing: 1.5px;
    color: #2b395b;
    margin-bottom: 0;
    font-size: 1.07rem;
    display: flex;
    align-items: center;
    gap: 9px;
}
.corporate-summary-card .card-header form {
    margin-bottom: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.corporate-summary-card .card-header form label {
    font-size: .96rem;
    color: #495a74;
    font-weight: 600;
    margin-bottom: 0;
}
.corporate-summary-card .card-header form input[type="date"] {
    font-size: .96rem;
    min-width: 125px;
    padding: 2px 10px 2px 10px;
    height: 32px;
    border-radius: 5px;
    border: 1px solid #e8edf3;
    background: #f9fbfd;
    color: #253146;
}
.corporate-summary-card .card-header form .btn-primary {
    padding: 3px 17px;
    border-radius: 18px;
    font-size: .97rem;
    font-weight: 700;
    border: none;
    background: linear-gradient(93deg, #3c71ff 0%, #42e3fa 100%);
    box-shadow: 0 2px 10px #5c7aff12;
}

.corporate-summary-card .table {
    margin-bottom: 0;
    background: transparent;
    table-layout: fixed;
}
.corporate-summary-card .table th, .corporate-summary-card .table td {
    white-space: nowrap;
    padding: 0.39rem 0.33rem !important;
    font-size: 0.97rem;
    border: none;
    vertical-align: middle;
    text-align: center;
}
.corporate-summary-card .table th {
    color: #2b395b;
    font-weight: 700;
    background: #f7faff !important;
    letter-spacing: .3px;
    border-bottom: 1.5px solid #e8edf3 !important;
    text-align: center;
}
.corporate-summary-card .table td {
    background: #fff;
    color: #253146;
    font-weight: 600;
    border-bottom: 1px solid #f2f7fa !important;
    font-size: 0.97rem;
}
.corporate-summary-card .table td:first-child,
.corporate-summary-card .table th:first-child {
    text-align: left;
    width: 16%;
}
.corporate-summary-card .table td:last-child,
.corporate-summary-card .table th:last-child {
    width: 10%;
}
.corporate-summary-card .table tr:last-child td {
    border-bottom: none !important;
}
.corporate-summary-card .table tbody tr:hover {
    background: #f6faff;
}
.corporate-summary-card .corporate-currency {
    color: #2059d4;
    font-weight: 900;
}
.corporate-summary-card .text-muted {
    font-style: italic;
    font-size: 0.97rem;
    color: #b5bbc1 !important;
}
.corporate-stats-row {
    --icon-size: 2.7rem;
    margin-bottom: 1.8rem;
}
.corporate-card {
    border: none;
    border-radius: 13px;
    background: #fff;
    box-shadow: 0 4px 16px 0 #a1b7e022;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    position: relative;
    transition: box-shadow .14s, transform .12s;
}
.corporate-card:hover {
    box-shadow: 0 8px 32px 0 #a1b7e044;
    transform: translateY(-3px) scale(1.012);
    z-index: 1;
}
.corporate-card .iconbox {
    width: var(--icon-size);
    height: var(--icon-size);
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    margin-bottom: .45rem;
    margin-top: .3rem;
    background: linear-gradient(120deg,#eaf1fd 0%,#e0ecff 100%);
    color: #255fc5;
    box-shadow: 0 1px 5px #c8d7ef44;
}
.corporate-card .stat-title {
    font-size: 1.08rem;
    font-weight: 700;
    letter-spacing: 1.6px;
    margin-bottom: .5rem;
    text-transform: uppercase;
    color: #2b395b;
}
.corporate-card .stats {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: 0.08rem;
    margin-bottom: .25rem;
}
.corporate-card .stat-row {
    display: flex; justify-content: space-between; align-items: center;
    font-size: .98rem;
    font-weight: 600;
    color: #253146;
    padding: 0.07rem 0;
}
.corporate-card .stat-label { color: #8994ad; font-weight: 500; letter-spacing: 1px;}
.corporate-card .stat-amount {
    font-weight: 700;
    font-size: 1.03rem;
    color: #2059d4;
    letter-spacing: .6px;
}
.corporate-card .btn-corporate {
    background: linear-gradient(90deg,#3c71ff 0,#42e3fa 100%);
    color: #fff;
    font-weight: 700;
    border-radius: 16px;
    box-shadow: 0 2px 12px #5c7aff22, 0 1px 2px #0002;
    border: none;
    margin-top: .7rem;
    font-size: .97rem;
    letter-spacing: .5px;
    transition: background .14s, color .14s;
}
.corporate-card .btn-corporate:hover {
    background: linear-gradient(90deg,#2059d4 0,#42e3fa 100%);
    color: #fff;
}
.corporate-stats-row {
    --icon-size: 2.7rem;
    margin-bottom: 1.8rem;
}
.corporate-card {
    border: none;
    border-radius: 13px;
    background: #fff;
    box-shadow: 0 4px 16px 0 #a1b7e022;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    position: relative;
    transition: box-shadow .14s, transform .12s;
}
.corporate-card:hover {
    box-shadow: 0 8px 32px 0 #a1b7e044;
    transform: translateY(-3px) scale(1.012);
    z-index: 1;
}
.corporate-card .iconbox {
    width: var(--icon-size);
    height: var(--icon-size);
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    margin-bottom: .45rem;
    margin-top: .3rem;
    background: linear-gradient(120deg,#eaf1fd 0%,#e0ecff 100%);
    color: #255fc5;
    box-shadow: 0 1px 5px #c8d7ef44;
}
.corporate-card .stat-title {
    font-size: 1.08rem;
    font-weight: 700;
    letter-spacing: 1.6px;
    margin-bottom: .5rem;
    text-transform: uppercase;
    color: #2b395b;
}
.corporate-card .stats {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: 0.08rem;
    margin-bottom: .25rem;
}
.corporate-card .stat-row {
    display: flex; justify-content: space-between; align-items: center;
    font-size: .98rem;
    font-weight: 600;
    color: #253146;
    padding: 0.07rem 0;
}
.corporate-card .stat-label { color: #8994ad; font-weight: 500; letter-spacing: 1px;}
.corporate-card .stat-amount {
    font-weight: 700;
    font-size: 1.03rem;
    color: #2059d4;
    letter-spacing: .6px;
}
.corporate-card .btn-corporate {
    background: linear-gradient(90deg,#3c71ff 0,#42e3fa 100%);
    color: #fff;
    font-weight: 700;
    border-radius: 16px;
    box-shadow: 0 2px 12px #5c7aff22, 0 1px 2px #0002;
    border: none;
    margin-top: .7rem;
    font-size: .97rem;
    letter-spacing: .5px;
    transition: background .14s, color .14s;
}
.corporate-card .btn-corporate:hover {
    background: linear-gradient(90deg,#2059d4 0,#42e3fa 100%);
    color: #fff;
}
.seductive-stats-row {
    margin-bottom: 2.2rem;
}
.seductive-stats-card {
    border: none;
    border-radius: 24px;
    background: linear-gradient(135deg, #2e143e 0%, #6b416c 60%, #ff5ca8 100%);
    color: #fff;
    box-shadow: 0 8px 32px 0 #4e194e55, 0 3px 10px #1113;
    min-height: 140px;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    transition: box-shadow .16s, transform .13s;
    overflow: hidden;
    padding: 1.1rem 0.7rem 1rem 0.7rem;
    z-index: 1;
}
.seductive-stats-card:before {
    content: "";
    position: absolute;
    bottom: -50px; right: -50px;
    width: 120px; height: 120px;
    background: radial-gradient(circle at 60% 60%, #ffb6d5 0 55%, transparent 80%);
    opacity: 0.27;
    z-index: 0;
}
.seductive-stats-card:after {
    content: "";
    position: absolute;
    top: -25px; left: -30px;
    width: 60px; height: 60px;
    background: radial-gradient(circle at 35% 35%, #fff7 0 60%, transparent 100%);
    opacity: 0.41;
    z-index: 0;
}
.seductive-stats-card h6 {
    font-size: 1.04rem;
    font-weight: 900;
    letter-spacing: 2px;
    color: #fff;
    margin-bottom: 0.4rem;
    margin-top: .1rem;
    text-shadow: 0 1px 8px #ff5ca888, 0 1px #fff2;
    z-index: 1;
}
.seductive-stats-card h3 {
    font-size: 2.25rem;
    font-weight: 900;
    margin-bottom: 0.35rem;
    color: #fff;
    letter-spacing: 2px;
    text-shadow: 0 4px 30px #ff5ca9cc, 0 1px #fff5;
    background: linear-gradient(90deg,#ffd6e7 0%,#ff5ca8 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.seductive-stats-card small {
    color: #ffe3f4 !important;
    font-size: 0.97rem;
    font-weight: 600;
    letter-spacing: 1px;
    z-index: 1;
    text-shadow: 0 2px 6px #0002;
}
.jhuma-stats-row {
    margin-bottom: 2.4rem;
}
.jhuma-card {
    border: none;
    border-radius: 18px;
    background: linear-gradient(120deg, #f8f4ff 0, #fbe6f7 55%, #f5e1e8 100%);
    color: #48174d;
    box-shadow: 0 8px 36px 0 #a469b533, 0 2px 8px #9d3a7040;
    min-height: 150px;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    transition: box-shadow .18s, transform .14s;
    overflow: hidden;
    padding: 1.2rem .7rem 1.1rem .7rem;
    z-index: 1;
}
.jhuma-card:before {
    content: "";
    position: absolute;
    bottom: -40px; right: -40px;
    width: 120px; height: 120px;
    background: radial-gradient(circle at 60% 60%, #f8b8eb 0 55%, transparent 80%);
    opacity: 0.16;
    z-index: 0;
}
.jhuma-card:after {
    content: "";
    position: absolute;
    top: -20px; left: -25px;
    width: 60px; height: 60px;
    background: radial-gradient(circle at 35% 35%, #ffeaf3 0 60%, transparent 100%);
    opacity: 0.19;
    z-index: 0;
}
.jhuma-card h6 {
    font-size: 1.08rem;
    font-weight: 900;
    letter-spacing: 2px;
    color: #b2147d;
    margin-bottom: 0.36rem;
    margin-top: .1rem;
    text-shadow: 0 2px 10px #fae1f7, 0 1px #fff2;
    z-index: 1;
    background: linear-gradient(90deg,#c52c8e 0%,#ff7ea6 60%,#e6b9f4 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.jhuma-card h3 {
    font-size: 2.3rem;
    font-weight: 900;
    margin-bottom: 0.28rem;
    letter-spacing: 2px;
    background: linear-gradient(87deg,#e956a1 0%,#ffb7de 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 2px 24px #f5c5ed70;
    z-index: 1;
}
.jhuma-card small {
    color: #b2147d !important;
    font-size: 0.98rem;
    font-weight: 700;
    letter-spacing: .8px;
    z-index: 1;
    background: linear-gradient(90deg,#b2147d 0,#ffb7de 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 1px 7px #ffd2eb55;
}
.corpnat-stats-row {
    margin-bottom: 2.2rem;
}
.corpnat-card {
    border: none;
    border-radius: 15px;
    background: linear-gradient(120deg, #f8fafc 0, #e8f0fa 100%);
    color: #234;
    box-shadow: 0 4px 20px 0 #b5c4d533, 0 1.5px 8px #b1b8bb18;
    min-height: 140px;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    transition: box-shadow .14s, transform .11s;
    overflow: hidden;
    padding: 1.1rem 0.7rem 1rem 0.7rem;
    z-index: 1;
}
.corpnat-card:hover {
    box-shadow: 0 12px 38px 0 #b5c4d566;
    transform: translateY(-2px) scale(1.01);
}
.corpnat-card h6 {
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: 1.3px;
    color: #295998;
    margin-bottom: 0.28rem;
    margin-top: .1rem;
    text-shadow: 0 2px 12px #e5eaf7, 0 1px #fff2;
    z-index: 1;
}
.corpnat-card h3 {
    font-size: 2.1rem;
    font-weight: 900;
    margin-bottom: 0.22rem;
    color: #295998;
    letter-spacing: 2px;
    text-shadow: 0 3px 14px #b7c8d9, 0 1px #fff5;
    z-index: 1;
}
.corpnat-card small {
    color: #5177a6 !important;
    font-size: 0.98rem;
    font-weight: 600;
    letter-spacing: .6px;
    z-index: 1;
    text-shadow: 0 1px 5px #dae4f744;
}
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <img src="../assets/images/logo.png" alt="Logo" style="height:36px;" class="me-2">
            <span><?= htmlspecialchars(SITE_NAME) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link<?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?' active':'' ?>" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="branch/"><i class="bi bi-building"></i> Branches</a></li>
                <li class="nav-item"><a class="nav-link" href="staff/"><i class="bi bi-people"></i> Staff</a></li>
                <li class="nav-item"><a class="nav-link" href="transactions/"><i class="bi bi-currency-exchange"></i> Transactions</a></li>
                <li class="nav-item"><a class="nav-link" href="bank/add.php"><i class="bi bi-bank"></i> Bank Accounts</a></li>
                <li class="nav-item"><a class="nav-link" href="reports/"><i class="bi bi-file-earmark-text"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
            </ul>
            <span class="navbar-text text-white d-none d-lg-inline">
                <i class="bi bi-person-badge"></i> <?= htmlspecialchars($currentUser); ?>
            </span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm ms-3">Logout</a>
        </div>
    </div>
</nav>

<div class="container-lg py-3">
    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success"><?= $successMsg; ?></div>
    <?php endif; ?>
    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger"><?= $errorMsg; ?></div>
    <?php endif; ?>

  
  <!-- Corporate Stats Row -->
    <div class="row g-3 corporate-stats-row">
        <?php foreach ($products as $key => $prod): ?>
        <div class="col-md-3">
            <div class="card corporate-card">
                <div class="card-body">
                    <div class="iconbox"><i class="bi bi-<?= $prod['icon'] ?>"></i></div>
                    <div class="stat-title"><?= $prod['label'] ?></div>
                    <div class="stats">
                        <div class="stat-row"><span class="stat-label">Opening</span><span class="stat-amount"><?= $prod['unit'] ?><?= number_format($status[$key]['opening_balance'],2); ?></span></div>
                        <div class="stat-row"><span class="stat-label">Balance Transfer</span><span class="stat-amount"><?= $prod['unit'] ?><?= number_format($status[$key]['new_purchase'],2); ?></span></div>
                        <div class="stat-row"><span class="stat-label">Available</span><span class="stat-amount"><?= $prod['unit'] ?><?= number_format($status[$key]['total_available'],2); ?></span></div>
                        <div class="stat-row"><span class="stat-label">Total Sale</span><span class="stat-amount"><?= $prod['unit'] ?><?= number_format($status[$key]['total_sale'],2); ?></span></div>
                        <div class="stat-row"><span class="stat-label">Closing</span><span class="stat-amount"><?= $prod['unit'] ?><?= number_format($status[$key]['closing_balance'],2); ?></span></div>
                    </div>
                    <button class="btn btn-corporate btn-sm w-100" data-bs-toggle="modal" data-bs-target="#<?= $key ?>Modal"><i class="bi bi-plus-circle"></i> Balance Transfer</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

	
 <h4 class="mb-3">Distributor Closing</h4>
    <div class="row g-4 mb-4">
        <?php foreach ($products as $key => $prod): ?>
        <div class="col-md-3">
            <div class="card shadow">
                <div class="card-header bg-warning fw-bold"><?= $prod['label'] ?> Distributor Closing</div>
                <div class="card-body">
                    <strong><?= $prod['unit'] ?><?= number_format($distributorClosing[$key],2) ?></strong>
                    <div class="small text-muted mt-1">Admin Available Stock minus Today's Branch Closing</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

     <h4 class="mb-3">Admin Stock</h4>
<div class="row g-4 mb-4">
    <?php foreach ($products as $key => $prod): ?>
    <?php
        $admin_opening = $status[$key]['opening_balance'];
        $admin_new_purchase = $newPurchase[$key];
        $admin_total_balance = $admin_opening + $admin_new_purchase;
        $admin_transfer = $transfers[$key]['total'];
        $admin_available = $admin_total_balance - $admin_transfer;
        $branch_closing = $status[$key]['closing_balance'];
        $distributor_closing = $admin_available - $branch_closing;
    ?>
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="bi bi-<?= $prod['icon'] ?>"></i> <?= $prod['label'] ?> Admin Stock
            </div>
            <div class="card-body">
                <div class="mb-2"><strong>Admin Opening Stock:</strong>
                    <span class="stock-value"><?= $prod['unit'] ?><?= number_format($admin_opening,2) ?></span>
                </div>
                <div class="mb-2"><strong>New Purchase:</strong>
                    <span class="stock-value"><?= $prod['unit'] ?><?= number_format($admin_new_purchase,2) ?></span>
                </div>
                <div class="mb-2"><strong>Total Balance:</strong>
                    <span class="stock-value"><?= $prod['unit'] ?><?= number_format($admin_total_balance,2) ?></span>
                </div>
                <div class="mb-2"><strong>Total Balance Transfer:</strong>
                    <span class="stock-value"><?= $prod['unit'] ?><?= number_format($admin_transfer,2) ?></span>
                </div>
                <div class="mb-2">
                  <strong>Branches & Qty Transferred:</strong>
                  <table class="table table-sm table-bordered">
                    <thead><tr><th>Branch</th><th>Qty</th></tr></thead>
                    <tbody>
                      <?php foreach ($transfers[$key]['list'] as $row): ?>
                      <tr>
                        <td><?= htmlspecialchars($row['branch_name']) ?></td>
                        <td><span class="stock-value"><?= $prod['unit'] ?><?= number_format($row['qty'],2) ?></span></td>
                      </tr>
                      <?php endforeach ?>
                      <?php if (empty($transfers[$key]['list'])): ?>
                      <tr><td colspan="2" class="text-muted text-center">No transfers today.</td></tr>
                      <?php endif ?>
                    </tbody>
                  </table>
                </div>
                <div class="mb-2"><strong>Total Available Stock:</strong>
                    <span class="stock-value"><?= $prod['unit'] ?><?= number_format($admin_available,2) ?></span>
                </div>
                <div class="mb-2"><strong>Distributor Closing:</strong>
                    <span class="badge bg-warning text-dark stock-badge"><?= $prod['unit'] ?><?= number_format($distributor_closing,2) ?></span>
                </div>
                <div class="mb-2">
                    <strong>Branch Closing:</strong>
                    <span class="badge bg-info text-dark stock-badge"><?= $prod['unit'] ?><?= number_format($branch_closing,2) ?></span>
                </div>
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#<?= $key ?>ProcurementModal">
                    <i class="bi bi-plus-circle"></i> Add New Purchase
                </button>
                <button class="btn btn-outline-success btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#<?= $key ?>Modal">
                    <i class="bi bi-arrow-down-up"></i> Balance Transfer
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
    <!-- Cash Deposit Table -->
   <div class="card mb-4 natural-table-card">
    <div class="card-header">
        <h5><i class="bi bi-cash-coin me-2"></i>Recent Cash Deposits</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Branch Name</th>
                        <th>Staff Name</th>
                        <th>Bank Name &amp; Account No.</th>
                        <th>Total Cash Deposit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cash_deposits)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">No cash deposit records found.</td>
                    </tr>
                    <?php else: foreach ($cash_deposits as $cd): ?>
                    <tr>
                        <td><?= date('d-m-Y H:i', strtotime($cd['created_at'])) ?></td>
                        <td><?= htmlspecialchars($cd['branch_name']) ?></td>
                        <td><?= htmlspecialchars($cd['staff_name']) ?></td>
                        <td><?= htmlspecialchars($cd['bank_name'] . " / " . $cd['account_number']) ?></td>
                        <td class="price-cell">₹<?= number_format($cd['total_amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

    <!-- LAPU Modal -->
    <div class="modal fade" id="lapuModal" tabindex="-1" aria-labelledby="lapuModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="post">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">LAPU Balance Transfer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label>Branch</label>
                    <select name="lapu_branch_id" class="form-control" required>
                        <?= branchOptions($branches); ?>
                    </select>
                    <label class="mt-2">Amount</label>
                    <input type="number" name="lapu_amount" step="0.01" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_lapu" class="btn btn-primary">Transfer</button>
                </div>
            </div>
        </form>
      </div>
    </div>
    <!-- APB Modal -->
    <div class="modal fade" id="apbModal" tabindex="-1" aria-labelledby="apbModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="post">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">APB Balance Transfer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label>Branch</label>
                    <select name="apb_branch_id" class="form-control" required>
                        <?= branchOptions($branches); ?>
                    </select>
                    <label class="mt-2">Quantity</label>
                    <input type="number" name="apb_qty" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_apb" class="btn btn-primary">Transfer</button>
                </div>
            </div>
        </form>
      </div>
    </div>
    <!-- SIM Modal -->
    <div class="modal fade" id="simModal" tabindex="-1" aria-labelledby="simModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="post">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">SIM Balance Transfer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label>Branch</label>
                    <select name="sim_branch_id" class="form-control" required>
                        <?= branchOptions($branches); ?>
                    </select>
                    <label class="mt-2">Quantity</label>
                    <input type="number" name="sim_qty" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_sim" class="btn btn-primary">Transfer</button>
                </div>
            </div>
        </form>
      </div>
    </div>
    <!-- DTH Modal -->
    <div class="modal fade" id="dthModal" tabindex="-1" aria-labelledby="dthModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="post">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">DTH Balance Transfer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label>Branch</label>
                    <select name="dth_branch_id" class="form-control" required>
                        <?= branchOptions($branches); ?>
                    </select>
                    <label class="mt-2">Amount</label>
                    <input type="number" name="dth_amount" step="0.01" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_dth" class="btn btn-primary">Transfer</button>
                </div>
            </div>
        </form>
      </div>
    </div>

    <!-- Modals for New Purchase (Procurement from Supplier) -->
     <!-- LAPU Procurement Modal -->
    <div class="modal fade" id="lapuProcurementModal" tabindex="-1" aria-labelledby="lapuProcurementModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="post">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">LAPU New Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label>Amount</label>
                    <input type="number" name="lapu_procurement_amount" step="0.01" class="form-control" required>
                    <label class="mt-2">Supplier</label>
                    <input type="text" name="lapu_procurement_supplier" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_lapu_procurement" class="btn btn-primary">Add</button>
                </div>
            </div>
        </form>
      </div>
    </div>
    <!-- APB Procurement Modal -->
    <div class="modal fade" id="apbProcurementModal" tabindex="-1" aria-labelledby="apbProcurementModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="post">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">APB New Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label>Quantity</label>
                    <input type="number" name="apb_procurement_qty" class="form-control" required>
                    <label class="mt-2">Supplier</label>
                    <input type="text" name="apb_procurement_supplier" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_apb_procurement" class="btn btn-primary">Add</button>
                </div>
            </div>
        </form>
      </div>
    </div>
    <!-- SIM Procurement Modal -->
    <div class="modal fade" id="simProcurementModal" tabindex="-1" aria-labelledby="simProcurementModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="post">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">SIM New Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label>Quantity</label>
                    <input type="number" name="sim_procurement_qty" class="form-control" required>
                    <label class="mt-2">Supplier</label>
                    <input type="text" name="sim_procurement_supplier" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_sim_procurement" class="btn btn-primary">Add</button>
                </div>
            </div>
        </form>
      </div>
    </div>
    <!-- DTH Procurement Modal -->
    <div class="modal fade" id="dthProcurementModal" tabindex="-1" aria-labelledby="dthProcurementModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="post">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">DTH New Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label>Amount</label>
                    <input type="number" name="dth_procurement_amount" step="0.01" class="form-control" required>
                    <label class="mt-2">Supplier</label>
                    <input type="text" name="dth_procurement_supplier" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_dth_procurement" class="btn btn-primary">Add</button>
                </div>
            </div>
        </form>
      </div>
    </div>



    <!-- APB Summary -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card corporate-summary-card">
            <div class="card-header">
                <h6><i class="bi bi-cash-stack"></i> APB Summary</h6>
                <form method="get">
                    <label for="apb_summary_date" class="form-label mb-0">Date:</label>
                    <input type="date" name="apb_summary_date" id="apb_summary_date" class="form-control"
                        value="<?= htmlspecialchars($_GET['apb_summary_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>">
                    <button class="btn btn-sm btn-primary" type="submit">Show</button>
                </form>
            </div>
            <div class="card-body p-0">
                <?php
                $apb_summary_date = $_GET['apb_summary_date'] ?? date('Y-m-d');
                try {
                    $stmt = $db->prepare("
                        SELECT 
                            b.branch_name,
                            a.transaction_date,
                            IFNULL(s.full_name, CONCAT('ID#', a.staff_id)) AS staff_name,
                            IFNULL(a.opening_stock, 0) AS opening_balance,
                            IFNULL(a.quantity_received, 0) AS new_purchase,
                            0 AS auto_amount,
                            IFNULL(a.total_available, 0) AS total_available,
                            IFNULL(a.total_sold, 0) AS total_sale,
                            IFNULL(a.closing_stock, 0) AS closing_balance
                        FROM branches b
                        LEFT JOIN apb a ON b.id = a.branch_id AND DATE(a.transaction_date) = ?
                        LEFT JOIN staff s ON a.staff_id = s.id
                        ORDER BY b.branch_name
                    ");
                    $stmt->execute([$apb_summary_date]);
                ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Date</th>
                                <th>Staff</th>
                                <th>Op</th>
                                <th>New</th>
                                <th>Auto</th>
                                <th>Avail</th>
                                <th>Sale</th>
                                <th>Close</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $hasRows = false;
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $hasRows = true;
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['transaction_date'] ?? $apb_summary_date) . "</td>";
                                echo "<td>" . htmlspecialchars($row['staff_name'] ?? '-') . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['opening_balance']) . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['new_purchase']) . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['auto_amount']) . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['total_available']) . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['total_sale']) . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['closing_balance']) . "</td>";
                                echo "</tr>";
                            }
                            if (!$hasRows) {
                                echo "<tr><td colspan='9' class='text-center text-muted'>No data available.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
                } catch(PDOException $e) {
                    echo "<div class='text-danger'>Data unavailable</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- SIM CARDS Summary -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card corporate-summary-card">
            <div class="card-header">
                <h6><i class="bi bi-sim"></i> SIM CARDS Summary</h6>
                <form method="get">
                    <label for="sim_summary_date" class="form-label mb-0">Date:</label>
                    <input type="date" name="sim_summary_date" id="sim_summary_date" class="form-control"
                        value="<?= htmlspecialchars($_GET['sim_summary_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>">
                    <button class="btn btn-sm btn-primary" type="submit">Show</button>
                </form>
            </div>
            <div class="card-body p-0">
                <?php
                $sim_summary_date = $_GET['sim_summary_date'] ?? date('Y-m-d');
                try {
                    $stmt = $db->prepare("
                        SELECT 
                            b.branch_name,
                            sc.transaction_date,
                            IFNULL(s.full_name, CONCAT('ID#', sc.staff_id)) AS staff_name,
                            IFNULL(sc.opening_stock, 0) AS opening_balance,
                            IFNULL(sc.quantity_received, 0) AS new_purchase,
                            IFNULL(sc.auto_quantity, 0) AS auto_amount,
                            IFNULL(sc.total_available, 0) AS total_available,
                            IFNULL(sc.total_sold, 0) AS total_sale,
                            IFNULL(sc.closing_stock, 0) AS closing_balance
                        FROM branches b
                        LEFT JOIN sim_cards sc ON b.id = sc.branch_id AND DATE(sc.transaction_date) = ?
                        LEFT JOIN staff s ON sc.staff_id = s.id
                        ORDER BY b.branch_name
                    ");
                    $stmt->execute([$sim_summary_date]);
                ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Date</th>
                                <th>Staff</th>
                                <th>Op</th>
                                <th>New</th>
                                <th>Auto</th>
                                <th>Avail</th>
                                <th>Sale</th>
                                <th>Close</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $hasRows = false;
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $hasRows = true;
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['transaction_date'] ?? $sim_summary_date) . "</td>";
                                echo "<td>" . htmlspecialchars($row['staff_name'] ?? '-') . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['opening_balance']) . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['new_purchase']) . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['auto_amount']) . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['total_available']) . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['total_sale']) . "</td>";
                                echo "<td class='corporate-currency'>" . number_format($row['closing_balance']) . "</td>";
                                echo "</tr>";
                            }
                            if (!$hasRows) {
                                echo "<tr><td colspan='9' class='text-center text-muted'>No data available.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
                } catch(PDOException $e) {
                    echo "<div class='text-danger'>Data unavailable</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- DTH Summary -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card corporate-summary-card">
            <div class="card-header">
                <h6><i class="bi bi-tv"></i> DTH Summary</h6>
                <form method="get">
                    <label for="dth_summary_date" class="form-label mb-0">Date:</label>
                    <input type="date" name="dth_summary_date" id="dth_summary_date" class="form-control"
                        value="<?= htmlspecialchars($_GET['dth_summary_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>">
                    <button class="btn btn-sm btn-primary" type="submit">Show</button>
                </form>
            </div>
            <div class="card-body p-0">
                <?php
                $dth_summary_date = $_GET['dth_summary_date'] ?? date('Y-m-d');
                try {
                    $stmt = $db->prepare("
                        SELECT 
                            b.branch_name,
                            d.transaction_date,
                            IFNULL(s.full_name, CONCAT('ID#', d.staff_id)) AS staff_name,
                            IFNULL(d.opening_balance, 0) AS opening_balance,
                            IFNULL(d.amount_received, 0) AS new_purchase,
                            IFNULL(d.auto_amount, 0) AS auto_amount,
                            IFNULL(d.total_available_fund, 0) AS total_available,
                            IFNULL(d.total_spent, 0) AS total_sale,
                            IFNULL(d.closing_amount, 0) AS closing_balance
                        FROM branches b
                        LEFT JOIN dth d ON b.id = d.branch_id AND DATE(d.transaction_date) = ?
                        LEFT JOIN staff s ON d.staff_id = s.id
                        ORDER BY b.branch_name
                    ");
                    $stmt->execute([$dth_summary_date]);
                ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Date</th>
                                <th>Staff</th>
                                <th>Op</th>
                                <th>New</th>
                                <th>Auto</th>
                                <th>Avail</th>
                                <th>Sale</th>
                                <th>Close</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $hasRows = false;
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $hasRows = true;
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['transaction_date'] ?? $dth_summary_date) . "</td>";
                                echo "<td>" . htmlspecialchars($row['staff_name'] ?? '-') . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['opening_balance'], 2) . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['new_purchase'], 2) . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['auto_amount'], 2) . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['total_available'], 2) . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['total_sale'], 2) . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['closing_balance'], 2) . "</td>";
                                echo "</tr>";
                            }
                            if (!$hasRows) {
                                echo "<tr><td colspan='9' class='text-center text-muted'>No data available.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
                } catch(PDOException $e) {
                    echo "<div class='text-danger'>Data unavailable</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- LAPU Summary -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card corporate-summary-card">
            <div class="card-header">
                <h6><i class="bi bi-wallet2"></i> LAPU Summary</h6>
                <form method="get">
                    <label for="lapu_summary_date" class="form-label mb-0">Date:</label>
                    <input type="date" name="lapu_summary_date" id="lapu_summary_date" class="form-control"
                        value="<?= htmlspecialchars($_GET['lapu_summary_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>">
                    <button class="btn btn-sm btn-primary" type="submit">Show</button>
                </form>
            </div>
            <div class="card-body p-0">
                <?php
                $lapu_summary_date = $_GET['lapu_summary_date'] ?? date('Y-m-d');
                try {
                    $stmt = $db->prepare("
                        SELECT 
                            b.branch_name,
                            l.transaction_date,
                            IFNULL(s.full_name, CONCAT('ID#', l.staff_id)) AS staff_name,
                            IFNULL(l.opening_balance, 0) AS opening_balance,
                            IFNULL(l.cash_received, 0) AS new_purchase,
                            IFNULL(l.auto_amount, 0) AS auto_amount,
                            IFNULL(l.total_available_fund, 0) AS total_available,
                            IFNULL(l.total_spent, 0) AS total_sale,
                            IFNULL(l.closing_amount, 0) AS closing_balance
                        FROM branches b
                        LEFT JOIN lapu l ON b.id = l.branch_id AND DATE(l.transaction_date) = ?
                        LEFT JOIN staff s ON l.staff_id = s.id
                        ORDER BY b.branch_name
                    ");
                    $stmt->execute([$lapu_summary_date]);
                ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Date</th>
                                <th>Staff</th>
                                <th>Op</th>
                                <th>New</th>
                                <th>Auto</th>
                                <th>Avail</th>
                                <th>Sale</th>
                                <th>Close</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $hasRows = false;
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $hasRows = true;
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['transaction_date'] ?? $lapu_summary_date) . "</td>";
                                echo "<td>" . htmlspecialchars($row['staff_name'] ?? '-') . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['opening_balance'], 2) . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['new_purchase'], 2) . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['auto_amount'], 2) . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['total_available'], 2) . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['total_sale'], 2) . "</td>";
                                echo "<td class='corporate-currency'>₹" . number_format($row['closing_balance'], 2) . "</td>";
                                echo "</tr>";
                            }
                            if (!$hasRows) {
                                echo "<tr><td colspan='9' class='text-center text-muted'>No data available.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
                } catch(PDOException $e) {
                    echo "<div class='text-danger'>Data unavailable</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>
  <div class="row g-4 corpnat-stats-row">
    <!-- Branch Stats -->
    <div class="col-md-3">
        <div class="corpnat-card">
            <h6>Branches</h6>
            <?php
            try {
                $stmt = $db->query("SELECT COUNT(*) as total FROM branches");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<h3>" . ($result['total'] ?? 0) . "</h3>";
            } catch(PDOException $e) {
                echo "<h3>0</h3>";
            }
            ?>
            <small>Total Branches</small>
        </div>
    </div>
    <!-- Staff Stats -->
    <div class="col-md-3">
        <div class="corpnat-card">
            <h6>Staff</h6>
            <?php
            try {
                $stmt = $db->query("SELECT COUNT(*) as total FROM staff");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<h3>" . ($result['total'] ?? 0) . "</h3>";
            } catch(PDOException $e) {
                echo "<h3>0</h3>";
            }
            ?>
            <small>Total Staff</small>
        </div>
    </div>
    <!-- Today's Transactions -->
    <div class="col-md-3">
        <div class="corpnat-card">
            <h6>Today's Transactions</h6>
            <?php
            try {
                $stmt = $db->query("SELECT COUNT(*) as total FROM transactions WHERE DATE(transaction_date) = CURDATE()");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<h3>" . ($result['total'] ?? 0) . "</h3>";
            } catch(PDOException $e) {
                echo "<h3>0</h3>";
            }
            ?>
            <small>Total Today</small>
        </div>
    </div>
    <!-- Bank Accounts -->
    <div class="col-md-3">
        <div class="corpnat-card">
            <h6>Bank Accounts</h6>
            <?php
            try {
                $stmt = $db->query("SELECT COUNT(*) as total FROM bank_accounts");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<h3>" . ($result['total'] ?? 0) . "</h3>";
            } catch(PDOException $e) {
                echo "<h3>0</h3>";
            }
            ?>
            <small>Active Accounts</small>
        </div>
    </div>
</div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="branch/add.php" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-building"></i><br>
                                Add Branch
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="branch/add-user.php" class="btn btn-success btn-lg w-100">
                                <i class="bi bi-person-plus-fill"></i><br>
                                Add Branch User
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="staff/add.php" class="btn btn-info btn-lg w-100">
                                <i class="bi bi-people"></i><br>
                                Add Staff
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="bank/add.php" class="btn btn-warning btn-lg w-100">
                                <i class="bi bi-bank"></i><br>
                                Add Bank Account
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="reports/" class="btn btn-danger btn-lg w-100">
                                <i class="bi bi-file-text"></i><br>
                                View Reports
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="branch/users.php" class="btn btn-secondary btn-lg w-100">
                                <i class="bi bi-people-fill"></i><br>
                                Manage Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

      <!-- Recent Data -->
    <div class="row">
        <!-- Recent Transactions -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Transactions</h5>
                    <a href="transactions/" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Branch</th>
                                    <th>Staff</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $db->query("
                                        SELECT t.*, b.branch_name, s.full_name as staff_name
                                        FROM transactions t
                                        LEFT JOIN branches b ON t.branch_id = b.id
                                        LEFT JOIN staff s ON t.staff_id = s.id
                                        ORDER BY t.created_at DESC LIMIT 5
                                    ");
                                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    if (empty($rows)) {
                                        echo "<tr><td colspan='5' class='text-center'>No transactions found</td></tr>";
                                    } else {
                                        foreach ($rows as $row) {
                                            echo "<tr>";
                                            // Use created_at if set, else transaction_date
                                            $date = $row['created_at'] ?? $row['transaction_date'] ?? '';
                                            echo "<td>" . ($date ? date('Y-m-d', strtotime($date)) : '-') . "</td>";
                                            echo "<td>" . htmlspecialchars($row['branch_name'] ?? '-') . "</td>";
                                            echo "<td>" . htmlspecialchars($row['staff_name'] ?? '-') . "</td>";
                                            // Show credit - debit (can be negative for outflow)
                                            $credit = (float)($row['credit'] ?? 0);
                                            $debit = (float)($row['debit'] ?? 0);
                                            echo "<td>₹" . number_format($credit - $debit, 2) . "</td>";
                                            echo "<td><span class='badge bg-success'>Completed</span></td>";
                                            echo "</tr>";
                                        }
                                    }
                                } catch(PDOException $e) {
                                    echo "<tr><td colspan='5' class='text-center'>Error fetching transactions</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- System Status -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">System Status</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Database Connection</span>
                                <span class="badge bg-success">Connected</span>
                            </div>
                        </li>
                        <li class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Last Update</span>
                                <span class="text-muted"><?php echo date('H:i:s'); ?></span>
                            </div>
                        </li>
                        <li class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>System Status</span>
                                <span class="badge bg-success">Online</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>
<footer class="footer mt-auto py-3 bg-light">
    <div class="container">
        <span class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</span>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo SITE_URL; ?>assets/js/jquery.min.js"></script>
<script src="<?php echo SITE_URL; ?>assets/js/custom.js"></script>
</body>
</html>