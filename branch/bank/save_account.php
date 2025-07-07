<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';

// Header display
$currentDateTime = date('Y-m-d H:i:s');
$currentUser = 'sgpriyom';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Save Bank Account</title>
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
// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $sql = "INSERT INTO bank_accounts (
                    bank_name, 
                    account_number, 
                    ifsc_code, 
                    opening_balance, 
                    current_balance,
                    created_by,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $_POST['bank_name'],
            $_POST['account_number'],
            $_POST['ifsc_code'],
            $_POST['opening_balance'],
            $_POST['opening_balance'],
            $currentUser,
            $currentDateTime
        ]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
</body>
</html>