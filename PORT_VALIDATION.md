# Port Validation Enhancement

This enhancement adds comprehensive port validation to the WireGuard Admin interface creation process. Before generating a listen port or accepting a user-specified port, the system now checks if the port is available by verifying it's not in use by:

## What is Checked

### 1. Socket Binding
- Uses `ss -lun` command to check if any process is already listening on the UDP port
- Attempts to bind to the port directly as a final verification

### 2. UFW Firewall Rules
- Checks `ufw status` output for existing rules using the port
- Looks for both specific `/udp` rules and general port mentions
- Handles inactive UFW status appropriately

### 3. Port Forwarding Rules
- Examines `iptables -t nat -L PREROUTING` output
- Searches for DNAT rules using the port (both `dpt:` and `--dport` formats)
- Prevents conflicts with existing port forwarding configurations

## New Functions Added

### `is_port_in_port_forwarding(int $port): bool`
Checks if a port is used in iptables PREROUTING rules for port forwarding.

### `is_port_completely_free(int $port): bool`
Comprehensive check combining all three validation methods.

### `validate_wireguard_port(int $port): array`
Returns detailed validation results with user-friendly messages.

### Enhanced `find_free_udp_port(int $start, int $end)`
Updated to use the comprehensive validation before suggesting a port.

## UI Enhancements

### Interactive Port Validation
- Real-time port validation when users enter a port number
- Visual feedback with success/error indicators
- "Check" button for manual validation

### Visual Feedback
- Green checkmark for available ports
- Red warning icon for unavailable ports
- Detailed messages explaining why a port cannot be used

## Backend API

### `/app/backend/port_validator.php`
New API endpoint that:
- Accepts POST requests with port numbers
- Returns JSON responses with validation results
- Requires user authentication
- Provides detailed error messages

## Usage Examples

### Automatic Port Generation
When creating a new interface, the system automatically finds an available port:
```php
$listen_port = find_free_udp_port(20000, 60000);
```

### Manual Port Validation
Users can specify their own port, which is validated before use:
```php
$validation = validate_wireguard_port($user_port);
if (!$validation['valid']) {
    // Show error message: $validation['message']
}
```

## Error Handling

The system is designed to be conservative:
- If any validation check fails, the port is considered unavailable
- Network/permission errors default to "port in use" for safety
- Detailed error logging for troubleshooting

## Testing

Use the included test script to verify functionality:
```bash
php test_port_validation.php
```

This will test various ports and show the validation results for each check.

## Configuration Requirements

Ensure the web server process has appropriate permissions to run:
- `ss -lun` (usually available to all users)
- `ufw status` (may require sudo configuration)
- `iptables -t nat -L PREROUTING` (requires sudo)

For production use, consider configuring sudo to allow specific commands without password prompts for the web server user.

## Benefits

1. **Prevents Conflicts**: Avoids port conflicts with existing services
2. **Better UX**: Immediate feedback on port availability
3. **Comprehensive**: Checks multiple potential conflict sources
4. **Robust**: Handles errors gracefully with conservative defaults
5. **Informative**: Provides clear explanations for port rejection

## Future Enhancements

- Cache validation results for better performance
- Add support for checking other firewall systems (firewalld, etc.)
- Port conflict resolution suggestions
- Integration with port forwarding management