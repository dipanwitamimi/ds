<?php
// Start session only if it's not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default timezone
date_default_timezone_set('UTC');
$currentDateTime = date('Y-m-d H:i:s');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'distribution_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Database connection class
class Database {
    private $host = DB_HOST;
    private $dbname = DB_NAME;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->dbname, $this->user, $this->pass);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}