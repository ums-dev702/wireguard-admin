<?php
// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'WireGuardAdmin\\';
    $baseDir = __DIR__ . '/classes/';
    if (str_starts_with($class, $prefix)) {
        $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) require $file;
    }
});

// Define DB path
define('DB_PATH', __DIR__ . '/data/wg-admin.db');

// Redirect helper
function redirect($page) {
    header("Location: $page");
    exit;
}

// Run checks
try {
    if (!file_exists('config.php') || !file_exists(DB_PATH)) {
        redirect('install.php');
    }

    $db = new \WireGuardAdmin\Database();
    $installer = new \WireGuardAdmin\Installer($db);

    if (!$installer->isInstalled()) {
        redirect('install.php');
    }

    redirect('login.php');
} catch (Exception $e) {
    redirect('install.php');
}
