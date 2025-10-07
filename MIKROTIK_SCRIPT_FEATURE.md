# MikroTik RouterOS Script Generation Feature

## Overview

The WireGuard Admin panel now includes the ability to generate MikroTik RouterOS scripts for each VPN peer. This feature allows you to easily configure WireGuard on MikroTik devices without manually entering configuration commands.

## Features

### 1. Script Generation
- **Preview**: View the script content before downloading
- **Download**: Download the script as a `.rsc` file
- **Auto-configuration**: Scripts include interface creation, IP assignment, and peer configuration

### 2. MikroTik Actions Dropdown
Each peer in the VPN Peers table now has a MikroTik dropdown button with:
- **Preview Script**: Opens a modal showing the complete script
- **Download Script**: Directly downloads the RouterOS script file

## How It Works

### Script Components
The generated MikroTik script includes:

1. **Interface Creation**
   ```routeros
   :if ([:len [/interface wireguard find where name="wg_interface_name"]] = 0) do={
       /interface wireguard add mtu=1420 name="wg_interface_name"
   }
   ```

2. **IP Address Assignment**
   ```routeros
   :if ([:len [/ip address find where address~"192.168.1.2/24"]] = 0) do={
       /ip address add address="192.168.1.2/24" interface="wg_interface_name" network="192.168.1.0"
   }
   ```

3. **Peer Configuration**
   ```routeros
   :if ([:len [/interface wireguard peers find where endpoint-address="server.domain.com"]] = 0) do={
       /interface wireguard peers add \
           allowed-address="192.168.1.0/24" \
           endpoint-address="server.domain.com" \
           endpoint-port=51820 \
           interface="wg_interface_name" \
           persistent-keepalive=1m \
           public-key="SERVER_PUBLIC_KEY"
   }
   ```

4. **Public Key Display**
   ```routeros
   :local wgPubKey [/interface wireguard get [find name="wg_interface_name"] value-name=public-key]
   :put ("Local Public Key: " . $wgPubKey)
   ```

## Usage Instructions

### For Users
1. **Navigate** to the VPN Peers page
2. **Click** the "MikroTik" dropdown button for any peer
3. **Choose** either "Preview Script" or "Download Script"
4. **Copy** the script to your MikroTik device
5. **Run** the script in RouterOS terminal
6. **Copy** the generated public key from the output
7. **Add** the public key to the peer configuration in the web admin

### Script Execution on MikroTik
1. Connect to your MikroTik device via Winbox, SSH, or Web interface
2. Open the Terminal
3. Paste the entire script and press Enter
4. The script will:
   - Create the WireGuard interface (if it doesn't exist)
   - Assign the IP address
   - Configure the peer connection
   - Display the generated public key
5. Copy the displayed public key
6. Return to the web admin and add this public key to the peer

## Technical Details

### File Structure
- **Backend**: `app/backend/generate_mikrotik_script.php`
- **Frontend**: JavaScript functions in `app/wg-peers.php`
- **Authentication**: Uses existing session authentication

### Security Features
- **Authentication check**: Requires valid session
- **Input validation**: Validates peer ID and interface parameters
- **SQL injection protection**: Uses prepared statements

### Error Handling
- Invalid peer ID or interface
- Database connection errors
- Missing configuration data
- Network connectivity issues for public IP detection

## Configuration

### Server Endpoint Detection
The script attempts to detect the server's public endpoint in this order:
1. `SERVER_ENDPOINT` constant from config.php
2. Automatic public IP detection via external services
3. Fallback to placeholder text

### Customization
You can customize the server endpoint by adding to your `config.php`:
```php
define('SERVER_ENDPOINT', 'your-server-domain.com');
```

## Benefits

### For Network Administrators
- **Simplified deployment**: No manual command entry on MikroTik devices
- **Reduced errors**: Pre-configured scripts eliminate typos
- **Consistent configuration**: Standardized setup across devices
- **Time saving**: Automated configuration generation

### For MikroTik Users
- **Easy setup**: Copy-paste script execution
- **No manual configuration**: All parameters pre-filled
- **Error prevention**: Conditional statements prevent duplicate configurations
- **Clear instructions**: Built-in status messages and guidance

## Troubleshooting

### Common Issues
1. **Script not downloading**: Check authentication and peer existence
2. **Wrong server endpoint**: Configure SERVER_ENDPOINT in config.php
3. **Network errors**: Verify MikroTik has internet connectivity
4. **Permission errors**: Ensure proper RouterOS user permissions

### Debug Information
The script includes status messages that will display:
- Interface creation status
- IP assignment results
- Peer configuration status
- Generated public key

## Example Output

When the script runs successfully on MikroTik, you'll see:
```
Created WireGuard interface: wg_trilink_cloudtik_wg
Added IP address 192.168.14.3/24 to interface wg_trilink_cloudtik_wg
Added peer configuration for secure.cloudtik.net:61670

==================== WIREGUARD SETUP COMPLETED ====================
Interface: wg_trilink_cloudtik_wg
Local IP: 192.168.14.3/24
Peer Endpoint: secure.cloudtik.net:61670
Peer Allowed Address: 192.168.14.0/24
Local Public Key: abc123...xyz789
====================================================================

IMPORTANT: Copy the 'Local Public Key' shown above
You need to add this public key to your WireGuard server
as a peer with allowed IPs: 192.168.14.3/24

To add this peer to your server, run:
sudo wg set wg_trilink_cloudtik_wg peer abc123...xyz789 allowed-ips 192.168.14.3/24
```

## Future Enhancements

Potential improvements for this feature:
- Batch script generation for multiple peers
- Custom endpoint configuration per peer
- Integration with MikroTik API for direct configuration
- Support for additional RouterOS features (routing, firewall rules)
- Script templates for different deployment scenarios