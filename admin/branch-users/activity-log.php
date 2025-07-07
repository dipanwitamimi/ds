<?php
session_start();
require_once '../../config/database.php';

$currentDateTime = "2025-03-12 07:03:44";
$currentUser = "sgpriyom";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user ID from query string
    $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    
    // Get activity logs
    $query = "
        SELECT 
            l.*,
            bu.username,
            bu.full_name,
            a.username as admin_username
        FROM branch_user_activity_logs l
        JOIN branch_users bu ON l.user_id = bu.id
        LEFT JOIN admin_users a ON l.created_by = a.id
    ";
    
    if ($user_id) {
        $query .= " WHERE l.user_id = ?";
        $params = [$user_id];
    } else {
        $params = [];
    }
    
    $query .= " ORDER BY l.created_at DESC LIMIT 100";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    $logs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Log</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        .header-info {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-line;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?>
Current User's Login: <?php echo $currentUser; ?>
admin/branch-users/activity-log.php</div>
    </div>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">User Activity Log</h5>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Users
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($log['username']); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($log['full_name']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getActionBadgeClass($log['action']); ?>">
                                            <?php echo formatAction($log['action']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td><?php echo htmlspecialchars($log['admin_username']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function getActionBadgeClass($action) {
    return match($action) {
        'login' => 'success',
        'logout' => 'secondary',
        'password_reset' => 'warning',
        'status_update' => 'info',
        default => 'primary'
    };
}

function formatAction($action) {
    return ucwords(str_replace('_', ' ', $action));
}
?>