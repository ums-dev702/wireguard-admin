<?php
// Application Settings
define('APP_NAME', 'WireGuard Admin');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'UTC');
define('WG_DEBUG', true);
define('DB_HOST', 'localhost');
define('DB_NAME', 'wireguard_admin');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);
define('WG_IFACE', 'wg0');
define('SERVER_IP', 'wg-vpn.netxtreme.top');
define('SERVER_PORT', '51820');
define('SUBNET', '10.0.0.0/24');
// Security Settings
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('ENABLE_LOGGING', true);
define('MAX_LOGIN_ATTEMPTS', 5);


// Set timezone
date_default_timezone_set(TIMEZONE);

// Auto-loader for classes
spl_autoload_register(function ($class) {
    $prefix = 'WireGuardAdmin\\';
    $baseDir = __DIR__ . '/classes/';
    
    if (strpos($class, $prefix) === 0) {
        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    }
});

// Helper function to get settings from database
function getSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        try {
            $db = new \WireGuardAdmin\Database();
            $result = $db->select("SELECT key, value FROM settings");
            $settings = array_column($result, 'value', 'key');
        } catch (Exception $e) {
            $settings = [];
        }
    }
    
    return $settings[$key] ?? $default;
}

// Error reporting (disable in production)
if (defined('WG_DEBUG') && constant('WG_DEBUG')) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>