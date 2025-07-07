<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/Database.php';

// Always set $currentUser for display
$currentUser = $_SESSION['username'] ?? "admin";

// Set current date/time
$currentDateTime = date("Y-m-d H:i:s");

// Helper for role badge
function getRoleBadgeClass($role) {
    return match($role) {
        'admin' => 'danger',
        'branch_manager' => 'primary',
        'cashier' => 'success',
        'operator' => 'info',
        'staff' => 'warning',
        default => 'secondary'
    };
}

// Fetch all users from multiple tables: admin_users, branch_users, staff, and any other users
try {
    $database = new Database();
    $db = $database->getConnection();

    // Admin users
    $adminUsers = $db->query("SELECT 
        id, 'admin' AS user_type,
        username AS user_id,
        full_name, email,
        '' AS branch_name, '' AS branch_code,
        role, status, created_at,
        0 AS login_count, 0 AS transaction_count
        FROM admin_users
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Branch users (no bu.phone)
    $branchUsers = $db->query("SELECT 
        bu.id AS user_id,
        bu.id,
        'branch' AS user_type,
        bu.full_name, bu.email, 
        b.branch_name, b.branch_code,
        bu.role, bu.status, bu.created_at,
        0 AS login_count,
        0 AS transaction_count
        FROM branch_users bu
        LEFT JOIN branches b ON bu.branch_id = b.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Staff users (no s.phone)
    $staffUsers = $db->query("SELECT 
        s.id AS user_id,
        s.id,
        'staff' AS user_type,
        s.full_name, s.email, 
        b.branch_name, b.branch_code,
        'staff' AS role, s.status, s.created_at,
        0 AS login_count, 0 AS transaction_count
        FROM staff s
        LEFT JOIN branches b ON s.branch_id = b.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Merge all users
    $users = array_merge($adminUsers, $branchUsers, $staffUsers);

    // Sort by creation date DESC
    usort($users, function($a, $b) {
        return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    });

} catch(Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users Management</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f6fa; }
        .sidebar {
            min-width: 220px; max-width: 220px;
            min-height: 100vh;
            background: #232c3d;
            color: #fff;
            position: fixed;
            top: 0; left: 0;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
            z-index: 100;
            display: flex;
            flex-direction: column;
        }
        .sidebar .logo {
            font-size: 1.3rem;
            font-weight: bold;
            padding: 20px 25px 10px 25px;
            color: #ffd700;
            letter-spacing: 1px;
            border-bottom: 1px solid #3d4657;
        }
        .sidebar .nav-link {
            color: #c0c7d1;
            font-weight: 500;
            padding: 13px 30px;
            border-radius: 0;
            transition: background .1s;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: #181f2f;
            color: #fff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            margin-left: 220px;
            padding: 30px 30px 0 30px;
        }
        .header-info {
            background: #fff;
            border-bottom: 1px solid #e3e6ee;
            padding: 12px 30px;
            font-family: monospace;
            font-size: 14px;
        }
        .user-avatar {
            width: 38px;
            height: 38px;
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
        .stats-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.07);
        }
    </style>
</head>
<body>
    <!-- Navigation Sidebar -->
    <nav class="sidebar d-flex flex-column">
        <div class="logo">
            <i class="bi bi-building"></i> Distributor Management
        </div>
        <ul class="nav nav-pills flex-column mt-3 mb-auto">
            <li class="nav-item">
                <a class="nav-link<?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php') echo ' active'; ?>" href="../dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li>
                <a class="nav-link<?php if (strpos($_SERVER['PHP_SELF'],'branch')!==false && strpos($_SERVER['PHP_SELF'],'users')===false) echo ' active'; ?>" href="../branch/">
                    <i class="bi bi-diagram-3"></i> Branch
                </a>
            </li>
            <li>
                <a class="nav-link<?php if (strpos($_SERVER['PHP_SELF'],'staff')!==false) echo ' active'; ?>" href="../staff/">
                    <i class="bi bi-people"></i> Staff
                </a>
            </li>
            <li>
                <a class="nav-link<?php if (strpos($_SERVER['PHP_SELF'],'transactions')!==false) echo ' active'; ?>" href="../transactions/">
                    <i class="bi bi-arrow-left-right"></i> Transactions
                </a>
            </li>
            <li>
                <a class="nav-link<?php if (strpos($_SERVER['PHP_SELF'],'reports')!==false) echo ' active'; ?>" href="../reports/">
                    <i class="bi bi-file-earmark-bar-graph"></i> Reports
                </a>
            </li>
            <li>
                <a class="nav-link<?php if (strpos($_SERVER['PHP_SELF'],'users')!==false) echo ' active'; ?>" href="users.php">
                    <i class="bi bi-person-bounding-box"></i> Users
                </a>
            </li>
        </ul>
        <div class="mt-auto mb-4 px-4">
            <small class="text-light d-block mb-1">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($currentUser); ?>
            </small>
            <a href="../../logout.php" class="btn btn-outline-light btn-sm w-100"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <div class="main-content">
        <!-- Header Info -->
        <div class="header-info mb-4">
            Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?> &nbsp; | 
            Current User's Login: <?php echo htmlspecialchars($currentUser); ?> &nbsp; | 
            admin/branch/users.php
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Total Users</h6>
                    <h3><?php echo count($users); ?></h3>
                    <small class="text-muted">All users</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Active Users</h6>
                    <h3><?php echo count(array_filter($users, fn($u)=>$u['status']==='active')); ?></h3>
                    <small class="text-success">Currently active</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Logins (Last 30d)</h6>
                    <h3><?php echo array_sum(array_column($users, 'login_count')); ?></h3>
                    <small class="text-primary">Last 30 days</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Transactions (Last 30d)</h6>
                    <h3><?php echo array_sum(array_column($users, 'transaction_count')); ?></h3>
                    <small class="text-info">Last 30 days</small>
                </div>
            </div>
        </div>

        <!-- Users List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Users (Admin, Branch, Staff, etc.)</h5>
                <div>
                    <button class="btn btn-success me-2" id="exportBtn">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <a href="add-user.php" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add New User
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table id="usersTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name & Contact</th>
                                <th>User Type</th>
                                <th>Branch</th>
                                <th>Role</th>
                                <th>Activity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                <br>
                                                <small>
                                                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                    </a>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($user['branch_name'] ?? ''); ?>
                                        <?php if (!empty($user['branch_code'])): ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($user['branch_code']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getRoleBadgeClass($user['role']); ?> badge-role">
                                            <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            Logins: <?php echo $user['login_count']; ?><br>
                                            Transactions: <?php echo $user['transaction_count']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view-user.php?id=<?php echo $user['id']; ?>&type=<?php echo $user['user_type']; ?>"
                                               class="btn btn-sm btn-info" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit-user.php?id=<?php echo $user['id']; ?>&type=<?php echo $user['user_type']; ?>"
                                               class="btn btn-sm btn-warning" title="Edit User">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="confirmDeactivate(<?php echo $user['id']; ?>, '<?php echo addslashes($user['full_name']); ?>', '<?php echo $user['user_type']; ?>')"
                                                    <?php echo $user['status'] === 'inactive' ? 'disabled' : ''; ?>
                                                    title="Deactivate User">
                                                <i class="bi bi-person-x"></i>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-sm btn-secondary"
                                                    onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo addslashes($user['full_name']); ?>', '<?php echo $user['user_type']; ?>')"
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
        <footer class="mt-5 py-4 text-center text-muted" style="font-size:13px;">
            &copy; <?php echo date('Y'); ?> Distributor Management System
        </footer>
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
            $('#usersTable').DataTable({
                "pageLength": 25,
                "order": [[0, "desc"]]
            });

            // Export functionality
            $('#exportBtn').click(function() {
                var wb = XLSX.utils.table_to_book(document.getElementById('usersTable'), {
                    sheet: "All Users"
                });
                XLSX.writeFile(wb, 'all_users_' + new Date().toISOString().slice(0,10) + '.xlsx');
            });
        });

        function confirmDeactivate(userId, userName, userType) {
            if (confirm(`Are you sure you want to deactivate user "${userName}"?`)) {
                window.location.href = `deactivate-user.php?id=${userId}&type=${userType}`;
            }
        }

        function resetPassword(userId, userName, userType) {
            if (confirm(`Are you sure you want to reset password for user "${userName}"?`)) {
                window.location.href = `reset-password.php?id=${userId}&type=${userType}`;
            }
        }
    </script>
</body>
</html>