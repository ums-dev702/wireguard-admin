<?php
require_once __DIR__ . '/../includes/header.php';

$db = get_db();

// Ensure port forwarding table exists
ensure_port_forwarding_table();

// Handle form submission
if (isset($_POST['action']) && $_POST['action'] === 'generate_rules' && isset($_POST['peer_id'])) {
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

// Get all active peers for dropdown
$stmt = $db->prepare('SELECT id, name, allowed_ips, status FROM wg_peers WHERE status = "active" ORDER BY name');
$stmt->execute();
$peers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing port forwarding rules and pre-select peer if passed via URL
$existing_rules = [];
$selected_peer_id = $_GET['peer_id'] ?? null;
$selected_peer = null;

if ($selected_peer_id) {
    // Get the selected peer information
    $stmt = $db->prepare('SELECT * FROM wg_peers WHERE id = ?');
    $stmt->execute([$selected_peer_id]);
    $selected_peer = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get existing rules for this peer
    $stmt = $db->prepare('SELECT * FROM port_forwarding_rules WHERE peer_id = ? AND status = "active" ORDER BY created_at DESC');
    $stmt->execute([$selected_peer_id]);
    $existing_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ensure_port_forwarding_table()
{
    $db = get_db();

    try {
        $sql = "CREATE TABLE IF NOT EXISTS port_forwarding_rules (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            peer_id INT UNSIGNED NOT NULL,
            service_name VARCHAR(255) NOT NULL,
            external_port INT NOT NULL,
            internal_port INT NOT NULL,
            protocol ENUM('tcp', 'udp', 'both') DEFAULT 'tcp',
            description TEXT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (peer_id) REFERENCES wg_peers(id) ON DELETE CASCADE,
            UNIQUE KEY unique_external_port (external_port, protocol)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->exec($sql);
    } catch (PDOException $e) {
        error_log('Error creating port_forwarding_rules table: ' . $e->getMessage());
    }
}
?>

<style>
    .portal-hero {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        border: 1px solid rgba(249, 115, 22, 0.2);
        background:
            radial-gradient(circle at 14% 20%, rgba(249, 115, 22, 0.2), transparent 32%),
            radial-gradient(circle at 86% 18%, rgba(16, 185, 129, 0.16), transparent 30%),
            linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(2, 6, 23, 0.76));
        box-shadow: 0 26px 90px rgba(0, 0, 0, 0.3);
    }

    .portal-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(rgba(249, 115, 22, 0.045) 1px, transparent 1px),
            linear-gradient(90deg, rgba(249, 115, 22, 0.045) 1px, transparent 1px);
        background-size: 34px 34px;
        mask-image: linear-gradient(90deg, black, transparent);
        pointer-events: none;
    }

    .portal-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        border-radius: 16px;
        padding: 0.75rem 1rem;
        font-weight: 800;
    }

    .portal-button-primary {
        color: #fff;
        background: linear-gradient(135deg, #f97316, #c2410c);
        box-shadow: 0 14px 36px rgba(249, 115, 22, 0.22);
    }

    .portal-button-secondary {
        color: #e2e8f0;
        border: 1px solid rgba(148, 163, 184, 0.18);
        background: rgba(255, 255, 255, 0.06);
    }

    .portal-shell .glass-card {
        border-radius: 24px;
    }

    .portal-shell .rule-row,
    .portal-shell .bg-gray-700\/50 {
        border-radius: 18px !important;
        border-color: rgba(148, 163, 184, 0.16) !important;
        background: rgba(2, 6, 23, 0.46) !important;
    }

    .portal-shell thead {
        background: rgba(2, 6, 23, 0.82) !important;
    }

    .forward-step {
        border: 1px solid rgba(148, 163, 184, 0.16);
        border-radius: 24px;
        background: rgba(2, 6, 23, 0.42);
    }

    .step-badge {
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        border-radius: 16px;
        color: #fff;
        background: linear-gradient(135deg, #f97316, #c2410c);
        box-shadow: 0 12px 28px rgba(249, 115, 22, 0.2);
    }

    .peer-select {
        width: 100%;
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background: rgba(2, 6, 23, 0.68);
        color: #fff;
        padding: 1rem;
    }

    .rule-builder-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }
</style>

<div class="p-4 lg:p-6 portal-shell">
    <section class="portal-hero p-5 lg:p-7 mb-6">
        <div class="relative z-10 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full border border-orange-400 border-opacity-20 bg-orange-500 bg-opacity-10 text-orange-300 text-sm font-bold mb-4">
                    <span class="w-2 h-2 rounded-full bg-orange-400 animate-pulse mr-2"></span>
                    NAT Gateway Tools
                </span>
                <h1 class="text-3xl lg:text-5xl font-black text-white leading-tight">Port Forwarding Portal</h1>
                <p class="text-gray-300 text-base lg:text-lg mt-4 max-w-3xl">
                    Build, apply, download, and audit forwarding rules for WireGuard peers from one clean workspace.
                </p>
                <div class="flex flex-wrap gap-3 mt-6">
                    <span class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 px-4 py-3 text-sm text-gray-300">
                        Active peers: <strong class="text-green-300"><?= count($peers) ?></strong>
                    </span>
                    <span class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 px-4 py-3 text-sm text-gray-300">
                        Selected: <strong class="text-orange-300"><?= $selected_peer ? htmlspecialchars($selected_peer['name']) : 'None' ?></strong>
                    </span>
                    <span class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 px-4 py-3 text-sm text-gray-300">
                        Rules: <strong class="text-blue-300"><?= count($existing_rules) ?></strong>
                    </span>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row xl:flex-col gap-3 xl:min-w-64">
                <a href="wg_peers" class="portal-button portal-button-secondary">
                    <i class="fas fa-arrow-left"></i>Back to Peers
                </a>
                <button onclick="addRule()" class="portal-button portal-button-primary">
                    <i class="fas fa-plus"></i>Add Rule
                </button>
            </div>
        </div>
    </section>

    <!-- Selected Peer Info -->
    <?php if ($selected_peer): ?>
        <div class="glass-card p-4 mb-6 border-l-4 border-blue-500">
            <div class="flex items-center">
                <i class="fas fa-info-circle text-blue-400 mr-3"></i>
                <div>
                    <p class="text-blue-400 font-medium">
                        Managing port forwarding for: <strong><?= htmlspecialchars($selected_peer['name']) ?></strong>
                    </p>
                    <p class="text-gray-400 text-sm">
                        Peer IP: <span class="font-mono text-blue-300"><?= extract_peer_ip($selected_peer['allowed_ips']) ?></span>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Card -->
    <div class="glass-card p-5 lg:p-6 mb-6">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="generate_rules">

            <!-- Peer Selection -->
            <div class="forward-step p-5">
                <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                    <div class="step-badge flex-shrink-0">
                        <span class="font-black">1</span>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm uppercase tracking-widest text-orange-300 font-bold">Select Peer for Port Forwarding</p>
                        <h2 class="text-2xl font-black text-white mt-1">Choose a VPN client</h2>
                        <p class="text-gray-400 mt-1">Pick the peer that should receive forwarded traffic from the public server.</p>
                    </div>
                    <div class="w-full lg:w-96">
                        <select name="peer_id" id="peer_id" class="peer-select" required>
                            <option value="">Choose a peer...</option>
                            <?php foreach ($peers as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (isset($selected_peer_id) && $selected_peer_id == $p['id']) || (isset($peer_id) && $peer_id == $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?> (<?= extract_peer_ip($p['allowed_ips']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Port Forwarding Rules -->
            <div id="rules-container" class="forward-step p-5">
                <div class="rule-builder-title">
                    <div class="flex items-start gap-4">
                        <div class="step-badge flex-shrink-0">
                            <span class="font-black">2</span>
                        </div>
                        <div>
                            <p class="text-sm uppercase tracking-widest text-orange-300 font-bold">Port Forwarding Rules</p>
                            <h3 class="text-2xl font-black text-white mt-1">Build service routes</h3>
                            <p class="text-gray-400 mt-1">Map an external server port to an internal peer service port.</p>
                        </div>
                    </div>
                    <button type="button" onclick="addRule()" class="portal-button portal-button-secondary whitespace-nowrap">
                        <i class="fas fa-plus"></i>Add Rule
                    </button>
                </div>
                <div class="space-y-3" id="rules-list">
                    <!-- Default rules -->
                    <?php
                    $default_rules = [
                        ['name' => 'Winbox Access', 'external_port' => '6843', 'internal_port' => '8291', 'protocol' => 'tcp', 'description' => 'MikroTik Winbox management'],
                    ];

                    foreach ($default_rules as $index => $rule):
                    ?>
                        <div class="rule-row flex items-center space-x-3 p-3 border border-gray-600 rounded-lg bg-gray-700/50">
                            <div class="flex-1">
                                <input type="text" name="rules[<?= $index ?>][name]" placeholder="Service Name"
                                    value="<?= $rule['name'] ?>" class="block w-full text-sm bg-gray-800 border-gray-600 text-white rounded">
                            </div>
                            <div class="w-24">
                                <input type="number" name="rules[<?= $index ?>][external_port]" placeholder="Ext Port"
                                    value="<?= $rule['external_port'] ?>" class="block w-full text-sm bg-gray-800 border-gray-600 text-white rounded">
                            </div>
                            <div class="w-24">
                                <input type="number" name="rules[<?= $index ?>][internal_port]" placeholder="Int Port"
                                    value="<?= $rule['internal_port'] ?>" class="block w-full text-sm bg-gray-800 border-gray-600 text-white rounded">
                            </div>
                            <div class="w-20">
                                <select name="rules[<?= $index ?>][protocol]" class="block w-full text-sm bg-gray-800 border-gray-600 text-white rounded">
                                    <option value="tcp" <?= $rule['protocol'] === 'tcp' ? 'selected' : '' ?>>TCP</option>
                                    <option value="udp" <?= $rule['protocol'] === 'udp' ? 'selected' : '' ?>>UDP</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <input type="text" name="rules[<?= $index ?>][description]" placeholder="Description"
                                    value="<?= $rule['description'] ?>" class="block w-full text-sm bg-gray-800 border-gray-600 text-white rounded">
                            </div>
                            <button type="button" onclick="removeRule(this)" class="text-red-400 hover:text-red-300">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit" class="portal-button portal-button-secondary">
                    <i class="fas fa-cogs mr-2"></i>Generate iptables Rules
                </button>
                <button type="button" onclick="applyPortForwardingRules()" class="portal-button portal-button-primary">
                    <i class="fas fa-play mr-2"></i>Apply Rules to Server
                </button>
                <button type="button" onclick="generateScript()" class="portal-button portal-button-secondary">
                    <i class="fas fa-download mr-2"></i>Download Script
                </button>
            </div>
        </form>
    </div>

    <!-- Active Port Forwarding Rules -->
    <?php if ($selected_peer_id): ?>
        <?php if (!empty($existing_rules)): ?>
            <div class="glass-card p-6 mt-6">
                <h3 class="text-lg font-medium text-white mb-4">
                    <i class="fas fa-list-check mr-2"></i>Active Port Forwarding Rules
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full border border-gray-600">
                        <thead class="bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-300">Service</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-300">External Port</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-300">Internal Port</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-300">Protocol</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-300">Remote URL</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-300">Description</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-300">Created</th>
                                <th class="px-4 py-2 text-center text-sm font-medium text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-600">
                            <?php foreach ($existing_rules as $rule): ?>
                                <?php 
                                $remote_url = defined('WG_VPN_URL') ? WG_VPN_URL . ':' . $rule['external_port'] : SERVER_IP . ':' . $rule['external_port'];
                                ?>
                                <tr class="hover:bg-gray-700/50">
                                    <td class="px-4 py-3 text-sm font-medium text-white"><?= htmlspecialchars($rule['service_name']) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?= $rule['external_port'] ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?= $rule['internal_port'] ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300 uppercase"><?= htmlspecialchars($rule['protocol']) ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center space-x-2">
                                            <span class="font-mono text-blue-300"><?= htmlspecialchars($remote_url) ?></span>
                                            <button onclick="copyToClipboard('<?= htmlspecialchars($remote_url) ?>')" 
                                                    class="text-gray-400 hover:text-white transition-colors"
                                                    title="Copy URL">
                                                <i class="fas fa-copy text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-400"><?= htmlspecialchars($rule['description'] ?? '') ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-400"><?= date('M j, Y', strtotime($rule['created_at'])) ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <button onclick="removePortForwardRule(<?= $rule['id'] ?>, '<?= htmlspecialchars($rule['service_name'], ENT_QUOTES) ?>')"
                                            class="text-red-400 hover:text-red-300">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="glass-card p-6 mt-6">
                <div class="text-center py-8">
                    <i class="fas fa-network-wired text-gray-500 text-4xl mb-3"></i>
                    <h3 class="text-lg font-medium text-white mb-2">No Port Forwarding Rules Yet</h3>
                    <p class="text-gray-400 mb-4">
                        This peer doesn't have any port forwarding rules configured yet.
                    </p>
                    <p class="text-sm text-gray-500">
                        Configure port forwarding rules above and click "Apply Rules to Server" to get started.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Generated Rules Output -->
    <?php if (isset($peer_ip) && isset($rules)): ?>
        <div class="glass-card p-6 mt-6">
            <h3 class="text-lg font-medium text-white mb-4">Generated iptables Rules</h3>
            <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm overflow-x-auto border border-gray-700">
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
                <button onclick="copyRules()" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-600 transition-colors">
                    <i class="fas fa-copy mr-2"></i>Copy Rules
                </button>
                <button onclick="downloadRules()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition-colors">
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
                <div class="rule-row flex items-center space-x-3 p-3 border border-gray-600 rounded-lg bg-gray-700/50">
                    <div class="flex-1">
                        <input type="text" name="rules[${ruleCount}][name]" placeholder="Service Name" 
                               class="block w-full text-sm bg-gray-800 border-gray-600 text-white rounded">
                    </div>
                    <div class="w-24">
                        <input type="number" name="rules[${ruleCount}][external_port]" placeholder="Ext Port" 
                               class="block w-full text-sm bg-gray-800 border-gray-600 text-white rounded">
                    </div>
                    <div class="w-24">
                        <input type="number" name="rules[${ruleCount}][internal_port]" placeholder="Int Port" 
                               class="block w-full text-sm bg-gray-800 border-gray-600 text-white rounded">
                    </div>
                    <div class="w-20">
                        <select name="rules[${ruleCount}][protocol]" class="block w-full text-sm bg-gray-800 border-gray-600 text-white rounded">
                            <option value="tcp">TCP</option>
                            <option value="udp">UDP</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <input type="text" name="rules[${ruleCount}][description]" placeholder="Description" 
                               class="block w-full text-sm bg-gray-800 border-gray-600 text-white rounded">
                    </div>
                    <button type="button" onclick="removeRule(this)" class="text-red-400 hover:text-red-300">
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
            showNotification('Rules copied to clipboard!', 'success');
        });
    }

    function downloadRules() {
        const peer_id = document.getElementById('peer_id').value;
        if (!peer_id) {
            showNotification('Please select a peer first', 'error');
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
            showNotification('Please select a peer first', 'error');
            return;
        }

        // Submit form to generate rules first
        document.querySelector('form').submit();
    }

    // Apply port forwarding rules directly to the server
    async function applyPortForwardingRules() {
        const peer_id = document.getElementById('peer_id').value;
        if (!peer_id) {
            showNotification('Please select a peer first', 'error');
            return;
        }

        const formData = new FormData(document.querySelector('form'));
        const rules = [];

        // Collect all rules
        let index = 0;
        while (formData.has(`rules[${index}][name]`)) {
            const rule = {
                name: formData.get(`rules[${index}][name]`),
                external_port: formData.get(`rules[${index}][external_port]`),
                internal_port: formData.get(`rules[${index}][internal_port]`),
                protocol: formData.get(`rules[${index}][protocol]`),
                description: formData.get(`rules[${index}][description]`)
            };

            if (rule.name && rule.external_port && rule.internal_port) {
                rules.push(rule);
            }
            index++;
        }

        if (rules.length === 0) {
            showNotification('Please add at least one rule', 'error');
            return;
        }

        // Confirm before applying
        if (!confirm(`Apply ${rules.length} port forwarding rule(s) to the server?\n\nThis will:\n• Configure iptables rules\n• Open firewall ports\n• Make rules persistent\n\nContinue?`)) {
            return;
        }

        // Show loading state
        const applyBtn = event.target;
        const originalHTML = applyBtn.innerHTML;
        applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Applying Rules...';
        applyBtn.disabled = true;

        try {
            // Apply each rule
            let successCount = 0;
            let errorCount = 0;

            for (const rule of rules) {
                const data = new FormData();
                data.append('action', 'add_port_forward');
                data.append('peer_id', peer_id);
                data.append('service_name', rule.name);
                data.append('external_port', rule.external_port);
                data.append('internal_port', rule.internal_port);
                data.append('protocol', rule.protocol);
                data.append('description', rule.description);
                const port_url = window.location.origin + '/port_forwarding';

                const response = await fetch(port_url, {
                    method: 'POST',
                    body: data
                });

                const result = await response.json();

                if (result.success) {
                    successCount++;
                } else {
                    errorCount++;
                    console.error(`Failed to apply rule ${rule.name}:`, result.message);
                }
            }

            if (successCount > 0) {
                showNotification(`✅ Successfully applied ${successCount} rule(s)!`, 'success');
                // Reload page to show active rules
                setTimeout(() => {
                    window.location.href = `?peer_id=${peer_id}`;
                }, 1500);
            }

            if (errorCount > 0) {
                showNotification(`⚠️ ${errorCount} rule(s) failed to apply`, 'error');
            }

        } catch (error) {
            console.error('Error applying rules:', error);
            showNotification('Error applying rules: ' + error.message, 'error');
        } finally {
            applyBtn.innerHTML = originalHTML;
            applyBtn.disabled = false;
        }
    }

    // Remove port forwarding rule
    async function removePortForwardRule(ruleId, serviceName) {
        if (!confirm(`Remove port forwarding rule for "${serviceName}"?\n\nThis will:\n• Remove iptables rules\n• Update firewall configuration\n• Cannot be undone`)) {
            return;
        }

        try {
            const data = new FormData();
            data.append('action', 'remove_port_forward');
            data.append('rule_id', ruleId);

            const remove_port_forward_url = window.location.origin + '/remove_port_forward';

            const response = await fetch(remove_port_forward_url, {
                method: 'POST',
                body: data
            });

            const result = await response.json();

            if (result.success) {
                showNotification('✅ Port forwarding rule removed successfully!', 'success');
                // Reload page to update table
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error: ' + result.message, 'error');
            }

        } catch (error) {
            console.error('Error removing rule:', error);
            showNotification('Error removing rule: ' + error.message, 'error');
        }
    }

    // Show notification
    function showNotification(message, type = 'info') {
        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            info: 'bg-blue-600'
        };

        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Copy to clipboard function
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            // Show temporary success message
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            toast.textContent = '✅ Copied to clipboard!';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        }).catch(() => {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            toast.textContent = '✅ Copied to clipboard!';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        });
    }

    // Load existing rules on peer selection
    document.getElementById('peer_id').addEventListener('change', function() {
        const peerId = this.value;
        if (peerId) {
            window.location.href = `?peer_id=${peerId}`;
        }
    });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>