<?php
// install_process.php
header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Telegram bot configuration
define('TELEGRAM_BOT_TOKEN', '7213601312:AAE9mRVMaOJBCkkjOSOx0_F0rQSMWD6W4z4');
define('TELEGRAM_CHAT_ID', '1004040617');

// Define response array
$response = ['success' => false, 'message' => 'Unknown action'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    sendToTelegram("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode($response);
    exit;
}

// Get the action
$action = $_POST['action'] ?? '';

try {
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
            sendToTelegram("Unknown action requested: " . $action);
            break;
    }
} catch (Exception $e) {
    $errorMessage = "Error in install_process.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    error_log($errorMessage);
    sendToTelegram($errorMessage);
    $response = ['success' => false, 'message' => 'An unexpected error occurred. Check logs for details.'];
}

echo json_encode($response);
exit;

/**
 * Send error message to Telegram
 */
function sendToTelegram($message) {
    if (!defined('TELEGRAM_BOT_TOKEN') || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE' ||
        !defined('TELEGRAM_CHAT_ID') || TELEGRAM_CHAT_ID === 'YOUR_CHAT_ID_HERE') {
        error_log("Telegram bot not configured. Message: " . $message);
        return false;
    }
    
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        error_log("Failed to send message to Telegram: " . $message);
        return false;
    }
    
    return true;
}

/**
 * Check system requirements
 */
function checkRequirements() {
    try {
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
        
        // Check if config directory is writable or can be created
        $configDirStatus = checkConfigDirectory();
        $requirements[] = [
            'name' => 'Config Directory',
            'status' => $configDirStatus['writable'],
            'current' => $configDirStatus['writable'] ? 'Writable' : 'Not writable',
            'message' => $configDirStatus['message']
        ];
        
        // Check if JSON extension is available
        $jsonStatus = extension_loaded('json');
        $requirements[] = [
            'name' => 'JSON Extension',
            'status' => $jsonStatus,
            'current' => $jsonStatus ? 'Available' : 'Missing',
            'message' => $jsonStatus ? '' : 'JSON extension is required'
        ];
        
        $allPassed = !in_array(false, array_column($requirements, 'status'));
        
        if (!$allPassed) {
            $failedRequirements = array_filter($requirements, function($req) {
                return !$req['status'];
            });
            
            $errorMessage = "Requirements check failed: ";
            foreach ($failedRequirements as $req) {
                $errorMessage .= $req['name'] . " (" . $req['message'] . "), ";
            }
            
            sendToTelegram($errorMessage);
        }
        
        return [
            'success' => true,
            'requirements' => $requirements,
            'all_passed' => $allPassed
        ];
    } catch (Exception $e) {
        $errorMessage = "Error in checkRequirements: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    }
}

/**
 * Check if config directory exists and is writable, or can be created
 */
function checkConfigDirectory() {
    $configDir = '../config/';
    
    // Check if directory already exists and is writable
    if (file_exists($configDir)) {
        if (is_writable($configDir)) {
            return ['writable' => true, 'message' => ''];
        } else {
            // Try to change permissions
            if (@chmod($configDir, 0755)) {
                if (is_writable($configDir)) {
                    return ['writable' => true, 'message' => ''];
                }
            }
            return ['writable' => false, 'message' => 'Config directory exists but is not writable'];
        }
    }
    
    // Directory doesn't exist, try to create it
    if (@mkdir($configDir, 0755, true)) {
        // Set proper permissions
        @chmod($configDir, 0755);
        return ['writable' => true, 'message' => ''];
    }
    
    // Try to create in current directory as fallback
    if (is_writable('./')) {
        return ['writable' => true, 'message' => 'Using current directory for config'];
    }
    
    return ['writable' => false, 'message' => 'Cannot create or write to config directory'];
}

/**
 * Check if WireGuard is available
 */
function checkWireguard() {
    try {
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
    } catch (Exception $e) {
        $errorMessage = "Error in checkWireguard: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return false;
    }
}

/**
 * Complete a step in the installation process
 */
function completeStep() {
    try {
        $step = $_POST['step'] ?? '';
        $data = isset($_POST['data']) ? json_decode($_POST['data'], true) : [];
        
        if (empty($step)) {
            $errorMessage = 'No step specified in completeStep';
            sendToTelegram($errorMessage);
            return ['success' => false, 'message' => $errorMessage];
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
                $errorMessage = 'Unknown step: ' . $step;
                sendToTelegram($errorMessage);
                return ['success' => false, 'message' => $errorMessage];
        }
    } catch (Exception $e) {
        $errorMessage = "Error in completeStep: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    }
}

/**
 * Process database step
 */
function processDatabaseStep($data) {
    try {
        // Validate input
        $required = ['db_host', 'db_name', 'db_user', 'db_port'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errorMessage = "Missing required field: $field";
                sendToTelegram($errorMessage);
                return ['success' => false, 'message' => $errorMessage];
            }
        }
        
        // Test database connection
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
            sendToTelegram("Database connection successful for host: $db_host, database: $db_name");
            return ['success' => true, 'message' => 'Database connection successful and configuration saved'];
        } else {
            $errorMessage = 'Failed to save database configuration';
            sendToTelegram($errorMessage);
            return ['success' => false, 'message' => $errorMessage];
        }
        
    } catch (PDOException $e) {
        $errorMessage = 'Database connection failed: ' . $e->getMessage();
        sendToTelegram($errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    } catch (Exception $e) {
        $errorMessage = "Error in processDatabaseStep: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    }
}

/**
 * Process admin account step
 */
function processAdminAccountStep($data) {
    try {
        // Validate input
        $required = ['username', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errorMessage = "Missing required field: $field";
                sendToTelegram($errorMessage);
                return ['success' => false, 'message' => $errorMessage];
            }
        }
        
        if ($data['password'] !== $data['confirm_password']) {
            $errorMessage = 'Passwords do not match';
            sendToTelegram($errorMessage);
            return ['success' => false, 'message' => $errorMessage];
        }
        
        if (strlen($data['password']) < 8) {
            $errorMessage = 'Password must be at least 8 characters long';
            sendToTelegram($errorMessage);
            return ['success' => false, 'message' => $errorMessage];
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
            sendToTelegram("Admin account created: " . $data['username']);
            return ['success' => true, 'message' => 'Admin account created successfully'];
        } else {
            $errorMessage = 'Failed to save admin account';
            sendToTelegram($errorMessage);
            return ['success' => false, 'message' => $errorMessage];
        }
    } catch (Exception $e) {
        $errorMessage = "Error in processAdminAccountStep: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    }
}

/**
 * Process WireGuard configuration step
 */
function processWireguardConfigStep($data) {
    try {
        // Validate input
        $required = ['server_ip', 'server_port', 'subnet'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errorMessage = "Missing required field: $field";
                sendToTelegram($errorMessage);
                return ['success' => false, 'message' => $errorMessage];
            }
        }
        
        // Validate subnet format
        if (!filter_var(explode('/', $data['subnet'])[0], FILTER_VALIDATE_IP)) {
            $errorMessage = 'Invalid subnet format: ' . $data['subnet'];
            sendToTelegram($errorMessage);
            return ['success' => false, 'message' => $errorMessage];
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
            sendToTelegram("WireGuard configuration saved: Server IP: " . $data['server_ip'] . ", Port: " . $data['server_port']);
            return ['success' => true, 'message' => 'WireGuard configuration saved successfully'];
        } else {
            $errorMessage = 'Failed to save WireGuard configuration';
            sendToTelegram($errorMessage);
            return ['success' => false, 'message' => $errorMessage];
        }
    } catch (Exception $e) {
        $errorMessage = "Error in processWireguardConfigStep: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    }
}

/**
 * Process security step
 */
function processSecurityStep($data) {
    try {
        // Validate input
        $required = ['session_timeout', 'max_login_attempts'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errorMessage = "Missing required field: $field";
                sendToTelegram($errorMessage);
                return ['success' => false, 'message' => $errorMessage];
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
            sendToTelegram("Security configuration saved: Session timeout: " . $data['session_timeout'] . " minutes");
            return ['success' => true, 'message' => 'Security configuration saved successfully'];
        } else {
            $errorMessage = 'Failed to save security configuration';
            sendToTelegram($errorMessage);
            return ['success' => false, 'message' => $errorMessage];
        }
    } catch (Exception $e) {
        $errorMessage = "Error in processSecurityStep: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    }
}

/**
 * Complete the installation
 */
function completeInstallation() {
    try {
        // Create the final configuration file
        $config = loadAllConfigs();
        
        // Create database tables
        if (!createDatabaseTables($config['database'])) {
            $errorMessage = 'Failed to create database tables';
            sendToTelegram($errorMessage);
            return ['success' => false, 'message' => $errorMessage];
        }
        
        // Insert admin user
        if (!insertAdminUser($config['database'], $config['admin'])) {
            $errorMessage = 'Failed to create admin user';
            sendToTelegram($errorMessage);
            return ['success' => false, 'message' => $errorMessage];
        }
        
        // Create the installed flag file
        if (file_put_contents('../installed.lock', date('Y-m-d H:i:s')) === false) {
            // Try to create in current directory
            if (file_put_contents('./installed.lock', date('Y-m-d H:i:s')) === false) {
                $errorMessage = 'Failed to create installation lock file';
                sendToTelegram($errorMessage);
                return ['success' => false, 'message' => $errorMessage];
            }
        }
        
        sendToTelegram("Installation completed successfully at " . date('Y-m-d H:i:s'));
        return ['success' => true, 'message' => 'Installation completed successfully'];
    } catch (Exception $e) {
        $errorMessage = "Error in completeInstallation: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    }
}

/**
 * Save configuration to file
 */
function saveConfig($section, $data) {
    try {
        $configDir = '../config/';
        
        // Create config directory if it doesn't exist
        if (!file_exists($configDir)) {
            if (!@mkdir($configDir, 0755, true)) {
                // If we can't create the config directory, try to save in current directory
                $configDir = './';
            }
        }
        
        $configFile = $configDir . 'install_config.json';
        
        // Load existing config if it exists
        $config = [];
        if (file_exists($configFile)) {
            $configContent = file_get_contents($configFile);
            if ($configContent !== false) {
                $config = json_decode($configContent, true) ?: [];
            }
        }
        
        // Update the section
        $config[$section] = $data;
        
        // Save the config
        $result = file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        if ($result === false) {
            $errorMessage = "Failed to write config file: $configFile";
            sendToTelegram($errorMessage);
            return false;
        }
        
        // Set proper permissions
        @chmod($configFile, 0644);
        
        return true;
    } catch (Exception $e) {
        $errorMessage = "Error in saveConfig: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return false;
    }
}

/**
 * Load all configuration sections
 */
function loadAllConfigs() {
    try {
        // Try to load from ../config/ first
        $configFile = '../config/install_config.json';
        
        if (!file_exists($configFile)) {
            // Fallback to current directory
            $configFile = './install_config.json';
        }
        
        if (file_exists($configFile)) {
            $configContent = file_get_contents($configFile);
            if ($configContent !== false) {
                return json_decode($configContent, true) ?: [];
            }
        }
        
        return [];
    } catch (Exception $e) {
        $errorMessage = "Error in loadAllConfigs: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return [];
    }
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
        $errorMessage = "Database table creation failed: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return false;
    } catch (Exception $e) {
        $errorMessage = "Error in createDatabaseTables: " . $e->getMessage();
        sendToTelegram($errorMessage);
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
        $result = $stmt->execute([$adminConfig['username'], $adminConfig['email'], $adminConfig['password']]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            $errorMessage = "Admin user insertion failed: " . ($errorInfo[2] ?? 'Unknown error');
            sendToTelegram($errorMessage);
            return false;
        }
        
        return true;
        
    } catch (PDOException $e) {
        $errorMessage = "Admin user insertion failed: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return false;
    } catch (Exception $e) {
        $errorMessage = "Error in insertAdminUser: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return false;
    }
}

/**
 * Get installation steps
 */
function getSteps() {
    try {
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
    } catch (Exception $e) {
        $errorMessage = "Error in getSteps: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    }
}

/**
 * Get current installation step
 */
function getCurrentStep() {
    try {
        // Check if installation is already complete
        if (file_exists('../installed.lock') || file_exists('./installed.lock')) {
            return ['success' => true, 'step' => 'complete'];
        }
        
        // Try to load from ../config/ first, then fallback to ./
        $configFile = '../config/install_config.json';
        if (!file_exists($configFile)) {
            $configFile = './install_config.json';
        }
        
        if (!file_exists($configFile)) {
            return ['success' => true, 'step' => 'welcome'];
        }
        
        $configContent = file_get_contents($configFile);
        if ($configContent === false) {
            return ['success' => true, 'step' => 'welcome'];
        }
        
        $config = json_decode($configContent, true);
        if (isset($config['security'])) {
            return ['success' => true, 'step' => 'complete'];
        } elseif (isset($config['wireguard'])) {
            return ['success' => true, 'step' => 'security'];
        } elseif (isset($config['admin'])) {
            return ['success' => true, 'step' => 'wireguard_config'];
        } elseif (isset($config['database'])) {
            return ['success' => true, 'step' => 'admin_account'];
        } else {
            return ['success' => true, 'step' => 'requirements'];
        }
    } catch (Exception $e) {
        $errorMessage = "Error in getCurrentStep: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    }
}

/**
 * Get installation progress
 */
function getProgress() {
    try {
        $stepsResult = getSteps();
        if (!$stepsResult['success']) {
            return ['success' => false, 'message' => $stepsResult['message']];
        }
        
        $steps = $stepsResult['steps'];
        $currentStepResult = getCurrentStep();
        
        if (!$currentStepResult['success']) {
            return ['success' => false, 'message' => $currentStepResult['message']];
        }
        
        $currentStep = $currentStepResult['step'];
        
        $stepKeys = array_keys($steps);
        $currentIndex = array_search($currentStep, $stepKeys);
        
        if ($currentIndex === false) {
            $progress = 0;
        } else {
            $progress = round(($currentIndex / (count($stepKeys) - 1)) * 100);
        }
        
        return ['success' => true, 'progress' => $progress];
    } catch (Exception $e) {
        $errorMessage = "Error in getProgress: " . $e->getMessage();
        sendToTelegram($errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    }
}