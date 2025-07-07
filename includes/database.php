<?php
// Ensure this file is included only once
if (!class_exists('Database')) {
    class Database {
        private $host = 'localhost';
        private $dbname = 'distribution_management';
        private $user = 'root';
        private $pass = '';
        private $conn;

        public function getConnection() {
            $this->conn = null;
            try {
                $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->dbname, $this->user, $this->pass);
                $this->conn->exec("set names utf8");
            } catch (PDOException $exception) {
                echo "Connection error: " . $exception->getMessage();
            }

            return $this->conn;
        }
    }
}