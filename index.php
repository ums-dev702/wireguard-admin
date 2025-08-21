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


// Redirect helper
function redirect($page)
{
    header("Location: $page");
    exit;
}

// Run checks
try {
    if (!file_exists('config.php')) {
        redirect('install.php');
    }
    require_once __DIR__ . '/config.php';
    $db = new \WireGuardAdmin\Database();
    $installer = new \WireGuardAdmin\Installer($db);
    if (!$installer->isInstalled()) {
        redirect('install.php');
    }

    redirect('login.php');
} catch (Exception $e) {
    redirect('install.php');
}
