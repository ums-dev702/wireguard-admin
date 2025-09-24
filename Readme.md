# WireGuard Admin Panel 2.0

Professional VPN Management System with Modern UI and Enhanced Security

## 🚀 Features

### ✨ New in Version 2.0
- **Professional Onboarding Experience** - Guided installation wizard
- **Object-Oriented PHP Architecture** - Secure and maintainable codebase
- **Modern Glass-morphism UI** - Beautiful and responsive design
- **Enhanced Security** - CSRF protection, audit logging, session management
- **Real-time Monitoring** - Live system stats and peer status
- **Comprehensive Database** - SQLite with proper relationships
- **Professional Animations** - Smooth transitions and interactive elements

### 🛡️ Security Features
- Password hashing with bcrypt
- CSRF token protection
- Session timeout management
- Audit trail logging
- Rate limiting for login attempts
- Remember me functionality with secure tokens
- Input validation and sanitization

### 📊 Management Features
- VPN peer management
- Port forwarding configuration
- Real-time system monitoring
- User management
- Audit logs
- Settings configuration

## 🎨 Modern UI Features
- Glass-morphism design
- Responsive layout
- Dark/Light theme support
- Animated backgrounds
- Professional dashboard
- Interactive notifications
- Loading states and transitions

## 🔧 Installation

### Prerequisites
- PHP 7.4 or higher
- WireGuard installed on server
- SQLite support
- Web server (Apache/Nginx)
- sudo access for www-data user

### Quick Start
1. **Clone/Download** the application to your web directory
2. **Navigate** to your domain/application URL
3. **Follow the installation wizard** - it will guide you through:
   - System requirements check
   - Database setup
   - Admin account creation
   - WireGuard configuration
   - Security settings

### Manual Installation Steps

#### 1. System Requirements
```bash
# Install WireGuard
sudo apt update
sudo apt install wireguard

# Install PHP extensions
sudo apt install php-sqlite3 php-pdo
```

#### 2. Permissions Setup
```bash
# Set directory permissions
sudo chown -R www-data:www-data /path/to/wireguard-admin
sudo chmod 755 -R /path/to/wireguard-admin
sudo chmod 775 /path/to/wireguard-admin/data

# Configure sudo access for WireGuard
sudo visudo -f /etc/sudoers.d/www-data
```

Add to sudoers file:



```
www-data ALL=(root) NOPASSWD: /usr/bin/wg, /usr/bin/wg-quick, /usr/sbin/iptables, /bin/cat /etc/wireguard/*
```

#### 3. Web Server Configuration

**Apache (.htaccess)**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

**Nginx**
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}

# Security headers
add_header X-Content-Type-Options nosniff;
add_header X-Frame-Options DENY;
add_header X-XSS-Protection "1; mode=block";
```

## 🏗️ Architecture

### Object-Oriented Structure
```
classes/
├── Database.php      # Database abstraction layer
├── Auth.php          # Authentication and authorization
├── WireGuard.php     # WireGuard management
└── Installer.php     # Installation wizard

includes/             # Legacy includes (deprecated)
assets/
├── css/             # Custom stylesheets
└── js/              # Custom JavaScript

data/                # Application data
└── wg-admin.db      # SQLite database
```

### Database Schema
- **users** - Admin users and authentication
- **peers** - VPN peer configurations
- **port_forwards** - Port forwarding rules
- **settings** - Application settings
- **audit_log** - Security and activity logs
- **installation_status** - Installation progress tracking

## 🎛️ Configuration

### Environment Variables
Create a `.env` file (optional):
```env
WG_DEBUG=false
DB_PATH=/custom/path/to/database.db
WG_CONF_PATH=/etc/wireguard/wg0.conf
SESSION_TIMEOUT=1800
```

### Runtime Configuration
Settings can be modified through the web interface or directly in the database:
- Server IP/Domain
- VPN subnet
- Session timeout
- Security settings
- Logging preferences

## 🔐 Security Best Practices

### Recommended Security Settings
1. **HTTPS Only** - Always use SSL/TLS encryption
2. **Strong Passwords** - Enforce complex admin passwords
3. **Regular Updates** - Keep WireGuard and PHP updated
4. **Firewall Rules** - Restrict admin panel access
5. **Backup Strategy** - Regular database backups
6. **Log Monitoring** - Review audit logs regularly

### Firewall Configuration
```bash
# Allow WireGuard port
sudo ufw allow 51820/udp

# Restrict admin panel access (example)
sudo ufw allow from 192.168.1.0/24 to any port 80
sudo ufw allow from 192.168.1.0/24 to any port 443
```

## 📱 Usage

### Adding VPN Peers
1. Navigate to **VPN Peers** page
2. Click **Add New Peer**
3. Fill in peer details:
   - Name (descriptive identifier)
   - Allowed IPs (peer's VPN IP)
   - DNS servers
4. Download the generated config file
5. Import into WireGuard client

### Port Forwarding
1. Go to **Port Forwarding** section
2. Select target peer
3. Configure:
   - External port
   - Internal port
   - Protocol (TCP/UDP)
4. Apply rules

### Monitoring
- **Dashboard** shows real-time system stats
- **Audit Logs** track all administrative actions
- **Peer Status** displays connection information

## 🐛 Troubleshooting

### Common Issues

**Installation stuck at requirements check:**
```bash
# Check PHP extensions
php -m | grep -E "(pdo|sqlite)"

# Check WireGuard installation
wg --version
```

**Permission denied errors:**
```bash
# Fix ownership
sudo chown -R www-data:www-data /path/to/app

# Check sudo configuration
sudo -u www-data sudo wg show
```

**Database connection errors:**
```bash
# Check directory permissions
ls -la data/
chmod 775 data/
```

### Log Files
- **System logs**: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
- **Application logs**: Check audit logs in admin panel
- **WireGuard logs**: `journalctl -u wg-quick@wg0`

## 🔄 Updates

### Updating from Version 1.x
1. **Backup** your current installation
2. **Replace** files with new version
3. **Run** the installation wizard (it will upgrade existing data)
4. **Review** new settings and configuration

### Automated Updates (Future)
We're working on an automated update system for future releases.

## 🤝 Contributing

### Development Setup
1. Clone the repository
2. Set up a local development environment
3. Enable debug mode: `define('WG_DEBUG', true);`
4. Make your changes
5. Test thoroughly
6. Submit a pull request

### Code Standards
- PSR-4 autoloading
- PSR-12 coding standards
- Comprehensive error handling
- Security-first approach

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

### Getting Help
- **Documentation**: Check this README first
- **Issues**: Create a GitHub issue for bugs
- **Security**: Report security issues privately
- **Community**: Join our Discord/Forum (coming soon)

### Commercial Support
Professional support and custom development available. Contact us for enterprise solutions.

## 🙏 Acknowledgments

- WireGuard team for the excellent VPN solution
- TailwindCSS for the beautiful styling framework
- Font Awesome for the icon library
- PHP community for continuous improvements

---

**Made with ❤️ for the VPN community**

Version 2.0.0 - Professional VPN Management Made Simple


git remote set-url origin git@github.com:alvin-kiveu/wireguard-admin.git
