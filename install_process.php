<?php
// install_process.php
header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define response array
$response = ['success' => false, 'message' => 'Unknown action'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get the action
$action = $_POST['action'] ?? '';

// Handle different actions
switch ($action) {
    case 'check_requirements':
        $response = checkRequirements();
        break;
        
    case 'complete_step':
        $response = completeStep();
        break;
        
    case 'get_steps':
        $response = getSteps();
        break;
        
    case 'get_current_step':
        $response = getCurrentStep();
        break;
        
    case 'get_progress':
        $response = getProgress();
        break;
        
    default:
        $response['message'] = 'Unknown action: ' . $action;
        break;
}

echo json_encode($response);
exit;

/**
 * Check system requirements
 */
function checkRequirements() {
    $requirements = [];
    
    // Check PHP version (7.4+)
    $phpVersion = phpversion();
    $phpStatus = version_compare($phpVersion, '7.4.0', '>=');
    $requirements[] = [
        'name' => 'PHP 7.4+',
        'status' => $phpStatus,
        'current' => $phpVersion,
        'message' => $phpStatus ? '' : 'PHP 7.4 or higher is required'
    ];
    
    // Check if WireGuard is available (simplified check)
    $wireguardStatus = checkWireguard();
    $requirements[] = [
        'name' => 'WireGuard',
        'status' => $wireguardStatus,
        'current' => $wireguardStatus ? 'Detected' : 'Not detected',
        'message' => $wireguardStatus ? '' : 'WireGuard is not installed or not accessible'
    ];
    
    // Check MySQL extension
    $mysqlStatus = extension_loaded('mysqli') || extension_loaded('pdo_mysql');
    $requirements[] = [
        'name' => 'MySQL Extension',
        'status' => $mysqlStatus,
        'current' => $mysqlStatus ? 'Available' : 'Missing',
        'message' => $mysqlStatus ? '' : 'MySQLi or PDO MySQL extension is required'
    ];
    
    // Check if config directory is writable
    $writableStatus = is_writable('../config/') || is_writable('./');
    $requirements[] = [
        'name' => 'Write Permissions',
        'status' => $writableStatus,
        'current' => $writableStatus ? 'Writable' : 'Not writable',
        'message' => $writableStatus ? '' : 'Config directory needs to be writable'
    ];
    
    // Check if JSON extension is available
    $jsonStatus = extension_loaded('json');
    $requirements[] = [
        'name' => 'JSON Extension',
        'status' => $jsonStatus,
        'current' => $jsonStatus ? 'Available' : 'Missing',
        'message' => $jsonStatus ? '' : 'JSON extension is required'
    ];
    
    return [
        'success' => true,
        'requirements' => $requirements,
        'all_passed' => !in_array(false, array_column($requirements, 'status'))
    ];
}

/**
 * Check if WireGuard is available
 */
function checkWireguard() {
    // Try different methods to detect WireGuard
    
    // Method 1: Check if wg command exists
    if (function_exists('shell_exec')) {
        $output = @shell_exec('which wg 2>/dev/null');
        if (!empty($output)) return true;
    }
    
    // Method 2: Check if module is loaded
    if (@file_exists('/proc/modules')) {
        $modules = @file_get_contents('/proc/modules');
        if (strpos($modules, 'wireguard') !== false) return true;
    }
    
    // Method 3: Check if config directory exists
    if (@is_dir('/etc/wireguard')) return true;
    
    return false;
}

/**
 * Complete a step in the installation process
 */
function completeStep() {
    $step = $_POST['step'] ?? '';
    $data = isset($_POST['data']) ? json_decode($_POST['data'], true) : [];
    
    if (empty($step)) {
        return ['success' => false, 'message' => 'No step specified'];
    }
    
    // Handle different steps
    switch ($step) {
        case 'welcome':
            // Nothing to process for welcome step
            return ['success' => true, 'message' => 'Welcome step completed'];
            
        case 'database':
            return processDatabaseStep($data);
            
        case 'admin_account':
            return processAdminAccountStep($data);
            
        case 'wireguard_config':
            return processWireguardConfigStep($data);
            
        case 'security':
            return processSecurityStep($data);
            
        case 'complete':
            return completeInstallation();
            
        default:
            return ['success' => false, 'message' => 'Unknown step: ' . $step];
    }
}

/**
 * Process database step
 */
function processDatabaseStep($data) {
    // Validate input
    $required = ['db_host', 'db_name', 'db_user', 'db_port'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }
    
    // Test database connection
    try {
        $db_host = $data['db_host'];
        $db_name = $data['db_name'];
        $db_user = $data['db_user'];
        $db_pass = $data['db_pass'] ?? '';
        $db_port = $data['db_port'];
        
        // Try to connect
        $dsn = "mysql:host=$db_host;port=$db_port;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        
        // Check if database exists, create if not
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");
        
        // Save database config
        $config = [
            'db_host' => $db_host,
            'db_name' => $db_name,
            'db_user' => $db_user,
            'db_pass' => $db_pass,
            'db_port' => $db_port
        ];
        
        if (saveConfig('database', $config)) {
            return ['success' => true, 'message' => 'Database connection successful and configuration saved'];
        } else {
            return ['success' => false, 'message' => 'Failed to save database configuration'];
        }
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
    }
}

/**
 * Process admin account step
 */
function processAdminAccountStep($data) {
    // Validate input
    $required = ['username', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        return ['success' => false, 'message' => 'Passwords do not match'];
    }
    
    if (strlen($data['password']) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
    }
    
    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Save admin account info
    $adminConfig = [
        'username' => $data['username'],
        'email' => $data['email'] ?? '',
        'password' => $hashedPassword,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    if (saveConfig('admin', $adminConfig)) {
        return ['success' => true, 'message' => 'Admin account created successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to save admin account'];
    }
}

/**
 * Process WireGuard configuration step
 */
function processWireguardConfigStep($data) {
    // Validate input
    $required = ['server_ip', 'server_port', 'subnet'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }
    
    // Validate subnet format
    if (!filter_var(explode('/', $data['subnet'])[0], FILTER_VALIDATE_IP)) {
        return ['success' => false, 'message' => 'Invalid subnet format'];
    }
    
    // Save WireGuard configuration
    $wgConfig = [
        'server_ip' => $data['server_ip'],
        'server_port' => $data['server_port'],
        'subnet' => $data['subnet'],
        'dns_servers' => '1.1.1.1, 8.8.8.8',
        'allowed_ips' => '0.0.0.0/0, ::/0',
        'persistent_keepalive' => '25',
        'mtu' => '1420'
    ];
    
    if (saveConfig('wireguard', $wgConfig)) {
        return ['success' => true, 'message' => 'WireGuard configuration saved successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to save WireGuard configuration'];
    }
}

/**
 * Process security step
 */
function processSecurityStep($data) {
    // Validate input
    $required = ['session_timeout', 'max_login_attempts'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }
    
    // Save security configuration
    $securityConfig = [
        'session_timeout' => intval($data['session_timeout']),
        'max_login_attempts' => intval($data['max_login_attempts']),
        'enable_logging' => isset($data['enable_logging']) && $data['enable_logging'] == '1',
        'csrf_protection' => true,
        'password_hashing' => 'bcrypt'
    ];
    
    if (saveConfig('security', $securityConfig)) {
        return ['success' => true, 'message' => 'Security configuration saved successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to save security configuration'];
    }
}

/**
 * Complete the installation
 */
function completeInstallation() {
    // Create the final configuration file
    $config = loadAllConfigs();
    
    // Create database tables
    if (!createDatabaseTables($config['database'])) {
        return ['success' => false, 'message' => 'Failed to create database tables'];
    }
    
    // Insert admin user
    if (!insertAdminUser($config['database'], $config['admin'])) {
        return ['success' => false, 'message' => 'Failed to create admin user'];
    }
    
    // Create the installed flag file
    if (file_put_contents('../installed.lock', date('Y-m-d H:i:s')) === false) {
        return ['success' => false, 'message' => 'Failed to create installation lock file'];
    }
    
    return ['success' => true, 'message' => 'Installation completed successfully'];
}

/**
 * Save configuration to file
 */
function saveConfig($section, $data) {
    $configDir = '../config/';
    
    // Create config directory if it doesn't exist
    if (!file_exists($configDir)) {
        if (!mkdir($configDir, 0755, true)) {
            return false;
        }
    }
    
    $configFile = $configDir . 'install_config.json';
    
    // Load existing config if it exists
    $config = [];
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true) ?: [];
    }
    
    // Update the section
    $config[$section] = $data;
    
    // Save the config
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Load all configuration sections
 */
function loadAllConfigs() {
    $configFile = '../config/install_config.json';
    
    if (file_exists($configFile)) {
        return json_decode(file_get_contents($configFile), true) ?: [];
    }
    
    return [];
}

/**
 * Create database tables
 */
function createDatabaseTables($dbConfig) {
    try {
        $dsn = "mysql:host={$dbConfig['db_host']};port={$dbConfig['db_port']};dbname={$dbConfig['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['db_user'], $dbConfig['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        // SQL to create tables
        $sql = [
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100),
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'user') DEFAULT 'user',
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            "CREATE TABLE IF NOT EXISTS peers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                public_key TEXT NOT NULL,
                private_key TEXT NOT NULL,
                preshared_key TEXT,
                allowed_ips VARCHAR(255),
                endpoint VARCHAR(255),
                dns_servers VARCHAR(255),
                persistent_keepalive INT DEFAULT 25,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            "CREATE TABLE IF NOT EXISTS server_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                server_ip VARCHAR(45) NOT NULL,
                server_port INT NOT NULL,
                subnet VARCHAR(18) NOT NULL,
                dns_servers VARCHAR(255),
                mtu INT DEFAULT 1420,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            "CREATE TABLE IF NOT EXISTS audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];
        
        // Execute each SQL statement
        foreach ($sql as $statement) {
            $pdo->exec($statement);
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Database table creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Insert admin user into database
 */
function insertAdminUser($dbConfig, $adminConfig) {
    try {
        $dsn = "mysql:host={$dbConfig['db_host']};port={$dbConfig['db_port']};dbname={$dbConfig['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['db_user'], $dbConfig['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        // Insert admin user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        return $stmt->execute([$adminConfig['username'], $adminConfig['email'], $adminConfig['password']]);
        
    } catch (PDOException $e) {
        error_log("Admin user insertion failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get installation steps
 */
function getSteps() {
    $steps = [
        'welcome' => [
            'title' => 'Welcome',
            'icon' => 'fas fa-home',
            'description' => 'Welcome to WireGuard Admin installation wizard'
        ],
        'requirements' => [
            'title' => 'Requirements',
            'icon' => 'fas fa-check-circle',
            'description' => 'Checking system requirements'
        ],
        'database' => [
            'title' => 'Database',
            'icon' => 'fas fa-database',
            'description' => 'Database setup'
        ],
        'admin_account' => [
            'title' => 'Admin Account',
            'icon' => 'fas fa-user-shield',
            'description' => 'Create admin account'
        ],
        'wireguard_config' => [
            'title' => 'Configuration',
            'icon' => 'fas fa-cog',
            'description' => 'WireGuard configuration'
        ],
        'security' => [
            'title' => 'Security',
            'icon' => 'fas fa-lock',
            'description' => 'Security settings'
        ],
        'complete' => [
            'title' => 'Complete',
            'icon' => 'fas fa-check',
            'description' => 'Installation complete'
        ]
    ];
    
    return ['success' => true, 'steps' => $steps];
}

/**
 * Get current installation step
 */
function getCurrentStep() {
    // Check if installation is already complete
    if (file_exists('../installed.lock')) {
        return ['success' => true, 'step' => 'complete'];
    }
    
    // Check config file to determine current step
    $configFile = '../config/install_config.json';
    
    if (!file_exists($configFile)) {
        return ['success' => true, 'step' => 'welcome'];
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    
    if (isset($config['security'])) {
        return ['success' => true, 'step' => 'complete'];
    } elseif (isset($config['wireguard_config'])) {
        return ['success' => true, 'step' => 'security'];
    } elseif (isset($config['admin_account'])) {
        return ['success' => true, 'step' => 'wireguard_config'];
    } elseif (isset($config['database'])) {
        return ['success' => true, 'step' => 'admin_account'];
    } else {
        return ['success' => true, 'step' => 'requirements'];
    }
}

/**
 * Get installation progress
 */
function getProgress() {
    $steps = getSteps()['steps'];
    $currentStep = getCurrentStep()['step'];
    
    $stepKeys = array_keys($steps);
    $currentIndex = array_search($currentStep, $stepKeys);
    
    if ($currentIndex === false) {
        $progress = 0;
    } else {
        $progress = round(($currentIndex / (count($stepKeys) - 1)) * 100);
    }
    
    return ['success' => true, 'progress' => $progress];
}