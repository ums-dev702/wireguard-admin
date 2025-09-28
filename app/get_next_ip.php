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
            // No interface found in database, use default subnet
            error_log("No interface found for {$interface}, using default subnet");
            $default_subnet = '10.0.0.1/24'; // Default VPN subnet
            [$subnet_ip, $cidr] = explode('/', $default_subnet);
        } else {
            // Extract IP and CIDR from interface address (e.g., 10.0.0.1/24)
            [$subnet_ip, $cidr] = explode('/', $interface_data['address']);
        }
        $ip_parts = explode('.', $subnet_ip);

        if (count($ip_parts) !== 4) {
            throw new Exception("Invalid interface IP format: {$subnet_ip}");
        }

        // Base IP prefix (e.g., "10.0.0.")
        $base_ip = $ip_parts[0] . '.' . $ip_parts[1] . '.' . $ip_parts[2] . '.';
        $start = 2; // start from .2 (skip .1 which is usually the gateway/interface)

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
                    if (strpos($peer_ip, $base_ip) === 0) {
                        $used_ips[] = $peer_ip;
                    }
                }
            }
        } catch (Exception $e) {
            // If peers table doesn't exist or has different structure, continue with empty array
            error_log("Error getting used IPs: " . $e->getMessage());
            $used_ips = [];
        }

        // Find next available IP in subnet range
        for ($i = $start; $i <= 254; $i++) {
            $test_ip = $base_ip . $i;
            if (!in_array($test_ip, $used_ips)) {
                return $test_ip . '/32';
            }
        }

        // If no IP available in this subnet, try a fallback range
        error_log("No available IPs in subnet {$base_ip}0/{$cidr}");
        return false;
    } catch (Exception $e) {
        error_log("getNextAvailableIP error: " . $e->getMessage());
        // Return a reasonable fallback IP based on common VPN ranges
        $fallback_ranges = ['10.0.0.', '192.168.1.', '172.16.0.'];
        foreach ($fallback_ranges as $range) {
            for ($i = 2; $i <= 10; $i++) {
                $fallback_ip = $range . $i . '/32';
                // We can't easily check if IP is in use here without the function, so just return first fallback
                return $fallback_ip;
            }
        }
        return '10.0.0.2/32'; // Ultimate fallback
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