<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check authentication
if (!is_authenticated()) {
    header('Location: auth/login.php');
    exit;
}

$db = get_db();

// Handle form submission
if ($_POST['action'] === 'generate_rules' && isset($_POST['peer_id'])) {
    $peer_id = $_POST['peer_id'];
    $rules = $_POST['rules'] ?? [];
    
    // Get peer information
    $stmt = $db->prepare('SELECT * FROM wg_peers WHERE id = ?');
    $stmt->execute([$peer_id]);
    $peer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($peer) {
        $peer_ip = extract_peer_ip($peer['allowed_ips']);
        $peer_name = $peer['name'];
    }
}

// Get all peers for dropdown
$stmt = $db->prepare('SELECT id, name, allowed_ips FROM wg_peers ORDER BY name');
$stmt->execute();
$peers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Port Forwarding Manager - WireGuard Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-network-wired mr-2"></i>
                Port Forwarding Manager
            </h1>
            
            <!-- Peer Selection -->
            <div class="mb-6">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="generate_rules">
                    
                    <div>
                        <label for="peer_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Select Peer for Port Forwarding
                        </label>
                        <select name="peer_id" id="peer_id" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Choose a peer...</option>
                            <?php foreach ($peers as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= isset($peer_id) && $peer_id == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?> (<?= extract_peer_ip($p['allowed_ips']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Port Forwarding Rules -->
                    <div id="rules-container">
                        <h3 class="text-lg font-medium text-gray-700 mb-4">Port Forwarding Rules</h3>
                        <div class="space-y-3" id="rules-list">
                            <!-- Default rules -->
                            <?php
                            $default_rules = [
                                ['name' => 'Winbox Access', 'external_port' => '6843', 'internal_port' => '8291', 'protocol' => 'tcp', 'description' => 'MikroTik Winbox management'],
                                ['name' => 'Web Config', 'external_port' => '6842', 'internal_port' => '80', 'protocol' => 'tcp', 'description' => 'HTTP web interface'],
                                ['name' => 'HTTPS Config', 'external_port' => '6844', 'internal_port' => '443', 'protocol' => 'tcp', 'description' => 'HTTPS web interface'],
                                ['name' => 'SSH Access', 'external_port' => '6845', 'internal_port' => '22', 'protocol' => 'tcp', 'description' => 'SSH remote access'],
                                ['name' => 'Custom Service', 'external_port' => '6846', 'internal_port' => '8080', 'protocol' => 'tcp', 'description' => 'Custom application']
                            ];
                            
                            foreach ($default_rules as $index => $rule):
                            ?>
                            <div class="rule-row flex items-center space-x-3 p-3 border rounded-lg bg-gray-50">
                                <div class="flex-1">
                                    <input type="text" name="rules[<?= $index ?>][name]" placeholder="Service Name" 
                                           value="<?= $rule['name'] ?>" class="block w-full text-sm border-gray-300 rounded">
                                </div>
                                <div class="w-24">
                                    <input type="number" name="rules[<?= $index ?>][external_port]" placeholder="Ext Port" 
                                           value="<?= $rule['external_port'] ?>" class="block w-full text-sm border-gray-300 rounded">
                                </div>
                                <div class="w-24">
                                    <input type="number" name="rules[<?= $index ?>][internal_port]" placeholder="Int Port" 
                                           value="<?= $rule['internal_port'] ?>" class="block w-full text-sm border-gray-300 rounded">
                                </div>
                                <div class="w-20">
                                    <select name="rules[<?= $index ?>][protocol]" class="block w-full text-sm border-gray-300 rounded">
                                        <option value="tcp" <?= $rule['protocol'] === 'tcp' ? 'selected' : '' ?>>TCP</option>
                                        <option value="udp" <?= $rule['protocol'] === 'udp' ? 'selected' : '' ?>>UDP</option>
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <input type="text" name="rules[<?= $index ?>][description]" placeholder="Description" 
                                           value="<?= $rule['description'] ?>" class="block w-full text-sm border-gray-300 rounded">
                                </div>
                                <button type="button" onclick="removeRule(this)" class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" onclick="addRule()" class="mt-3 text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i> Add Rule
                        </button>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            <i class="fas fa-cogs mr-2"></i>Generate iptables Rules
                        </button>
                        <button type="button" onclick="generateScript()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                            <i class="fas fa-download mr-2"></i>Download Script
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Generated Rules Output -->
            <?php if (isset($peer_ip) && isset($rules)): ?>
            <div class="mt-6">
                <h3 class="text-lg font-medium text-gray-700 mb-4">Generated iptables Rules</h3>
                <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm overflow-x-auto">
                    <div class="mb-4">
                        <div class="text-yellow-400"># Port Forwarding Rules for <?= htmlspecialchars($peer_name) ?> (<?= $peer_ip ?>)</div>
                        <div class="text-yellow-400"># Generated on <?= date('Y-m-d H:i:s') ?></div>
                        <div class="text-gray-400"># Add these rules to your VPS</div>
                    </div>
                    
                    <?php foreach ($rules as $rule): ?>
                        <?php if (!empty($rule['name']) && !empty($rule['external_port']) && !empty($rule['internal_port'])): ?>
                        <div class="mb-4">
                            <div class="text-blue-400"># <?= htmlspecialchars($rule['name']) ?> - <?= htmlspecialchars($rule['description'] ?? '') ?></div>
                            <div>iptables -t nat -A PREROUTING -p <?= $rule['protocol'] ?> --dport <?= $rule['external_port'] ?> -j DNAT --to-destination <?= $peer_ip ?>:<?= $rule['internal_port'] ?></div>
                            <div>iptables -t nat -A POSTROUTING -p <?= $rule['protocol'] ?> -d <?= $peer_ip ?> --dport <?= $rule['internal_port'] ?> -j MASQUERADE</div>
                            <div>iptables -A FORWARD -p <?= $rule['protocol'] ?> -d <?= $peer_ip ?> --dport <?= $rule['internal_port'] ?> -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT</div>
                            <div>iptables -A FORWARD -p <?= $rule['protocol'] ?> -s <?= $peer_ip ?> --sport <?= $rule['internal_port'] ?> -m state --state ESTABLISHED,RELATED -j ACCEPT</div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div class="mt-4 pt-4 border-t border-gray-600">
                        <div class="text-yellow-400"># Make rules persistent</div>
                        <div>sudo apt install iptables-persistent -y</div>
                        <div>sudo netfilter-persistent save</div>
                        <div class="mt-2"></div>
                        <div class="text-yellow-400"># Open ports in UFW</div>
                        <?php 
                        $ports = [];
                        foreach ($rules as $rule) {
                            if (!empty($rule['external_port'])) {
                                $ports[] = $rule['external_port'] . '/' . ($rule['protocol'] ?? 'tcp');
                            }
                        }
                        if (!empty($ports)):
                        ?>
                        <div>sudo ufw allow <?= implode(',', array_unique($ports)) ?></div>
                        <div>sudo ufw reload</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-4 flex space-x-3">
                    <button onclick="copyRules()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        <i class="fas fa-copy mr-2"></i>Copy Rules
                    </button>
                    <button onclick="downloadRules()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                        <i class="fas fa-download mr-2"></i>Download as Script
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        let ruleCount = <?= count($default_rules) ?>;
        
        function addRule() {
            const container = document.getElementById('rules-list');
            const ruleHtml = `
                <div class="rule-row flex items-center space-x-3 p-3 border rounded-lg bg-gray-50">
                    <div class="flex-1">
                        <input type="text" name="rules[${ruleCount}][name]" placeholder="Service Name" 
                               class="block w-full text-sm border-gray-300 rounded">
                    </div>
                    <div class="w-24">
                        <input type="number" name="rules[${ruleCount}][external_port]" placeholder="Ext Port" 
                               class="block w-full text-sm border-gray-300 rounded">
                    </div>
                    <div class="w-24">
                        <input type="number" name="rules[${ruleCount}][internal_port]" placeholder="Int Port" 
                               class="block w-full text-sm border-gray-300 rounded">
                    </div>
                    <div class="w-20">
                        <select name="rules[${ruleCount}][protocol]" class="block w-full text-sm border-gray-300 rounded">
                            <option value="tcp">TCP</option>
                            <option value="udp">UDP</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <input type="text" name="rules[${ruleCount}][description]" placeholder="Description" 
                               class="block w-full text-sm border-gray-300 rounded">
                    </div>
                    <button type="button" onclick="removeRule(this)" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', ruleHtml);
            ruleCount++;
        }
        
        function removeRule(button) {
            button.closest('.rule-row').remove();
        }
        
        function copyRules() {
            const rulesText = document.querySelector('.bg-gray-900').textContent;
            navigator.clipboard.writeText(rulesText).then(() => {
                alert('Rules copied to clipboard!');
            });
        }
        
        function downloadRules() {
            const peer_id = document.getElementById('peer_id').value;
            if (!peer_id) {
                alert('Please select a peer first');
                return;
            }
            
            // Create form data
            const formData = new FormData(document.querySelector('form'));
            formData.set('action', 'download_script');
            
            // Create download link
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'backend/download_port_forwarding_script.php';
            
            for (let [key, value] of formData.entries()) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        function generateScript() {
            const peer_id = document.getElementById('peer_id').value;
            if (!peer_id) {
                alert('Please select a peer first');
                return;
            }
            
            // Submit form to generate rules first
            document.querySelector('form').submit();
        }
    </script>
</body>
</html>