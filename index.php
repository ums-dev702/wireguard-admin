<?php
if (!file_exists(__DIR__ . '/config.php')) {
    include __DIR__ . '/includes/missing_config.php';
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';

// Autoload classes
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'WireGuardAdmin\\')) {
        $file = __DIR__ . '/classes/' . str_replace('\\', '/', substr($class, 15)) . '.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }
    return false;
});

// Define routes
$routes = [
    '/'          => 'auth/login.php',
    '/login'           => 'auth/login.php',
    '/logout'          => 'auth/logout.php',
    '/dashboard'       => 'dashboard.php',
    '/create_interface' => 'create_interface.php',
    '/wg_peers'        => 'wg-peers.php',
    '/wg_status'       => 'wg_status.php',
    '/logs'           => 'logs.php',
    '/get_next_ip'    => 'get_next_ip.php',
    '/generate_mikrotik_script' => 'backend/generate_mikrotik_script.php',
    '/port_forwarding' => 'backend/port_forwarding_backend.php',
    '/manage_port_forwarding' => 'port_forwarding.php',
    '/check_peer_status' => 'backend/check_peer_status.php',
];

// Get request path (without query string)
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Detect base path (relative to document root)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($scriptDir !== '/' && strpos($requestUri, $scriptDir) === 0) {
    $request = substr($requestUri, strlen($scriptDir));
} else {
    $request = $requestUri;
}

// Normalize request
if ($request === '' || $request === false) {
    $request = '/';
}
if ($request !== '/' && substr($request, -1) === '/') {
    $request = rtrim($request, '/');
}

// Match route
if (isset($routes[$request])) {
    $filePath = __DIR__ . '/app/' . $routes[$request];
    if (file_exists($filePath)) {
        require $filePath;
    } else {
        http_response_code(404);
        include __DIR__ . '/includes/404.php';
    }
} else {
    http_response_code(404);
    include __DIR__ . '/includes/404.php';
}
