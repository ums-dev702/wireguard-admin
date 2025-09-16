<?php
// Autoload classes
spl_autoload_register(fn($class) =>
    str_starts_with($class, 'WireGuardAdmin\\')
    && file_exists($f = __DIR__ . '/classes/' . str_replace('\\', '/', substr($class, 15)) . '.php')
    && require $f
);
// Redirect or show error
if (!file_exists('config.php')) {
    exit("Configuration file not found. Please create config.php from config_sample.php");
}
header("Location: login.php");
exit;
