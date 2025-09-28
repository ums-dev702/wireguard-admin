<?php
/**
 * AJAX endpoint to get the next available IP for an interface
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_authenticated']) || $_SESSION['user_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get interface from query parameter
$interface = $_GET['interface'] ?? '';

if (empty($interface)) {
    echo json_encode(['success' => false, 'error' => 'Interface parameter required']);
    exit;
}

// Function to get next available IP for an interface
function getNextAvailableIP($interface)
{
    try {
        $db = get_db();

        // Get interface subnet from database
        $stmt = $db->prepare('SELECT address FROM interfaces WHERE name = ? LIMIT 1');
        $stmt->execute([$interface]);
        $interface_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$interface_data || empty($interface_data['address'])) {
            error_log("No interface found for {$interface}");
            return false;
        }

        // Extract IP and CIDR from interface address (e.g., 10.0.0.1/24)
        $address_parts = explode('/', $interface_data['address']);
        if (count($address_parts) !== 2) {
            throw new Exception("Invalid interface address format: {$interface_data['address']}");
        }

        $subnet_ip = $address_parts[0];
        $cidr = intval($address_parts[1]);
        
        // Validate CIDR
        if ($cidr < 8 || $cidr > 30) {
            throw new Exception("Invalid CIDR: {$cidr}. Must be between 8 and 30.");
        }

        // Calculate network address and usable range
        $ip_int = ip2long($subnet_ip);
        if ($ip_int === false) {
            throw new Exception("Invalid IP format: {$subnet_ip}");
        }

        // Calculate network mask and network address
        $host_bits = 32 - $cidr;
        $network_mask = ~((1 << $host_bits) - 1);
        $network_int = $ip_int & $network_mask;
        
        // Calculate usable IP range (skip network and broadcast addresses)
        $first_usable = $network_int + 1;
        $last_usable = $network_int + (1 << $host_bits) - 2;
        
        // The interface IP itself should be skipped
        $interface_ip_int = $ip_int;

        // Get all used IPs from peers
        $used_ips = [];
        try {
            $stmt = $db->prepare('SELECT allowed_ips FROM peers WHERE status = "active"');
            $stmt->execute();
            $peers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($peers as $peer) {
                if (!empty($peer['allowed_ips'])) {
                    // Extract only the IP part (e.g., 10.0.0.2/32 -> 10.0.0.2)
                    [$peer_ip] = explode('/', $peer['allowed_ips']);
                    $peer_ip_int = ip2long($peer_ip);
                    if ($peer_ip_int !== false && $peer_ip_int >= $first_usable && $peer_ip_int <= $last_usable) {
                        $used_ips[] = $peer_ip_int;
                    }
                }
            }
        } catch (Exception $e) {
            // If peers table doesn't exist or has different structure, continue with empty array
            error_log("Error getting used IPs: " . $e->getMessage());
            $used_ips = [];
        }

        // Find next available IP in the subnet range
        for ($ip_int = $first_usable; $ip_int <= $last_usable; $ip_int++) {
            // Skip the interface IP and already used IPs
            if ($ip_int !== $interface_ip_int && !in_array($ip_int, $used_ips)) {
                $next_ip = long2ip($ip_int);
                if ($next_ip !== false) {
                    return $next_ip . '/32';
                }
            }
        }

        // If no IP available in this subnet
        error_log("No available IPs in subnet {$subnet_ip}/{$cidr}");
        return false;
        
    } catch (Exception $e) {
        error_log("getNextAvailableIP error: " . $e->getMessage());
        return false;
    }
}

try {
    // Get next available IP for this interface
    $nextIP = getNextAvailableIP($interface);
    
    if ($nextIP) {
        echo json_encode(['success' => true, 'ip' => $nextIP]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Could not generate next available IP', 'fallback' => '10.0.0.2/32']);
    }
    
} catch (Exception $e) {
    error_log("Error getting next available IP: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred', 'fallback' => '10.0.0.2/32']);
}
?>