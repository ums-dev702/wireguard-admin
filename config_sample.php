<?php
// Application Settings
define('APP_NAME', 'WireGuard Admin'); // Application Settings
define('APP_VERSION', '1.0.0'); // Application Settings
define('TIMEZONE', 'UTC');
define('WG_DEBUG', true);
define('DB_HOST', 'localhost');
define('DB_NAME', 'wireguard_admin');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);
define('WG_PUBLIC_KEY', 'YOUR_WG_PUBLIC_KEY_HERE');
define('WG_PRIVATE_KEY', 'YOUR_WG_PRIVATE_KEY_HERE');
define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
define('TELEGRAM_CHAT_ID', 'YOUR_CHAT_ID_HERE');
define('SERVER_IP', 'wg-vpn.netxtreme.top');
// Security Settings
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('ENABLE_LOGGING', true);
define('MAX_LOGIN_ATTEMPTS', 5);

?>