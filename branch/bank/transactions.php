<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';

$pageTitle = 'Bank Transactions';
$currentDateTime = date('Y-m-d H:i:s');
$currentUser = 'sgpriyom';

$db = new Database();
$conn = $db->getConnection();

// Get filters from URL parameters
$accountId = $_GET['id'] ?? 0;
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$transactionType = $_GET['type'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$minAmount = $_GET['min_amount'] ?? '';
$maxAmount = $_GET['max_amount'] ?? '';

// Base query
$query = "SELECT t.*, b.bank_name, b.account_number 
          FROM transactions t
          JOIN bank_accounts b ON t.account_id = b.id 
          WHERE t.account_id = :account_id";
$params = ['account_id' => $accountId];

// Apply filters
if ($startDate) {
    $query .= " AND DATE(t.created_at) >= :start_date";
    $params['start_date'] = $startDate;
}
if ($endDate) {
    $query .= " AND DATE(t.created_at) <= :end_date";
    $params['end_date'] = $endDate;
}
if ($transactionType) {
    if ($transactionType === 'credit') {
        $query .= " AND t.credit > 0";
    } elseif ($transactionType === 'debit') {
        $query .= " AND t.debit > 0";
    }
}
if ($searchTerm) {
    $query .= " AND (t.description LIKE :search OR t.created_by LIKE :search)";
    $params['search'] = "%$searchTerm%";
}
if ($minAmount) {
    $query .= " AND (t.credit >= :min_amount OR t.debit >= :min_amount)";
    $params['min_amount'] = $minAmount;
}
if ($maxAmount) {
    $query .= " AND (t.credit <= :max_amount OR t.debit <= :max_amount)";
    $params['max_amount'] = $maxAmount;
}

$query .= " ORDER BY t.created_at DESC";

// Execute query
$stmt = $conn->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get account details
$stmt = $conn->prepare("SELECT * FROM bank_accounts WHERE id = ?");
$stmt->execute([$accountId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        .header-info {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
            font-family: monospace;
            font-size: 14px;
        }
        .filter-section {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 20px;
            padding: 15px;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 
                    <span id="current-datetime"><?php echo $currentDateTime; ?></span>
                </div>
                <div class="col-md-4 text-end">
                    Current User's Login: 
                    <span id="current-user"><?php echo $currentUser; ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Filter Section -->
        <div class="filter-section">
            <form id="filterForm" method="GET" class="row g-3">
                <input type="hidden" name="id" value="<?php echo $accountId; ?>">
                
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <div class="input-group">
                        <input type="date" class="form-control" name="start_date" 
                               value="<?php echo $startDate; ?>">
                        <span class="input-group-text">to</span>
                        <input type="date" class="form-control" name="end_date" 
                               value="<?php echo $endDate; ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Transaction Type</label>
                    <select class="form-select" name="type">
                        <option value="">All</option>
                        <option value="credit" <?php echo $transactionType === 'credit' ? 'selected' : ''; ?>>Credit</option>
                        <option value="debit" <?php echo $transactionType === 'debit' ? 'selected' : ''; ?>>Debit</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Amount Range</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="min_amount" 
                               placeholder="Min" value="<?php echo $minAmount; ?>">
                        <span class="input-group-text">to</span>
                        <input type="number" class="form-control" name="max_amount" 
                               placeholder="Max" value="<?php echo $maxAmount; ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" 
                           placeholder="Description or User" value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <div class="btn-group w-100">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                            <i class="bi bi-x-circle"></i> Reset
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Transaction Results</h5>
                <div>
                    <button class="btn btn-success btn-sm" onclick="exportFilteredResults()">
                        <i class="bi bi-file-excel"></i> Export Results
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Description</th>
                                <th>Credit</th>
                                <th>Debit</th>
                                <th>Balance</th>
                                <th>Created By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $balance = $account['opening_balance'];
                            foreach ($transactions as $trans): 
                                $balance += ($trans['credit'] - $trans['debit']);
                            ?>
                                <tr>
                                    <td><?php echo $trans['created_at']; ?></td>
                                    <td><?php echo htmlspecialchars($trans['description']); ?></td>
                                    <td class="text-success">
                                        <?php echo $trans['credit'] > 0 ? '₹' . number_format($trans['credit'], 2) : ''; ?>
                                    </td>
                                    <td class="text-danger">
                                        <?php echo $trans['debit'] > 0 ? '₹' . number_format($trans['debit'], 2) : ''; ?>
                                    </td>
                                    <td class="fw-bold">₹<?php echo number_format($balance, 2); ?></td>
                                    <td><?php echo htmlspecialchars($trans['created_by']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Update datetime function
    function updateDateTime() {
        const now = new Date();
        const formatted = now.getUTCFullYear() + '-' + 
                         String(now.getUTCMonth() + 1).padStart(2, '0') + '-' + 
                         String(now.getUTCDate()).padStart(2, '0') + ' ' + 
                         String(now.getUTCHours()).padStart(2, '0') + ':' + 
                         String(now.getUTCMinutes()).padStart(2, '0') + ':' + 
                         String(now.getUTCSeconds()).padStart(2, '0');
        
        document.getElementById('current-datetime').textContent = formatted;
    }

    // Update time every second
    setInterval(updateDateTime, 1000);
    updateDateTime();

    // Reset filters
    function resetFilters() {
        window.location.href = 'transactions.php?id=<?php echo $accountId; ?>';
    }

    // Export filtered results
    function exportFilteredResults() {
        const form = document.getElementById('filterForm');
        const formData = new FormData(form);
        formData.append('export', '1');
        
        const params = new URLSearchParams(formData);
        window.location.href = 'export_transactions.php?' + params.toString();
    }
    </script>
</body>
</html>