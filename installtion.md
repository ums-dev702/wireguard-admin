


Alvin kiveu

# WireGuard Admin 2.0 - Professional Installation Guide

## 🚀 Quick Start

### Automated Installation (Recommended)
1. Upload files to your web server
2. Navigate to your domain/IP in a web browser
3. Follow the beautiful installation wizard!

The installation wizard will guide you through:
- ✅ System requirements check
- 🗄️ Database creation
- 👤 Admin account setup  
- ⚙️ WireGuard configuration
- 🔒 Security settings

---

## 🔧 Manual Installation

### Prerequisites

#### System Requirements
- **OS**: Ubuntu 20.04+, Debian 10+, CentOS 8+
- **PHP**: 7.4 or higher with extensions:
  - `pdo_sqlite`
  - `curl`
  - `json`
  - `mbstring`
- **WireGuard**: Latest version
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **RAM**: Minimum 512MB, Recommended 1GB+
- **Storage**: 1GB free space

### Step 1: System Preparation

#### Update System
```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

# CentOS/RHEL
sudo yum update -y
# OR for newer versions
sudo dnf update -y
```

#### Install WireGuard
```bash
# Ubuntu/Debian
sudo apt install wireguard wireguard-tools -y

# CentOS/RHEL
sudo yum install epel-release elrepo-release -y
sudo yum install kmod-wireguard wireguard-tools -y
```

#### Install PHP and Extensions
```bash
# Ubuntu/Debian
sudo apt install php php-cli php-fpm php-sqlite3 php-pdo php-curl php-json php-mbstring -y

# CentOS/RHEL
sudo yum install php php-cli php-fpm php-pdo php-sqlite3 php-curl php-json php-mbstring -y
```

### Step 2: Web Server Setup

 Set Ownership and Permissions
--------------------------------

```bash
sudo chown -R www-data:www-data /var/www/html/wireguard-admin && sudo chmod -R 755 /var/www/html/wireguard-admin
```

#### Apache Configuration
```bash
# Install Apache
sudo apt install apache2 -y  # Ubuntu/Debian
sudo yum install httpd -y     # CentOS/RHEL

# Enable required modules
sudo a2enmod rewrite ssl headers
sudo systemctl restart apache2
```
4. Add Virtual Host Config
--------------------------

```bash
sudo nano /etc/apache2/sites-available/wg.ispnetwork.xyz.conf
```

Paste:

```bash
<VirtualHost *:80>
    ServerName wg.ispnetwork.xyz
    ServerAdmin webmaster@wg.ispnetwork.xyz
    DocumentRoot /var/www/html/wireguard

    <Directory /var/www/html/wireguard/>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/wg_error.log
    CustomLog ${APACHE_LOG_DIR}/wg_access.log combined

    <IfModule mod_dir.c>
        DirectoryIndex index.html index.php
    </IfModule>
</VirtualHost>
```

5. Enable Site and Modules
--------------------------
```bash
sudo a2ensite wg.ispnetwork.xyz.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

6. Restart Apache
-----------------
```bash
sudo systemctl restart apache2
```

7. Enable SSL (Let's Encrypt)
-----------------------------
```bash
sudo certbot --apache -d wg.ispnetwork.xyz
sudo certbot --nginx -d wg.ispnetwork.xyz
```


======================================================
  NGINX CONFIGURATION
======================================================

4. Create Server Block
----------------------

```bash
sudo nano /etc/nginx/sites-available/wg.ispnetwork.xyz
```

Paste:

```bash
server {
    listen 80;
    server_name wg.ispnetwork.xyz;

    root /var/www/html/wireguard-admin;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    error_log /var/log/nginx/wg_error.log;
    access_log /var/log/nginx/wg_access.log;
}
```

5. Enable Site
--------------
```bash
sudo ln -s /etc/nginx/sites-available/wg.ispnetwork.xyz /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

sudo rm /etc/nginx/sites-available/wg.ispnetwork.xyz
sudo rm /etc/nginx/sites-enabled/wg.ispnetwork.xyz
```

6. Enable SSL (Let's Encrypt)
-----------------------------
```bash
sudo certbot --nginx -d wg.ispnetwork.xyz
```

### Step 4: Sudo Configuration

#### Configure WireGuard Access
```bash
# Edit sudoers file
sudo visudo
```

Add the following content:
```bash
www-data ALL=(ALL) NOPASSWD: /usr/sbin/iptables, /usr/sbin/iptables-save, /usr/sbin/iptables-restore, /usr/bin/wg-quick up *, /usr/bin/wg-quick down *, /usr/bin/wg set *, /usr/bin/wg, /usr/bin/wg show, /usr/local/bin/wg-dump, /usr/sbin/ufw, /sbin/ip, /usr/bin/wg

```

check if iptables is in /usr/sbin/iptables
```bash
which iptables
```

check all the port fowardin in iptables
```bash
sudo iptables -S
```



#### Test Sudo Access
```bash
# Test as www-data user
sudo -u www-data sudo wg --version
sudo -u www-data sudo wg-quick --version
```

### Step 5: Firewall Configuration

#### UFW (Ubuntu/Debian)
```bash
# Install UFW
sudo apt install ufw -y

# Basic rules
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Allow SSH (adjust port as needed)
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow WireGuard (adjust port as configured)
sudo ufw allow 51820/udp

# Enable firewall
sudo ufw enable
```

#### FirewallD (CentOS/RHEL)
```bash
# Start and enable firewalld
sudo systemctl start firewalld
sudo systemctl enable firewalld

# Allow services
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-port=51820/udp

# Reload
sudo firewall-cmd --reload
```

### Step 6: SSL Certificate (Recommended)

#### Using Let's Encrypt (Free)
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache -y  # Apache
sudo apt install certbot python3-certbot-nginx -y   # Nginx

# Generate certificate
sudo certbot --apache -d your-domain.com  # Apache
sudo certbot --nginx -d your-domain.com   # Nginx

# Auto-renewal
sudo crontab -e
# Add line:
0 12 * * * /usr/bin/certbot renew --quiet
```

#### Using Self-Signed Certificate
```bash
# Generate self-signed certificate
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/wireguard-admin.key \
    -out /etc/ssl/certs/wireguard-admin.crt
```

### Step 7: WireGuard Server Setup

#### Generate Server Keys
```bash
# Create WireGuard directory
sudo mkdir -p /etc/wireguard
sudo chmod 700 /etc/wireguard

# Generate server keys
sudo wg genkey | sudo tee /etc/wireguard/server-private.key
sudo cat /etc/wireguard/server-private.key | wg pubkey | sudo tee /etc/wireguard/server-public.key

# Set permissions
sudo chmod 600 /etc/wireguard/server-private.key
sudo chmod 644 /etc/wireguard/server-public.key
```

#### Create Basic WireGuard Config
```bash
# Create basic config
sudo tee /etc/wireguard/wg0.conf > /dev/null <<EOF
[Interface]
PrivateKey = $(sudo cat /etc/wireguard/server-private.key)
Address = 10.0.0.1/24
ListenPort = 51820
SaveConfig = true

# Enable IP forwarding
PostUp = echo 1 > /proc/sys/net/ipv4/ip_forward
PostUp = iptables -A FORWARD -i wg0 -j ACCEPT
PostUp = iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE

# Cleanup rules on shutdown
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT
PostDown = iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE
EOF

# Set permissions
sudo chmod 600 /etc/wireguard/wg0.conf
```

#### Enable IP Forwarding
```bash
# Enable permanently
echo 'net.ipv4.ip_forward = 1' | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

### Step 8: Start Services

#### Start WireGuard
```bash
# Enable and start WireGuard
sudo systemctl enable wg-quick@wg0
sudo systemctl start wg-quick@wg0

# Check status
sudo systemctl status wg-quick@wg0
sudo wg show
```

#### Start Web Server
```bash
# Apache
sudo systemctl enable apache2
sudo systemctl start apache2

# Nginx
sudo systemctl enable nginx
sudo systemctl start nginx

# PHP-FPM (if using Nginx)
sudo systemctl enable php7.4-fpm
sudo systemctl start php7.4-fpm
```

## 🌐 Complete Installation

1. **Open your browser** and navigate to your domain/IP
2. **Follow the installation wizard** which will:
   - Verify all requirements are met
   - Create the database and tables
   - Set up your admin account
   - Configure WireGuard settings
   - Apply security settings

## 🔍 Verification

### Test Web Interface
```bash
# Check if web server is responding
curl -I http://your-domain.com

# Check PHP is working
echo "<?php phpinfo(); ?>" | sudo tee /var/www/html/test.php
```

### Test WireGuard
```bash
# Check WireGuard status
sudo wg show
sudo systemctl status wg-quick@wg0

# Test sudo access
sudo -u www-data sudo wg show
```

### Test Database
```bash
# Check if data directory is writable
sudo -u www-data touch /var/www/html/wireguard-admin/data/test.txt
```

## 🐛 Troubleshooting

### Common Issues and Solutions

#### "Permission denied" when accessing WireGuard
```bash
# Check sudo configuration
sudo visudo -c
sudo -u www-data sudo wg --version
```

#### "Database connection failed"
```bash
# Check data directory permissions
ls -la /var/www/html/wireguard-admin/data/
sudo chown www-data:www-data /var/www/html/wireguard-admin/data/
sudo chmod 775 /var/www/html/wireguard-admin/data/
```

#### "Requirements not met" in installer
```bash
# Check PHP extensions
php -m | grep -E "(pdo|sqlite)"

# Install missing extensions
sudo apt install php-sqlite3 php-pdo
```

#### WireGuard interface won't start
```bash
# Check configuration
sudo wg-quick up wg0
journalctl -u wg-quick@wg0

# Check IP forwarding
cat /proc/sys/net/ipv4/ip_forward
```

## 🚀 Post-Installation

### Security Hardening
1. **Change default SSH port**
2. **Set up fail2ban**
3. **Configure automatic updates**
4. **Set up monitoring**
5. **Regular backups**

### Backup Strategy
```bash
# Create backup script
sudo tee /usr/local/bin/wireguard-backup.sh > /dev/null <<'EOF'
#!/bin/bash
BACKUP_DIR="/var/backups/wireguard-admin"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup database
cp /var/www/html/wireguard-admin/data/wg-admin.db $BACKUP_DIR/wg-admin_$DATE.db

# Backup WireGuard configs
cp -r /etc/wireguard $BACKUP_DIR/wireguard_$DATE

# Cleanup old backups (keep 30 days)
find $BACKUP_DIR -type f -mtime +30 -delete
EOF

sudo chmod +x /usr/local/bin/wireguard-backup.sh

# Schedule daily backups
echo "0 2 * * * /usr/local/bin/wireguard-backup.sh" | sudo crontab -
```

## 📞 Support

If you encounter any issues during installation:

1. **Check the logs**:
   - Web server logs
   - PHP error logs
   - System journal (`journalctl`)

2. **Verify requirements** are fully met

3. **Check permissions** for all directories and files

4. **Test connectivity** and firewall rules

5. **Review configuration** files for syntax errors

---

**🎉 Congratulations!** You now have a professional WireGuard Admin Panel ready to manage your VPN infrastructure!


Change ownership and permissions:

```bash
sudo chown -R www-data:www-data /var/www/html/wg_admin/ && sudo chmod -R 755 /var/www/html/wg_admin/
```


```bash
sudo nano /etc/apache2/sites-available/wgadmin.mikrol.ink.conf
```

8. Add the following config (replace `example.com` with your domain):

```apache
<VirtualHost *:80>
    ServerName wgadmin.mikrol.ink
    ServerAdmin mail@wgadmin.mikrol.ink
    DocumentRoot /var/www/html/wg_admin

    <Directory /var/www/html/wg_admin/>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined

    <IfModule mod_dir.c>
        DirectoryIndex index.html index.php
    </IfModule>
</VirtualHost>
```


9. Enable site and required modules:

```bash
sudo a2ensite wgadmin.mikrol.ink.conf
systemctl reload apache2
sudo a2enmod rewrite
```

10. Restart Apache:

```bash
sudo systemctl restart apache2
```

Give permissions to data directory TO /etc/wireguard/

```bash
sudo chmod 775 /etc/wireguard/ && sudo chown www-data:www-data /etc/wireguard/
```