<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check authentication
if (!is_authenticated()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

$db = get_db();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add_port_forward':
            // Add port forwarding rule
            $peer_id = $_POST['peer_id'] ?? null;
            $service_name = $_POST['service_name'] ?? '';
            $external_port = intval($_POST['external_port'] ?? 0);
            $internal_port = intval($_POST['internal_port'] ?? 0);
            $protocol = $_POST['protocol'] ?? 'tcp';
            $description = $_POST['description'] ?? '';
            
            if (!$peer_id || !$external_port || !$internal_port) {
                throw new Exception('Missing required parameters');
            }
            
            // Get peer information
            $stmt = $db->prepare('SELECT * FROM wg_peers WHERE id = ?');
            $stmt->execute([$peer_id]);
            $peer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$peer) {
                throw new Exception('Peer not found');
            }
            
            $peer_ip = extract_peer_ip($peer['allowed_ips']);
            if ($peer_ip === 'N/A') {
                throw new Exception('Invalid peer IP address');
            }
            
            // Execute iptables commands
            $commands = [
                "sudo iptables -t nat -A PREROUTING -p {$protocol} --dport {$external_port} -j DNAT --to-destination {$peer_ip}:{$internal_port}",
                "sudo iptables -t nat -A POSTROUTING -p {$protocol} -d {$peer_ip} --dport {$internal_port} -j MASQUERADE",
                "sudo iptables -A FORWARD -p {$protocol} -d {$peer_ip} --dport {$internal_port} -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT",
                "sudo iptables -A FORWARD -p {$protocol} -s {$peer_ip} --sport {$internal_port} -m state --state ESTABLISHED,RELATED -j ACCEPT"
            ];
            
            $results = [];
            foreach ($commands as $cmd) {
                $output = shell_exec($cmd . ' 2>&1');
                $results[] = [
                    'command' => $cmd,
                    'output' => $output
                ];
            }
            
            // Add UFW rule
            $ufw_cmd = "sudo ufw allow {$external_port}/{$protocol}";
            shell_exec($ufw_cmd);
            
            // Make rules persistent
            shell_exec('sudo netfilter-persistent save 2>&1');
            
            // Save to database
            $stmt = $db->prepare('
                INSERT INTO port_forwarding_rules 
                (peer_id, service_name, external_port, internal_port, protocol, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$peer_id, $service_name, $external_port, $internal_port, $protocol, $description]);
            
            // Send Telegram notification
            if (function_exists('sendToTelegram')) {
                $msg = "🔁 Port Forwarding Rule Added\n";
                $msg .= "============================\n";
                $msg .= "Peer: {$peer['name']}\n";
                $msg .= "IP: {$peer_ip}\n";
                $msg .= "Service: {$service_name}\n";
                $msg .= "External Port: {$external_port}\n";
                $msg .= "Internal Port: {$internal_port}\n";
                $msg .= "Protocol: " . strtoupper($protocol) . "\n";
                $msg .= "Description: {$description}\n";
                sendToTelegram($msg);
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Port forwarding rule added successfully",
                'data' => [
                    'peer_ip' => $peer_ip,
                    'external_port' => $external_port,
                    'internal_port' => $internal_port,
                    'protocol' => $protocol
                ]
            ]);
            break;
            
        case 'remove_port_forward':
            // Remove port forwarding rule
            $rule_id = intval($_POST['rule_id'] ?? 0);
            
            if (!$rule_id) {
                throw new Exception('Rule ID is required');
            }
            
            // Get rule information
            $stmt = $db->prepare('
                SELECT pf.*, p.allowed_ips, p.name as peer_name 
                FROM port_forwarding_rules pf 
                JOIN wg_peers p ON p.id = pf.peer_id 
                WHERE pf.id = ?
            ');
            $stmt->execute([$rule_id]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$rule) {
                throw new Exception('Rule not found');
            }
            
            $peer_ip = extract_peer_ip($rule['allowed_ips']);
            $protocol = $rule['protocol'];
            $external_port = $rule['external_port'];
            $internal_port = $rule['internal_port'];
            
            // Remove iptables rules
            $commands = [
                "sudo iptables -t nat -D PREROUTING -p {$protocol} --dport {$external_port} -j DNAT --to-destination {$peer_ip}:{$internal_port}",
                "sudo iptables -t nat -D POSTROUTING -p {$protocol} -d {$peer_ip} --dport {$internal_port} -j MASQUERADE",
                "sudo iptables -D FORWARD -p {$protocol} -d {$peer_ip} --dport {$internal_port} -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT",
                "sudo iptables -D FORWARD -p {$protocol} -s {$peer_ip} --sport {$internal_port} -m state --state ESTABLISHED,RELATED -j ACCEPT"
            ];
            
            foreach ($commands as $cmd) {
                shell_exec($cmd . ' 2>&1');
            }
            
            // Make rules persistent
            shell_exec('sudo netfilter-persistent save 2>&1');
            
            // Delete from database
            $stmt = $db->prepare('DELETE FROM port_forwarding_rules WHERE id = ?');
            $stmt->execute([$rule_id]);
            
            // Send Telegram notification
            if (function_exists('sendToTelegram')) {
                $msg = "🗑️ Port Forwarding Rule Removed\n";
                $msg .= "============================\n";
                $msg .= "Peer: {$rule['peer_name']}\n";
                $msg .= "IP: {$peer_ip}\n";
                $msg .= "Service: {$rule['service_name']}\n";
                $msg .= "External Port: {$external_port}\n";
                $msg .= "Internal Port: {$internal_port}\n";
                sendToTelegram($msg);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Port forwarding rule removed successfully'
            ]);
            break;
            
        case 'get_port_rules':
            // Get all port forwarding rules for a peer
            $peer_id = intval($_POST['peer_id'] ?? 0);
            
            if ($peer_id) {
                $stmt = $db->prepare('SELECT * FROM port_forwarding_rules WHERE peer_id = ? ORDER BY created_at DESC');
                $stmt->execute([$peer_id]);
            } else {
                $stmt = $db->query('SELECT * FROM port_forwarding_rules ORDER BY created_at DESC');
            }
            
            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'rules' => $rules
            ]);
            break;
            
        case 'validate_port':
            // Validate if a port is available
            $port = intval($_POST['port'] ?? 0);
            $protocol = $_POST['protocol'] ?? 'tcp';
            
            if (!$port || $port < 1 || $port > 65535) {
                throw new Exception('Invalid port number');
            }
            
            // Check if port is in use
            $is_free = is_port_completely_free($port);
            
            echo json_encode([
                'success' => true,
                'available' => $is_free,
                'message' => $is_free ? "Port {$port} is available" : "Port {$port} is already in use"
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Create port_forwarding_rules table if it doesn't exist
function ensure_port_forwarding_table() {
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
        throw $e;
    }
}

// Ensure table exists
ensure_port_forwarding_table();
