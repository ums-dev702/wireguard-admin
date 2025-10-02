<?php

namespace WireGuardAdmin;

class WireGuard {
    private $db;
    private $interfaceName;
    private $configPath;

    public function __construct(Database $db, $interfaceName = null, $configPath = null) {
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
    private function getFirstInterface() {
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
    public function getInterfaceName() {
        return $this->interfaceName;
    }

    /**
     * Get the current config path
     * @return string
     */
    public function getConfigPath() {
        return $this->configPath;
    }

    /**
     * Refresh interface settings to use the first available interface
     * @return bool True if interface was found and set, false otherwise
     */
    public function refreshInterface() {
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

    public function getStatus() {
        try {
            $output = shell_exec("sudo wg show {$this->interfaceName} 2>/dev/null");
            return $output !== null ? trim($output) : 'Interface not running';
        } catch (\Exception $e) {
            return 'Error getting status: ' . $e->getMessage();
        }
    }

    
    public function isRunning() {
        $output = shell_exec("sudo wg show {$this->interfaceName} 2>/dev/null");
        return !empty($output);
    }

    public function startInterface() {
        try {
            $output = shell_exec("sudo wg-quick up {$this->interfaceName} 2>&1");
            return strpos($output, 'FAILED') === false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function stopInterface() {
        try {
            $output = shell_exec("sudo wg-quick down {$this->interfaceName} 2>&1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function restartInterface() {
        $this->stopInterface();
        sleep(2);
        return $this->startInterface();
    }

    public function generateKeyPair() {
        $privateKey = trim(shell_exec('wg genkey'));
        $publicKey = trim(shell_exec("echo '{$privateKey}' | wg pubkey"));
        
        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey
        ];
    }

    public function createPeer($name, $allowedIps, $dns = '8.8.8.8', $endpoint = null) {
        try {
            $keyPair = $this->generateKeyPair();
            
            // Get current interface name without wg_ prefix for database
            $interface_name = preg_replace('/^wg_/', '', $this->interfaceName);
            
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
                'iface_id' => $interface_name,
                'public_key' => $keyPair['public_key'],
                'private_key' => $keyPair['private_key'],
                'allowed_ips' => $allowedIps,
                'endpoint' => $endpoint,
                'dns_servers' => $dns,
                'status' => 'active'
            ]);

            // Add peer to WireGuard config
            $this->addPeerToConfig($keyPair['public_key'], $allowedIps);
            
            // Restart interface to apply changes
            $this->restartInterface();
            
            $this->db->commit();
            
            return [
                'id' => $peerId,
                'peer_id' => $peer_id,
                'name' => $name,
                'private_key' => $keyPair['private_key'],
                'public_key' => $keyPair['public_key'],
                'allowed_ips' => $allowedIps,
                'iface_id' => $interface_name,
                'endpoint' => $endpoint
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw new \Exception("Failed to create peer: " . $e->getMessage());
        }
    }

    public function deletePeer($peerId) {
        try {
            // Get peer info from wg_peers table
            $peer = $this->db->selectOne("SELECT * FROM wg_peers WHERE id = ?", [$peerId]);
            if (!$peer) {
                throw new \Exception("Peer not found");
            }

            $this->db->beginTransaction();

            // Remove from WireGuard
            shell_exec("sudo wg set {$this->interfaceName} peer {$peer['public_key']} remove");
            
            // Remove from config file
            $this->removePeerFromConfig($peer['public_key']);
            
            // Mark as inactive in database
            $this->db->update('wg_peers', 
                ['status' => 'inactive'], 
                'id = ?', 
                [$peerId]
            );

            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw new \Exception("Failed to delete peer: " . $e->getMessage());
        }
    }

    public function getPeers($activeOnly = true) {
        // Get current interface name without wg_ prefix for database lookup
        $interface_name = preg_replace('/^wg_/', '', $this->interfaceName);
         echo "SLECTING INTERFACE NAME: " . $interface_name . "\n";
        
        $sql = "SELECT * FROM wg_peers WHERE iface_id = ?";
        $params = [$interface_name];
        
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        $sql .= " ORDER BY created_at DESC";
        
        try {
            return $this->db->select($sql, $params);
        } catch (\Exception $e) {
            error_log("Error getting peers for interface {$interface_name}: " . $e->getMessage());
            return [];
        }
    }

    public function getPeer($peerId) {
        return $this->db->selectOne("SELECT * FROM wg_peers WHERE id = ?", [$peerId]);
    }

    public function updatePeerStats() {
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

    public function generateClientConfig($peerId, $serverPublicKey, $serverEndpoint, $serverPort = 51820) {
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

    private function addPeerToConfig($publicKey, $allowedIps) {
        $peerConfig = "\n[Peer]\n";
        $peerConfig .= "PublicKey = {$publicKey}\n";
        $peerConfig .= "AllowedIPs = {$allowedIps}\n";
        
        file_put_contents($this->configPath, $peerConfig, FILE_APPEND | LOCK_EX);
    }

    private function removePeerFromConfig($publicKey) {
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

    public function getSystemStats() {
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

    private function getNetworkStats() {
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

    public function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes) / log(1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
