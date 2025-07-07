<?php
session_start();
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get branches with filters
    $where = "WHERE 1=1";
    $params = [];
    
    if (isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive'])) {
        $where .= " AND status = ?";
        $params[] = $_GET['status'];
    }
    
    if (isset($_GET['search'])) {
        $where .= " AND (branch_code LIKE ? OR branch_name LIKE ? OR city LIKE ?)";
        $search = "%" . $_GET['search'] . "%";
        $params = array_merge($params, [$search, $search, $search]);
    }
    
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            COUNT(bu.id) as total_users,
            COALESCE(SUM(CASE WHEN bu.status = 'active' THEN 1 ELSE 0 END), 0) as active_users
        FROM branches b
        LEFT JOIN branch_users bu ON b.id = bu.branch_id
        $where
        GROUP BY b.id
        ORDER BY b.created_at DESC
    ");
    
    $stmt->execute($params);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    $branches = [];
}
// Include the header
require_once '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Management</title>
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
        .branch-status {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-active { background: #28a745; }
        .status-inactive { background: #dc3545; }
    </style>
</head>
<body>
    

    <div class="container-fluid mt-4">
        <!-- Quick Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Total Branches</h6>
                    <h3><?php echo count($branches); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Active Branches</h6>
                    <h3><?php echo count(array_filter($branches, fn($b) => $b['status'] === 'active')); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Total Users</h6>
                    <h3><?php echo array_sum(array_column($branches, 'total_users')); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Active Users</h6>
                    <h3><?php echo array_sum(array_column($branches, 'active_users')); ?></h3>
                </div>
            </div>
        </div>

        <!-- Branch List -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Branch Management</h5>
                <a href="../branch/add.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Add New Branch
                </a>
            </div>
            <div class="card-body">
                <!-- Search and Filter -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search branches...">
                            <button class="btn btn-outline-secondary" type="button">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Branch Table -->
                <div class="table-responsive">
                    <table id="branchTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Branch Code</th>
                                <th>Branch Name</th>
                                <th>Location</th>
                                <th>Contact</th>
                                <th>Users</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branches as $branch): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($branch['branch_code']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($branch['branch_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($branch['branch_type']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($branch['city']); ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($branch['postal_code']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($branch['contact_number']); ?>
                                    <br>
                                    <small>
                                        <a href="mailto:<?php echo htmlspecialchars($branch['email']); ?>">
                                            <?php echo htmlspecialchars($branch['email']); ?>
                                        </a>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo $branch['active_users']; ?>/<?php echo $branch['total_users']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="branch-status status-<?php echo $branch['status']; ?>"></span>
                                    <?php echo ucfirst($branch['status']); ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="branch/view.php?id=<?php echo $branch['id']; ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="branch/edit.php?id=<?php echo $branch['id']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger"
                                                onclick="confirmDelete(<?php echo $branch['id']; ?>)">
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
                    Are you sure you want to delete this branch?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" action="branch/delete.php" method="POST" style="display: inline;">
                        <input type="hidden" name="branch_id" id="deleteBranchId">
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
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#branchTable').DataTable({
                "pageLength": 10,
                "order": [[1, "asc"]]
            });

            // Search functionality
            $('#searchInput').on('keyup', function() {
                table.search(this.value).draw();
            });

            // Status filter
            $('#statusFilter').on('change', function() {
                table.column(5)
                    .search(this.value)
                    .draw();
            });
        });

        // Delete confirmation
        function confirmDelete(branchId) {
            $('#deleteBranchId').val(branchId);
            $('#deleteModal').modal('show');
        }
    </script>
</body>
</html>