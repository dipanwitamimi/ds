<?php
session_start();
require_once '../../config/database.php';

$currentDateTime = "2025-03-12 06:46:44";
$currentUser = "sgpriyom";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new Exception("Invalid staff ID");
    }

    // Get staff details with branch info
    $stmt = $db->prepare("
        SELECT 
            bs.*,
            b.branch_name,
            b.branch_code,
            (
                SELECT COUNT(*) 
                FROM branch_staff_attendance 
                WHERE staff_id = bs.id 
                AND status = 'present'
                AND MONTH(date) = MONTH(CURRENT_DATE)
            ) as present_days,
            (
                SELECT COUNT(*) 
                FROM branch_staff_leaves 
                WHERE staff_id = bs.id 
                AND status = 'approved'
                AND YEAR(start_date) = YEAR(CURRENT_DATE)
            ) as leaves_taken
        FROM branch_staff bs
        LEFT JOIN branches b ON bs.branch_id = b.id
        WHERE bs.id = ?
    ");
    
    $stmt->execute([$id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        throw new Exception("Staff not found");
    }

    // Get recent attendance
    $stmt = $db->prepare("
        SELECT * FROM branch_staff_attendance
        WHERE staff_id = ?
        ORDER BY date DESC
        LIMIT 10
    ");
    $stmt->execute([$id]);
    $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get documents
    $stmt = $db->prepare("
        SELECT * FROM branch_staff_documents
        WHERE staff_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile - <?php echo htmlspecialchars($staff['full_name']); ?></title>
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
        .profile-header {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            margin-right: 20px;
        }
        .info-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?>
Current User's Login: <?php echo $currentUser; ?>
branch/staff/view.php</div>
    </div>

    <div class="container mt-4">
        <!-- Action Buttons -->
        <div class="d-flex justify-content-between mb-4">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
            <div>
                <a href="edit.php?id=<?php echo $staff['id']; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Edit Profile
                </a>
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print Profile
                </button>
            </div>
        </div>

        <!-- Profile Header -->
        <div class="profile-header d-flex align-items-center mb-4">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($staff['full_name'], 0, 2)); ?>
            </div>
            <div>
                <h2 class="mb-1"><?php echo htmlspecialchars($staff['full_name']); ?></h2>
                <p class="mb-2"><?php echo htmlspecialchars($staff['designation']); ?> - <?php echo htmlspecialchars($staff['department']); ?></p>
                <span class="status-badge bg-<?php echo $staff['status'] === 'active' ? 'success' : 'danger'; ?>">
                    <?php echo ucfirst($staff['status']); ?>
                </span>
            </div>
        </div>

        <div class="row">
            <!-- Personal Information -->
            <div class="col-md-6">
                <div class="info-card">
                    <h5 class="mb-3">Personal Information</h5>
                    <table class="table">
                        <tr>
                            <th width="35%">Staff ID</th>
                            <td><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>
                                <a href="mailto:<?php echo $staff['email']; ?>">
                                    <?php echo htmlspecialchars($staff['email']); ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Mobile</th>
                            <td>
                                <a href="tel:<?php echo $staff['mobile']; ?>">
                                    <?php echo htmlspecialchars($staff['mobile']); ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Gender</th>
                            <td><?php echo ucfirst($staff['gender']); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth</th>
                            <td><?php echo date('d M Y', strtotime($staff['dob'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Employment Information -->
            <div class="col-md-6">
                <div class="info-card">
                    <h5 class="mb-3">Employment Information</h5>
                    <table class="table">
                        <tr>
                            <th width="35%">Branch</th>
                            <td>
                                <?php echo htmlspecialchars($staff['branch_name']); ?>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($staff['branch_code']); ?></small>
                            </td>
                        </tr>
                        <tr>
                            <th>Joining Date</th>
                            <td><?php echo date('d M Y', strtotime($staff['joining_date'])); ?></td>
                        </tr>
                        <tr>
                            <th>Department</th>
                            <td><?php echo htmlspecialchars($staff['department']); ?></td>
                        </tr>
                        <tr>
                            <th>Designation</th>
                            <td><?php echo htmlspecialchars($staff['designation']); ?></td>
                        </tr>
                        <tr>
                            <th>Experience</th>
                            <td>
                                <?php 
                                    $join = new DateTime($staff['joining_date']);
                                    $now = new DateTime();
                                    $interval = $join->diff($now);
                                    echo $interval->y . " years, " . $interval->m . " months";
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Attendance Summary -->
            <div class="col-md-6">
                <div class="info-card">
                    <h5 class="mb-3">Attendance Summary</h5>
                    <div class="row text-center">
                        <div class="col-4">
                            <h3><?php echo $staff['present_days']; ?></h3>
                            <small class="text-muted">Present Days<br>This Month</small>
                        </div>
                        <div class="col-4">
                            <h3><?php echo $staff['leaves_taken']; ?></h3>
                            <small class="text-muted">Leaves Taken<br>This Year</small>
                        </div>
                        <div class="col-4">
                            <h3><?php echo date('d'); ?></h3>
                            <small class="text-muted">Total Working<br>Days</small>
                        </div>
                    </div>
                    
                    <h6 class="mt-4 mb-3">Recent Attendance</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_attendance as $attendance): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($attendance['date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusColor($attendance['status']); ?>">
                                                <?php echo ucfirst($attendance['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $attendance['check_in'] ? date('H:i', strtotime($attendance['check_in'])) : '-'; ?></td>
                                        <td><?php echo $attendance['check_out'] ? date('H:i', strtotime($attendance['check_out'])) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="col-md-6">
                <div class="info-card">
                    <h5 class="mb-3">Documents</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Document Type</th>
                                    <th>Number</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($documents)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No documents found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($documents as $doc): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                            <td><?php echo htmlspecialchars($doc['document_number']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $doc['verified'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $doc['verified'] ? 'Verified' : 'Pending'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../../uploads/staff/<?php echo $doc['document_file']; ?>" 
                                                   class="btn btn-sm btn-info"
                                                   target="_blank">
                                                    <i class="bi bi-eye"></i>
                                                </a>
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
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function getStatusColor($status) {
    return match($status) {
        'present' => 'success',
        'absent' => 'danger',
        'half-day' => 'warning',
        'leave' => 'info',
        default => 'secondary'
    };
}
?>