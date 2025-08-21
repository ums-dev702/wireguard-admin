<?php
// Application Settings
define('APP_NAME', 'WireGuard Admin');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'UTC');
define('WG_DEBUG', true);
define('DB_HOST', 'localhost');
define('DB_NAME', 'wireguard_admin');
define('DB_USER', 'TSHMainPassword');
define('DB_PASS', 'TSHMainPassword@46');
define('DB_PORT', 3306);
define('WG_IFACE', 'wg0');
define('SERVER_IP', 'wg-vpn.netxtreme.top');
define('SERVER_PORT', '51820');
define('SUBNET', '10.0.0.0/24');
// Security Settings
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('ENABLE_LOGGING', true);
define('MAX_LOGIN_ATTEMPTS', 5);

?>