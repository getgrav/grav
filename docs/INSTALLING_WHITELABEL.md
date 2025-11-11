# Installing ddCMS White-Label Customizations

Quick guide for applying the ddCMS admin panel branding after deploying to a server.

## Prerequisites

- Grav installed and running
- SSH or command-line access to server
- Basic familiarity with terminal commands

## Installation Steps

### 1. Install Required Plugins

First, install the admin plugin and dependencies:

```bash
cd /path/to/your/grav/installation
bin/gpm install admin login form email flex-objects -y
```

**What this does**:
- Installs the Grav admin panel
- Installs authentication (login)
- Installs form handling
- Installs email functionality
- Installs flexible content management

### 2. Verify Custom Files Exist

Check that the white-label files are in place:

```bash
# Custom admin CSS
ls -la user/data/ddcms-admin/css/ddcms-admin.css

# Logo files
ls -la user/data/ddcms-admin/images/

# Admin configuration
cat user/config/plugins/admin.yaml

# Language overrides
tail -50 user/languages/en.yaml
```

All of these files should already be in your repository and deployed.

### 3. Create Admin User

Create your first admin user:

```bash
bin/plugin login newuser
```

Follow the prompts:
- **Username**: Choose your username (e.g., `admin`)
- **Password**: Strong password (min 8 characters)
- **Email**: Your email address
- **Full Name**: Your name
- **Title**: Administrator
- **Permissions**: Site administrator: **Yes**

### 4. Clear Cache

Clear the Grav cache to ensure customizations load:

```bash
bin/grav cache-clear
```

### 5. Access Admin Panel

Visit your admin panel in a web browser:

```
https://yoursite.com/admin
```

or

```
http://yoursite.com/admin
```

### 6. Verify Branding

You should now see:

✅ **Login Page**:
- ddCMS logo at top
- Purple gradient background
- White login card
- Professional styling

✅ **Admin Header**:
- Dixon Digital blue gradient
- ddCMS logo (white) in top left
- Clean navigation

✅ **Admin Interface**:
- Dixon Digital blue color scheme
- Custom button styles
- Dark sidebar
- Professional appearance

✅ **Admin Footer**:
- "Powered by ddCMS — A Dixon Digital Product"

## Troubleshooting

### Issue: CSS not loading (still looks like default Grav)

**Solution 1**: Hard refresh your browser
- Windows/Linux: `Ctrl + Shift + R`
- Mac: `Cmd + Shift + R`

**Solution 2**: Clear Grav cache again
```bash
bin/grav cache-clear
```

**Solution 3**: Verify CSS file path in admin config
```bash
grep -A 5 "add_css" user/config/plugins/admin.yaml
```

Should show:
```yaml
add_css:
  - 'user://data/ddcms-admin/css/ddcms-admin.css'
```

**Solution 4**: Check file permissions
```bash
chmod -R 755 user/data/ddcms-admin/
```

### Issue: Logos not displaying

**Check logo files exist**:
```bash
ls -la user/data/ddcms-admin/images/
```

Should show:
- `ddcms-admin-logo.svg`
- `ddcms-login-logo.svg`

**Check file permissions**:
```bash
chmod 644 user/data/ddcms-admin/images/*.svg
```

**Check browser console** (F12):
- Look for 404 errors for logo files
- Check Network tab for failed requests

### Issue: Still see "Grav" references

**Clear browser cache**:
- Hard refresh (Ctrl+Shift+R)
- Or clear all browser cache

**Verify language file**:
```bash
tail -100 user/languages/en.yaml | grep "PLUGIN_ADMIN"
```

Should see ddCMS language overrides.

**Clear Grav cache**:
```bash
bin/grav cache-clear
```

### Issue: Admin panel won't load at all

**Check admin plugin is installed**:
```bash
bin/gpm list | grep admin
```

Should show admin plugin installed.

**Check error logs**:
```bash
tail -50 logs/grav.log
```

**Check PHP error logs**:
```bash
tail -50 /var/log/apache2/error.log  # Apache
# or
tail -50 /var/log/nginx/error.log    # Nginx
```

**Reinstall admin plugin**:
```bash
bin/gpm uninstall admin
bin/gpm install admin -y
bin/grav cache-clear
```

## Manual CSS Installation (Alternative Method)

If the automatic CSS loading doesn't work, manually install to admin theme:

```bash
# Create directories in admin plugin
mkdir -p user/plugins/admin/themes/grav/css
mkdir -p user/plugins/admin/themes/grav/images

# Copy CSS
cp user/data/ddcms-admin/css/ddcms-admin.css user/plugins/admin/themes/grav/css/

# Copy images
cp user/data/ddcms-admin/images/*.svg user/plugins/admin/themes/grav/images/

# Update admin config to use plugin path
# Edit user/config/plugins/admin.yaml
# Change add_css to:
#   add_css:
#     - plugin://admin/themes/grav/css/ddcms-admin.css

# Clear cache
bin/grav cache-clear
```

## Testing Checklist

After installation, verify these features:

### Visual Testing
- [ ] Login page shows ddCMS logo
- [ ] Login page has purple gradient background
- [ ] Admin header has blue gradient background
- [ ] Admin header shows ddCMS logo (white)
- [ ] Sidebar has dark background
- [ ] Buttons are Dixon Digital blue
- [ ] Footer shows "Powered by ddCMS — A Dixon Digital Product"

### Functional Testing
- [ ] Can login successfully
- [ ] Dashboard loads correctly
- [ ] Can navigate to Pages section
- [ ] Can edit a page
- [ ] Can save a page
- [ ] Can upload media
- [ ] Configuration pages work
- [ ] Plugins page works
- [ ] Themes page works

### Text/Language Testing
- [ ] Dashboard says "Welcome to ddCMS"
- [ ] Admin panel title is "ddCMS Admin"
- [ ] No visible "Grav" references
- [ ] Footer shows Dixon Digital attribution

### Browser Testing
- [ ] Works in Chrome/Edge
- [ ] Works in Firefox
- [ ] Works in Safari
- [ ] Responsive on mobile

## Post-Installation

### Recommended Next Steps

1. **Configure Site Settings**:
   - Go to Configuration > Site
   - Update site title, author, etc.

2. **Set Up Email**:
   - Go to Configuration > Plugins > Email
   - Configure SMTP settings

3. **Install Additional Plugins** (optional):
   - SimpleSimplesearch (site search)
   - Sitemap (XML sitemap)
   - Breadcrumbs (navigation)
   - See `docs/ESSENTIAL_PLUGINS.md`

4. **Customize Theme Settings**:
   - Go to Configuration > Themes > ddcms
   - Update logo, colors, footer, etc.

5. **Create Content**:
   - Add pages
   - Upload images
   - Configure navigation

## Security Recommendations

1. **Strong Password**: Use a strong, unique password for admin account

2. **Two-Factor Authentication** (optional):
   ```bash
   bin/gpm install login-oauth -y
   ```

3. **Limit Login Attempts** (optional):
   ```bash
   bin/gpm install login-ratelimit -y
   ```

4. **HTTPS Only**: Ensure SSL certificate is installed and enforce HTTPS

5. **Hide Admin URL** (optional): Change `/admin` to custom path:
   ```yaml
   # user/config/plugins/admin.yaml
   route: '/my-custom-admin-path'
   ```

6. **File Permissions**: Ensure proper permissions:
   ```bash
   find user/ -type f -exec chmod 644 {} \;
   find user/ -type d -exec chmod 755 {} \;
   chmod -R 775 cache/ logs/ backup/ tmp/
   ```

## Support

If you encounter issues:

1. **Check Documentation**: See `docs/PHASE2_WHITELABELING.md`
2. **Check Logs**: `logs/grav.log`
3. **Grav Discord**: https://chat.getgrav.org/
4. **Dixon Digital Support**: support@dixondigital.com

## Quick Reference

### Important Commands

```bash
# Clear cache
bin/grav cache-clear

# List plugins
bin/gpm list

# Update plugins
bin/gpm update

# Create backup
bin/plugin backup backup

# Check Grav version
bin/grav --version
```

### Important Files

- **Admin Config**: `user/config/plugins/admin.yaml`
- **Custom CSS**: `user/data/ddcms-admin/css/ddcms-admin.css`
- **Language**: `user/languages/en.yaml`
- **Error Logs**: `logs/grav.log`

### Important URLs

- **Admin Panel**: `https://yoursite.com/admin`
- **Frontend**: `https://yoursite.com/`
- **API**: `https://yoursite.com/api` (if using API plugin)

---

**Installation Complete!** Your Grav installation is now branded as ddCMS. 🎉
