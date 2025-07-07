<?php
// Site Configuration
define('SITE_URL', 'http://localhost/y/admin/');
define('SITE_NAME', 'Distributor Management System');
define('SITE_VERSION', '1.0.0');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'distribution_management');



// Path Configuration
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');

// Session Configuration
define('SESSION_LIFETIME', 7200); // 2 hours
define('SESSION_NAME', 'y_session');

// Default Settings
define('DEFAULT_TIMEZONE', 'UTC');
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// Security Configuration
define('HASH_COST', 10);
define('MIN_PASSWORD_LENGTH', 6);

// Pagination Configuration
define('ITEMS_PER_PAGE', 10);

// File Upload Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);

// Create necessary directories
$directories = [
    UPLOAD_PATH,
    UPLOAD_PATH . '/staff',
    UPLOAD_PATH . '/branches',
    UPLOAD_PATH . '/documents',
    LOG_PATH
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Set default timezone
date_default_timezone_set(DEFAULT_TIMEZONE);


?>

