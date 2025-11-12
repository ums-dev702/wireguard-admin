<?php

namespace WireGuardAdmin;

class WireGuard
{
    private $db;
    private $interfaceName;
    private $configPath;

    public function __construct(Database $db, $interfaceName = null, $configPath = null)
    {
        $this->db = $db;

        // If no interface name provided, get the first interface from database
        if ($interfaceName === null) {
            try {
                $firstInterface = $this->getFirstInterface();
                if ($firstInterface) {
                    $this->interfaceName = 'wg_' . $firstInterface['name'];
                    $this->configPath = '/etc/wireguard/wg_' . $firstInterface['name'] . '.conf';
                } else {
                    // Default fallback if no interfaces exist - this should work even with no database entries
                    $this->interfaceName = 'wg_alvodata';
                    $this->configPath = '/etc/wireguard/wg_alvodata.conf';
                    error_log("No interfaces found in database, using default interface: " . $this->interfaceName);
                }
            } catch (\Exception $e) {
                // Fallback to default if database query fails
                error_log("Failed to get first interface, using default: " . $e->getMessage());
                $this->interfaceName = 'wg_alvodata';
                $this->configPath = '/etc/wireguard/wg_alvodata.conf';
            }
        } else {
            $this->interfaceName = $interfaceName;
            $this->configPath = $configPath ?: '/etc/wireguard/' . $interfaceName . '.conf';
        }
    }

    /**
     * Get the first interface from the database
     * @return array|null Interface data or null if no interfaces exist
     */
    private function getFirstInterface()
    {
        try {
            // First try with status column
            $sql = "SELECT * FROM interfaces WHERE status = 'active' ORDER BY created_at ASC LIMIT 1";
            $result = $this->db->select($sql);
            if (!empty($result)) {
                return $result[0];
            }

            // If no active interfaces found, try without status filter
            $sql = "SELECT * FROM interfaces ORDER BY created_at ASC LIMIT 1";
            $result = $this->db->select($sql);
            return !empty($result) ? $result[0] : null;
        } catch (\Exception $e) {
            error_log("Failed to get first interface: " . $e->getMessage());

            // If status column doesn't exist, try without it
            try {
                $sql = "SELECT * FROM interfaces ORDER BY created_at ASC LIMIT 1";
                $result = $this->db->select($sql);
                return !empty($result) ? $result[0] : null;
            } catch (\Exception $e2) {
                error_log("Failed to get any interface: " . $e2->getMessage());
                return null;
            }
        }
    }

    /**
     * Get the current interface name
     * @return string
     */
    public function getInterfaceName()
    {
        return $this->interfaceName;
    }

    /**
     * Get the current config path
     * @return string
     */
    public function getConfigPath()
    {
        return $this->configPath;
    }

    /**
     * Refresh interface settings to use the first available interface
     * @return bool True if interface was found and set, false otherwise
     */
    public function refreshInterface()
    {
        try {
            $firstInterface = $this->getFirstInterface();
            if ($firstInterface) {
                $this->interfaceName = 'wg_' . $firstInterface['name'];
                $this->configPath = '/etc/wireguard/wg_' . $firstInterface['name'] . '.conf';
                return true;
            }
            return false;
        } catch (\Exception $e) {
            error_log("Failed to refresh interface: " . $e->getMessage());
            return false;
        }
    }

    public function getStatus()
    {
        try {
            $output = shell_exec("sudo wg show {$this->interfaceName} 2>/dev/null");
            return $output !== null ? trim($output) : 'Interface not running';
        } catch (\Exception $e) {
            return 'Error getting status: ' . $e->getMessage();
        }
    }


    public function isRunning()
    {
        $output = shell_exec("sudo wg show {$this->interfaceName} 2>/dev/null");
        return !empty($output);
    }

    public function startInterface()
    {
        try {
            $output = shell_exec("sudo wg-quick up {$this->interfaceName} 2>&1");
            return strpos($output, 'FAILED') === false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function stopInterface()
    {
        try {
            $output = shell_exec("sudo wg-quick down {$this->interfaceName} 2>&1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function restartInterface()
    {
        $this->stopInterface();
        sleep(2);
        return $this->startInterface();
    }


    /**
     * Add peer to WireGuard interface using wg set command
     * @param string $publicKey The peer's public key
     * @param string $allowedIps The allowed IPs for the peer
     * @return array Result with success status and message
     */
    public function addPeerToInterface($publicKey, $allowedIps)
    {
        try {
            // Get the actual interface name without wg_ prefix for the command
            $actual_interface = preg_replace('/^wg_/', '', $this->interfaceName);
            
            // Build the wg set command
            $wg_command = "sudo wg set wg_{$actual_interface} peer {$publicKey} allowed-ips {$allowedIps}";
            
            // Execute the command
            $wg_output = shell_exec($wg_command . ' 2>&1');
            
            // Check if command was successful (no output usually means success)
            if ($wg_output === null || empty(trim($wg_output))) {
                // Save the configuration to make it persistent
                $save_command = "sudo wg-quick save wg_{$actual_interface}";
                shell_exec($save_command . ' 2>&1');
                
                // Send Telegram notification if the function exists
                if (function_exists('sendToTelegram')) {
                    $telegram_msg = "✅ WireGuard Peer Added to Interface\n";
                    $telegram_msg .= "==================================\n";
                    $telegram_msg .= "Interface: wg_{$actual_interface}\n";
                    $telegram_msg .= "Public Key: " . substr($publicKey, 0, 20) . "...\n";
                    $telegram_msg .= "Allowed IPs: {$allowedIps}\n";
                    $telegram_msg .= "Command: {$wg_command}\n";
                    $telegram_msg .= "Status: ✅ Active\n";
                    $telegram_msg .= "==================================\n";
                    sendToTelegram($telegram_msg);
                }
                
                return [
                    'success' => true,
                    'message' => 'Peer successfully added to WireGuard interface',
                    'command' => $wg_command
                ];
            } else {
                // Command failed
                error_log("WireGuard add peer command failed: " . $wg_output);
                return [
                    'success' => false,
                    'message' => 'Failed to add peer to WireGuard interface: ' . trim($wg_output),
                    'command' => $wg_command
                ];
            }
        } catch (\Exception $e) {
            error_log("Exception in addPeerToInterface: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception occurred: ' . $e->getMessage(),
                'command' => $wg_command ?? 'N/A'
            ];
        }
    }

    /**
     * Remove peer from WireGuard interface using wg set command
     * @param string $publicKey The peer's public key
     * @return array Result with success status and message
     */
    public function removePeerFromInterface($publicKey)
    {
        try {
            // Get the actual interface name without wg_ prefix for the command
            $actual_interface = preg_replace('/^wg_/', '', $this->interfaceName);
            
            // Build the wg set command to remove peer
            $wg_command = "sudo wg set wg_{$actual_interface} peer {$publicKey} remove";
            
            // Execute the command
            $wg_output = shell_exec($wg_command . ' 2>&1');
            
            // Save the configuration to make it persistent
            $save_command = "sudo wg-quick save wg_{$actual_interface}";
            shell_exec($save_command . ' 2>&1');
            
            // Send Telegram notification if the function exists
            if (function_exists('sendToTelegram')) {
                $telegram_msg = "🗑️ WireGuard Peer Removed from Interface\n";
                $telegram_msg .= "========================================\n";
                $telegram_msg .= "Interface: wg_{$actual_interface}\n";
                $telegram_msg .= "Public Key: " . substr($publicKey, 0, 20) . "...\n";
                $telegram_msg .= "Command: {$wg_command}\n";
                $telegram_msg .= "Status: ❌ Removed\n";
                $telegram_msg .= "========================================\n";
                sendToTelegram($telegram_msg);
            }
            
            return [
                'success' => true,
                'message' => 'Peer successfully removed from WireGuard interface',
                'command' => $wg_command
            ];
        } catch (\Exception $e) {
            error_log("Exception in removePeerFromInterface: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception occurred: ' . $e->getMessage(),
                'command' => $wg_command ?? 'N/A'
            ];
        }
    }

    public function createPeer($name,$iface_id, $allowedIps)
    {
        try {
            // Generate unique peer ID
            do {
                $peer_id = "PWG" . rand(10000, 99999);
                // Check if this ID already exists
                $check_stmt = $this->db->selectOne('SELECT COUNT(*) as count FROM wg_peers WHERE peer_id = ?', [$peer_id]);
                $exists = $check_stmt && $check_stmt['count'] > 0;
            } while ($exists);
            // Start transaction
            $this->db->beginTransaction();
            // Insert peer into wg_peers database
            $peerId = $this->db->insert('wg_peers', [
                'peer_id' => $peer_id,
                'name' => $name,
                'iface_id' => $iface_id,
                'allowed_ips' => $allowedIps,
                'status' => 'unconfigured'
            ]);
            $this->db->commit();
            return [
                'id' => $peerId,
                'peer_id' => $peer_id,
                'name' => $name,
                'allowed_ips' => $allowedIps,
                'iface_id' => $iface_id
            ];
        } catch (\Exception $e) {
            $this->db->rollback();
            throw new \Exception("Failed to create peer: " . $e->getMessage());
        }
    }

    public function deletePeer($peerId)
    {
        try {
            // Get peer info from wg_peers table
            $peer = $this->db->selectOne("SELECT * FROM wg_peers WHERE id = ?", [$peerId]);
            if (!$peer) {
                throw new \Exception("Peer not found");
            }

            $this->db->beginTransaction();

            // Remove from WireGuard interface if public key exists
            if (!empty($peer['public_key'])) {
                $result = $this->removePeerFromInterface($peer['public_key']);
                if (!$result['success']) {
                    error_log("Failed to remove peer from interface: " . $result['message']);
                    // Continue anyway to clean up database
                }
            }

            // Remove from config file (legacy method as backup)
            if (!empty($peer['public_key'])) {
                $this->removePeerFromConfig($peer['public_key']);
            }

            // Remove all port forwarding rules for this peer
            $this->removePortForwardingRules($peerId);

            // Delete the peer from database completely
            $sql = "DELETE FROM wg_peers WHERE id = ?";
            $this->db->query($sql, [$peerId]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw new \Exception("Failed to delete peer: " . $e->getMessage());
        }
    }

    public function getPeers()
    {
        // Get current interface name without wg_ prefix for database lookup
        $interface_name = preg_replace('/^wg_/', '', $this->interfaceName);

        // Get iface_id from interfaces
        $iface = $this->db->selectOne('SELECT iface_id FROM interfaces WHERE name = ?', [$interface_name]);

        if ($iface) {
            $iface_id = $iface['iface_id'];
            return $this->db->select("SELECT * FROM wg_peers WHERE iface_id = ? ORDER BY created_at DESC", [$iface_id]);
        } else {
            // If no matching interface found, return empty array
            return [];
        }
    }


    public function getPeer($peerId)
    {
        return $this->db->selectOne("SELECT * FROM wg_peers WHERE id = ?", [$peerId]);
    }

    public function updatePeerStats()
    {
        try {
            $output = shell_exec("sudo wg show {$this->interfaceName} dump");
            if (empty($output)) {
                return false;
            }

            $lines = explode("\n", trim($output));

            foreach ($lines as $line) {
                if (empty($line)) continue;

                $parts = explode("\t", $line);
                if (count($parts) < 6) continue;

                $publicKey = $parts[0];
                $lastHandshake = $parts[4];
                $transferRx = intval($parts[5]);
                $transferTx = intval($parts[6]);

                // Update in database
                $this->db->update('wg_peers', [
                    'last_handshake' => $lastHandshake > 0 ? date('Y-m-d H:i:s', $lastHandshake) : null,
                    'rx_bytes' => $transferRx,
                    'tx_bytes' => $transferTx
                ], 'public_key = ?', [$publicKey]);
            }

            return true;
        } catch (\Exception $e) {
            error_log("Failed to update peer stats: " . $e->getMessage());
            return false;
        }
    }

    public function generateClientConfig($peerId, $serverPublicKey, $serverEndpoint, $serverPort = 51820)
    {
        $peer = $this->getPeer($peerId);
        if (!$peer) {
            throw new \Exception("Peer not found");
        }

        $config = "[Interface]\n";
        $config .= "PrivateKey = {$peer['private_key']}\n";
        $config .= "Address = {$peer['allowed_ips']}\n";
        $config .= "DNS = {$peer['dns_servers']}\n\n";
        $config .= "[Peer]\n";
        $config .= "PublicKey = {$serverPublicKey}\n";
        $config .= "Endpoint = {$serverEndpoint}:{$serverPort}\n";
        $config .= "AllowedIPs = 0.0.0.0/0\n";
        $config .= "PersistentKeepalive = 25\n";

        return $config;
    }

    private function addPeerToConfig($publicKey, $allowedIps)
    {
        $peerConfig = "\n[Peer]\n";
        $peerConfig .= "PublicKey = {$publicKey}\n";
        $peerConfig .= "AllowedIPs = {$allowedIps}\n";

        file_put_contents($this->configPath, $peerConfig, FILE_APPEND | LOCK_EX);
    }

    private function removePeerFromConfig($publicKey)
    {
        $config = file_get_contents($this->configPath);
        if ($config === false) {
            return false;
        }

        // Remove the peer section
        $pattern = "/\n\[Peer\][^\[]*?PublicKey\s*=\s*" . preg_quote($publicKey, '/') . "[^\[]*/s";
        $newConfig = preg_replace($pattern, '', $config);

        file_put_contents($this->configPath, $newConfig, LOCK_EX);
        return true;
    }

    public function getSystemStats()
    {
        $stats = [];
        // System load (cross-platform)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $stats['load'] = [
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2]
            ];
        } else {
            // Not available on Windows, set to 0
            $stats['load'] = [
                '1min' => 0,
                '5min' => 0,
                '15min' => 0
            ];
        }

        // Memory usage
        if (file_exists('/proc/meminfo')) {
            $meminfo = file('/proc/meminfo');
            $memtotal = intval(preg_replace('/[^0-9]/', '', $meminfo[0])) * 1024;
            $memavailable = intval(preg_replace('/[^0-9]/', '', $meminfo[2])) * 1024;
            $memused = $memtotal - $memavailable;

            $stats['memory'] = [
                'total' => $memtotal,
                'used' => $memused,
                'free' => $memavailable,
                'percent' => round(($memused / $memtotal) * 100, 2)
            ];
        }

        // Disk usage
        $diskFree = disk_free_space("/");
        $diskTotal = disk_total_space("/");
        $diskUsed = $diskTotal - $diskFree;

        $stats['disk'] = [
            'total' => $diskTotal,
            'used' => $diskUsed,
            'free' => $diskFree,
            'percent' => round(($diskUsed / $diskTotal) * 100, 2)
        ];

        // Network stats
        $stats['network'] = $this->getNetworkStats();

        return $stats;
    }

    private function getNetworkStats()
    {
        $stats = [];

        try {
            // Get interface statistics
            $output = shell_exec("cat /proc/net/dev | grep {$this->interfaceName}");
            if ($output) {
                $parts = preg_split('/\s+/', trim($output));
                $stats = [
                    'rx_bytes' => intval($parts[1]),
                    'rx_packets' => intval($parts[2]),
                    'tx_bytes' => intval($parts[9]),
                    'tx_packets' => intval($parts[10])
                ];
            }
        } catch (\Exception $e) {
            error_log("Failed to get network stats: " . $e->getMessage());
        }

        return $stats;
    }

    public function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes) / log(1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Remove all port forwarding rules for a peer
     * @param int $peerId The peer ID
     * @return void
     */
    private function removePortForwardingRules($peerId)
    {
        try {
            // Check if port_forwarding_rules table exists
            $tables = $this->db->select("SHOW TABLES LIKE 'port_forwarding_rules'");
            if (empty($tables)) {
                return; // Table doesn't exist, nothing to clean up
            }

            // Get peer info to extract IP
            $peer = $this->db->selectOne("SELECT * FROM wg_peers WHERE id = ?", [$peerId]);
            if (!$peer) {
                return;
            }

            // Get all port forwarding rules for this peer
            $rules = $this->db->select("SELECT * FROM port_forwarding_rules WHERE peer_id = ? AND status = 'active'", [$peerId]);
            
            if (empty($rules)) {
                return; // No rules to remove
            }

            // Extract peer IP
            $peer_ip = $this->extractPeerIp($peer['allowed_ips']);
            if (empty($peer_ip)) {
                error_log("Could not extract peer IP for port forwarding cleanup");
                return;
            }

            // Remove iptables rules for each port forwarding rule
            foreach ($rules as $rule) {
                $protocol = $rule['protocol'];
                $external_port = $rule['external_port'];
                $internal_port = $rule['internal_port'];

                // Remove PREROUTING DNAT rule
                $cmd1 = "sudo /usr/sbin/iptables -t nat -D PREROUTING -p {$protocol} --dport {$external_port} -j DNAT --to-destination {$peer_ip}:{$internal_port} 2>&1";
                shell_exec($cmd1);

                // Remove POSTROUTING MASQUERADE rule
                $cmd2 = "sudo /usr/sbin/iptables -t nat -D POSTROUTING -p {$protocol} -d {$peer_ip} --dport {$internal_port} -j MASQUERADE 2>&1";
                shell_exec($cmd2);

                // Remove FORWARD rules
                $cmd3 = "sudo /usr/sbin/iptables -D FORWARD -p {$protocol} -d {$peer_ip} --dport {$internal_port} -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT 2>&1";
                shell_exec($cmd3);

                $cmd4 = "sudo /usr/sbin/iptables -D FORWARD -p {$protocol} -s {$peer_ip} --sport {$internal_port} -m state --state ESTABLISHED,RELATED -j ACCEPT 2>&1";
                shell_exec($cmd4);

                error_log("Removed port forwarding rule for peer {$peerId}: {$external_port} -> {$peer_ip}:{$internal_port}");
            }

            // Save iptables rules using iptables-save and tee
            shell_exec("sudo /usr/sbin/iptables-save | sudo /bin/tee /etc/iptables/rules.v4 > /dev/null 2>&1");

            // Delete rules from database completely
            $sql = "DELETE FROM port_forwarding_rules WHERE peer_id = ?";
            $this->db->query($sql, [$peerId]);

            error_log("Successfully removed all port forwarding rules for peer {$peerId}");
        } catch (\Exception $e) {
            error_log("Error removing port forwarding rules for peer {$peerId}: " . $e->getMessage());
            // Don't throw exception, just log it - we don't want to stop peer deletion if port forwarding cleanup fails
        }
    }

    /**
     * Extract peer IP from allowed_ips string
     * @param string $allowed_ips The allowed_ips string (e.g., "10.0.0.2/32")
     * @return string The extracted IP address
     */
    private function extractPeerIp($allowed_ips)
    {
        if (empty($allowed_ips)) {
            return '';
        }

        // Handle multiple IPs (comma-separated)
        if (strpos($allowed_ips, ',') !== false) {
            $ips = explode(',', $allowed_ips);
            $allowed_ips = trim($ips[0]); // Use first IP
        }

        // Remove CIDR notation
        $ip = preg_replace('/\/\d+$/', '', trim($allowed_ips));
        
        return $ip;
    }
}
