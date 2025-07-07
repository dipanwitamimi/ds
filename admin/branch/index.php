<?php
session_start();
require_once '../../config/database.php';

// Handle delete post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['branch_id'])) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $branchId = intval($_POST['branch_id']);
        $stmt = $conn->prepare("DELETE FROM branches WHERE id = ?");
        $stmt->execute([$branchId]);
        $_SESSION['success'] = "Branch deleted successfully.";
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting branch: " . $e->getMessage();
        header("Location: index.php");
        exit;
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get branches with filters
    $where = "WHERE 1=1";
    $params = [];

    if (isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive'])) {
        $where .= " AND b.status = ?";
        $params[] = $_GET['status'];
    }

    if (isset($_GET['search'])) {
        $where .= " AND (b.branch_code LIKE ? OR b.branch_name LIKE ? OR b.city LIKE ?)";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Management - Distributor Management</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
    :root {
        --header-height: 60px;
        --nav-height: 50px;
        --primary-color: #007bff;
        --secondary-color: #6c757d;
        --light-bg: #f8f9fa;
        --card-shadow: rgba(0, 0, 0, 0.1) 0px 5px 15px;
    }
    body {
        background-color: var(--light-bg);
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        color: #343a40;
    }
    .main-header {
        background: #fff;
        border-bottom: 1px solid #dee2e6;
        box-shadow: var(--card-shadow);
        padding: 0.5rem 1rem;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1030;
        height: var(--header-height);
        display: flex;
        align-items: center;
    }
    .header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }
    .brand-logo {
        font-weight: bold;
        font-size: 1.4rem;
        color: var(--primary-color);
        letter-spacing: 1px;
        text-decoration: none;
    }
    .datetime-display {
        background: var(--light-bg);
        padding: 0.5rem 1rem;
        border-radius: 5px;
        font-size: 0.95rem;
        font-weight: 500;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .service-card {
        background: #fff;
        border-radius: 10px;
        padding: 1.5rem;
        height: 100%;
        border: 1px solid #e0e0e0;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .service-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-shadow);
    }
    .stats-value {
        font-size: 1.5rem;
        font-weight: bold;
        color: #495057;
    }
    .transaction-list {
        max-height: 400px;
        overflow-y: auto;
    }
    .status-badge {
        font-size: 0.85rem;
        padding: 0.35rem 0.65rem;
        border-radius: 3px;
    }
    a {
        color: var(--primary-color);
        transition: color 0.3s ease;
    }
    a:hover {
        color: var(--secondary-color);
        text-decoration: none;
    }
    .navbar-dark .navbar-nav .nav-link.active {
        background-color: var(--primary-color);
        color: #fff !important;
        border-radius: 4px;
    }
    .table-hover tbody tr:hover {
        background-color: #f1f1f1;
    }
    .btn-outline-primary:hover {
        background-color: var(--primary-color);
        color: #fff;
    }
    .container-fluid {
        padding-top: 80px; /* header height + spacing */
    }
    .btn-action {
        min-width: 38px;
        min-height: 38px;
        padding: 0;
        font-size: 1.1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 4px;
    }
    .btn-action:last-child {
        margin-right: 0;
    }
    .btn-label {
        display: none;
        margin-left: 6px;
        font-size:0.97rem;
        font-weight:500;
    }
    .btn-action:hover .btn-label,
    .btn-action:focus .btn-label {
        display: inline;
    }
    @media (max-width: 767px) {
        .btn-label { display: inline; }
    }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="header-container">
            <a href="../../admin/dashboard.php" class="brand-logo">
                <i class="bi bi-building"></i> Distributor Management
            </a>
            <div class="datetime-display">
                <span id="currentDate"><?php echo date('Y-m-d'); ?></span>
                <span id="currentTime"><?php echo date('H:i:s'); ?> UTC</span>
            </div>
            <div>
                <a href="../../admin/dashboard.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a href="../../admin/branch/" class="btn btn-outline-primary btn-sm active">
                    <i class="bi bi-diagram-3"></i> Branches
                </a>
                <a href="../../admin/staff/" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-people"></i> Staff
                </a>
                <a href="../../logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="container-fluid mt-4">
        <!-- Quick Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h6>Total Branches</h6>
                    <h3><?php echo count($branches); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h6>Active Branches</h6>
                    <h3><?php echo count(array_filter($branches, fn($b) => $b['status'] === 'active')); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h6>Total Users</h6>
                    <h3><?php echo array_sum(array_column($branches, 'total_users')); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h6>Active Users</h6>
                    <h3><?php echo array_sum(array_column($branches, 'active_users')); ?></h3>
                </div>
            </div>
        </div>

        <!-- Branch List -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Branch List</h5>
                <a href="add.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Add (New Branch)</span>
                </a>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <table id="branchTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Branch Code</th>
                            <th>Branch Name</th>
                            <th>Location</th>
                            <th>Contact</th>
                            <th>Users</th>
                            <th>Status</th>
                            <th>Actions<br><small class="text-muted">(Add / Edit / Delete)</small></th>
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
                                <?php echo ucfirst($branch['status']); ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group" aria-label="Actions">
                                    <!-- View -->
                                    <a href="view.php?id=<?php echo $branch['id']; ?>" 
                                        class="btn btn-action btn-info" data-bs-toggle="tooltip" title="View Branch">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <!-- Edit/Modify -->
                                    <a href="edit.php?id=<?php echo $branch['id']; ?>" 
                                        class="btn btn-action btn-warning" data-bs-toggle="tooltip" title="Edit (Modify) Branch">
                                        <i class="bi bi-pencil"></i>
                                        <span class="btn-label d-none d-md-inline">Modify</span>
                                    </a>
                                    <!-- Delete -->
                                    <button type="button"
                                            class="btn btn-action btn-danger"
                                            onclick="confirmDelete(<?php echo $branch['id']; ?>)"
                                            data-bs-toggle="tooltip" title="Delete Branch">
                                        <i class="bi bi-trash"></i>
                                        <span class="btn-label d-none d-md-inline">Delete</span>
                                    </button>
                                </div>
                                <div class="d-md-none mt-2">
                                    <a href="edit.php?id=<?php echo $branch['id']; ?>" class="btn btn-warning btn-sm w-100 mb-1">Edit/Modify</a>
                                    <button type="button" onclick="confirmDelete(<?php echo $branch['id']; ?>)" class="btn btn-danger btn-sm w-100">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="mt-2">
                    <span class="badge bg-primary"><i class="bi bi-plus-lg"></i> Add</span>
                    <span class="badge bg-warning text-dark"><i class="bi bi-pencil"></i> Edit/Modify</span>
                    <span class="badge bg-danger"><i class="bi bi-trash"></i> Delete</span>
                    <small class="text-muted ms-2">Legend: Add / Edit / Delete</small>
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
                    Are you sure you want to <strong>delete</strong> this branch?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" action="index.php" method="POST" style="display: inline;">
                        <input type="hidden" name="branch_id" id="deleteBranchId">
                        <button type="submit" class="btn btn-danger">Delete Branch</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/jquery.dataTables.min.js"></script>
    <script src="../../assets/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#branchTable').DataTable();

            // Delete confirmation modal
            window.confirmDelete = function(branchId) {
                $('#deleteBranchId').val(branchId);
                $('#deleteModal').modal('show');
            };
        });

        // Tooltips and time
        $(document).ready(function () {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(
                document.querySelectorAll("[data-bs-toggle='tooltip']")
            );
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Update time every second
            function updateTime() {
                const now = new Date();
                let hours = String(now.getUTCHours()).padStart(2, "0");
                let minutes = String(now.getUTCMinutes()).padStart(2, "0");
                let seconds = String(now.getUTCSeconds()).padStart(2, "0");
                $("#currentTime").text(`${hours}:${minutes}:${seconds} UTC`);
            }
            setInterval(updateTime, 1000);

            // Auto-hide alerts after 5 seconds
            $(".alert").delay(5000).fadeOut(500);

            // Animate service cards on hover
            $(".service-card").hover(
                function () {
                    $(this).css("transform", "translateY(-10px)");
                    $(this).css("box-shadow", "0px 10px 20px rgba(0,0,0,0.1)");
                },
                function () {
                    $(this).css("transform", "translateY(0px)");
                    $(this).css("box-shadow", "var(--card-shadow)");
                }
            );
        });
    </script>
</body>
</html>