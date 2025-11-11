# ddCMS Admin Panel White-Label Customization

This directory contains all the custom branding assets and styles for transforming the Grav admin panel into the ddCMS branded experience.

## Contents

```
ddcms-admin/
├── css/
│   └── ddcms-admin.css          # Custom admin panel styles
├── images/
│   ├── ddcms-admin-logo.svg     # Logo for admin header (white)
│   └── ddcms-login-logo.svg     # Logo for login page (full color)
└── README.md                     # This file
```

## Features

### Visual Branding
- ✅ Custom Dixon Digital blue color scheme
- ✅ ddCMS logo in admin header
- ✅ Branded login page with custom logo
- ✅ Custom button colors and styles
- ✅ Professional card and panel styling
- ✅ Branded footer with Dixon Digital attribution

### Functionality
- ✅ Hides Grav update notifications
- ✅ Disables anonymous data collection
- ✅ Custom support links to Dixon Digital
- ✅ Professional welcome messages
- ✅ Clean, client-ready interface

### Language Customization
- ✅ All "Grav" references replaced with "ddCMS"
- ✅ Custom dashboard messages
- ✅ Dixon Digital attribution in footer
- ✅ Professional terminology throughout

## Installation

The white-label customizations are **automatically applied** when the admin plugin is installed, thanks to the configuration in:

- `user/config/plugins/admin.yaml` - Admin configuration
- `user/languages/en.yaml` - Language overrides

### Verify Installation

1. Install the admin plugin:
   ```bash
   bin/gpm install admin -y
   ```

2. Access the admin panel:
   ```
   http://yoursite.com/admin
   ```

3. You should see:
   - ddCMS logo in the header
   - Dixon Digital blue color scheme
   - Custom login page with ddCMS branding
   - "Powered by Dixon Digital" in footer

### Manual Installation (if needed)

If the custom CSS doesn't load automatically:

1. **Copy custom CSS to admin theme**:
   ```bash
   mkdir -p user/plugins/admin/themes/grav/css
   cp user/data/ddcms-admin/css/ddcms-admin.css user/plugins/admin/themes/grav/css/
   ```

2. **Copy logo images to admin theme**:
   ```bash
   mkdir -p user/plugins/admin/themes/grav/images
   cp user/data/ddcms-admin/images/*.svg user/plugins/admin/themes/grav/images/
   ```

3. **Update admin config** to reference the files:
   ```yaml
   # user/config/plugins/admin.yaml
   add_css:
     - plugin://admin/themes/grav/css/ddcms-admin.css
   ```

4. **Clear cache**:
   ```bash
   bin/grav cache-clear
   ```

## Customization

### Changing Colors

Edit `css/ddcms-admin.css` and modify the CSS variables at the top:

```css
:root {
    --ddcms-primary: #2563eb;      /* Primary blue */
    --ddcms-primary-hover: #1d4ed8; /* Hover state */
    --ddcms-accent: #f59e0b;       /* Accent color */
    /* ... etc */
}
```

### Changing Logos

Replace the SVG files in `images/` with your own:

- **ddcms-admin-logo.svg** - Header logo (160x40px recommended, white version)
- **ddcms-login-logo.svg** - Login page logo (240x100px recommended, full color)

**Tips**:
- Use SVG format for scalability
- Header logo should work on dark background
- Login logo can be full color
- Keep file names the same for automatic loading

### Changing Text/Branding

Edit language overrides in `user/languages/en.yaml`:

```yaml
PLUGIN_ADMIN:
    ADMIN: 'Your CMS Name'
    ADMIN_PANEL: 'Your CMS Admin Panel'
    FOOTER_POWERED_BY: 'Powered by Your Company'
```

## What's Customized

### Admin Header
- ✅ Dixon Digital blue gradient background
- ✅ ddCMS logo replacing Grav logo
- ✅ White text for better contrast
- ✅ Custom hover effects

### Login Page
- ✅ Purple gradient background
- ✅ White card design
- ✅ ddCMS logo at top
- ✅ Smooth animations
- ✅ Professional appearance

### Dashboard
- ✅ Welcome banner with ddCMS branding
- ✅ Custom statistics widgets
- ✅ Quick links to Dixon Digital support
- ✅ No Grav update notifications

### Sidebar
- ✅ Dark background (Dixon Digital navy)
- ✅ Hover effects
- ✅ Active state highlighting
- ✅ Icon colors matching brand

### Buttons & Forms
- ✅ Primary buttons in Dixon Digital blue
- ✅ Success, warning, danger states
- ✅ Focus states with brand colors
- ✅ Smooth transitions

### Footer
- ✅ "Powered by ddCMS — A Dixon Digital Product"
- ✅ Hides original Grav attribution
- ✅ Professional appearance

## Browser Compatibility

Tested and working in:
- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Mobile browsers (responsive)

## Troubleshooting

### CSS not loading

1. Check that admin plugin is installed:
   ```bash
   bin/gpm list
   ```

2. Verify custom CSS file exists:
   ```bash
   ls -la user/data/ddcms-admin/css/ddcms-admin.css
   ```

3. Check admin configuration:
   ```bash
   cat user/config/plugins/admin.yaml
   ```

4. Clear cache:
   ```bash
   bin/grav cache-clear
   ```

5. Hard refresh browser (Ctrl+Shift+R or Cmd+Shift+R)

### Logos not displaying

1. Verify logo files exist:
   ```bash
   ls -la user/data/ddcms-admin/images/
   ```

2. Check file permissions:
   ```bash
   chmod 644 user/data/ddcms-admin/images/*.svg
   ```

3. Verify CSS is loading the correct paths:
   - Check developer console for 404 errors
   - Adjust paths in CSS if needed

### Language overrides not working

1. Check language file:
   ```bash
   tail -50 user/languages/en.yaml
   ```

2. Verify YAML syntax (no tabs, proper indentation)

3. Clear cache and reload

### Admin panel looks default

If you still see Grav branding:

1. Ensure admin plugin version is compatible (v1.10+)
2. Check for plugin conflicts
3. Try disabling other admin-related plugins
4. Review browser console for JavaScript errors

## Updating

### When Updating Grav or Admin Plugin

1. **Backup** custom files first:
   ```bash
   cp -r user/data/ddcms-admin user/data/ddcms-admin.backup
   ```

2. **Update** Grav/plugins:
   ```bash
   bin/gpm selfupgrade
   bin/gpm update
   ```

3. **Re-verify** customizations are still applied

4. **Re-apply** if needed (see Manual Installation above)

### Keeping Customizations in Version Control

These customization files should be committed to your repository:

```bash
git add user/data/ddcms-admin/
git add user/config/plugins/admin.yaml
git add user/languages/en.yaml
git commit -m "Add ddCMS admin panel customizations"
```

## Support

For issues or questions about ddCMS admin customizations:

- **Email**: support@dixondigital.com
- **Documentation**: https://dixondigital.com/ddcms/docs
- **Issue Tracker**: GitHub repository

## Credits

- **CMS Platform**: Grav CMS (https://getgrav.org)
- **White-Label Branding**: Dixon Digital
- **Design**: ddCMS Custom Theme
- **Version**: 1.0.0

## License

These customization files are proprietary to Dixon Digital.

- ✅ Use in client projects delivered by Dixon Digital
- ✅ Modify for specific client needs
- ❌ Do not redistribute separately
- ❌ Do not use outside Dixon Digital projects

---

**ddCMS** - A Professional CMS by Dixon Digital
