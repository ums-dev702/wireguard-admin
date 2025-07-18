<?php
// Simple autoloader for classes
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

// Define constants if not already defined
if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/data/wg-admin.db');
}

try {
    // Check if installation is complete
    if (!file_exists(DB_PATH)) {
        header("Location: install.php");
        exit;
    }
    
    $db = new \WireGuardAdmin\Database();
    $installer = new \WireGuardAdmin\Installer($db);
    
    if (!$installer->isInstalled()) {
        header("Location: install.php");
        exit;
    }
    
    // Installation complete, redirect to login
    header("Location: login.php");
    exit;
    
} catch (Exception $e) {
    // If there's any error, go to installation
    header("Location: install.php");
    exit;
}