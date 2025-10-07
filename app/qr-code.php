<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check authentication
if (!is_authenticated()) {
    header('Location: ../auth/login.php');
    exit;
}

// Get parameters
$peer_id = $_GET['peer_id'] ?? '';
$interface = $_GET['interface'] ?? '';

if (empty($peer_id) || empty($interface)) {
    header('Location: wg-peers.php?error=' . urlencode('Missing peer_id or interface parameter'));
    exit;
}

try {
    $db = get_db();
    
    // Get peer information
    $stmt = $db->prepare('SELECT * FROM wg_peers WHERE id = ?');
    $stmt->execute([$peer_id]);
    $peer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$peer) {
        header('Location: wg-peers.php?error=' . urlencode('Peer not found'));
        exit;
    }
    
    // Get interface information
    $interface_name = preg_replace('/^wg_/', '', $interface);
    $stmt = $db->prepare('SELECT * FROM interfaces WHERE name = ?');
    $stmt->execute([$interface_name]);
    $interface_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$interface_info) {
        header('Location: wg-peers.php?error=' . urlencode('Interface not found'));
        exit;
    }
    
    // Generate WireGuard client configuration
    $config = generateWireGuardConfig($peer, $interface_info, $interface);
    
    // Generate QR code using Google Charts API as fallback
    $qr_data_encoded = urlencode($config);
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . $qr_data_encoded;
    
    // Alternative QR code URL using chart.googleapis.com
    $qr_url_google = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . $qr_data_encoded;
    
} catch (Exception $e) {
    header('Location: wg-peers.php?error=' . urlencode('Error generating QR code: ' . $e->getMessage()));
    exit;
}

/**
 * Generate WireGuard client configuration
 */
function generateWireGuardConfig($peer, $interface_info, $interface_name) {
    $peer_name = $peer['name'] ?? 'Unnamed';
    $peer_allowed_ips = $peer['allowed_ips'] ?? '';
    
    // Interface information
    $interface_address = $interface_info['address'] ?? '10.0.0.1/24';
    $interface_port = $interface_info['port'] ?? '51820';
    $interface_public_key = $interface_info['public_key'] ?? '';
    
    // Get server endpoint
    $server_endpoint = getServerEndpoint();
    
    // Generate private key for this peer (you should store this in database)
    $private_key = generatePrivateKey();
    
    // Extract peer IP
    $peer_ip = extract_peer_ip($peer_allowed_ips);
    if ($peer_ip === 'N/A') {
        $peer_ip = '10.0.0.2';
    }
    
    // Parse interface to get network for AllowedIPs
    list($server_ip, $cidr) = explode('/', $interface_address);
    $network_mask = (0xFFFFFFFF << (32 - intval($cidr))) & 0xFFFFFFFF;
    $network = long2ip(ip2long($server_ip) & $network_mask);
    $allowed_ips = $network . '/' . $cidr;
    
    // Generate client configuration
    $config = "[Interface]\n";
    $config .= "PrivateKey = {$private_key}\n";
    $config .= "Address = {$peer_allowed_ips}\n";
    $config .= "DNS = 1.1.1.1, 8.8.8.8\n";
    $config .= "\n";
    $config .= "[Peer]\n";
    $config .= "PublicKey = {$interface_public_key}\n";
    $config .= "Endpoint = {$server_endpoint}:{$interface_port}\n";
    $config .= "AllowedIPs = {$allowed_ips}\n";
    $config .= "PersistentKeepalive = 25\n";
    
    return $config;
}

/**
 * Get server endpoint
 */
function getServerEndpoint() {
    // Try to get from config
    if (defined('SERVER_ENDPOINT') && !empty(constant('SERVER_ENDPOINT'))) {
        return constant('SERVER_ENDPOINT');
    }
    
    // Try to get public IP
    $public_ip = getPublicIP();
    if ($public_ip) {
        return $public_ip;
    }
    
    // Fallback to server IP
    return $_SERVER['SERVER_ADDR'] ?? 'YOUR_SERVER_IP';
}

/**
 * Get public IP
 */
function getPublicIP() {
    $services = [
        'https://api.ipify.org',
        'https://ifconfig.me/ip',
        'https://icanhazip.com'
    ];
    
    foreach ($services as $service) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'user_agent' => 'WireGuard Admin QR Generator'
                ]
            ]);
            
            $ip = @file_get_contents($service, false, $context);
            if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return trim($ip);
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    return null;
}

/**
 * Generate a private key (simplified - in production, use proper key generation)
 */
function generatePrivateKey() {
    // This is a placeholder - in production, you should generate proper WireGuard keys
    // and store them in the database
    return base64_encode(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WireGuard QR Code - <?= htmlspecialchars($peer['name'] ?? 'Unnamed') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass-card {
            background: rgba(17, 24, 39, 0.8);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(55, 65, 81, 0.3);
        }
        
        .qr-container {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .config-text {
            font-family: 'Courier New', monospace;
            line-height: 1.4;
            white-space: pre-wrap;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-blue-400 mb-2">
                <i class="fas fa-qrcode mr-3"></i>WireGuard QR Code
            </h1>
            <p class="text-gray-400">
                Peer: <span class="text-white font-medium"><?= htmlspecialchars($peer['name'] ?? 'Unnamed') ?></span>
                • Interface: <span class="text-white font-medium"><?= htmlspecialchars($interface) ?></span>
            </p>
        </div>
        
        <!-- QR Code Section -->
        <div class="max-w-2xl mx-auto">
            <div class="glass-card p-6 rounded-xl mb-6">
                <div class="text-center">
                    <h2 class="text-xl font-semibold mb-4 text-gray-200">
                        <i class="fas fa-mobile-alt mr-2"></i>Scan with WireGuard App
                    </h2>
                    
                    <div class="qr-container inline-block mb-4">
                        <img src="<?= htmlspecialchars($qr_url) ?>" 
                             alt="WireGuard QR Code" 
                             class="max-w-full h-auto"
                             onerror="this.src='<?= htmlspecialchars($qr_url_google) ?>'">
                    </div>
                    
                    <div class="text-sm text-gray-400 mb-4">
                        <p><i class="fas fa-info-circle mr-1"></i>Scan this QR code with the WireGuard mobile app</p>
                    </div>
                    
                    <div class="flex flex-wrap gap-3 justify-center">
                        <button onclick="printQR()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            <i class="fas fa-print mr-2"></i>Print QR Code
                        </button>
                        <button onclick="downloadConfig()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                            <i class="fas fa-download mr-2"></i>Download Config
                        </button>
                        <button onclick="copyConfig()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                            <i class="fas fa-copy mr-2"></i>Copy Config
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Configuration Text -->
            <div class="glass-card p-6 rounded-xl mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-200">
                    <i class="fas fa-file-code mr-2"></i>Configuration File
                </h3>
                <div class="bg-gray-800 rounded-lg p-4 overflow-auto">
                    <pre id="configText" class="config-text text-sm text-gray-300"><?= htmlspecialchars($config) ?></pre>
                </div>
            </div>
            
            <!-- Instructions -->
            <div class="glass-card p-6 rounded-xl mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-200">
                    <i class="fas fa-list-ol mr-2"></i>Setup Instructions
                </h3>
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold mr-3 mt-1">1</div>
                        <div>
                            <h4 class="font-medium text-white">Install WireGuard App</h4>
                            <p class="text-gray-400 text-sm">Download from App Store (iOS) or Google Play (Android)</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold mr-3 mt-1">2</div>
                        <div>
                            <h4 class="font-medium text-white">Scan QR Code</h4>
                            <p class="text-gray-400 text-sm">Open the app and tap "+" then "Create from QR code"</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold mr-3 mt-1">3</div>
                        <div>
                            <h4 class="font-medium text-white">Connect</h4>
                            <p class="text-gray-400 text-sm">Toggle the connection switch to connect to the VPN</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Back Button -->
            <div class="text-center">
                <a href="wg-peers.php?interface=<?= urlencode($interface) ?>" 
                   class="inline-flex items-center px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Peers
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function printQR() {
            window.print();
        }
        
        function downloadConfig() {
            const config = document.getElementById('configText').textContent;
            const blob = new Blob([config], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = '<?= htmlspecialchars($peer['name'] ?? 'peer') ?>_wireguard.conf';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            // Show success message
            showToast('Configuration downloaded!', 'success');
        }
        
        function copyConfig() {
            const config = document.getElementById('configText').textContent;
            navigator.clipboard.writeText(config).then(() => {
                showToast('Configuration copied to clipboard!', 'success');
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = config;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('Configuration copied!', 'success');
            });
        }
        
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-600' : 'bg-blue-600';
            toast.className = `fixed top-4 right-4 ${bgColor} text-white px-4 py-2 rounded-lg shadow-lg z-50`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        // Print styles
        const printStyle = document.createElement('style');
        printStyle.textContent = `
            @media print {
                body * { visibility: hidden; }
                .qr-container, .qr-container * { visibility: visible; }
                .qr-container { 
                    position: absolute; 
                    left: 50%; 
                    top: 50%; 
                    transform: translate(-50%, -50%);
                }
            }
        `;
        document.head.appendChild(printStyle);
    </script>
</body>
</html>