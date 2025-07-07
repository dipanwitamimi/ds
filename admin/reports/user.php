<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';

$currentDateTime = date('Y-m-d H:i:s');
$currentUser = $_SESSION['admin_user']['username'] ?? 'subhanmimi';

// Filters
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? '';
$branch_id = $_GET['branch_id'] ?? 'All';
$staff_id = $_GET['staff_id'] ?? 'All';
$branch_user_id = $_GET['branch_user_id'] ?? 'All';

// Build WHERE clause for activities/transactions since today
$where = [];
$params = [];
$where[] = "( 
    (al.created_at >= :today) 
    OR (sa.created_at >= :today)
    OR (ba.created_at >= :today)
    OR (cd.created_at >= :today)
    OR (dth.created_at >= :today)
    OR (lapu.created_at >= :today)
    OR (sim.created_at >= :today)
    OR (apb.created_at >= :today)
)";
$params[':today'] = date('Y-m-d') . ' 00:00:00';

// For filtering by branch, staff, branch user
if ($branch_id !== 'All' && $branch_id !== '') {
    $where[] = "(bu.branch_id = :branch_id OR s.branch_id = :branch_id)";
    $params[':branch_id'] = $branch_id;
}
if ($staff_id !== 'All' && $staff_id !== '') {
    $where[] = "s.id = :staff_id";
    $params[':staff_id'] = $staff_id;
}
if ($branch_user_id !== 'All' && $branch_user_id !== '') {
    $where[] = "bu.id = :branch_user_id";
    $params[':branch_user_id'] = $branch_user_id;
}
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch branches, staff, branch users for dropdowns
try {
    $db = (new Database())->getConnection();
    $branches = $db->query("SELECT id, branch_name FROM branches ORDER BY branch_name")->fetchAll(PDO::FETCH_ASSOC);
    $staff = $db->query("SELECT id, full_name FROM staff ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
    $branch_users = $db->query("SELECT id, username, full_name, branch_id FROM branch_users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

    // Main report query: Show all branch, branch_users and staff who are involved in any current/future activity or transaction
    $sql = "
      SELECT
        b.branch_name,
        bu.id AS branch_user_id,
        bu.username AS branch_user_username,
        bu.full_name AS branch_user_full_name,
        bu.role AS branch_user_role,
        bu.status AS branch_user_status,
        bu.created_at AS branch_user_created_at,
        s.id AS staff_id,
        s.full_name AS staff_full_name,
        s.role AS staff_role,
        s.status AS staff_status,
        s.created_at AS staff_created_at,
        -- Last activities/transactions for branch_user
        MAX(al.created_at) AS last_activity_log,
        MAX(ba.created_at) AS last_bank_account,
        MAX(cd.created_at) AS last_cash_deposit,
        MAX(dth.created_at) AS last_dth,
        MAX(lapu.created_at) AS last_lapu,
        MAX(sim.created_at) AS last_sim,
        MAX(apb.created_at) AS last_apb,
        -- Last activities/transactions for staff
        MAX(sa.created_at) AS last_staff_activity,
        MAX(cd2.created_at) AS last_staff_cash_deposit,
        MAX(dth2.created_at) AS last_staff_dth,
        MAX(lapu2.created_at) AS last_staff_lapu,
        MAX(sim2.created_at) AS last_staff_sim,
        MAX(apb2.created_at) AS last_staff_apb
      FROM branches b
      LEFT JOIN branch_users bu ON bu.branch_id = b.id
      LEFT JOIN staff s ON s.branch_id = b.id
      -- Activity logs for branch_users
      LEFT JOIN activity_logs al ON al.user_id = bu.id
      LEFT JOIN bank_accounts ba ON ba.branch_id = b.id
      LEFT JOIN cash_deposits cd ON cd.branch_id = b.id
      LEFT JOIN dth dth ON dth.branch_id = b.id
      LEFT JOIN lapu lapu ON lapu.branch_id = b.id
      LEFT JOIN sim_cards sim ON sim.branch_id = b.id
      LEFT JOIN apb apb ON apb.branch_id = b.id
      -- Activity logs for staff
      LEFT JOIN staff_activity_logs sa ON sa.staff_id = s.id
      LEFT JOIN cash_deposits cd2 ON cd2.staff_id = s.id
      LEFT JOIN dth dth2 ON dth2.staff_id = s.id
      LEFT JOIN lapu lapu2 ON lapu2.staff_id = s.id
      LEFT JOIN sim_cards sim2 ON sim2.staff_id = s.id
      LEFT JOIN apb apb2 ON apb2.staff_id = s.id
      $whereSQL
      GROUP BY b.id, bu.id, s.id
      ORDER BY b.branch_name, bu.username, s.full_name
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    $data = [];
    $branches = [];
    $staff = [];
    $branch_users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Report (Branches, Branch Users & Staff)</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stats-card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
        .header-info { background: #e9ecef; padding: 10px 0; margin-bottom: 16px; }
        .sidebar-nav {
            width: 220px;
            min-height: 100vh;
            background: #295998;
            color: #fff;
            position: fixed;
            left: 0; top: 0; bottom: 0;
            z-index: 100;
            padding-top: 64px;
        }
        .sidebar-nav .nav-link {
            color: #eaf1fc;
            font-weight: 500;
            padding: 12px 24px;
            display: flex;
            align-items: center;
        }
        .sidebar-nav .nav-link.active, .sidebar-nav .nav-link:hover {
            background: #eaf1fc;
            color: #295998;
        }
        .main-content { margin-left: 230px; }
        @media (max-width: 991px) { .sidebar-nav { position: static; width: 100%; min-height: auto; padding-top: 0; } .main-content { margin-left: 0; } }
        td, th { font-size: 14px; }
    </style>
</head>
<body>
    <!-- Sidebar Navigation Panel -->
    <nav class="sidebar-nav d-none d-lg-block">
        <div class="text-center mb-4">
            <img src="../../assets/img/logo.png" alt="Logo" style="max-width:120px;">
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
            <li class="nav-item"><a class="nav-link active" href="user.php"><i class="bi bi-person-badge me-2"></i>User Activities</a></li>
            <li class="nav-item"><a class="nav-link" href="staff.php"><i class="bi bi-people-fill me-2"></i>Staff Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="branch.php"><i class="bi bi-diagram-3 me-2"></i>Branch Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="bank.php"><i class="bi bi-bank me-2"></i>Bank Transactions</a></li>
            <li class="nav-item"><a class="nav-link" href="cash.php"><i class="bi bi-cash-coin me-2"></i>Cash Deposits</a></li>
            <li class="nav-item"><a class="nav-link" href="lapu.php"><i class="bi bi-phone me-2"></i>LAPU Activity</a></li>
            <li class="nav-item"><a class="nav-link" href="sim.php"><i class="bi bi-sim me-2"></i>SIM Card Activity</a></li>
            <li class="nav-item"><a class="nav-link" href="apb.php"><i class="bi bi-archive me-2"></i>APB Activity</a></li>
            <li class="nav-item"><a class="nav-link" href="dth.php"><i class="bi bi-tv me-2"></i>DTH Activity</a></li>
            <li class="nav-item"><a class="nav-link" href="transaction.php"><i class="bi bi-bar-chart-fill me-2"></i>All Transactions</a></li>
            <li class="nav-item"><a class="nav-link" href="../../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
    </nav>

    <div class="main-content">
        <!-- Header Info -->
        <div class="header-info">
            <div class="container-fluid">
                Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <span id="current-datetime"><?php echo $currentDateTime; ?></span>
                &nbsp; | &nbsp;
                Current User's Login: <span id="current-user"><?php echo htmlspecialchars($currentUser); ?></span>
            </div>
        </div>
        <div class="container-fluid mt-4">
            <h3 class="mb-3"><i class="bi bi-person-badge me-2"></i>User/Branch/Staff Activities & Transactions (Current & Future)</h3>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">User Activity by Branch, Branch User & Staff</h5>
                    <div>
                        <button class="btn btn-success me-2" onclick="exportTableToExcel('userTable', 'user_report.xlsx')">
                            <i class="bi bi-file-earmark-excel"></i> Export Excel
                        </button>
                        <button class="btn btn-info" onclick="printElement('reportContent')">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                    </div>
                </div>
                <div class="card-body" id="reportContent">
                    <!-- Filters -->
                    <form method="get" class="row mb-3">
                        <div class="col-md-2">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Branch</label>
                            <select class="form-select" name="branch_id" onchange="this.form.submit()">
                                <option value="All">All Branches</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo $b['id']; ?>" <?php if ($branch_id == $b['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($b['branch_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Branch User</label>
                            <select class="form-select" name="branch_user_id" onchange="this.form.submit()">
                                <option value="All">All Branch Users</option>
                                <?php foreach ($branch_users as $bu): ?>
                                    <option value="<?php echo $bu['id']; ?>" <?php if ($branch_user_id == $bu['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($bu['username'] . ($bu['full_name'] ? " ({$bu['full_name']})" : "")); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Staff</label>
                            <select class="form-select" name="staff_id" onchange="this.form.submit()">
                                <option value="All">All Staff</option>
                                <?php foreach ($staff as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php if ($staff_id == $s['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($s['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mt-4">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>

                    <!-- User/Branch/Staff Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="userTable">
                            <thead>
                                <tr>
                                    <th>Branch</th>
                                    <th>Branch User</th>
                                    <th>Branch User Role</th>
                                    <th>Branch User Status</th>
                                    <th>Branch User Created</th>
                                    <th>Staff</th>
                                    <th>Staff Role</th>
                                    <th>Staff Status</th>
                                    <th>Staff Created</th>
                                    <th>Last Activity Log</th>
                                    <th>Last Staff Activity</th>
                                    <th>Last Bank Account</th>
                                    <th>Last Cash Deposit</th>
                                    <th>Last DTH</th>
                                    <th>Last LAPU</th>
                                    <th>Last SIM</th>
                                    <th>Last APB</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['branch_user_username'] . ($row['branch_user_full_name'] ? " ({$row['branch_user_full_name']})" : "")); ?></td>
                                    <td><?php echo htmlspecialchars($row['branch_user_role']); ?></td>
                                    <td><?php echo htmlspecialchars($row['branch_user_status']); ?></td>
                                    <td><?php echo htmlspecialchars($row['branch_user_created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($row['staff_full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['staff_role']); ?></td>
                                    <td><?php echo htmlspecialchars($row['staff_status']); ?></td>
                                    <td><?php echo htmlspecialchars($row['staff_created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($row['last_activity_log']); ?></td>
                                    <td><?php echo htmlspecialchars($row['last_staff_activity']); ?></td>
                                    <td><?php echo htmlspecialchars($row['last_bank_account']); ?></td>
                                    <td><?php echo htmlspecialchars($row['last_cash_deposit'] ?? $row['last_staff_cash_deposit']); ?></td>
                                    <td><?php echo htmlspecialchars($row['last_dth'] ?? $row['last_staff_dth']); ?></td>
                                    <td><?php echo htmlspecialchars($row['last_lapu'] ?? $row['last_staff_lapu']); ?></td>
                                    <td><?php echo htmlspecialchars($row['last_sim'] ?? $row['last_staff_sim']); ?></td>
                                    <td><?php echo htmlspecialchars($row['last_apb'] ?? $row['last_staff_apb']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($data)): ?>
                                    <tr><td colspan="17" class="text-center text-muted">No user/branch/staff activity found for current/future filter.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Scripts -->
    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    // Excel export (simple)
    function exportTableToExcel(tableID, filename = ''){
        var downloadLink;
        var dataType = 'application/vnd.ms-excel';
        var tableSelect = document.getElementById(tableID);
        var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
        filename = filename ? filename : 'user_report.xls';
        downloadLink = document.createElement("a");
        document.body.appendChild(downloadLink);
        if(navigator.msSaveOrOpenBlob){
            var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
            navigator.msSaveOrOpenBlob(blob, filename);
        }else{
            downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
            downloadLink.download = filename;
            downloadLink.click();
        }
    }
    function printElement(elId) {
        var content = document.getElementById(elId).innerHTML;
        var mywindow = window.open('', 'Print', 'height=600,width=900');
        mywindow.document.write('<html><head><title>Print</title>');
        mywindow.document.write('<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">');
        mywindow.document.write('<style>body{font-family:Segoe UI,sans-serif;padding:20px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:8px;}</style>');
        mywindow.document.write('</head><body>');
        mywindow.document.write(content);
        mywindow.document.write('</body></html>');
        mywindow.document.close();
        mywindow.focus();
        setTimeout(function(){ mywindow.print(); mywindow.close(); }, 600);
        return true;
    }
    </script>
</body>
</html>