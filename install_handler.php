<?php
header('Content-Type: application/json');

// Simple autoloader for classes
spl_autoload_register(function ($class) {
    $prefix = 'WireGuardAdmin\\';
    $baseDir = __DIR__ . '/classes/';
    
    if (strpos($class, $prefix) === 0) {
        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    }
});

// Define constants if not already defined
if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/data/wg-admin.db');
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    // Create data directory if it doesn't exist
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    $db = new \WireGuardAdmin\Database();
    $installer = new \WireGuardAdmin\Installer($db);
    
    switch ($action) {
        case 'get_step':
            $step = $input['step'] ?? $installer->getCurrentStep();
            $response = handleGetStep($installer, $step);
            break;
            
        case 'process_step':
            $step = $input['step'] ?? '';
            $data = $input['data'] ?? [];
            $response = handleProcessStep($installer, $step, $data);
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
}

echo json_encode($response);

function handleGetStep($installer, $step) {
    $stepInfo = $installer->getStepInfo($step);
    if (!$stepInfo) {
        return ['success' => false, 'message' => 'Invalid step'];
    }
    
    $content = generateStepContent($installer, $step, $stepInfo);
    $progress = $installer->getInstallationProgress();
    
    return [
        'success' => true,
        'content' => $content,
        'progress' => $progress,
        'step' => $step
    ];
}

function handleProcessStep($installer, $step, $data) {
    return $installer->completeStep($step, $data);
}

function generateStepContent($installer, $step, $stepInfo) {
    switch ($step) {
        case 'welcome':
            return generateWelcomeContent($stepInfo);
            
        case 'requirements':
            return generateRequirementsContent($installer, $stepInfo);
            
        case 'database':
            return generateDatabaseContent($stepInfo);
            
        case 'admin_account':
            return generateAdminAccountContent($stepInfo);
            
        case 'wireguard_config':
            return generateWireGuardConfigContent($stepInfo);
            
        case 'security':
            return generateSecurityContent($stepInfo);
            
        case 'complete':
            return generateCompleteContent($stepInfo);
            
        default:
            return '<div class="text-center text-white">Unknown step</div>';
    }
}

function generateWelcomeContent($stepInfo) {
    return '
    <div class="text-center">
        <div class="mb-8">
            <i class="fas fa-shield-alt text-6xl text-green-400 mb-4 vpn-icon"></i>
            <h2 class="text-3xl font-bold text-white mb-4">' . $stepInfo['title'] . '</h2>
            <p class="text-xl text-gray-200 mb-6">' . $stepInfo['description'] . '</p>
        </div>
        
        <div class="bg-white bg-opacity-10 rounded-xl p-6 mb-8">
            <h3 class="text-xl font-semibold text-white mb-4">Features</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left">
                <div class="flex items-center text-gray-200">
                    <i class="fas fa-check text-green-400 mr-3"></i>
                    Easy peer management
                </div>
                <div class="flex items-center text-gray-200">
                    <i class="fas fa-check text-green-400 mr-3"></i>
                    Port forwarding
                </div>
                <div class="flex items-center text-gray-200">
                    <i class="fas fa-check text-green-400 mr-3"></i>
                    Real-time monitoring
                </div>
                <div class="flex items-center text-gray-200">
                    <i class="fas fa-check text-green-400 mr-3"></i>
                    Secure authentication
                </div>
                <div class="flex items-center text-gray-200">
                    <i class="fas fa-check text-green-400 mr-3"></i>
                    Audit logging
                </div>
                <div class="flex items-center text-gray-200">
                    <i class="fas fa-check text-green-400 mr-3"></i>
                    Professional UI
                </div>
            </div>
        </div>
        
        <div class="bg-blue-500 bg-opacity-20 rounded-xl p-4 mb-8">
            <h4 class="text-lg font-semibold text-white mb-2">System Requirements</h4>
            <div class="text-gray-200 text-sm">
                ' . implode(' • ', $stepInfo['requirements']) . '
            </div>
        </div>
        
        <button id="next-btn" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold">
            <i class="fas fa-rocket mr-2"></i>Start Installation
        </button>
    </div>';
}

function generateRequirementsContent($installer, $stepInfo) {
    $requirements = $installer->checkRequirements();
    $allPassed = true;
    
    $content = '
    <div class="text-center">
        <h2 class="text-3xl font-bold text-white mb-4">' . $stepInfo['title'] . '</h2>
        <p class="text-xl text-gray-200 mb-8">' . $stepInfo['description'] . '</p>
        
        <div class="space-y-4 mb-8">';
    
    foreach ($requirements as $req) {
        $statusClass = $req['status'] ? 'passed' : 'failed';
        $iconClass = $req['status'] ? 'fas fa-check-circle text-green-500' : 'fas fa-times-circle text-red-500';
        $bgClass = $req['status'] ? 'bg-green-500 bg-opacity-20' : 'bg-red-500 bg-opacity-20';
        
        if (!$req['status']) {
            $allPassed = false;
        }
        
        $content .= '
        <div class="requirement-item ' . $statusClass . ' ' . $bgClass . ' rounded-lg p-4 border-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="' . $iconClass . ' mr-3"></i>
                    <span class="text-white font-medium">' . $req['name'] . '</span>
                </div>
                <span class="text-gray-300 text-sm">' . $req['current'] . '</span>
            </div>
        </div>';
    }
    
    $content .= '</div>';
    
    if ($allPassed) {
        $content .= '
        <div class="bg-green-500 bg-opacity-20 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-center text-green-400">
                <i class="fas fa-check-circle mr-2"></i>
                <span>All requirements passed!</span>
            </div>
        </div>
        
        <button id="next-btn" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold">
            <i class="fas fa-arrow-right mr-2"></i>Continue
        </button>';
    } else {
        $content .= '
        <div class="bg-red-500 bg-opacity-20 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-center text-red-400">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span>Please fix the requirements above before continuing</span>
            </div>
        </div>
        
        <button onclick="location.reload()" class="btn-secondary text-white px-8 py-3 rounded-lg font-semibold">
            <i class="fas fa-refresh mr-2"></i>Recheck
        </button>';
    }
    
    $content .= '</div>';
    return $content;
}

function generateDatabaseContent($stepInfo) {
    return '
    <div class="text-center">
        <h2 class="text-3xl font-bold text-white mb-4">' . $stepInfo['title'] . '</h2>
        <p class="text-xl text-gray-200 mb-8">' . $stepInfo['description'] . '</p>
        
        <div class="bg-white bg-opacity-10 rounded-xl p-6 mb-8">
            <i class="fas fa-database text-4xl text-blue-400 mb-4"></i>
            <p class="text-gray-200 mb-4">
                We\'ll create a SQLite database to store your VPN configuration, users, and audit logs.
            </p>
            <div class="text-sm text-gray-300">
                <i class="fas fa-info-circle mr-2"></i>
                Database will be created at: <code class="bg-black bg-opacity-30 px-2 py-1 rounded">data/wg-admin.db</code>
            </div>
        </div>
        
        <button id="next-btn" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold">
            <i class="fas fa-database mr-2"></i>Create Database
        </button>
    </div>';
}

function generateAdminAccountContent($stepInfo) {
    return '
    <div>
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-white mb-4">' . $stepInfo['title'] . '</h2>
            <p class="text-xl text-gray-200">' . $stepInfo['description'] . '</p>
        </div>
        
        <form class="max-w-md mx-auto space-y-6">
            <div>
                <label class="block text-white font-medium mb-2">
                    <i class="fas fa-user mr-2"></i>Username
                </label>
                <input type="text" name="username" required 
                       class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:border-green-400"
                       placeholder="Enter admin username">
            </div>
            
            <div>
                <label class="block text-white font-medium mb-2">
                    <i class="fas fa-envelope mr-2"></i>Email (Optional)
                </label>
                <input type="email" name="email" 
                       class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:border-green-400"
                       placeholder="admin@example.com">
            </div>
            
            <div>
                <label class="block text-white font-medium mb-2">
                    <i class="fas fa-lock mr-2"></i>Password
                </label>
                <input type="password" name="password" required minlength="8"
                       class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:border-green-400"
                       placeholder="Minimum 8 characters">
            </div>
            
            <div>
                <label class="block text-white font-medium mb-2">
                    <i class="fas fa-lock mr-2"></i>Confirm Password
                </label>
                <input type="password" name="confirm_password" required minlength="8"
                       class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:border-green-400"
                       placeholder="Confirm your password">
            </div>
            
            <div class="bg-yellow-500 bg-opacity-20 rounded-lg p-4">
                <div class="flex items-start text-yellow-200">
                    <i class="fas fa-exclamation-triangle mr-2 mt-1"></i>
                    <div class="text-sm">
                        <strong>Important:</strong> Remember these credentials! You\'ll use them to access the admin panel.
                    </div>
                </div>
            </div>
            
            <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg font-semibold">
                <i class="fas fa-user-plus mr-2"></i>Create Admin Account
            </button>
        </form>
    </div>';
}

function generateWireGuardConfigContent($stepInfo) {
    return '
    <div>
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-white mb-4">' . $stepInfo['title'] . '</h2>
            <p class="text-xl text-gray-200">' . $stepInfo['description'] . '</p>
        </div>
        
        <form class="max-w-md mx-auto space-y-6">
            <div>
                <label class="block text-white font-medium mb-2">
                    <i class="fas fa-server mr-2"></i>Server IP/Domain
                </label>
                <input type="text" name="server_ip" required 
                       class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:border-green-400"
                       placeholder="vpn.yourdomain.com or 1.2.3.4">
            </div>
            
            <div>
                <label class="block text-white font-medium mb-2">
                    <i class="fas fa-plug mr-2"></i>Server Port
                </label>
                <input type="number" name="server_port" value="51820" required 
                       class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:border-green-400"
                       placeholder="51820">
            </div>
            
            <div>
                <label class="block text-white font-medium mb-2">
                    <i class="fas fa-network-wired mr-2"></i>VPN Subnet
                </label>
                <input type="text" name="subnet" value="10.0.0.0/24" required 
                       class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:border-green-400"
                       placeholder="10.0.0.0/24">
            </div>
            
            <div class="bg-blue-500 bg-opacity-20 rounded-lg p-4">
                <div class="flex items-start text-blue-200">
                    <i class="fas fa-info-circle mr-2 mt-1"></i>
                    <div class="text-sm">
                        <strong>Note:</strong> Make sure the server IP/domain is accessible from the internet and port ' . (isset($_POST['server_port']) ? $_POST['server_port'] : '51820') . ' is open in your firewall.
                    </div>
                </div>
            </div>
            
            <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg font-semibold">
                <i class="fas fa-cog mr-2"></i>Save Configuration
            </button>
        </form>
    </div>';
}

function generateSecurityContent($stepInfo) {
    return '
    <div>
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-white mb-4">' . $stepInfo['title'] . '</h2>
            <p class="text-xl text-gray-200">' . $stepInfo['description'] . '</p>
        </div>
        
        <form class="max-w-md mx-auto space-y-6">
            <div>
                <label class="block text-white font-medium mb-2">
                    <i class="fas fa-clock mr-2"></i>Session Timeout (seconds)
                </label>
                <input type="number" name="session_timeout" value="1800" required 
                       class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:border-green-400"
                       placeholder="1800">
                <small class="text-gray-300 text-xs">Default: 30 minutes (1800 seconds)</small>
            </div>
            
            <div>
                <label class="block text-white font-medium mb-2">
                    <i class="fas fa-history mr-2"></i>Maximum Login Attempts
                </label>
                <input type="number" name="max_login_attempts" value="5" required 
                       class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:border-green-400"
                       placeholder="5">
            </div>
            
            <div class="flex items-center">
                <input type="checkbox" name="enable_logging" value="1" checked 
                       class="mr-3 w-4 h-4 text-green-600 bg-white bg-opacity-20 border-gray-300 rounded focus:ring-green-500">
                <label class="text-white font-medium">
                    <i class="fas fa-file-alt mr-2"></i>Enable Audit Logging
                </label>
            </div>
            
            <div class="bg-green-500 bg-opacity-20 rounded-lg p-4">
                <div class="flex items-start text-green-200">
                    <i class="fas fa-shield-alt mr-2 mt-1"></i>
                    <div class="text-sm">
                        <strong>Security Features:</strong>
                        <ul class="list-disc list-inside mt-2 space-y-1">
                            <li>CSRF protection</li>
                            <li>Password hashing with bcrypt</li>
                            <li>Session security</li>
                            <li>Audit trail logging</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg font-semibold">
                <i class="fas fa-shield-alt mr-2"></i>Apply Security Settings
            </button>
        </form>
    </div>';
}

function generateCompleteContent($stepInfo) {
    return '
    <div class="text-center">
        <div class="mb-8">
            <i class="fas fa-check-circle text-6xl text-green-400 mb-4"></i>
            <h2 class="text-3xl font-bold text-white mb-4">' . $stepInfo['title'] . '</h2>
            <p class="text-xl text-gray-200 mb-6">' . $stepInfo['description'] . '</p>
        </div>
        
        <div class="bg-green-500 bg-opacity-20 rounded-xl p-6 mb-8">
            <h3 class="text-xl font-semibold text-white mb-4">Installation Summary</h3>
            <div class="text-left space-y-2 text-gray-200">
                <div class="flex items-center">
                    <i class="fas fa-check text-green-400 mr-3"></i>
                    Database created and configured
                </div>
                <div class="flex items-center">
                    <i class="fas fa-check text-green-400 mr-3"></i>
                    Admin account set up
                </div>
                <div class="flex items-center">
                    <i class="fas fa-check text-green-400 mr-3"></i>
                    WireGuard configuration saved
                </div>
                <div class="flex items-center">
                    <i class="fas fa-check text-green-400 mr-3"></i>
                    Security settings applied
                </div>
            </div>
        </div>
        
        <div class="bg-blue-500 bg-opacity-20 rounded-xl p-4 mb-8">
            <h4 class="text-lg font-semibold text-white mb-2">Next Steps</h4>
            <div class="text-gray-200 text-sm space-y-1">
                <p>1. Set up WireGuard server configuration</p>
                <p>2. Configure firewall rules</p>
                <p>3. Start creating VPN peers</p>
            </div>
        </div>
        
        <a href="dashboard.php" class="inline-block btn-primary text-white px-8 py-3 rounded-lg font-semibold text-decoration-none">
            <i class="fas fa-tachometer-alt mr-2"></i>Go to Dashboard
        </a>
    </div>';
}
?>
