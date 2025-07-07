<?php
class Session {
    private static $user;
    private static $timezone = 'UTC';

    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function setUser($userData) {
        self::init();
        $_SESSION['user'] = $userData;
        $_SESSION['login_time'] = self::getCurrentDateTime();
        self::$user = $userData;
    }

    public static function getUser() {
        self::init();
        return $_SESSION['user'] ?? null;
    }

    public static function getUserLogin() {
        self::init();
        return $_SESSION['user']['username'] ?? '';
    }

    public static function getCurrentDateTime() {
        return date('Y-m-d H:i:s');
    }

    public static function getCurrentDate() {
        return date('Y-m-d');
    }

    public static function isLoggedIn() {
        self::init();
        return isset($_SESSION['user']);
    }

    public static function logout() {
        self::init();
        session_destroy();
        self::$user = null;
    }
}