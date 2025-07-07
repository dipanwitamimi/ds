<?php
session_start();
require_once '../../config/database.php';

$currentDateTime = "2025-03-12 06:43:02";
$currentUser = "sgpriyom";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all branches for filter
    $stmt = $db->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all departments for filter
    $stmt = $db->query("SELECT DISTINCT department FROM branch_staff WHERE department IS NOT NULL ORDER BY department");
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Base query with joins
    $query = "
        SELECT 
            bs.*,
            b.branch_name,
            COALESCE(a.present_count, 0) as attendance_count
        FROM branch_staff bs
        LEFT JOIN branches b ON bs.branch_id = b.id
        LEFT JOIN (
            SELECT staff_id, COUNT(*) as present_count
            FROM branch_staff_attendance
            WHERE MONTH(date) = MONTH(CURRENT_DATE)
            AND status = 'present'
            GROUP BY staff_id
        ) a ON bs.id = a.staff_id
    ";
    
    // Apply filters if set
    $where = [];
    $params = [];
    
    if (!empty($_GET['search'])) {
        $where[] = "(bs.full_name LIKE ? OR bs.staff_id LIKE ? OR bs.email LIKE ? OR bs.mobile LIKE ?)";
        $searchTerm = "%" . $_GET['search'] . "%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    
    if (!empty($_GET['branch'])) {
        $where[] = "bs.branch_id = ?";
        $params[] = $_GET['branch'];
    }
    
    if (!empty($_GET['department'])) {
        $where[] = "bs.department = ?";
        $params[] = $_GET['department'];
    }
    
    if (!empty($_GET['status'])) {
        $where[] = "bs.status = ?";
        $params[] = $_GET['status'];
    }
    
    if (!empty($where)) {
        $query .= " WHERE " . implode(" AND ", $where);
    }
    
    $query .= " ORDER BY bs.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    $staffs = [];
    $branches = [];
    $departments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management</title>
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
        .filters-card {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?>
Current User's Login: <?php echo $currentUser; ?>
branch/staff/index.php</div>
    </div>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Staff Management</h2>
            <div>
                <button class="btn btn-success me-2" id="exportBtn">
                    <i class="bi bi-download"></i> Export to Excel
                </button>
                <a href="add.php" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i> Add New Staff
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search staff..." 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="branch">
                        <option value="">All Branches</option>
                        <?php foreach($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>"
                                <?php echo (isset($_GET['branch']) && $_GET['branch'] == $branch['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="department">
                        <option value="">All Departments</option>
                        <?php foreach($departments as $dept): ?>
                            <option value="<?php echo $dept; ?>"
                                <?php echo (isset($_GET['department']) && $_GET['department'] == $dept) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Search
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Staff Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="staffTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Staff ID</th>
                                <th>Name</th>
                                <th>Branch</th>
                                <th>Department</th>
                                <th>Contact</th>
                                <th>Attendance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($staffs)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No staff members found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($staffs as $staff): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="staff-avatar me-2">
                                                    <?php echo strtoupper(substr($staff['full_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <?php echo htmlspecialchars($staff['full_name']); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        Joined: <?php echo date('Y-m-d', strtotime($staff['joining_date'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($staff['branch_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($staff['department']); ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($staff['designation']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($staff['mobile']); ?>
                                            <br>
                                            <small>
                                                <a href="mailto:<?php echo $staff['email']; ?>">
                                                    <?php echo htmlspecialchars($staff['email']); ?>
                                                </a>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $staff['attendance_count']; ?> days present
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $staff['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($staff['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view.php?id=<?php echo $staff['id']; ?>" 
                                                   class="btn btn-sm btn-info" 
                                                   title="View Profile">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $staff['id']; ?>" 
                                                   class="btn btn-sm btn-warning"
                                                   title="Edit Staff">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger"
                                                        onclick="confirmDelete(<?php echo $staff['id']; ?>, '<?php echo addslashes($staff['full_name']); ?>')"
                                                        title="Delete Staff">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                        <input type="hidden" name="id" id="deleteId">
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
                "ordering": true,
                "info": true,
                "dom": 'rtip'
            });

            // Export to Excel
            $('#exportBtn').click(function() {
                var wb = XLSX.utils.table_to_book(document.getElementById('staffTable'), {
                    sheet: "Staff List",
                    dateNF: 'yyyy-mm-dd'
                });
                XLSX.writeFile(wb, 'staff_list_' + new Date().toISOString().slice(0,10) + '.xlsx');
            });
        });

        // Delete confirmation
        function confirmDelete(id, name) {
            if (confirm('Are you sure you want to delete staff member: ' + name + '?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>