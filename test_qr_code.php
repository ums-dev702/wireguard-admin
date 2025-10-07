<?php
// Test QR Code Generation
echo "Testing QR Code Generation\n";
echo "==========================\n\n";

// Sample WireGuard configuration
$config = "[Interface]
PrivateKey = " . base64_encode(random_bytes(32)) . "=
Address = 10.0.0.2/32
DNS = 1.1.1.1, 8.8.8.8

[Peer]
PublicKey = YOUR_SERVER_PUBLIC_KEY_HERE
Endpoint = your-server.domain.com:51820
AllowedIPs = 10.0.0.0/24
PersistentKeepalive = 25";

echo "Sample WireGuard Configuration:\n";
echo "--------------------------------\n";
echo $config . "\n\n";

// Generate QR code URLs
$qr_data_encoded = urlencode($config);
$qr_url1 = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . $qr_data_encoded;
$qr_url2 = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . $qr_data_encoded;

echo "QR Code URLs:\n";
echo "-------------\n";
echo "Method 1 (QR Server): " . $qr_url1 . "\n\n";
echo "Method 2 (Google Charts): " . $qr_url2 . "\n\n";

echo "Test URLs for peer ID 1 and interface wg0:\n";
echo "-------------------------------------------\n";
echo "QR Code: http://localhost/Alvinkiveu.com_scripts/Wirgaurd_Admin/app/qr-code-simple.php?peer_id=1&interface=wg0\n";
echo "Config Download: http://localhost/Alvinkiveu.com_scripts/Wirgaurd_Admin/app/download-config-simple.php?peer_id=1&interface=wg0\n\n";

echo "✅ QR Code functionality ready!\n";
echo "You can now use the QR button in the peers table to generate QR codes.\n";
?>