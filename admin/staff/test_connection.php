<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "Database connection successful!<br>";
        
        // Test query
        $stmt = $conn->query("SELECT COUNT(*) as count FROM staff");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Current staff count: " . $result['count'];
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>