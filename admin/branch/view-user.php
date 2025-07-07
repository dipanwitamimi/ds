<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/Database.php';

$currentDateTime = "2025-03-12 07:13:50";
$currentUser = "sgpriyom";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user ID from URL
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new Exception("Invalid user ID");
    }

    // Get user details with related information
    $stmt = $db->prepare("
        SELECT 
            bu.*,
            b.branch_name,
            b.branch_code,
            (
                SELECT COUNT(*) 
                FROM transactions 
                WHERE created_by = bu.id
            ) as total_transactions,
            (
                SELECT COUNT(*) 
                FROM activity_logs 
                WHERE user_id = bu.id 
                AND action = 'login'
            ) as total_logins,
            (
                SELECT created_at 
                FROM activity_logs 
                WHERE user_id = bu.id 
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_activity
        FROM branch_users bu
        LEFT JOIN branches b ON bu.branch_id = b.id
        WHERE bu.id = ?
    ");
    
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    // Get recent activity logs
    $stmt = $db->prepare("
        SELECT *
        FROM activity_logs
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent transactions
    $stmt = $db->prepare("
        SELECT t.*, bt.transaction_type
        FROM transactions t
        LEFT JOIN branch_transactions bt ON t.transaction_id = bt.id
        WHERE t.created_by = ?
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: users.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Branch User</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>
        .header-info {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-line;
        }
        .user-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        .info-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .activity-item {
            position: relative;
            margin-bottom: 20px;
        }
        .activity-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?>
Current User's Login: <?php echo $currentUser; ?>
admin/branch/view-user.php</div>
    </div>

    <div class="container mt-4">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="d-flex justify-content-between mb-4">
            <a href="users.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
            <div>
                <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Edit User
                </a>
                <button type="button" class="btn btn-info" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print Profile
                </button>
            </div>
        </div>

        <div class="row">
            <!-- User Profile Card -->
            <div class="col-md-4">
                <div class="info-card text-center">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                    </div>
                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($user['user_id']); ?></p>
                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                    <hr>
                    <div class="text-start">
                        <p><strong>Branch:</strong> <?php echo htmlspecialchars($user['branch_name']); ?></p>
                        <p><strong>Role:</strong> <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p><strong>Mobile:</strong> <?php echo htmlspecialchars($user['mobile']); ?></p>
                        <?php if($user['address']): ?>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($user['address']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Card -->
                <div class="info-card">
                    <h6 class="mb-3">Statistics</h6>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h3><?php echo number_format($user['total_transactions']); ?></h3>
                            <small class="text-muted">Total Transactions</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h3><?php echo number_format($user['total_logins']); ?></h3>
                            <small class="text-muted">Total Logins</small>
                        </div>
                    </div>
                    <div class="text-start">
                        <p><strong>Created On:</strong> <?php echo date('Y-m-d', strtotime($user['created_at'])); ?></p>
                        <p><strong>Last Login:</strong> <?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></p>
                        <p><strong>Last Activity:</strong> <?php echo $user['last_activity'] ? date('Y-m-d H:i', strtotime($user['last_activity'])) : 'No activity'; ?></p>
                    </div>
                </div>
            </div>

            <!-- Activity and Transactions -->
            <div class="col-md-8">
                <!-- Permissions -->
                <div class="info-card mb-4">
                    <h6 class="mb-3">User Permissions</h6>
                    <?php 
                    $permissions = json_decode($user['permissions'], true) ?? [];
                    if (empty($permissions)): ?>
                        <p class="text-muted">No special permissions assigned</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach($permissions as $permission): ?>
                                <div class="col-md-6 mb-2">
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                    <?php echo ucwords(str_replace('_', ' ', $permission)); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Transactions -->
                <div class="info-card mb-4">
                    <h6 class="mb-3">Recent Transactions</h6>
                    <?php if (empty($transactions)): ?>
                        <p class="text-muted">No transactions found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($transactions as $trans): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i', strtotime($trans['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($trans['transaction_type']); ?></td>
                                            <td>₹<?php echo number_format($trans['amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-success">Completed</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Timeline -->
                <div class="info-card">
                    <h6 class="mb-3">Recent Activity</h6>
                    <div class="activity-timeline">
                        <?php if (empty($activities)): ?>
                            <p class="text-muted">No recent activity found</p>
                        <?php else: ?>
                            <?php foreach($activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo ucfirst($activity['action']); ?></strong>
                                        <small class="text-muted">
                                            <?php echo date('Y-m-d H:i', strtotime($activity['created_at'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-0"><?php echo htmlspecialchars($activity['details']); ?></p>
                                    <small class="text-muted">
                                        IP: <?php echo htmlspecialchars($activity['ip_address']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>