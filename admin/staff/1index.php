<?php
// index.php for the /y/admin/staff/ directory

// Start a session
session_start();

// Include necessary configuration or utility files

require_once '../../config/database.php';


// Check if the user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Redirect to login page
    header('Location: ../../login.php');
    exit();
}

// Display the admin staff management page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Staff Management</title>
    <link rel="stylesheet" href="../../styles/admin.css">
</head>
<body>
    <header>
        <h1>Admin - Staff Management</h1>
        <nav>
            <a href="../../dashboard.php">Dashboard</a>
            <a href="../../logout.php">Logout</a>
        </nav>
    </header>
    <main>
        <h2>Staff List</h2>
        <p>Welcome to the staff management page. Here you can view and manage staff details.</p>
        <!-- Add staff management functionality here -->
    </main>
    <footer>
        <p>&copy; 2025 Your Company. All rights reserved.</p>
    </footer>
</body>
</html>