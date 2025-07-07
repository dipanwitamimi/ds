<?php
session_start();
require_once '../config/database.php';
$currentDateTime = "2025-03-11 22:17:06";
$currentUser = "sgpriyom";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get staff with filters
    $where = "WHERE 1=1";
    $params = [];
    
    if (isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive'])) {
        $where .= " AND s.status = ?";
        $params[] = $_GET['status'];
    }
    
    if (isset($_GET['branch_id'])) {
        $where .= " AND s.branch_id = ?";
        $params[] = $_GET['branch_id'];
    }
    
    if (isset($_GET['search'])) {
        $where .= " AND (s.staff_id LIKE ? OR s.full_name LIKE ? OR s.email LIKE ?)";
        $search = "%" . $_GET['search'] . "%";
        $params = array_merge($params, [$search, $search, $search]);
    }
    
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            b.branch_name,
            COALESCE(ll.last_login, 'Never') as last_login
        FROM staff s
        LEFT JOIN branches b ON s.branch_id = b.id
        LEFT JOIN (
            SELECT staff_id, MAX(login_time) as last_login
            FROM staff_login_logs
            WHERE status = 'success'
            GROUP BY staff_id
        ) ll ON s.id = ll.staff_id
        $where
        ORDER BY s.created_at DESC
    ");
    
    $stmt->execute($params);
    $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    $staff_members = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?>
Current User's Login: <?php echo $currentUser; ?>
admin/staff.php</div>
    </div>

    <div class="container-fluid mt-4">
        <!-- Quick Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Total Staff</h6>
                    <h3><?php echo count($staff_members); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Active Staff</h6>
                    <h3><?php echo count(array_filter($staff_members, fn($s) => $s['status'] === 'active')); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Managers</h6>
                    <h3><?php echo count(array_filter($staff_members, fn($s) => $s['role'] === 'manager')); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Staff</h6>
                    <h3><?php echo count(array_filter($staff_members, fn($s) => $s['role'] === 'staff')); ?></h3>
                </div>
            </div>
        </div>

        <!-- Staff List -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Staff List</h5>
                <a href="staff/add.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Add New Staff
                </a>
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

                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search staff...">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="branchFilter">
                            <option value="">All Branches</option>
                            <?php
                            $branches = $conn->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name")->fetchAll();
                            foreach ($branches as $branch) {
                                echo "<option value='{$branch['id']}'>{$branch['branch_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" id="exportBtn" class="btn btn-success">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Staff Table -->
                <div class="table-responsive">
                    <table id="staffTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Staff ID</th>
                                <th>Name</th>
                                <th>Branch</th>
                                <th>Role</th>
                                <th>Contact</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_members as $staff): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo $staff['profile_photo'] ? '../uploads/staff/profile_photos/' . $staff['profile_photo'] : '../assets/images/default-avatar.png'; ?>" 
                                         class="profile-img" alt="Profile">
                                </td>
                                <td><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($staff['department']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($staff['branch_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $staff['role'] === 'manager' ? 'primary' : 'secondary'; ?>">
                                        <?php echo ucfirst($staff['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($staff['mobile']); ?>
                                    <br>
                                    <small>
                                        <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>">
                                            <?php echo htmlspecialchars($staff['email']); ?>
                                        </a>
                                    </small>
                                </td>
                                <td><?php echo $staff['last_login']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $staff['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($staff['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="staff/view.php?id=<?php echo $staff['id']; ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="staff/edit.php?id=<?php echo $staff['id']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger"
                                                onclick="confirmDelete(<?php echo $staff['id']; ?>)">
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
                    <form id="deleteForm" action="staff/delete.php" method="POST">
                        <input type="hidden" name="staff_id" id="deleteStaffId">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/jquery.dataTables.min.js"></script>
    <script src="../assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="../assets/js/xlsx.full.min.js"></script>
	<script src="../../assets/js/jquery.min.js"></script>
<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/jquery.validate.min.js"></script>
<script src="../../assets/js/select2.min.js"></script>
<script src="../../assets/js/moment.min.js"></script>
<script src="../../assets/js/bootstrap-datepicker.min.js"></script>
<script src="../../assets/js/sweetalert2.min.js"></script>
<script src="../../assets/js/xlsx.full.min.js"></script>
<script src="../../assets/js/pdfmake.min.js"></script>
<script src="../../assets/js/vfs_fonts.js"></script>
<script src="../../assets/js/chart.min.js"></script>
<script src="js/staff.js"></script>
<script src="js/staff-view.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#staffTable').DataTable({
                "pageLength": 10,
                "order": [[2, "asc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [0, 8] }
                ]
            });

            // Search functionality
            $('#searchInput').on('keyup', function() {
                table.search(this.value).draw();
            });

            // Branch filter
            $('#branchFilter').on('change', function() {
                table.column(3)
                    .search(this.value ? $(this).find('option:selected').text() : '')
                    .draw();
            });

            // Status filter
            $('#statusFilter').on('change', function() {
                table.column(7)
                    .search(this.value)
                    .draw();
            });

            // Export to Excel
            $('#exportBtn').click(function() {
                var wb = XLSX.utils.table_to_book(document.getElementById('staffTable'), {sheet:"Staff List"});
                XLSX.writeFile(wb, 'staff_list_' + new Date().toISOString().slice(0,10) + '.xlsx');
            });
        });

        // Delete confirmation
        function confirmDelete(staffId) {
            $('#deleteStaffId').val(staffId);
            $('#deleteModal').modal('show');
        }
    </script>
</body>
</html>