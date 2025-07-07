<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';

$currentDateTime = date('Y-m-d H:i:s');
$currentUser = 'sgpriyom';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Save Transaction</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .header-info {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
            font-family: monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header-info">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 
                    <span id="current-datetime"><?php echo $currentDateTime; ?></span>
                </div>
                <div class="col-md-4 text-end">
                    Current User's Login: 
                    <span id="current-user"><?php echo $currentUser; ?></span>
                </div>
            </div>
        </div>
    </div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Start transaction
        $conn->beginTransaction();
        
        // Insert transaction
        $sql = "INSERT INTO transactions (
                    account_id,
                    type,
                    amount,
                    description,
                    created_by,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $_POST['account_id'],
            $_POST['type'],
            $_POST['amount'],
            $_POST['description'],
            $currentUser,
            $currentDateTime
        ]);
        
        // Update account balance
        $sql = "UPDATE bank_accounts 
                SET current_balance = current_balance " . 
                ($_POST['type'] === 'credit' ? '+' : '-') . " ?,
                    updated_at = ?,
                    updated_by = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $_POST['amount'],
            $currentDateTime,
            $currentUser,
            $_POST['account_id']
        ]);
        
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
</body>
</html>