<?php

// WireGuard Admin Configuration
// Professional VPN Management System

// Database
define('DB_PATH', __DIR__ . '/data/wg-admin.db');

// WireGuard Settings
define('WG_CONF_PATH', '/etc/wireguard/wg0.conf');
define('WG_IFACE', 'wg0');

// Default Server Settings (can be overridden during installation)
define('SERVER_IP', 'wg-vpn.netxtreme.top');
define('SERVER_PORT', '51820');
define('SUBNET', '10.0.0.0/24');

// Security Settings
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('ENABLE_LOGGING', true);
define('MAX_LOGIN_ATTEMPTS', 5);

// Application Settings
define('APP_NAME', 'WireGuard Admin');
define('APP_VERSION', '2.0.0');
define('TIMEZONE', 'UTC');

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
    
    if ($settings === null && file_exists(DB_PATH)) {
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