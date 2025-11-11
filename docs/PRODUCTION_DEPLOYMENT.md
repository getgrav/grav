# Production Deployment Guide - OCEDC Website

Complete guide for deploying the OCEDC website to a production server.

## Pre-Deployment Checklist

### 1. Domain & Hosting

- [ ] Domain name registered (e.g., ocedc.org, oglecoedg.org)
- [ ] DNS configured to point to hosting server
- [ ] Web hosting account set up with:
  - PHP 7.3.6+ (PHP 8.1+ recommended)
  - MySQL/MariaDB (if using database plugins)
  - SSH access (recommended)
  - Sufficient disk space (minimum 500MB, 2GB+ recommended)
  - SSL certificate support

### 2. Server Requirements

**Minimum Requirements**:
- PHP 7.3.6 or higher
- Apache 2.4+ or Nginx 1.10+
- PHP Extensions:
  - json
  - openssl
  - curl
  - zip
  - dom
  - libxml
  - gd or imagick
  - mbstring (recommended)
  - iconv (recommended)

**Recommended Server Specs**:
- PHP 8.1 or 8.2
- 1GB RAM minimum (2GB+ recommended)
- SSD storage
- PHP OPcache enabled
- mod_rewrite enabled (Apache)

### 3. Email Configuration

- [ ] SMTP service set up (Gmail, SendGrid, Mailgun, etc.)
- [ ] SMTP credentials obtained
- [ ] From/To email addresses configured
- [ ] Test email sending capability

### 4. SSL Certificate

- [ ] SSL certificate obtained (Let's Encrypt free or commercial)
- [ ] Certificate installed on server
- [ ] HTTPS redirect configured
- [ ] Mixed content issues resolved

## Deployment Methods

### Method 1: Git Deployment (Recommended)

#### Initial Setup

1. **On Server**: Clone the repository
```bash
cd /var/www/html  # or your web root
git clone https://github.com/yourusername/ocedc-website.git
cd ocedc-website
```

2. **Install Dependencies**
```bash
composer install --no-dev --optimize-autoloader
bin/grav install
```

3. **Install Plugins**
```bash
bin/gpm install admin login form email flex-objects -y
bin/gpm install simplesearch sitemap breadcrumbs feed -y
bin/gpm install recaptcha seo -y
```

4. **Set Permissions**
```bash
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
find bin -type f -exec chmod 755 {} \;
chmod -R 775 cache/ logs/ backup/ tmp/ user/data/ user/accounts/
```

5. **Create Admin User**
```bash
bin/plugin login newuser
# Follow prompts to create admin account
```

#### Updates

```bash
cd /var/www/html/ocedc-website
git pull origin main
composer install --no-dev --optimize-autoloader
bin/grav cache-clear
```

### Method 2: FTP/SFTP Deployment

1. **Prepare Local Build**
```bash
# On local machine
composer install --no-dev --optimize-autoloader
bin/grav install
```

2. **Upload Files via FTP/SFTP**
- Upload all files to server web root
- **Exclude**:
  - `.git/` directory
  - `cache/` directory (will be regenerated)
  - `logs/` directory (will be regenerated)

3. **Set Permissions** (via SSH or hosting control panel)
```bash
chmod -R 755 user/
chmod -R 775 cache/ logs/ backup/ tmp/
```

4. **Install Plugins via SSH or Admin Panel**
```bash
bin/gpm install admin login form email flex-objects -y
# Or use /admin panel to install plugins
```

### Method 3: cPanel Deployment

1. **Upload Files**
- Use cPanel File Manager or FTP
- Upload to `public_html/` or subdirectory
- Extract archive if using zip

2. **Set up Database** (if needed)
- Create MySQL database in cPanel
- Note credentials for plugin configuration

3. **Configure PHP** (if needed)
- PHP version should be 7.3.6+
- Enable required extensions via PHP Selector

4. **Create Cron Jobs** (optional, for cache warming)
```
0 */6 * * * cd /home/username/public_html && /usr/bin/php bin/grav cache-clear
```

## Server Configuration

### Apache (.htaccess)

The included `.htaccess` file should work out of the box. If you need to customize:

```apache
# Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Grav rewrite rules
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [L]
</IfModule>

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

### Nginx Configuration

Create configuration file: `/etc/nginx/sites-available/ocedc`

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name ocedc.org www.ocedc.org;

    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ocedc.org www.ocedc.org;

    root /var/www/html/ocedc-website;
    index index.php index.html;

    # SSL Configuration
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;

    # Security headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Grav specific
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /(vendor|tests|tmp|logs) {
        deny all;
    }

    # Cache static files
    location ~* \.(jpg|jpeg|gif|png|css|js|ico|xml|svg|woff|woff2|ttf|eot)$ {
        expires 365d;
        add_header Cache-Control "public, no-transform";
    }
}
```

Enable site:
```bash
ln -s /etc/nginx/sites-available/ocedc /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

## Production Configuration

### 1. Update Site Configuration

**File**: `user/config/site.yaml`

```yaml
title: 'Ogle County Economic Development Corporation'
default_lang: en
author:
  name: 'Ogle County EDC'
  email: 'info@oglecoedg.org'

metadata:
  description: 'Ogle County Economic Development Corporation - Promoting business growth and economic development in Ogle County, Illinois.'

# Set production URL
url: 'https://ocedc.org'
```

### 2. Update System Configuration

**File**: `user/config/system.yaml`

```yaml
cache:
  enabled: true
  check:
    method: file
  driver: auto
  prefix: 'g'
  purge_at: '0 3 * * *'
  clear_at: '0 4 * * *'

twig:
  cache: true
  debug: false
  auto_reload: false
  autoescape: true

errors:
  display: 0
  log: true

debugger:
  enabled: false

pages:
  theme: ddcms
```

### 3. Update Email Configuration

**File**: `user/config/plugins/email.yaml`

Update with your production SMTP settings:

```yaml
enabled: true
from: 'noreply@ocedc.org'
from_name: 'OCEDC Website'
to: 'info@oglecoedg.org'
to_name: 'OCEDC Admin'

mailer:
  engine: smtp
  smtp:
    server: 'smtp.yourmailserver.com'
    port: 587
    encryption: tls
    user: 'your-email@ocedc.org'
    password: 'your-password'

debug: false
```

### 4. Security Configuration

The file `user/config/security.yaml` will be auto-generated. Keep it secret!

**Never commit** `security.yaml` to version control.

## Post-Deployment Tasks

### 1. Create Admin Account
```bash
bin/plugin login newuser
# Username: admin (or your preferred username)
# Email: youremail@ocedc.org
# Password: [strong password]
# Fullname: Admin User
# Title: Administrator
# Permissions: Site administrator: true
```

### 2. Configure Admin Panel
- Access `/admin`
- Update site settings
- Configure theme settings
- Set up user roles

### 3. Install SSL Certificate

**Using Let's Encrypt** (free):
```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-apache  # For Apache
# or
sudo apt-get install certbot python3-certbot-nginx  # For Nginx

# Get certificate
sudo certbot --apache  # For Apache
# or
sudo certbot --nginx  # For Nginx

# Auto-renewal (should be set up automatically)
sudo certbot renew --dry-run
```

### 4. Configure DNS

Point domain to server IP:
```
A Record:    @          -> YOUR_SERVER_IP
A Record:    www        -> YOUR_SERVER_IP
CNAME:       www        -> ocedc.org (alternative to A record)
```

TTL: 3600 seconds (1 hour) initially, increase after verification

### 5. Set Up Email SMTP

Choose an SMTP provider:

**Gmail** (for testing only):
- Use App Password, not regular password
- Enable 2FA on Google account
- Generate App Password
- server: smtp.gmail.com, port: 587, encryption: tls

**SendGrid** (recommended for production):
- Sign up: https://sendgrid.com/
- Create API key
- server: smtp.sendgrid.net, port: 587
- user: apikey, password: [YOUR_API_KEY]

**Mailgun**:
- Sign up: https://mailgun.com/
- Verify domain
- Get SMTP credentials
- server: smtp.mailgun.org, port: 587

### 6. Test Everything

- [ ] Homepage loads correctly
- [ ] All pages load without errors
- [ ] Navigation works
- [ ] Forms submit successfully
- [ ] Emails send correctly
- [ ] Admin panel accessible
- [ ] SSL certificate valid (https://)
- [ ] Mobile responsive
- [ ] Search functionality works
- [ ] Images load correctly

### 7. Performance Optimization

**Enable OPcache** (php.ini):
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

**Enable Grav Cache**:
```bash
# In user/config/system.yaml
cache:
  enabled: true
```

**Set up CDN** (optional):
- Cloudflare (free tier available)
- CloudFront
- KeyCDN

### 8. Security Hardening

**File Permissions**:
```bash
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 cache/ logs/ backup/ tmp/ user/data/
```

**Hide sensitive files**:
Add to `.htaccess` or nginx config to deny access to:
- `/vendor/*`
- `/tests/*`
- `/tmp/*`
- `/logs/*`
- `*.md` files in user directories
- `*.yaml` files in user/config

**Two-Factor Authentication**:
```bash
bin/gpm install login-oauth -y
# Or use Google Authenticator plugin
```

## Monitoring & Maintenance

### Set Up Monitoring

**Uptime Monitoring**:
- UptimeRobot (free)
- Pingdom
- StatusCake

**Error Tracking**:
- Check logs regularly: `logs/grav.log`
- Set up error notifications

**Analytics**:
- Google Analytics
- Google Search Console
- Bing Webmaster Tools

### Backup Strategy

**Automated Backups**:
```bash
# Install backup plugin
bin/gpm install backup -y

# Create backup script
#!/bin/bash
cd /var/www/html/ocedc-website
bin/plugin backup backup

# Copy to remote server
rsync -avz backup/ user@backup-server:/backups/ocedc/

# Add to crontab
0 2 * * * /path/to/backup-script.sh
```

**Manual Backup**:
```bash
# Backup entire site
tar -czf ocedc-backup-$(date +%Y%m%d).tar.gz .

# Backup user directory only
tar -czf ocedc-user-backup-$(date +%Y%m%d).tar.gz user/
```

**What to Backup**:
- `user/` directory (content, config, themes)
- `logs/` directory (optional, for troubleshooting)
- Database (if using database plugins)

### Update Schedule

**Weekly**:
- Check error logs
- Review analytics
- Moderate form submissions

**Monthly**:
- Update plugins: `bin/gpm update`
- Update Grav core: `bin/gpm selfupgrade`
- Review security updates
- Test backups

**Quarterly**:
- Content audit
- Performance review
- SEO review
- Security audit

## Troubleshooting

### Site not loading

1. Check server error logs: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
2. Check Grav logs: `logs/grav.log`
3. Verify file permissions
4. Clear cache: `bin/grav cache-clear`

### White screen / 500 error

1. Enable error display temporarily:
   ```yaml
   # user/config/system.yaml
   errors:
     display: 1
   ```
2. Check PHP error logs
3. Verify PHP version compatibility
4. Check for plugin conflicts

### Forms not sending emails

1. Test SMTP settings
2. Check email logs: `logs/email.log`
3. Verify firewall not blocking port 587/465
4. Test with different SMTP provider

### Admin panel not accessible

1. Verify admin plugin installed and enabled
2. Clear cache
3. Check file permissions on `user/` directory
4. Recreate admin user if necessary

## Rollback Procedure

If deployment fails:

1. **Restore from backup**:
```bash
tar -xzf ocedc-backup-YYYYMMDD.tar.gz
```

2. **Or rollback Git**:
```bash
git log  # Find commit hash
git reset --hard [commit-hash]
composer install
bin/grav cache-clear
```

3. **Clear cache and restart**:
```bash
bin/grav cache-clear
sudo systemctl restart php8.1-fpm  # or apache2
```

## Support Resources

- **Grav Documentation**: https://learn.getgrav.org/
- **Grav Discord**: https://chat.getgrav.org/
- **Dixon Digital Support**: info@dixondigital.com
- **Server/Hosting Support**: Contact your hosting provider

## Deployment Checklist

Final checklist before going live:

- [ ] All content reviewed and updated
- [ ] All images optimized and uploaded
- [ ] Contact information updated (real phone, email, address)
- [ ] Social media links updated with real URLs
- [ ] Forms tested and working
- [ ] Email sending tested
- [ ] SSL certificate installed and working
- [ ] DNS configured correctly
- [ ] Sitemap submitted to search engines
- [ ] Google Analytics installed
- [ ] Admin account created with strong password
- [ ] All plugins updated to latest version
- [ ] Error display disabled in production
- [ ] Cache enabled
- [ ] Backups configured
- [ ] Monitoring set up
- [ ] 404 page customized
- [ ] Performance tested
- [ ] Mobile responsiveness verified
- [ ] Cross-browser testing completed
- [ ] Accessibility checked
- [ ] Client training completed
- [ ] Documentation provided to client

## Go Live!

Once all checks pass:

1. Announce on social media
2. Submit to search engines
3. Monitor closely for first 48 hours
4. Address any issues immediately
5. Celebrate! 🎉
