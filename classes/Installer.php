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
            $this->db->beginTransaction();

            switch ($step) {
                case 'database':
                    $result = $this->processDatabaseStep($data);
                    break;

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


    private function processDatabaseStep($data)
    {
        try {
            // Database tables are created in Database constructor
            // Just verify they exist
            $tables = ['users', 'peers', 'port_forwards', 'settings', 'audit_log', 'installation_status'];

            foreach ($tables as $table) {
                $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$table]);
                if (!$result->fetch()) {
                    return ['success' => false, 'message' => "Table {$table} not created"];
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

        $configContent .= "// Database\n";
        $configContent .= "define('DB_PATH', __DIR__ . '/data/wg-admin.db');\n\n";

        $configContent .= "// WireGuard Settings\n";
        $configContent .= "define('WG_CONF_PATH', '/etc/wireguard/wg0.conf');\n";
        $configContent .= "define('WG_IFACE', 'wg0');\n";
        $configContent .= "define('SERVER_IP', '{$data['server_ip']}');\n";
        $configContent .= "define('SERVER_PORT', '{$data['server_port']}');\n";
        $configContent .= "define('SUBNET', '{$data['subnet']}');\n\n";

        $configContent .= "// Security Settings\n";
        $configContent .= "define('SESSION_TIMEOUT', {$data['session_timeout']});\n";
        $configContent .= "define('ENABLE_LOGGING', " . ($data['enable_logging'] ? 'true' : 'false') . ");\n";
        $configContent .= "define('MAX_LOGIN_ATTEMPTS', {$data['max_login_attempts']});\n\n";

        $configContent .= "// Application Settings\n";
        $configContent .= "define('APP_NAME', 'WireGuard Admin');\n";
        $configContent .= "define('APP_VERSION', '2.0.0');\n";
        $configContent .= "define('TIMEZONE', 'UTC');\n\n";

        $configContent .= "// Auto-loader\n";
        $configContent .= "spl_autoload_register(function (\$class) {\n";
        $configContent .= "    \$prefix = 'WireGuardAdmin\\\\';\n";
        $configContent .= "    \$baseDir = __DIR__ . '/classes/';\n";
        $configContent .= "    \n";
        $configContent .= "    if (strpos(\$class, \$prefix) === 0) {\n";
        $configContent .= "        \$relativeClass = substr(\$class, strlen(\$prefix));\n";
        $configContent .= "        \$file = \$baseDir . str_replace('\\\\', '/', \$relativeClass) . '.php';\n";
        $configContent .= "        \n";
        $configContent .= "        if (file_exists(\$file)) {\n";
        $configContent .= "            require \$file;\n";
        $configContent .= "        }\n";
        $configContent .= "    }\n";
        $configContent .= "});\n";

        file_put_contents(__DIR__ . '/../config.php', $configContent);
    }
}
