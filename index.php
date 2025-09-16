<?php
// Autoload classes
spl_autoload_register(
    fn($class) =>
    str_starts_with($class, 'WireGuardAdmin\\')
        && file_exists($f = __DIR__ . '/classes/' . str_replace('\\', '/', substr($class, 15)) . '.php')
        && require $f
);

if (!file_exists('config.php')) {
   include 'includes/missing_config.php';
    exit;
}

header("Location: login.php");
exit;
