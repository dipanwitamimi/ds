<?php
class SessionHandler {
    private static $instance = null;
    private $userData = null;
    
    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new SessionHandler();
        }
        return self::$instance;
    }
    
    public function setUser($username) {
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = date('Y-m-d H:i:s');
        $this->userData = $username;
    }
    
    public function getUser() {
        return $_SESSION['username'] ?? 'sgpriyom';
    }
    
    public function getLoginTime() {
        return $_SESSION['login_time'] ?? date('Y-m-d H:i:s');
    }
    
    public function getCurrentDateTime() {
        return date('Y-m-d H:i:s');
    }
    
    public function logout() {
        session_destroy();
        $this->userData = null;
    }
}