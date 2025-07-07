<?php
session_start();
require_once '../../config/database.php';

$currentDateTime = "2025-03-12 07:01:47";
$currentUser = "sgpriyom";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get branch users with related details
    $query = "
        SELECT 
            bu.*,
            b.branch_name,
            b.branch_code,
            COALESCE(
                (SELECT COUNT(*) 
                FROM branch_user_activity_logs 
                WHERE user_id = bu.id 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ), 0
            ) as activity_count
        FROM branch_users bu
        LEFT JOIN branches b ON bu.branch_id = b.id
        ORDER BY bu.created_at DESC
    ";
    
    $stmt = $db->query($query);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get branches for filter
    $stmt = $db->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    $users = [];
    $branches = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Users Management</title>
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
        .user-avatar {
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
        .badge-role {
            font-size: 0.8rem;
            padding: 0.4em 0.8em;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container-fluid">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?>
Current User's Login: <?php echo $currentUser; ?>
admin/branch-users/index.php</div>
    </div>

    <div class="container-fluid mt-4">
        <!-- Quick Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Total Users</h6>
                    <h3><?php echo count($users); ?></h3>
                    <small class="text-muted">Across all branches</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Active Users</h6>
                    <h3><?php 
                        echo count(array_filter($users, function($user) {
                            return $user['status'] === 'active';
                        }));
                    ?></h3>
                    <small class="text-success">Currently active</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Branch Admins</h6>
                    <h3><?php 
                        echo count(array_filter($users, function($user) {
                            return $user['role'] === 'branch_admin';
                        }));
                    ?></h3>
                    <small class="text-primary">Administrative users</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Recent Activity</h6>
                    <h3><?php 
                        echo array_sum(array_column($users, 'activity_count'));
                    ?></h3>
                    <small class="text-muted">Actions in last 30 days</small>
                </div>
            </div>
        </div>

        <!-- Users List -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Branch Users</h5>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i> Add New User
                </a>
            </div>
            <div class="card-body">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search users...">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="branchFilter">
                            <option value="">All Branches</option>
                            <?php foreach($branches as $branch): ?>
                                <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="roleFilter">
                            <option value="">All Roles</option>
                            <option value="branch_admin">Branch Admin</option>
                            <option value="manager">Manager</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-end">
                        <button type="button" class="btn btn-success" id="exportBtn">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="table-responsive">
                    <table id="usersTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Username</th>
                                <th>Branch</th>
                                <th>Role</th>
                                <th>Contact</th>
                                <th>Last Activity</th>
                                <th>Status</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($user['full_name']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($user['branch_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($user['branch_code']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getRoleBadgeClass($user['role']); ?> badge-role">
                                            <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($user['mobile']); ?>
                                        <br>
                                        <small>
                                            <a href="mailto:<?php echo $user['email']; ?>">
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </a>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if($user['activity_count'] > 0): ?>
                                            <span class="text-success">
                                                <?php echo $user['activity_count']; ?> actions
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No recent activity</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="edit.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-warning"
                                               title="Edit User">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if($user['status'] === 'active'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger"
                                                        onclick="confirmDeactivate(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')"
                                                        title="Deactivate User">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-success"
                                                        onclick="confirmActivate(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')"
                                                        title="Activate User">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-info"
                                                    onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')"
                                                    title="Reset Password">
                                                <i class="bi bi-key"></i>
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

    <!-- Scripts -->
    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/jquery.dataTables.min.js"></script>
    <script src="../../assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="../../assets/js/xlsx.full.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#usersTable').DataTable({
                "pageLength": 25,
                "order": [[1, "asc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [0, 7] }
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

            // Role filter
            $('#roleFilter').on('change', function() {
                table.column(3)
                    .search(this.value ? $(this).find('option:selected').text() : '')
                    .draw();
            });

            // Status filter
            $('#statusFilter').on('change', function() {
                table.column(6)
                    .search(this.value ? $(this).find('option:selected').text() : '')
                    .draw();
            });

            // Export functionality
            $('#exportBtn').click(function() {
                var wb = XLSX.utils.table_to_book(document.getElementById('usersTable'), {
                    sheet: "Branch Users"
                });
                XLSX.writeFile(wb, 'branch_users_' + new Date().toISOString().slice(0,10) + '.xlsx');
            });
        });

        // User management functions
        function confirmDeactivate(userId, username) {
            if (confirm(`Are you sure you want to deactivate user "${username}"?`)) {
                window.location.href = `update-status.php?id=${userId}&status=inactive`;
            }
        }

        function confirmActivate(userId, username) {
            if (confirm(`Are you sure you want to activate user "${username}"?`)) {
                window.location.href = `update-status.php?id=${userId}&status=active`;
            }
        }

        function resetPassword(userId, username) {
            if (confirm(`Are you sure you want to reset password for user "${username}"?`)) {
                window.location.href = `reset-password.php?id=${userId}`;
            }
        }
    </script>

    <?php
    function getRoleBadgeClass($role) {
        return match($role) {
            'branch_admin' => 'primary',
            'manager' => 'success',
            'staff' => 'secondary',
            default => 'info'
        };