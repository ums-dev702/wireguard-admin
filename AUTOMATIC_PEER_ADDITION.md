# Automatic WireGuard Peer Addition Feature

This enhancement adds automatic peer addition to WireGuard interfaces when users provide public keys, mimicking the command:
```bash
sudo wg set wg0 peer uKJVHa0NZoI9q3Jp6IHB4QrldrYftDMOW4db1+s5f2k= allowed-ips 10.7.0.5/32
```

## How It Works

### 1. User Workflow
1. User creates a peer in the web interface (gets assigned an IP)
2. User clicks "Edit Public Key" or "Add public key" for the peer
3. User pastes their WireGuard public key
4. System automatically executes the `wg set` command
5. Peer becomes immediately active in the WireGuard interface

### 2. Technical Implementation

#### Backend Functions Added

**In `includes/functions.php`:**
```php
add_peer_to_wireguard_interface($interface_name, $public_key, $allowed_ips)
remove_peer_from_wireguard_interface($interface_name, $public_key)
```

**In `classes/WireGuard.php`:**
```php
addPeerToInterface($publicKey, $allowedIps)
removePeerFromInterface($publicKey)
```

#### Command Execution
When a public key is provided, the system:
1. Validates the key format
2. Executes: `sudo wg set wg_INTERFACE peer PUBLIC_KEY allowed-ips ALLOWED_IPS`
3. Saves configuration: `sudo wg-quick save wg_INTERFACE`
4. Updates database status to 'active'
5. Sends Telegram notification (if configured)

### 3. User Interface Enhancements

#### Command Preview
- Shows the exact command that will be executed when user enters a public key
- Real-time preview updates as user types/pastes the key
- Example: `sudo wg set wg_acs peer ABC123... allowed-ips 10.7.0.5/32`

#### Visual Feedback
- Success messages include the executed command
- Error messages show what went wrong
- Status changes from 'unconfigured' to 'active'

## Example Usage

### Adding a Peer
```php
// Example from the documentation
$interface = "acs";
$public_key = "uKJVHa0NZoI9q3Jp6IHB4QrldrYftDMOW4db1+s5f2k=";
$allowed_ips = "10.7.0.5/32";

$result = add_peer_to_wireguard_interface($interface, $public_key, $allowed_ips);

if ($result['success']) {
    echo "Peer added successfully!";
    echo "Command: " . $result['command'];
} else {
    echo "Failed: " . $result['message'];
}
```

### Telegram Notification
```
✅ WireGuard Peer Added via Function
===================================
Interface: wg_acs
Public Key: uKJVHa0NZoI9q3Jp6I...
Allowed IPs: 10.7.0.5/32
Command: sudo wg set wg_acs peer uKJVHa0NZoI9q3Jp6IHB4QrldrYftDMOW4db1+s5f2k= allowed-ips 10.7.0.5/32
Status: ✅ Active
===================================
```

## Configuration Requirements

### System Requirements
1. **sudo Access**: Web server user needs sudo access for `wg` commands
2. **WireGuard Installed**: `wg` and `wg-quick` commands available
3. **Permissions**: Ability to read/write WireGuard config files

### Recommended sudo Configuration
Add to `/etc/sudoers` (use `visudo`):
```
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg set *
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg-quick save *
```

Or give broader access (less secure):
```
www-data ALL=(ALL) NOPASSWD: ALL
```

## Security Considerations

### Input Validation
- Public keys are validated for proper WireGuard format
- Interface names are sanitized to prevent injection
- All shell commands use proper escaping

### Logging
- All commands executed are logged
- Success/failure status tracked
- User activities logged with IP and user agent

### Error Handling
- Graceful handling of command failures
- Database rollback on errors
- Detailed error messages for troubleshooting

## Benefits

1. **Immediate Activation**: Peers become active instantly when key is added
2. **No Manual Commands**: Eliminates need for SSH access to run wg commands
3. **User Friendly**: Simple web interface for complex operations
4. **Audit Trail**: Complete logging of all peer additions/removals
5. **Notifications**: Real-time alerts via Telegram
6. **Transparency**: Users see exactly what commands are executed

## Troubleshooting

### Common Issues

**"Permission denied" errors:**
- Check sudo configuration for www-data user
- Verify wg commands are executable

**"Interface not found" errors:**
- Ensure WireGuard interface is running
- Check interface naming convention (wg_ prefix)

**Database out of sync:**
- Use the repair/sync functions to align database with actual WireGuard state

### Debug Mode
Enable verbose logging by setting:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Testing
Run the demo script:
```bash
php demo_peer_addition.php
```

## Migration from Manual Process

### Before (Manual)
1. User creates peer in web interface
2. Admin SSHs to server
3. Admin runs: `sudo wg set wg0 peer PUBLIC_KEY allowed-ips IP/32`
4. Admin runs: `sudo wg-quick save wg0`
5. User's device connects

### After (Automatic)
1. User creates peer in web interface
2. User pastes public key in web interface
3. System automatically executes commands
4. User's device connects immediately

## Future Enhancements

- **Bulk Operations**: Add multiple peers at once
- **QR Code Integration**: Generate QR codes with embedded keys
- **Client Config Generation**: Auto-generate complete client configs
- **Health Monitoring**: Check peer connectivity status
- **Advanced Routing**: Support for complex routing scenarios