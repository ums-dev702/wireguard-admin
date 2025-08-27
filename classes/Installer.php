<?php

namespace WireGuardAdmin;

class Installer
{
    private $db;
    private $steps;
    private $currentStep;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->initializeSteps();
    }

    private function initializeSteps()
    {
        $this->steps = [
            'welcome' => [
                'title' => 'Welcome to WireGuard Admin',
                'description' => 'Let\'s set up your VPN management system',
                'requirements' => [
                    'PHP >= 7.4',
                    'WireGuard installed',
                    'SQLite support',
                    'sudo access for www-data'
                ]
            ],
            'requirements' => [
                'title' => 'System Requirements Check',
                'description' => 'Checking if your system meets the requirements'
            ],
            'database' => [
                'title' => 'Database Setup',
                'description' => 'Creating database and tables'
            ],
            'admin_account' => [
                'title' => 'Admin Account',
                'description' => 'Create your administrator account'
            ],
            'wireguard_config' => [
                'title' => 'WireGuard Configuration',
                'description' => 'Configure WireGuard server settings'
            ],
            'security' => [
                'title' => 'Security Setup',
                'description' => 'Configure security settings'
            ],
            'complete' => [
                'title' => 'Installation Complete',
                'description' => 'Your WireGuard Admin panel is ready!'
            ]
        ];
    }

    public function isInstalled()
    {
        try {
            $status = $this->db->selectOne(
                "SELECT * FROM installation_status WHERE step = 'complete' AND status = 'completed'"
            );
            return $status !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getCurrentStep()
    {
        try {
            $completedSteps = $this->db->select(
                "SELECT step FROM installation_status WHERE status = 'completed' ORDER BY completed_at"
            );

            $completedStepNames = array_column($completedSteps, 'step');

            foreach (array_keys($this->steps) as $step) {
                if (!in_array($step, $completedStepNames)) {
                    return $step;
                }
            }

            return 'complete';
        } catch (\Exception $e) {
            return 'welcome';
        }
    }

    public function getStepInfo($step)
    {
        return $this->steps[$step] ?? null;
    }

    public function getAllSteps()
    {
        return $this->steps;
    }



    public function completeStep($step, $data = [])
    {
        try {
            if ($step === 'database') {
                // Use MySQL credentials from form
                $host = $data['db_host'] ?? 'localhost';
                $dbname = $data['db_name'] ?? 'wireguard_admin';
                $user = $data['db_user'] ?? 'root';
                $pass = $data['db_pass'] ?? '';
                $port = $data['db_port'] ?? 3306;

                // Try to connect and create tables
                try {
                    $db = new \WireGuardAdmin\Database($host, $dbname, $user, $pass, $port);
                } catch (\Exception $e) {
                    return ['success' => false, 'message' => 'MySQL connection failed: ' . $e->getMessage()];
                }

                // Test table creation
                $result = $this->processDatabaseStep([], $db);
                if ($result['success']) {
                    // Save config
                    $this->createConfigFile([
                        'db_host' => $host,
                        'db_name' => $dbname,
                        'db_user' => $user,
                        'db_pass' => $pass,
                        'db_port' => $port
                    ]);
                    $this->db = $db;
                    $this->markStepCompleted($step, $result['message']);
                }
                return $result;
            }

            $this->db->beginTransaction();
            switch ($step) {
                case 'admin_account':
                    $result = $this->processAdminAccountStep($data);
                    break;
                case 'wireguard_config':
                    $result = $this->processWireGuardConfigStep($data);
                    break;
                case 'security':
                    $result = $this->processSecurityStep($data);
                    break;
                default:
                    $result = ['success' => true, 'message' => 'Step completed'];
            }
            if ($result['success']) {
                $this->markStepCompleted($step, $result['message']);
                $this->db->commit();
            } else {
                $this->db->rollback();
            }
            return $result;
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }


    private function processDatabaseStep($data = [], $dbOverride = null)
    {
        $db = $dbOverride ?: $this->db;
        try {
            // Check if tables exist in MySQL
            $tables = ['users', 'peers', 'port_forwards', 'settings', 'audit_log', 'installation_status'];
            foreach ($tables as $table) {
                $result = $db->query("SHOW TABLES LIKE ?", [$table]);
                if (!$result->fetch()) {
                    return ['success' => false, 'message' => "Table {$table} not created or accessible."];
                }
            }
            return ['success' => true, 'message' => 'Database initialized successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Database setup failed: ' . $e->getMessage()];
        }
    }

    private function processAdminAccountStep($data)
    {
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $email = $data['email'] ?? '';

        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }

        try {
            $auth = new Auth($this->db);
            $userId = $auth->createUser($username, $password, $email, 'admin');

            return ['success' => true, 'message' => 'Admin account created successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to create admin account: ' . $e->getMessage()];
        }
    }

    private function processWireGuardConfigStep($data)
    {
        $serverIp = $data['server_ip'] ?? '';
        $serverPort = $data['server_port'] ?? '51820';
        $subnet = $data['subnet'] ?? '10.0.0.0/24';

        if (empty($serverIp)) {
            return ['success' => false, 'message' => 'Server IP is required'];
        }

        try {
            // Save settings
            $settings = [
                ['key' => 'server_ip', 'value' => $serverIp],
                ['key' => 'server_port', 'value' => $serverPort],
                ['key' => 'subnet', 'value' => $subnet],
                ['key' => 'interface_name', 'value' => 'wg0']
            ];

            foreach ($settings as $setting) {
                $this->db->query(
                    "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, ?)",
                    [$setting['key'], $setting['value'], date('Y-m-d H:i:s')]
                );
            }

            return ['success' => true, 'message' => 'WireGuard configuration saved'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Configuration failed: ' . $e->getMessage()];
        }
    }

    private function processSecurityStep($data)
    {
        $sessionTimeout = $data['session_timeout'] ?? '1800';
        $enableLogging = $data['enable_logging'] ?? '1';
        $maxLoginAttempts = $data['max_login_attempts'] ?? '5';

        try {
            $settings = [
                ['key' => 'session_timeout', 'value' => $sessionTimeout],
                ['key' => 'enable_logging', 'value' => $enableLogging],
                ['key' => 'max_login_attempts', 'value' => $maxLoginAttempts]
            ];

            foreach ($settings as $setting) {
                $this->db->query(
                    "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, ?)",
                    [$setting['key'], $setting['value'], date('Y-m-d H:i:s')]
                );
            }

            return ['success' => true, 'message' => 'Security settings configured'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Security setup failed: ' . $e->getMessage()];
        }
    }

    private function markStepCompleted($step, $message = '')
    {
        $this->db->query(
            "INSERT OR REPLACE INTO installation_status (step, status, message, completed_at) VALUES (?, ?, ?, ?)",
            [$step, 'completed', $message, date('Y-m-d H:i:s')]
        );
    }

    public function getInstallationProgress()
    {
        $totalSteps = count($this->steps);
        $completedSteps = $this->db->select(
            "SELECT COUNT(*) as count FROM installation_status WHERE status = 'completed'"
        );

        $completed = $completedSteps[0]['count'] ?? 0;
        return round(($completed / $totalSteps) * 100);
    }

    public function createConfigFile($data)
    {
        $configContent = "<?php\n\n";
        $configContent .= "// WireGuard Admin Configuration\n";
        $configContent .= "// Generated on " . date('Y-m-d H:i:s') . "\n\n";

        $configContent .= "// MySQL Database Configuration\n";
        $configContent .= "define('DB_HOST', '" . addslashes($data['db_host'] ?? 'localhost') . "');\n";
        $configContent .= "define('DB_NAME', '" . addslashes($data['db_name'] ?? 'wireguard_admin') . "');\n";
        $configContent .= "define('DB_USER', '" . addslashes($data['db_user'] ?? 'root') . "');\n";
        $configContent .= "define('DB_PASS', '" . addslashes($data['db_pass'] ?? '') . "');\n";
        $configContent .= "define('DB_PORT', " . intval($data['db_port'] ?? 3306) . ");\n\n";

        $configContent .= "// WireGuard Settings\n";
        $configContent .= "define('WG_CONF_PATH', '/etc/wireguard/wg0.conf');\n";
        $configContent .= "define('WG_IFACE', 'wg0');\n";
        if (!empty($data['server_ip'])) $configContent .= "define('SERVER_IP', '" . addslashes($data['server_ip']) . "');\n";
        if (!empty($data['server_port'])) $configContent .= "define('SERVER_PORT', '" . addslashes($data['server_port']) . "');\n";
        if (!empty($data['subnet'])) $configContent .= "define('SUBNET', '" . addslashes($data['subnet']) . "');\n";
        $configContent .= "\n";

        if (!empty($data['session_timeout'])) $configContent .= "define('SESSION_TIMEOUT', " . intval($data['session_timeout']) . ");\n";
        if (isset($data['enable_logging'])) $configContent .= "define('ENABLE_LOGGING', " . ($data['enable_logging'] ? 'true' : 'false') . ");\n";
        if (!empty($data['max_login_attempts'])) $configContent .= "define('MAX_LOGIN_ATTEMPTS', " . intval($data['max_login_attempts']) . ");\n";
        $configContent .= "\n";

        $configContent .= "// Application Settings\n";
        $configContent .= "define('APP_NAME', 'WireGuard Admin');\n";
        $configContent .= "define('APP_VERSION', '2.0.0');\n";
        $configContent .= "define('TIMEZONE', 'UTC');\n\n";

        $configContent .= "// Auto-loader\n";
        $configContent .= "spl_autoload_register(function (\$class) {\n";
        $configContent .= "    \$prefix = 'WireGuardAdmin\\\\';\n";
        $configContent .= "    \$baseDir = __DIR__ . '/classes/';\n";
        $configContent .= "    if (strpos(\$class, \$prefix) === 0) {\n";
        $configContent .= "        \$relativeClass = substr(\$class, strlen(\$prefix));\n";
        $configContent .= "        \$file = \$baseDir . str_replace('\\\\', '/', \$relativeClass) . '.php';\n";
        $configContent .= "        if (file_exists(\$file)) {\n";
        $configContent .= "            require \$file;\n";
        $configContent .= "        }\n";
        $configContent .= "    }\n";
        $configContent .= "});\n";

        file_put_contents(__DIR__ . '/../config.php', $configContent);
    }
}
