<?php
try {
    $conn = new PDO('mysql:host=localhost;dbname=distribution_management', 'admin14', 'Betichod@321');
    echo "Connected successfully";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>