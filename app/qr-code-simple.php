<?php
require_once __DIR__ . '/../includes/auth.php';

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

// Sample configuration for demo purposes
$peer_name = "Demo Peer";
$config = generateSampleConfig($peer_id, $interface);

// Generate QR code URL
$qr_data_encoded = urlencode($config);
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . $qr_data_encoded;
$qr_url_google = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . $qr_data_encoded;

/**
 * Generate sample WireGuard configuration
 */
function generateSampleConfig($peer_id, $interface) {
    // Try to get server details from various sources
    $server_endpoint = getServerEndpoint();
    $server_port = "51820";
    $server_public_key = getServerPublicKey();
    
    // Generate a sample private key (in production, this should be properly generated and stored)
    $private_key = generateWireGuardPrivateKey();
    
    // Sample peer configuration based on peer ID
    $peer_ip = "10.0.0." . (2 + intval($peer_id)) . "/32";
    $allowed_ips = "10.0.0.0/24";  // Allow access to the entire VPN network
    
    $config = "[Interface]\n";
    $config .= "PrivateKey = {$private_key}\n";
    $config .= "Address = {$peer_ip}\n";
    $config .= "DNS = 1.1.1.1, 8.8.8.8\n";
    $config .= "\n";
    $config .= "[Peer]\n";
    $config .= "PublicKey = {$server_public_key}\n";
    $config .= "Endpoint = {$server_endpoint}:{$server_port}\n";
    $config .= "AllowedIPs = {$allowed_ips}\n";
    $config .= "PersistentKeepalive = 25\n";
    
    return $config;
}

/**
 * Get server endpoint
 */
function getServerEndpoint() {
    // Try to get from environment or config
    if (getenv('SERVER_ENDPOINT')) {
        return getenv('SERVER_ENDPOINT');
    }
    
    // Try to get public IP
    $public_ip = getPublicIP();
    if ($public_ip) {
        return $public_ip;
    }
    
    // Fallback
    return $_SERVER['HTTP_HOST'] ?? 'your-server.domain.com';
}

/**
 * Get server public key (customize this for your setup)
 */
function getServerPublicKey() {
    // In production, get this from your WireGuard server configuration
    // You can read it from /etc/wireguard/publickey or your database
    return 'YOUR_SERVER_PUBLIC_KEY_HERE';  // Replace with actual server public key
}

/**
 * Generate a proper WireGuard private key
 */
function generateWireGuardPrivateKey() {
    // Generate 32 random bytes and encode as base64
    return base64_encode(random_bytes(32));
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
                    'user_agent' => 'WireGuard QR Generator'
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WireGuard QR Code - Peer <?= htmlspecialchars($peer_id) ?></title>
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
            display: inline-block;
        }
        
        .config-text {
            font-family: 'Courier New', monospace;
            line-height: 1.4;
            white-space: pre-wrap;
        }
        
        @media print {
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            .print-area { 
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 100%;
                background: white;
                color: black;
                padding: 20px;
            }
            .qr-container {
                background: white;
                color: black;
            }
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
                Peer ID: <span class="text-white font-medium"><?= htmlspecialchars($peer_id) ?></span>
                • Interface: <span class="text-white font-medium"><?= htmlspecialchars($interface) ?></span>
            </p>
        </div>
        
        <!-- Print Area -->
        <div class="print-area">
            <!-- QR Code Section -->
            <div class="max-w-2xl mx-auto">
                <div class="glass-card p-6 rounded-xl mb-6">
                    <div class="text-center">
                        <h2 class="text-xl font-semibold mb-4 text-gray-200">
                            <i class="fas fa-mobile-alt mr-2"></i>Scan with WireGuard App
                        </h2>
                        
                        <div class="qr-container mb-4">
                            <img src="<?= htmlspecialchars($qr_url) ?>" 
                                 alt="WireGuard QR Code" 
                                 class="max-w-full h-auto"
                                 style="max-width: 300px;"
                                 onerror="this.src='<?= htmlspecialchars($qr_url_google) ?>'">
                        </div>
                        
                        <div class="text-sm text-gray-400 mb-4">
                            <p><i class="fas fa-info-circle mr-1"></i>Scan this QR code with the WireGuard mobile app</p>
                        </div>
                        
                        <div class="flex flex-wrap gap-3 justify-center print:hidden">
                            <button onclick="printQR()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                <i class="fas fa-print mr-2"></i>Print QR Code
                            </button>
                            <button onclick="downloadConfig()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                                <i class="fas fa-download mr-2"></i>Download Config
                            </button>
                            <button onclick="copyConfig()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                                <i class="fas fa-copy mr-2"></i>Copy Config
                            </button>
                            <button onclick="generateNewKey()" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors">
                                <i class="fas fa-refresh mr-2"></i>New Key
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Configuration Text -->
                <div class="glass-card p-6 rounded-xl mb-6 print:bg-white print:text-black">
                    <h3 class="text-lg font-semibold mb-4 text-gray-200 print:text-black">
                        <i class="fas fa-file-code mr-2"></i>Configuration File
                    </h3>
                    <div class="bg-gray-800 rounded-lg p-4 overflow-auto print:bg-gray-100 print:text-black">
                        <pre id="configText" class="config-text text-sm text-gray-300 print:text-black"><?= htmlspecialchars($config) ?></pre>
                    </div>
                </div>
                
                <!-- Instructions -->
                <div class="glass-card p-6 rounded-xl mb-6 print:hidden">
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
                
                <!-- Configuration Note -->
                <div class="glass-card p-6 rounded-xl mb-6 border-l-4 border-yellow-500 print:hidden">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-400 mr-3 mt-1"></i>
                        <div>
                            <h4 class="font-medium text-yellow-400 mb-2">Configuration Note</h4>
                            <p class="text-gray-300 text-sm mb-2">
                                This is a sample configuration. To use this properly, you need to:
                            </p>
                            <ul class="text-gray-400 text-sm space-y-1 list-disc list-inside">
                                <li>Replace "your-server.domain.com" with your actual server address</li>
                                <li>Replace "YOUR_SERVER_PUBLIC_KEY_HERE" with your server's public key</li>
                                <li>Ensure the IP addresses match your network configuration</li>
                                <li>Add the peer's public key to your server configuration</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Back Button -->
                <div class="text-center print:hidden">
                    <a href="wg-peers.php?interface=<?= urlencode($interface) ?>" 
                       class="inline-flex items-center px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Peers
                    </a>
                </div>
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
            a.download = 'peer_<?= htmlspecialchars($peer_id) ?>_wireguard.conf';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
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
        
        function generateNewKey() {
            // Generate a new private key and refresh the page
            location.reload();
        }
        
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-600' : 'bg-blue-600';
            toast.className = `fixed top-4 right-4 ${bgColor} text-white px-4 py-2 rounded-lg shadow-lg z-50`;
            toast.innerHTML = `<i class="fas fa-check mr-2"></i>${message}`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        // Auto-focus for easy keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.close();
            } else if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printQR();
            } else if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                downloadConfig();
            } else if (e.ctrlKey && e.key === 'c') {
                e.preventDefault();
                copyConfig();
            }
        });
    </script>
</body>
</html>