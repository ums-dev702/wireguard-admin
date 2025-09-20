<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

if (!is_authenticated()) {
    header('Location: /login.php');
    exit;
}

// Handle peer creation
if (isset($_POST['add_peer'])) {
    $allowed_ips = $_POST['allowed_ips'] ?? '';
    $result = add_wg_peer($allowed_ips);
    $new_peer = $result;
}

// Handle peer removal
if (isset($_GET['remove_peer'])) {
    $public_key = $_GET['remove_peer'];
    remove_wg_peer($public_key);
    header('Location: /peers-and-forwarding.php');
    exit;
}

// Handle rule creation
if (isset($_POST['add_rule'])) {
    $ext_port = $_POST['ext_port'] ?? '';
    $int_ip = $_POST['int_ip'] ?? '';
    $int_port = $_POST['int_port'] ?? '';
    if ($ext_port && $int_ip && $int_port) {
        add_port_rule($ext_port, $int_ip, $int_port);
        header('Location: /peers-and-forwarding.php');
        exit;
    }
}

// Handle rule removal
if (isset($_GET['remove_rule'])) {
    $rule_num = $_GET['remove_rule'];
    remove_port_rule($rule_num);
    header('Location: /peers-and-forwarding.php');
    exit;
}

$peers = get_wg_peers();
$port_rules = get_port_rules();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Peers & Port Forwarding - WireGuard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .card-hover { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }
        .table-row-hover { transition: background-color 0.2s ease; }
        .table-row-hover:hover { background-color: #f1f5f9; }
        .modal-enter { transition: opacity 0.3s ease, transform 0.3s ease; }
        .modal-enter-active { opacity: 1; transform: translateY(0); }
        .modal-exit { opacity: 0; transform: translateY(-10px); }
        .alert-slide { animation: slideIn 0.5s ease-out; }
        @keyframes slideIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row gap-8">
            <!-- VPN Peers Section -->
            <div class="flex-1">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                        <svg class="w-8 h-8 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        VPN Peers
                    </h1>
                    <button class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors flex items-center" data-bs-toggle="modal" data-bs-target="#addPeerModal">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Peer
                    </button>
                </div>
                <?php if (isset($new_peer)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-800 p-4 mb-6 rounded-r alert-slide" role="alert">
                    <h5 class="font-semibold">New Peer Created Successfully!</h5>
                    <p><strong>Private Key:</strong> <?= htmlspecialchars($new_peer['private_key']) ?></p>
                    <p><strong>Public Key:</strong> <?= htmlspecialchars($new_peer['public_key']) ?></p>
                    <p class="text-red-600 font-medium">Save this private key now - it won't be shown again!</p>
                </div>
                <?php endif; ?>
                <div class="bg-white rounded-xl shadow-md card-hover">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-gray-600">
                                        <th class="py-3 px-4">Public Key</th>
                                        <th class="py-3 px-4">Allowed IPs</th>
                                        <th class="py-3 px-4">Transfer</th>
                                        <th class="py-3 px-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($peers)): ?>
                                    <tr>
                                        <td colspan="4" class="py-4 px-4 text-center text-gray-500">No peers configured</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($peers as $peer): ?>
                                    <tr class="border-t table-row-hover">
                                        <td class="py-3 px-4" title="<?= htmlspecialchars($peer['public_key']) ?>">
                                            <?= htmlspecialchars(substr($peer['public_key'], 0, 16)) ?>...
                                        </td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($peer['allowed_ips']) ?></td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($peer['transfer']) ?></td>
                                        <td class="py-3 px-4">
                                            <a href="?remove_peer=<?= urlencode($peer['public_key']) ?>" class="bg-red-600 text-white py-1 px-3 rounded-lg hover:bg-red-700 transition-colors" onclick="return confirm('Are you sure?')">
                                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4M7 7h10"></path>
                                                </svg>
                                                Remove
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Add Peer Modal -->
                <div class="modal fade" id="addPeerModal" tabindex="-1" aria-labelledby="addPeerModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-xl shadow-lg">
                            <form method="POST">
                                <div class="modal-header border-b p-6">
                                    <h5 class="text-xl font-semibold text-gray-800" id="addPeerModalLabel">Add New VPN Peer</h5>
                                    <button type="button" class="text-gray-400 hover:text-gray-600" data-bs-dismiss="modal" aria-label="Close">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                                <div class="modal-body p-6">
                                    <div class="mb-4">
                                        <label for="allowed_ips" class="block text-sm font-medium text-gray-700">Allowed IPs</label>
                                        <input type="text" class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" id="allowed_ips" name="allowed_ips" required placeholder="e.g., 10.7.0. peered">
                                        <p class="mt-1 text-sm text-gray-500">The IP address(es) this peer will be assigned</p>
                                    </div>
                                </div>
                                <div class="modal-footer border-t p-6 flex justify-end space-x-2">
                                    <button type="button" class="bg-gray-200 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-300 transition-colors" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_peer" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">Create Peer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Port Forwarding Section -->
            <div class="flex-1">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                        <svg class="w-8 h-8 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m0-2v-2m0-2V7m6 10v-2m0-2v-2m0-2V7m-3 14v-14"></path>
                        </svg>
                        Port Forwarding
                    </h1>
                    <button class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors flex items-center" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Rule
                    </button>
                </div>
                <div class="bg-white rounded-xl shadow-md card-hover">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-gray-600">
                                        <th class="py-3 px-4">#</th>
                                        <th class="py-3 px-4">External Port</th>
                                        <th class="py-3 px-4">Internal IP</th>
                                        <th class="py-3 px-4">Internal Port</th>
                                        <th class="py-3 px-4">Public URL</th>
                                        <th class="py-3 px-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($port_rules)): ?>
                                    <tr>
                                        <td colspan="6" class="py-4 px-4 text-center text-gray-500">No port forwarding rules configured</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($port_rules as $rule): ?>
                                    <tr class="border-t table-row-hover">
                                        <td class="py-3 px-4"><?= htmlspecialchars($rule['num']) ?></td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($rule['ext_port']) ?></td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($rule['int_ip']) ?></td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($rule['int_port']) ?></td>
                                        <td class="py-3 px-4">
                                            <a href="http://<?= htmlspecialchars(SERVER_IP) ?>:<?= htmlspecialchars($rule['ext_port']) ?>" target="_blank" class="text-blue-600 hover:underline">
                                                http://<?= htmlspecialchars(SERVER_IP) ?>:<?= htmlspecialchars($rule['ext_port']) ?>
                                            </a>
                                        </td>
                                        <td class="py-3 px-4">
                                            <a href="?remove_rule=<?= htmlspecialchars($rule['num']) ?>" class="bg-red-600 text-white py-1 px-3 rounded-lg hover:bg-red-700 transition-colors" onclick="return confirm('Are you sure?')">
                                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4M7 7h10"></path>
                                                </svg>
                                                Remove
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Add Rule Modal -->
                <div class="modal fade" id="addRuleModal" tabindex="-1" aria-labelledby="addRuleModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-xl shadow-lg">
                            <form method="POST">
                                <div class="modal-header border-b p-6">
                                    <h5 class="text-xl font-semibold text-gray-800" id="addRuleModalLabel">Add Port Forwarding Rule</h5>
                                    <button type="button" class="text-gray-400 hover:text-gray-600" data-bs-dismiss="modal" aria-label="Close">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                                <div class="modal-body p-6">
                                    <div class="mb-4">
                                        <label for="ext_port" class="block text-sm font-medium text-gray-700">External Port</label>
                                        <input type="number" class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" id="ext_port" name="ext_port" min="1024" max="65535" required placeholder="1024-65535">
                                    </div>
                                    <div class="mb-4">
                                        <label for="int_ip" class="block text-sm font-medium text-gray-700">Internal IP</label>
                                        <input type="text" class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" id="int_ip" name="int_ip" required placeholder="e.g., 10.7.0.3">
                                    </div>
                                    <div class="mb-4">
                                        <label for="int_port" class="block text-sm font-medium text-gray-700">Internal Port</label>
                                        <input type="number" class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" id="int_port" name="int_port" min="1" max="65535" required placeholder="1-65535">
                                    </div>
                                </div>
                                <div class="modal-footer border-t p-6 flex justify-end space-x-2">
                                    <button type="button" class="bg-gray-200 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-300 transition-colors" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_rule" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">Add Rule</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
