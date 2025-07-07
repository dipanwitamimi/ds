<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';

$currentDateTime = date(DATETIME_FORMAT);
$currentUser = $_SESSION['username'] ?? 'sgpriyom';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get staff list with branch details and attendance status
    $query = "
        SELECT 
            bs.*,
            b.branch_name,
            b.branch_code,
            COALESCE(a.status, 'absent') as today_attendance,
            COALESCE(
                (SELECT COUNT(*) 
                FROM branch_staff_attendance 
                WHERE staff_id = bs.id 
                AND status = 'present'
                AND MONTH(date) = MONTH(CURRENT_DATE)
                ), 0
            ) as present_days,
            COALESCE(l.pending_leaves, 0) as pending_leaves
        FROM branch_staff bs
        LEFT JOIN branches b ON bs.branch_id = b.id
        LEFT JOIN branch_staff_attendance a ON bs.id = a.staff_id 
            AND DATE(a.date) = CURRENT_DATE
        LEFT JOIN (
            SELECT staff_id, COUNT(*) as pending_leaves
            FROM branch_staff_leaves
            WHERE status = 'pending'
            GROUP BY staff_id
        ) l ON bs.id = l.staff_id
        ORDER BY bs.created_at DESC
    ";
    
    $stmt = $db->query($query);
    $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get branches for filter
    $stmt = $db->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    error_log("Staff List Error: " . $e->getMessage());
    $_SESSION['error'] = "Error loading staff list";
    $staff_members = [];
    $branches = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - <?php echo SITE_NAME; ?></title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .header-info {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-line;
        }
        .stats-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .staff-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }
        .status-leave { background-color: #ffc107; }
        .filters-card {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container-fluid">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?>
Current User's Login: <?php echo $currentUser; ?>
branch/staff/list.php</div>
    </div>

    <div class="container-fluid mt-4">
        <!-- Quick Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Total Staff</h6>
                    <h3><?php echo count($staff_members); ?></h3>
                    <small class="text-muted">Across all branches</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Active Staff</h6>
                    <h3><?php 
                        echo count(array_filter($staff_members, function($staff) {
                            return $staff['status'] === 'active';
                        }));
                    ?></h3>
                    <small class="text-success">Currently working</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>On Leave Today</h6>
                    <h3><?php 
                        echo count(array_filter($staff_members, function($staff) {
                            return $staff['today_attendance'] === 'leave';
                        }));
                    ?></h3>
                    <small class="text-warning">Away from work</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Pending Leave Requests</h6>
                    <h3><?php 
                        echo array_sum(array_column($staff_members, 'pending_leaves'));
                    ?></h3>
                    <small class="text-primary">Awaiting approval</small>
                </div>
            </div>
        </div>

        <!-- Staff List -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Staff Management</h5>
                <div>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add New Staff
                    </a>
                    <button type="button" class="btn btn-success" id="exportBtn">
                        <i class="bi bi-download"></i> Export Data
                    </button>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Filters -->
                <div class="filters-card">
                    <div class="row">
                        <div class="col-md-3">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search staff...">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="branchFilter">
                                <option value="">All Branches</option>
                                <?php foreach($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>">
                                        <?php echo htmlspecialchars($branch['branch_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="departmentFilter">
                                <option value="">All Departments</option>
                                <option value="Sales">Sales</option>
                                <option value="Operations">Operations</option>
                                <option value="Accounts">Accounts</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Staff Table -->
                <div class="table-responsive">
                    <table id="staffTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Staff ID</th>
                                <th>Name & Contact</th>
                                <th>Branch</th>
                                <th>Department</th>
                                <th>Attendance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($staff_members as $staff): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($staff['staff_id']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Joined: <?php echo date('d M Y', strtotime($staff['joining_date'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="staff-avatar me-2">
                                                <?php echo strtoupper(substr($staff['full_name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <?php echo htmlspecialchars($staff['full_name']); ?>
                                                <br>
                                                <small>
                                                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($staff['mobile']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($staff['branch_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($staff['branch_code']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($staff['department']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($staff['designation']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getAttendanceColor($staff['today_attendance']); ?>">
                                            <?php echo ucfirst($staff['today_attendance']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            Present: <?php echo $staff['present_days']; ?> days this month
                                        </small>
                                    </td>
                                    <td>
                                        <span class="status-indicator status-<?php echo $staff['status']; ?>"></span>
                                        <?php echo ucfirst($staff['status']); ?>
                                        <?php if($staff['pending_leaves'] > 0): ?>
                                            <br>
                                            <small class="text-warning">
                                                <?php echo $staff['pending_leaves']; ?> pending leave(s)
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $staff['id']; ?>" 
                                               class="btn btn-sm btn-info" 
                                               title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $staff['id']; ?>" 
                                               class="btn btn-sm btn-warning"
                                               title="Edit Staff">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger"
                                                    onclick="confirmDelete(<?php echo $staff['id']; ?>, '<?php echo addslashes($staff['staff_id']); ?>')"
                                                    title="Delete Staff">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this staff member? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" action="delete.php" method="POST">
                        <input type="hidden" name="staff_id" id="deleteStaffId">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/jquery.dataTables.min.js"></script>
    <script src="../../assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="../../assets/js/xlsx.full.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#staffTable').DataTable({
                "pageLength": 25,
                "order": [[0, "desc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [6] }
                ]
            });

            // Search functionality
            $('#searchInput').on('keyup', function() {
                table.search(this.value).draw();
            });

            // Branch filter
            $('#branchFilter').on('change', function() {
                table.column(2)
                    .search(this.value ? $(this).find('option:selected').text() : '')
                    .draw();
            });

            // Department filter
            $('#departmentFilter').on('change', function() {
                table.column(3)
                    .search(this.value ? this.value : '')
                    .draw();
            });

            // Status filter
            $('#statusFilter').on('change', function() {
                table.column(5)
                    .search(this.value ? this.value : '')
                    .draw();
            });

            // Export functionality
            $('#exportBtn').click(function() {
                var wb = XLSX.utils.table_to_book(
                    document.getElementById('staffTable'),