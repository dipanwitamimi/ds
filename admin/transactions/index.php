<?php
session_start();
require_once '../../config/database.php';

$pageTitle = "Transaction Management";

$date = $_GET['date'] ?? '';
$branch_id = $_GET['branch_id'] ?? '';
$staff_id = $_GET['staff_id'] ?? '';
$account_number = $_GET['account_number'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch branch and staff lists (no status filter)
    $branchList = $conn->query("SELECT id, branch_name FROM branches ORDER BY branch_name")->fetchAll(PDO::FETCH_ASSOC);
    $staffList = $conn->query("SELECT id, full_name FROM staff ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch bank accounts
    $accountList = $conn->query("SELECT id, account_number, bank_name, IFNULL(current_balance,0) as current_balance FROM bank_accounts ORDER BY bank_name, account_number")->fetchAll(PDO::FETCH_ASSOC);

    // Map account_number => id for filtering
    $accountNumberToId = [];
    foreach ($accountList as $acc) {
        $accountNumberToId[$acc['account_number']] = $acc['id'];
    }
    $selected_bank_account_id = ($account_number && isset($accountNumberToId[$account_number])) ? $accountNumberToId[$account_number] : null;

    // Cash deposit summary for each account (filtered)
    $depositWhere = "WHERE 1=1";
    $depositParams = [];
    if ($selected_bank_account_id) {
        $depositWhere .= " AND cd.bank_account_id = ?";
        $depositParams[] = $selected_bank_account_id;
    }
    if ($branch_id) {
        $depositWhere .= " AND cd.branch_id = ?";
        $depositParams[] = $branch_id;
    }
    if ($staff_id) {
        $depositWhere .= " AND cd.staff_id = ?";
        $depositParams[] = $staff_id;
    }
    if ($date) {
        $depositWhere .= " AND cd.deposit_date = ?";
        $depositParams[] = $date;
    }
    $depositStmt = $conn->prepare("
        SELECT cd.bank_account_id, ba.account_number, ba.bank_name, SUM(cd.total_amount) as total_deposit
        FROM cash_deposits cd
        LEFT JOIN bank_accounts ba ON cd.bank_account_id = ba.id
        $depositWhere
        GROUP BY cd.bank_account_id, ba.account_number, ba.bank_name
    ");
    $depositStmt->execute($depositParams);
    $accountDeposits = $depositStmt->fetchAll(PDO::FETCH_ASSOC);

    // Map account_id to deposit totals
    $accountDepositMap = [];
    foreach ($accountDeposits as $dep) {
        $accountDepositMap[$dep['bank_account_id']] = [
            'bank_name' => $dep['bank_name'],
            'account_number' => $dep['account_number'],
            'total_deposit' => $dep['total_deposit']
        ];
    }

    // Transaction Table (showing all cash_deposits with joins)
    $where = "WHERE 1=1";
    $params = [];
    if ($branch_id) {
        $where .= " AND cd.branch_id = ?";
        $params[] = $branch_id;
    }
    if ($staff_id) {
        $where .= " AND cd.staff_id = ?";
        $params[] = $staff_id;
    }
    if ($selected_bank_account_id) {
        $where .= " AND cd.bank_account_id = ?";
        $params[] = $selected_bank_account_id;
    }
    if ($date) {
        $where .= " AND cd.deposit_date = ?";
        $params[] = $date;
    }
    $stmt = $conn->prepare("
        SELECT 
            cd.*,
            b.branch_name,
            s.full_name AS staff_name,
            ba.account_number,
            ba.bank_name
        FROM cash_deposits cd
        LEFT JOIN branches b ON cd.branch_id = b.id
        LEFT JOIN staff s ON cd.staff_id = s.id
        LEFT JOIN bank_accounts ba ON cd.bank_account_id = ba.id
        $where
        ORDER BY cd.created_at DESC
    ");
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Quick Stats
    $branchTotals = [];
    $grandTotal = 0;
    foreach ($transactions as $tx) {
        if (!isset($branchTotals[$tx['branch_id']])) {
            $branchTotals[$tx['branch_id']] = [
                'branch_name' => $tx['branch_name'],
                'amount' => 0
            ];
        }
        $branchTotals[$tx['branch_id']]['amount'] += $tx['total_amount'];
        $grandTotal += $tx['total_amount'];
    }
    $accountTotals = [];
    foreach ($transactions as $tx) {
        $accKey = $tx['account_number'];
        if (!isset($accountTotals[$accKey])) {
            $accountTotals[$accKey] = [
                'bank_name' => $tx['bank_name'],
                'amount' => 0
            ];
        }
        $accountTotals[$accKey]['amount'] += $tx['total_amount'];
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    $transactions = [];
    $branchTotals = [];
    $accountTotals = [];
    $grandTotal = 0;
    $accountList = [];
    $accountDepositMap = [];
    $branchList = [];
    $staffList = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Management</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css" rel="stylesheet">
    <style>
        .stats-card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
        .table td, .table th { vertical-align: middle;}
        .denom-list { font-family: monospace; font-size: 0.9em;}
        .header-bar { background: #0069d9; color: #fff; padding: 1rem 0.5rem; margin-bottom: 1.5rem;}
        .header-bar .container { display: flex; justify-content: space-between; align-items: center;}
        .header-bar h2 { margin: 0; font-size: 1.55rem; font-weight: 600;}
    </style>
</head>
<body>
    <!-- Main Header -->
    <div class="header-bar mb-4">
        <div class="container">
            <h2><i class="bi bi-cash-coin"></i> Transaction Management</h2>
            <div>
                <a href="../../admin/dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a href="../../admin/branch/" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-diagram-3"></i> Branches
                </a>
                <a href="../../admin/staff/" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-people"></i> Staff
                </a>
                <a href="../../admin/transactions/" class="btn btn-light btn-sm fw-bold">
                    <i class="bi bi-cash-stack"></i> Transactions
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Filters -->
        <form class="row mb-3" method="get" id="filterForm">
            <div class="col-sm-3 mb-2">
                <select name="branch_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Branches</option>
                    <?php foreach ($branchList as $branch): ?>
                        <option value="<?php echo $branch['id']; ?>" <?php if ($branch_id == $branch['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3 mb-2">
                <select name="staff_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Staff</option>
                    <?php foreach ($staffList as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>" <?php if ($staff_id == $staff['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($staff['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3 mb-2">
                <select name="account_number" class="form-select" onchange="this.form.submit()">
                    <option value="">All Bank Accounts</option>
                    <?php foreach ($accountList as $account): ?>
                        <option value="<?php echo htmlspecialchars($account['account_number']); ?>" <?php if ($account_number == $account['account_number']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($account['bank_name'] . " / " . $account['account_number']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2 mb-2">
                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>">
            </div>
            <div class="col-sm-1 mb-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
            </div>
        </form>

        <!-- Quick Stats -->
        <div class="row">
            <div class="col-md-4">
                <div class="stats-card">
                    <h6>Total Transactions</h6>
                    <h3><?php echo count($transactions); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h6>Total Amount (Filtered)</h6>
                    <h3>₹<?php echo number_format($grandTotal, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h6>Totals by Branch<?php if($date) echo " ({$date})"; ?></h6>
                    <?php
                    foreach ($branchTotals as $bt) {
                        echo "<span class='badge bg-success mb-1'>" . htmlspecialchars($bt['branch_name']) . ": ₹" . number_format($bt['amount'],2) . "</span> ";
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Cash Deposits & Account Balances -->
        <div class="row">
            <div class="col-md-12 mb-3">
                <div class="stats-card">
                    <h6>Bank Account Cash Deposits & Current Balances</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Bank Name</th>
                                    <th>Account Number</th>
                                    <th>Current Balance</th>
                                    <th>Total Cash Deposited<?php if($date) echo " ($date)"; ?></th>
                                    <th>Theoretical Updated Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accountList as $account): 
                                    $acc_id = $account['id'];
                                    $totalDeposit = isset($accountDepositMap[$acc_id]) ? $accountDepositMap[$acc_id]['total_deposit'] : 0;
                                    $currentBalance = $account['current_balance'] ?? 0;
                                    $updatedBalance = $currentBalance + $totalDeposit;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($account['bank_name']); ?></td>
                                    <td><?php echo htmlspecialchars($account['account_number']); ?></td>
                                    <td>₹<?php echo number_format($currentBalance,2); ?></td>
                                    <td>₹<?php echo number_format($totalDeposit,2); ?></td>
                                    <td>₹<?php echo number_format($updatedBalance,2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <small class="text-muted">"Theoretical Updated Balance" = Current Balance + Cash Deposited (for reporting only)<br>
                        Update actual balances in your cash deposit logic if needed.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Table -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Cash Deposits List</h5>
                <button id="exportExcel" class="btn btn-success">
                    <i class="bi bi-download"></i> Export to Excel
                </button>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table id="transactionsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Bank Account</th>
                                <th>Bank Name</th>
                                <th>Branch Name</th>
                                <th>Staff Name</th>
                                <th>Denomination</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($transactions as $tx) {
                                // Denomination summary
                                $denomination = '';
                                $notes = [
                                    2000 => $tx['notes_2000'],
                                    500 => $tx['notes_500'],
                                    200 => $tx['notes_200'],
                                    100 => $tx['notes_100'],
                                    50 => $tx['notes_50'],
                                    20 => $tx['notes_20'],
                                    10 => $tx['notes_10'],
                                    5 => $tx['notes_5'],
                                    2 => $tx['notes_2'],
                                    1 => $tx['notes_1'],
                                ];
                                foreach($notes as $note => $qty) {
                                    if ($qty > 0) {
                                        $denomination .= "<span>{$note}x{$qty}</span>, ";
                                    }
                                }
                                $denomination = rtrim($denomination, ', ');
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($tx['deposit_date'] ? $tx['deposit_date'] : date('Y-m-d', strtotime($tx['created_at']))) . "</td>";
                                echo "<td>" . htmlspecialchars($tx['account_number']) . "</td>";
                                echo "<td>" . htmlspecialchars($tx['bank_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($tx['branch_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($tx['staff_name']) . "</td>";
                                echo "<td class='denom-list'>" . $denomination . "</td>";
                                echo "<td>₹" . number_format($tx['total_amount'], 2) . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                        <?php if($staff_id): ?>
                        <!-- Total by staff for filtered staff -->
                        <tfoot>
                        <tr>
                            <td colspan="6" class="text-end fw-bold">Total Deposited by Staff</td>
                            <td class="fw-bold">
                                ₹<?php
                                $total = 0;
                                foreach ($transactions as $tx) $total += $tx['total_amount'];
                                echo number_format($total,2);
                                ?>
                            </td>
                        </tr>
                        </tfoot>
                        <?php endif; ?>
                        <?php if($branch_id): ?>
                        <!-- Total by branch for filtered branch -->
                        <tfoot>
                        <tr>
                            <td colspan="6" class="text-end fw-bold">Total Deposited by Branch</td>
                            <td class="fw-bold">
                                ₹<?php
                                $total = 0;
                                foreach ($transactions as $tx) $total += $tx['total_amount'];
                                echo number_format($total,2);
                                ?>
                            </td>
                        </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts (DataTables with Buttons for Export) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#transactionsTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excelHtml5',
                        title: 'Cash Deposits',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ],
                pageLength: 25,
                order: [[0, "desc"]],
                columnDefs: [
                    { orderable: false, targets: [5] }
                ]
            });

            // Hide the built-in button toolbar
            $('.dt-buttons').hide();

            // Trigger the DataTables Excel export programmatically
            $('#exportExcel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });
        });
    </script>
</body>
</html>