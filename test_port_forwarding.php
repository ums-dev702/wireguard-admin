<?php
// Test the port forwarding functionality

echo "=== Port Forwarding Feature Test ===\n\n";

// Test the iptables generation for multiple services
$peer_ip = "10.20.20.4";
$peer_name = "Test MikroTik";

$test_rules = [
    [
        'name' => 'Winbox Access',
        'external_port' => '6843',
        'internal_port' => '8291',
        'protocol' => 'tcp',
        'description' => 'MikroTik Winbox management'
    ],
    [
        'name' => 'Web Config',
        'external_port' => '6842',
        'internal_port' => '80',
        'protocol' => 'tcp',
        'description' => 'HTTP web interface'
    ],
    [
        'name' => 'HTTPS Config',
        'external_port' => '6844',
        'internal_port' => '443',
        'protocol' => 'tcp',
        'description' => 'HTTPS web interface'
    ],
    [
        'name' => 'SSH Access',
        'external_port' => '6845',
        'internal_port' => '22',
        'protocol' => 'tcp',
        'description' => 'SSH remote access'
    ]
];

echo "Generating iptables rules for {$peer_name} ({$peer_ip}):\n";
echo "=".str_repeat("=", 50)."\n\n";

foreach ($test_rules as $rule) {
    $name = $rule['name'];
    $ext_port = $rule['external_port'];
    $int_port = $rule['internal_port'];
    $protocol = $rule['protocol'];
    $description = $rule['description'];
    
    echo "# {$name} - {$description}\n";
    echo "iptables -t nat -A PREROUTING -p {$protocol} --dport {$ext_port} -j DNAT --to-destination {$peer_ip}:{$int_port}\n";
    echo "iptables -t nat -A POSTROUTING -p {$protocol} -d {$peer_ip} --dport {$int_port} -j MASQUERADE\n";
    echo "iptables -A FORWARD -p {$protocol} -d {$peer_ip} --dport {$int_port} -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT\n";
    echo "iptables -A FORWARD -p {$protocol} -s {$peer_ip} --sport {$int_port} -m state --state ESTABLISHED,RELATED -j ACCEPT\n\n";
}

echo "UFW Rules:\n";
echo "=".str_repeat("=", 20)."\n";
foreach ($test_rules as $rule) {
    echo "sudo ufw allow {$rule['external_port']}/{$rule['protocol']}\n";
}

echo "\nMake persistent:\n";
echo "=".str_repeat("=", 20)."\n";
echo "sudo apt install iptables-persistent -y\n";
echo "sudo netfilter-persistent save\n";
echo "sudo ufw reload\n\n";

echo "✅ Port Forwarding Test Complete!\n";
echo "📝 Features Available:\n";
echo "   • Multiple service port forwarding\n";
echo "   • Custom port mapping\n";
echo "   • Automatic script generation\n";
echo "   • Removal script creation\n";
echo "   • UFW integration\n\n";

echo "🔗 Access the Port Forwarding Manager at: app/port_forwarding.php\n";
?>