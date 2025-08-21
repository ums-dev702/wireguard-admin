<?php


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