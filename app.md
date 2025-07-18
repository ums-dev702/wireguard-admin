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