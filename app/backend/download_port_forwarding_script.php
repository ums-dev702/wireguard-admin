<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check authentication
if (!is_authenticated()) {
    http_response_code(401);
    echo "# Error: Unauthorized access\n";
    exit;
}

// Check if this is a download request
if ($_POST['action'] !== 'download_script' || !isset($_POST['peer_id'])) {
    http_response_code(400);
    echo "# Error: Invalid request\n";
    exit;
}

$peer_id = $_POST['peer_id'];
$rules = $_POST['rules'] ?? [];

try {
    $db = get_db();
    
    // Get peer information
    $stmt = $db->prepare('SELECT * FROM wg_peers WHERE id = ?');
    $stmt->execute([$peer_id]);
    $peer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$peer) {
        http_response_code(404);
        echo "# Error: Peer not found\n";
        exit;
    }
    
    $peer_ip = extract_peer_ip($peer['allowed_ips']);
    $peer_name = $peer['name'];
    
    // Set headers for download
    $filename = "port_forwarding_{$peer_name}_" . date('Y-m-d_H-i-s') . ".sh";
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Generate script content
    echo generate_port_forwarding_script($peer_name, $peer_ip, $rules);
    
} catch (Exception $e) {
    http_response_code(500);
    echo "# Error generating script: " . $e->getMessage() . "\n";
}

/**
 * Generate port forwarding shell script
 */
function generate_port_forwarding_script($peer_name, $peer_ip, $rules) {
    $timestamp = date('Y-m-d H:i:s');
    
    $script = <<<SCRIPT
#!/bin/bash
# Port Forwarding Setup Script
# Generated on: {$timestamp}
# Peer: {$peer_name}
# Target IP: {$peer_ip}

echo "Setting up port forwarding for {$peer_name} ({$peer_ip})"
echo "=================================================="

# Check if running as root
if [[ \$EUID -ne 0 ]]; then
   echo "This script must be run as root (use sudo)" 
   exit 1
fi

# Enable IP forwarding
echo "Enabling IP forwarding..."
sysctl -w net.ipv4.ip_forward=1
echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf

# Add iptables rules
echo "Adding iptables rules..."

SCRIPT;

    // Add rules
    foreach ($rules as $rule) {
        if (empty($rule['name']) || empty($rule['external_port']) || empty($rule['internal_port'])) {
            continue;
        }
        
        $name = $rule['name'];
        $ext_port = $rule['external_port'];
        $int_port = $rule['internal_port'];
        $protocol = $rule['protocol'] ?? 'tcp';
        $description = $rule['description'] ?? '';
        
        $script .= "\n# {$name} - {$description}\n";
        $script .= "echo \"Setting up {$name} ({$ext_port} -> {$int_port})...\"\n";
        $script .= "iptables -t nat -A PREROUTING -p {$protocol} --dport {$ext_port} -j DNAT --to-destination {$peer_ip}:{$int_port}\n";
        $script .= "iptables -t nat -A POSTROUTING -p {$protocol} -d {$peer_ip} --dport {$int_port} -j MASQUERADE\n";
        $script .= "iptables -A FORWARD -p {$protocol} -d {$peer_ip} --dport {$int_port} -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT\n";
        $script .= "iptables -A FORWARD -p {$protocol} -s {$peer_ip} --sport {$int_port} -m state --state ESTABLISHED,RELATED -j ACCEPT\n";
    }
    
    // UFW rules
    $ports = [];
    foreach ($rules as $rule) {
        if (!empty($rule['external_port'])) {
            $ports[] = $rule['external_port'] . '/' . ($rule['protocol'] ?? 'tcp');
        }
    }
    
    if (!empty($ports)) {
        $script .= "\n# Configure UFW\n";
        $script .= "echo \"Configuring UFW firewall...\"\n";
        foreach (array_unique($ports) as $port) {
            $script .= "ufw allow {$port}\n";
        }
        $script .= "ufw reload\n";
    }
    
    $script .= <<<SCRIPT

# Make iptables rules persistent
echo "Making rules persistent..."
if ! command -v netfilter-persistent &> /dev/null; then
    echo "Installing iptables-persistent..."
    apt update
    DEBIAN_FRONTEND=noninteractive apt install -y iptables-persistent
fi

netfilter-persistent save

echo ""
echo "✅ Port forwarding setup completed!"
echo "=================================================="
echo "Active rules for {$peer_name} ({$peer_ip}):"

SCRIPT;

    // Add verification commands
    foreach ($rules as $rule) {
        if (empty($rule['name']) || empty($rule['external_port']) || empty($rule['internal_port'])) {
            continue;
        }
        
        $name = $rule['name'];
        $ext_port = $rule['external_port'];
        $int_port = $rule['internal_port'];
        
        $script .= "echo \"  {$name}: External port {$ext_port} -> Internal port {$int_port}\"\n";
    }
    
    $script .= <<<SCRIPT

echo ""
echo "To remove these rules later, run:"
echo "bash {$peer_name}_remove_forwarding.sh"

# Generate removal script
cat > {$peer_name}_remove_forwarding.sh << 'EOF'
#!/bin/bash
# Remove port forwarding rules for {$peer_name}
echo "Removing port forwarding rules for {$peer_name}..."

SCRIPT;

    // Add removal commands
    foreach ($rules as $rule) {
        if (empty($rule['external_port']) || empty($rule['internal_port'])) {
            continue;
        }
        
        $ext_port = $rule['external_port'];
        $int_port = $rule['internal_port'];
        $protocol = $rule['protocol'] ?? 'tcp';
        
        $script .= "iptables -t nat -D PREROUTING -p {$protocol} --dport {$ext_port} -j DNAT --to-destination {$peer_ip}:{$int_port} 2>/dev/null\n";
        $script .= "iptables -t nat -D POSTROUTING -p {$protocol} -d {$peer_ip} --dport {$int_port} -j MASQUERADE 2>/dev/null\n";
        $script .= "iptables -D FORWARD -p {$protocol} -d {$peer_ip} --dport {$int_port} -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT 2>/dev/null\n";
        $script .= "iptables -D FORWARD -p {$protocol} -s {$peer_ip} --sport {$int_port} -m state --state ESTABLISHED,RELATED -j ACCEPT 2>/dev/null\n";
    }
    
    $script .= <<<SCRIPT
netfilter-persistent save
echo "✅ Rules removed successfully!"
EOF

chmod +x {$peer_name}_remove_forwarding.sh
echo "📝 Removal script created: {$peer_name}_remove_forwarding.sh"
SCRIPT;

    return $script;
}
?>