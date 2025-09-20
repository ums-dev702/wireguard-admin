<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to a file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

try {
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/header.php';
    require_once __DIR__ . '/includes/functions.php';
    require_once __DIR__ . '/config.php';

    if (!is_authenticated()) {
        header('Location: /login.php');
        exit;
    }

    // Path to store server public key
    define('WG_ADMIN_CONF', __DIR__ . '/data/wg-admin.conf');

    // Ensure /data directory exists
    $data_dir = __DIR__ . '/data';
    if (!is_dir($data_dir)) {
        if (!mkdir($data_dir, 0755, true)) {
            throw new Exception("Failed to create directory: $data_dir");
        }
    }

    // Load server public key from file if it exists
    $server_public_key = '';
    if (file_exists(WG_ADMIN_CONF) && is_readable(WG_ADMIN_CONF)) {
        $server_public_key = trim(file_get_contents(WG_ADMIN_CONF));
    }

    // Handle form submission
    $local_ip = isset($_POST['local_ip']) ? htmlspecialchars(trim($_POST['local_ip'])) : '10.7.0.10';
    $mikrotik_public_key = isset($_POST['mikrotik_public_key']) ? htmlspecialchars(trim($_POST['mikrotik_public_key'])) : '';
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle server public key if submitted
        if (!empty($_POST['server_public_key'])) {
            $submitted_key = htmlspecialchars(trim($_POST['server_public_key']));
            // Validate server public key (44-character base64 string)
            if (preg_match('/^[A-Za-z0-9+\/=]{44}$/', $submitted_key)) {
                // Save to file securely
                if (!file_put_contents(WG_ADMIN_CONF, $submitted_key, LOCK_EX)) {
                    $errors[] = "Failed to save server public key to " . WG_ADMIN_CONF;
                } else {
                    $server_public_key = $submitted_key;
                    // Set file permissions
                    chmod(WG_ADMIN_CONF, 0660);
                }
            } else {
                $errors[] = "Invalid server public key. It should be a 44-character base64 string.";
            }
        }
        // Handle server public key reset
        if (isset($_POST['reset_server_key']) && file_exists(WG_ADMIN_CONF)) {
            if (!unlink(WG_ADMIN_CONF)) {
                $errors[] = "Failed to reset server public key.";
            } else {
                $server_public_key = '';
            }
        }
        // Validate local IP (10.7.0.2 to 10.7.0.254)
        if (empty($local_ip) || !preg_match('/^10\.7\.0\.(2|[3-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-4])$/', $local_ip)) {
            $errors[] = "Invalid IP address. It should be in the range 10.7.0.2 to 10.7.0.254.";
        }
        // Validate MikroTik public key if provided
        if (!empty($mikrotik_public_key) && !preg_match('/^[A-Za-z0-9+\/=]{44}$/', $mikrotik_public_key)) {
            $errors[] = "Invalid MikroTik public key. It should be a 44-character base64 string.";
        }
    }

    // Derive ports from the last octet of the local IP
    $ip_last_octet = (int) explode('.', $local_ip)[3];
    $winbox_port = $ip_last_octet >= 2 && $ip_last_octet <= 254 ? 6000 + $ip_last_octet : 6007;
    $api_port = $ip_last_octet >= 2 && $ip_last_octet <= 254 ? 7000 + $ip_last_octet : 7007;
    $webconfig_port = $ip_last_octet >= 2 && $ip_last_octet <= 254 ? 5000 + $ip_last_octet : 5007;

    // Validate derived ports
    if ($winbox_port < 6000 || $winbox_port > 6999) {
        $errors[] = "Derived Winbox port ($winbox_port) is out of range (6000–6999).";
    }
    if ($api_port < 7000 || $api_port > 7999) {
        $errors[] = "Derived API port ($api_port) is out of range (7000–7999).";
    }
    if ($webconfig_port < 5000 || $webconfig_port > 5999) {
        $errors[] = "Derived WebConfig port ($webconfig_port) is out of range (5000–5999).";
    }
} catch (Exception $e) {
    $errors[] = "An error occurred: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MikroTik Configuration - WireGuard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .table-row-hover {
            transition: background-color 0.2s ease;
        }
        .table-row-hover:hover {
            background-color: #f1f5f9;
        }
        pre {
            background-color: #f4f4f5;
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
        }
        code {
            font-family: monospace;
        }
        .copy-button {
            background-color: #2563eb;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .copy-button:hover {
            background-color: #1d4ed8;
        }
        .copy-button.copied {
            background-color: #16a34a;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php
    try {
        require_once __DIR__ . '/includes/header.php';
    } catch (Exception $e) {
        $errors[] = "Failed to load header: " . htmlspecialchars($e->getMessage());
    }
    ?>
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">WireGuard Setup: MikroTik ↔ Ubuntu Server</h1>

        <!-- Input Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 card-hover">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2-2 4-2 4m-4-2H6a2 2 0 01-2-2V7a2 2 0 012-2h4m4 2h4a2 2 0 012 2v6a2 2 0 01-2 2h-4m-4 0H6m6-6v6"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-700">Configuration Inputs</h2>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Error:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <?php if (empty($server_public_key)): ?>
                <div>
                    <label for="server_public_key" class="block text-sm font-medium text-gray-700">Server Public Key</label>
                    <input type="text" name="server_public_key" id="server_public_key" value="<?= $server_public_key ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" placeholder="Enter the WireGuard server public key">
                </div>
                <?php else: ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Server Public Key</label>
                    <p class="mt-1 text-gray-600">Stored securely in configuration file. <button type="submit" name="reset_server_key" class="text-blue-600 hover:underline">Reset Key</button></p>
                </div>
                <?php endif; ?>
                <div>
                    <label for="local_ip" class="block text-sm font-medium text-gray-700">MikroTik Local IP (10.7.0.x)</label>
                    <input type="text" name="local_ip" id="local_ip" value="<?= $local_ip ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" placeholder="e.g., 10.7.0.10">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Derived Ports</label>
                    <p class="text-sm text-gray-600">Winbox: <?= $winbox_port ?> (6000–6999), API: <?= $api_port ?> (7000–7999), WebConfig: <?= $webconfig_port ?> (5000–5999)</p>
                </div>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">Generate Configuration</button>
            </form>
        </div>

        <!-- Section 1: Generate and Configure WireGuard Keys on MikroTik -->
        <?php if (empty($errors) && !empty($server_public_key)): ?>
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 card-hover">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2-2 4-2 4m-4-2H6a2 2 0 01-2-2V7a2 2 0 012-2h4m4 2h4a2 2 0 012 2v6a2 2 0 01-2 2h-4m-4 0H6m6-6v6"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-700">1. Generate and Configure WireGuard Keys on MikroTik</h2>
            </div>
            <h3 class="text-lg font-semibold text-gray-600 mb-2">MikroTik Terminal Script</h3>
            <button type="button" class="copy-button mb-2" onclick="copyToClipboard('mikrotik-script')">Copy Script</button>
            <pre id="mikrotik-script"><code># 1. Create WireGuard interface if it doesn't exist
:if ([:len [/interface wireguard find where name="xtreme_my_wg"]] = 0) do={
    /interface wireguard add mtu=1420 name="xtreme_my_wg"
}

# 2. Assign local IP address
:if ([:len [/ip address find where address~"<?= $local_ip ?>/24"]] = 0) do={
    /ip address add address="<?= $local_ip ?>/24" interface="xtreme_my_wg" network="10.7.0.0"
}

# 3. Add peer (WireGuard server)
:if ([:len [/interface wireguard peers find where endpoint-address="<?= SERVER_IP ?>"]] = 0) do={
    /interface wireguard peers add \
        allowed-address="10.7.0.1/24" \
        endpoint-address="<?= SERVER_IP ?>" \
        endpoint-port=51820 \
        interface="xtreme_my_wg" \
        persistent-keepalive=1m \
        public-key="<?= $server_public_key ?>"
}

# 4. Output info
:put "\r\n==================== WIREGUARD SETUP COMPLETED ===================="
:put "Interface: xtreme_my_wg"
:put "Local IP: <?= $local_ip ?>/24"
:put "Peer Endpoint: <?= SERVER_IP ?>:51820"
:put "Peer Allowed Address: 10.7.0.1/24"
:put ("Local Public Key: " . [/interface wireguard get [find name="xtreme_my_wg"] value-name=public-key])
:put "===================================================================="
</code></pre>
            <p class="text-sm text-gray-600 mt-2"><strong>Note:</strong> Copy the printed public key from MikroTik and paste it below.</p>
            <form method="POST" class="mt-4">
                <input type="hidden" name="local_ip" value="<?= $local_ip ?>">
                <div>
                    <label for="mikrotik_public_key" class="block text-sm font-medium text-gray-700">MikroTik Public Key</label>
                    <input type="text" name="mikrotik_public_key" id="mikrotik_public_key" value="<?= $mikrotik_public_key ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" placeholder="Paste the MikroTik public key here">
                </div>
                <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">Update MikroTik Public Key</button>
            </form>
        </div>

        <!-- Section 2: Configure Peer on Ubuntu Server -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 card-hover">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-700">2. Configure Peer on Ubuntu Server (WireGuard)</h2>
            </div>
            <p class="text-gray-600 mb-2">On your Ubuntu WireGuard server, run the following command with the MikroTik public key:</p>
            <button type="button" class="copy-button mb-2" onclick="copyToClipboard('ubuntu-peer')">Copy Command</button>
            <pre id="ubuntu-peer"><code>sudo wg set wg0 peer <?= $mikrotik_public_key ?: '[Paste MikroTik Public Key Here]' ?> allowed-ips <?= $local_ip ?>/32</code></pre>
            <p class="text-sm text-gray-600 mt-2"><strong>Note:</strong> Ensure the MikroTik public key is entered above to update this command.</p>
        </div>

        <!-- Section 3: Port Forwarding Rules -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 card-hover">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m0-2v-2m0-2V7m6 10v-2m0-2v-2m0-2V7m-3 14v-14"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-700">3. Port Forwarding Rules</h2>
            </div>
            <!-- Winbox -->
            <h3 class="text-lg font-semibold text-gray-600 mb-2">A. Winbox (Port 8291 → <?= $winbox_port ?>)</h3>
            <button type="button" class="copy-button mb-2" onclick="copyToClipboard('winbox-rules')">Copy Rules</button>
            <pre id="winbox-rules"><code>iptables -t nat -A PREROUTING -p tcp --dport <?= $winbox_port ?> -j DNAT --to-destination <?= $local_ip ?>:8291
iptables -t nat -A POSTROUTING -p tcp -d <?= $local_ip ?> --dport 8291 -j MASQUERADE
iptables -A FORWARD -p tcp -d <?= $local_ip ?> --dport 8291 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
iptables -A FORWARD -p tcp -s <?= $local_ip ?> --sport 8291 -m state --state ESTABLISHED,RELATED -j ACCEPT</code></pre>
            <p class="text-sm text-gray-600 mt-2"><strong>Access via:</strong> <code><?= SERVER_IP ?>:<?= $winbox_port ?></code> or <code><a href="http://<?= SERVER_IP ?>:<?= $winbox_port ?>" target="_blank">http://<?= SERVER_IP ?>:<?= $winbox_port ?></a></code></p>
            <!-- API -->
            <h3 class="text-lg font-semibold text-gray-600 mb-2 mt-4">B. API (Port 8728 → <?= $api_port ?>)</h3>
            <button type="button" class="copy-button mb-2" onclick="copyToClipboard('api-rules')">Copy Rules</button>
            <pre id="api-rules"><code>iptables -t nat -A PREROUTING -p tcp --dport <?= $api_port ?> -j DNAT --to-destination <?= $local_ip ?>:8728
iptables -t nat -A POSTROUTING -p tcp -d <?= $local_ip ?> --dport 8728 -j MASQUERADE
iptables -A FORWARD -p tcp -d <?= $local_ip ?> --dport 8728 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
iptables -A FORWARD -p tcp -s <?= $local_ip ?> --sport 8728 -m state --state ESTABLISHED,RELATED -j ACCEPT</code></pre>
            <p class="text-sm text-gray-600 mt-2"><strong>Access via:</strong> <code><?= SERVER_IP ?>:<?= $api_port ?></code> or <code><a href="http://<?= SERVER_IP ?>:<?= $api_port ?>" target="_blank">http://<?= SERVER_IP ?>:<?= $api_port ?></a></code></p>
            <!-- WebConfig -->
            <h3 class="text-lg font-semibold text-gray-600 mb-2 mt-4">C. WebConfig (Port 80 → <?= $webconfig_port ?>)</h3>
            <button type="button" class="copy-button mb-2" onclick="copyToClipboard('webconfig-rules')">Copy Rules</button>
            <pre id="webconfig-rules"><code>iptables -t nat -A PREROUTING -p tcp --dport <?= $webconfig_port ?> -j DNAT --to-destination <?= $local_ip ?>:80
iptables -t nat -A POSTROUTING -p tcp -d <?= $local_ip ?> --dport 80 -j MASQUERADE
iptables -A FORWARD -p tcp -d <?= $local_ip ?> --dport 80 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
iptables -A FORWARD -p tcp -s <?= $local_ip ?> --sport 80 -m state --state ESTABLISHED,RELATED -j ACCEPT</code></pre>
            <p class="text-sm text-gray-600 mt-2"><strong>Access via:</strong> <code><?= SERVER_IP ?>:<?= $webconfig_port ?></code> or <code><a href="http://<?= SERVER_IP ?>:<?= $webconfig_port ?>" target="_blank">http://<?= SERVER_IP ?>:<?= $webconfig_port ?></a></code></p>
        </div>

        <!-- Section 4: How to Remove Winbox Port Forwarding -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 card-hover">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-700">4. How to Remove Winbox Port Forwarding</h2>
            </div>
            <p class="text-gray-600 mb-2">If you need to remove the Winbox forward:</p>
            <button type="button" class="copy-button mb-2" onclick="copyToClipboard('winbox-remove')">Copy Rules</button>
            <pre id="winbox-remove"><code>iptables -t nat -D PREROUTING -p tcp --dport <?= $winbox_port ?> -j DNAT --to-destination <?= $local_ip ?>:8291
iptables -t nat -D POSTROUTING -p tcp -d <?= $local_ip ?> --dport 8291 -j MASQUERADE
iptables -D FORWARD -p tcp -d <?= $local_ip ?> --dport 8291 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
iptables -D FORWARD -p tcp -s <?= $local_ip ?> --sport 8291 -m state --state ESTABLISHED,RELATED -j ACCEPT</code></pre>
        </div>

        <!-- Section 5: Summary -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 card-hover">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-teal-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2-12H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2z"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-700">5. Summary</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-600">
                            <th class="py-2 px-4">Service</th>
                            <th class="py-2 px-4">MikroTik IP</th>
                            <th class="py-2 px-4">Forwarded Port</th>
                            <th class="py-2 px-4">External Port</th>
                            <th class="py-2 px-4">Access From</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t table-row-hover">
                            <td class="py-2 px-4">Winbox</td>
                            <td class="py-2 px-4"><?= $local_ip ?>:8291</td>
                            <td class="py-2 px-4">✅</td>
                            <td class="py-2 px-4"><?= $winbox_port ?></td>
                            <td class="py-2 px-4"><code><?= SERVER_IP ?>:<?= $winbox_port ?></code> or <a href="http://<?= SERVER_IP ?>:<?= $winbox_port ?>" target="_blank"><?= SERVER_IP ?>:<?= $winbox_port ?></a></td>
                        </tr>
                        <tr class="border-t table-row-hover">
                            <td class="py-2 px-4">API</td>
                            <td class="py-2 px-4"><?= $local_ip ?>:8728</td>
                            <td class="py-2 px-4">✅</td>
                            <td class="py-2 px-4"><?= $api_port ?></td>
                            <td class="py-2 px-4"><code><?= SERVER_IP ?>:<?= $api_port ?></code> or <a href="http://<?= SERVER_IP ?>:<?= $api_port ?>" target="_blank"><?= SERVER_IP ?>:<?= $api_port ?></a></td>
                        </tr>
                        <tr class="border-t table-row-hover">
                            <td class="py-2 px-4">WebConfig</td>
                            <td class="py-2 px-4"><?= $local_ip ?>:80</td>
                            <td class="py-2 px-4">✅</td>
                            <td class="py-2 px-4"><?= $webconfig_port ?></td>
                            <td class="py-2 px-4"><code><?= SERVER_IP ?>:<?= $webconfig_port ?></code> or <a href="http://<?= SERVER_IP ?>:<?= $webconfig_port ?>" target="_blank">http://<?= SERVER_IP ?>:<?= $webconfig_port ?></a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    try {
        require_once __DIR__ . '/includes/footer.php';
    } catch (Exception $e) {
        $errors[] = "Failed to load footer: " . htmlspecialchars($e->getMessage());
    }
    ?>
    <script>
        function copyToClipboard(elementId) {
            const codeElement = document.getElementById(elementId).querySelector('code');
            const text = codeElement.innerText;
            navigator.clipboard.writeText(text).then(() => {
                const button = document.querySelector(`button[onclick="copyToClipboard('${elementId}')"]`);
                button.textContent = 'Copied!';
                button.classList.add('copied');
                setTimeout(() => {
                    button.textContent = 'Copy Script';
                    button.classList.remove('copied');
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }
    </script>
</body>
</html>