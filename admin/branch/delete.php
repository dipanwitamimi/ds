<?php
session_start();
$currentDateTime = date('Y-m-d H:i:s');
$currentUser = 'sgpriyom';

// Process the deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['branch_id'])) {
    $branch_id = $_POST['branch_id'];
    // Add your deletion logic here
    
    // Redirect back to the branch list
    header('Location: index.php');
    exit();
} else {
    // If accessed directly without POST data, redirect to index
    header('Location: index.php');
    exit();
}
?>