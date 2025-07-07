<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';

// Date filter logic
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$dateWhere = '';
$dateParams = [];
if ($start_date && $end_date) {
    $dateWhere = "AND (s.created_at BETWEEN :start_date AND :end_date OR bu.created_at BETWEEN :start_date AND :end_date)";
    $dateParams[':start_date'] = $start_date . ' 00:00:00';
    $dateParams[':end_date'] = $end_date . ' 23:59:59';
}

$currentDateTime = date('Y-m-d H:i:s');
$currentUser = $_SESSION['admin_user']['username'] ?? 'Admin';

try {
    $db = (new Database())->getConnection();

    // Total staff count (staff + branch_users)
    $stmt = $db->prepare("
        SELECT
            (SELECT COUNT(*) FROM staff WHERE 1=1 $dateWhere)
          + (SELECT COUNT(*) FROM branch_users WHERE 1=1 $dateWhere)
          AS total_staff
    ");
    $stmt->execute($dateParams);
    $staffSummary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Unified query for both staff and branch_users
    $sql = "
    SELECT
        s.staff_id,
        s.full_name,
        s.role,
        s.status,
        b.branch_name,
        s.email,
        s.mobile,
        s.department,
        (SELECT SUM(quantity_received) FROM apb WHERE staff_id = s.id" . ($start_date && $end_date ? " AND created_at BETWEEN :start_date AND :end_date" : "") . ") AS apb_qty,
        (SELECT SUM(amount_received) FROM dth WHERE staff_id = s.id" . ($start_date && $end_date ? " AND created_at BETWEEN :start_date AND :end_date" : "") . ") AS dth_amount,
        (SELECT SUM(cash_received) FROM lapu WHERE staff_id = s.id" . ($start_date && $end_date ? " AND created_at BETWEEN :start_date AND :end_date" : "") . ") AS lapu_amount,
        (SELECT SUM(quantity_received) FROM sim_cards WHERE staff_id = s.id" . ($start_date && $end_date ? " AND created_at BETWEEN :start_date AND :end_date" : "") . ") AS sim_qty,
        (SELECT SUM(total_amount) FROM cash_deposits WHERE staff_id = s.id" . ($start_date && $end_date ? " AND created_at BETWEEN :start_date AND :end_date" : "") . ") AS cash_deposit,
        s.created_at
    FROM staff s
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE 1=1 $dateWhere
    UNION ALL
    SELECT
        bu.id as staff_id,
        bu.full_name,
        IF(bu.role='', 'staff', bu.role) AS role,
        bu.status,
        b.branch_name,
        bu.email,
        bu.mobile,
        NULL as department,
        (SELECT SUM(quantity_received) FROM apb WHERE staff_id = bu.id" . ($start_date && $end_date ? " AND created_at BETWEEN :start_date AND :end_date" : "") . ") AS apb_qty,
        (SELECT SUM(amount_received) FROM dth WHERE staff_id = bu.id" . ($start_date && $end_date ? " AND created_at BETWEEN :start_date AND :end_date" : "") . ") AS dth_amount,
        (SELECT SUM(cash_received) FROM lapu WHERE staff_id = bu.id" . ($start_date && $end_date ? " AND created_at BETWEEN :start_date AND :end_date" : "") . ") AS lapu_amount,
        (SELECT SUM(quantity_received) FROM sim_cards WHERE staff_id = bu.id" . ($start_date && $end_date ? " AND created_at BETWEEN :start_date AND :end_date" : "") . ") AS sim_qty,
        (SELECT SUM(total_amount) FROM cash_deposits WHERE staff_id = bu.id" . ($start_date && $end_date ? " AND created_at BETWEEN :start_date AND :end_date" : "") . ") AS cash_deposit,
        bu.created_at
    FROM branch_users bu
    LEFT JOIN branches b ON bu.branch_id = b.id
    WHERE 1=1 $dateWhere
    ORDER BY full_name ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($dateParams, $dateParams));
    $staffDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching staff data: " . $e->getMessage();
    $staffSummary = ['total_staff' => 0];
    $staffDetails = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Performance Report</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
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
        .main-content {
            margin-left: 230px;
        }
        @media (max-width: 991px) {
            .sidebar-nav { position: static; width: 100%; min-height: auto; padding-top: 0; }
            .main-content { margin-left: 0; }
        }
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
            <li class="nav-item"><a class="nav-link" href="staff.php"><i class="bi bi-people-fill me-2"></i>Staff Performance</a></li>
            <li class="nav-item"><a class="nav-link" href="branch.php"><i class="bi bi-diagram-3 me-2"></i>Branch Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="cash.php"><i class="bi bi-cash-coin me-2"></i>Cash Deposits</a></li>
            <li class="nav-item"><a class="nav-link" href="sales.php"><i class="bi bi-bar-chart-fill me-2"></i>Sales Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="attendance.php"><i class="bi bi-calendar-check me-2"></i>Attendance</a></li>
            <li class="nav-item"><a class="nav-link" href="../../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
    </nav>

    <div class="main-content">
        <!-- Header Info -->
        <div class="header-info">
            <div class="container-fluid">
                Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted):
                <span id="current-datetime"><?php echo $currentDateTime; ?></span>
                &nbsp; | &nbsp;
                Current User's Login:
                <span id="current-user"><?php echo htmlspecialchars($currentUser); ?></span>
            </div>
        </div>

        <div class="container-fluid mt-4">
            <h3 class="mb-3"><i class="bi bi-people-fill me-2"></i>Staff & Branch User Performance Report</h3>
            <form class="mb-3 row" method="get">
                <div class="col-auto">
                    <label for="start_date" class="col-form-label">From:</label>
                </div>
                <div class="col-auto">
                    <input type="date" class="form-control" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-auto">
                    <label for="end_date" class="col-form-label">To:</label>
                </div>
                <div class="col-auto">
                    <input type="date" class="form-control" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="staff.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <h6>Total Employees</h6>
                        <h3><?php echo $staffSummary['total_staff']; ?></h3>
                        <small class="text-muted">All staff and branch users</small>
                    </div>
                </div>
            </div>

            <!-- Staff Performance Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Detailed Staff Performance</h5>
                    <div>
                        <button class="btn btn-success me-2" id="exportExcel">
                            <i class="bi bi-file-earmark-excel"></i> Export Excel
                        </button>
                        <button class="btn btn-info" onclick="printElement('reportContent')">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                    </div>
                </div>
                <div class="card-body" id="reportContent">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="staffTable">
                            <thead>
                                <tr>
                                    <th>Staff/User ID</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Branch</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Department</th>
                                    <th>APB (Qty)</th>
                                    <th>DTH (₹)</th>
                                    <th>LAPU (₹)</th>
                                    <th>SIM (Qty)</th>
                                    <th>Cash Deposit (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staffDetails as $staff) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['role']); ?></td>
                                        <td>
                                            <?php if ($staff['status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($staff['branch_name']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['mobile']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['department']); ?></td>
                                        <td><?php echo is_null($staff['apb_qty']) ? 0 : (int)$staff['apb_qty']; ?></td>
                                        <td><?php echo is_null($staff['dth_amount']) ? 0 : number_format($staff['dth_amount'], 2); ?></td>
                                        <td><?php echo is_null($staff['lapu_amount']) ? 0 : number_format($staff['lapu_amount'], 2); ?></td>
                                        <td><?php echo is_null($staff['sim_qty']) ? 0 : (int)$staff['sim_qty']; ?></td>
                                        <td><?php echo is_null($staff['cash_deposit']) ? 0 : number_format($staff['cash_deposit'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($staffDetails)): ?>
                                    <tr><td colspan="13" class="text-center text-muted">No employee data found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Scripts (DataTables Excel Export) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#staffTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excelHtml5',
                        title: 'Staff_Performance_Report',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ],
                pageLength: 25,
                order: [[1, "asc"]]
            });

            $('.dt-buttons').hide(); // Hide built-in Excel button

            $('#exportExcel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });
        });

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