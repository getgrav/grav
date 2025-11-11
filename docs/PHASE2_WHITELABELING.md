# Phase 2: White-Labeling & Branding Guide

**Objective**: Transform Grav CMS into ddCMS (Dixon Digital CMS) with complete white-labeling and custom branding.

## Overview

Phase 2 focuses on making the admin panel and overall CMS experience reflect the Dixon Digital brand, creating a professional white-labeled product that can be delivered to clients.

---

## White-Labeling Strategy

### What is White-Labeling?

White-labeling means removing all "Grav" branding and replacing it with "ddCMS" (Dixon Digital CMS) branding throughout the admin interface, while maintaining full functionality.

### Goals

1. **Admin Panel Branding** - ddCMS logo, colors, and identity
2. **Login Page Customization** - Branded login experience
3. **Language/Text Updates** - Replace "Grav" references with "ddCMS"
4. **Dashboard Customization** - Welcome message and widgets
5. **Footer Branding** - Dixon Digital credit in admin
6. **Documentation** - Professional screenshots and materials

---

## Implementation Plan

### 1. Admin Panel Visual Branding

#### Custom Admin CSS
Create custom stylesheet to override admin theme colors and styling.

**File**: `user/plugins/admin/themes/grav/css/custom-ddcms.css`

**Elements to Customize**:
- Header background color
- Primary accent colors
- Logo replacement
- Button colors
- Link colors
- Sidebar styling
- Card/panel styling

**Color Scheme** (Dixon Digital):
- Primary: #2563eb (Blue)
- Secondary: #64748b (Slate)
- Accent: #f59e0b (Amber)
- Success: #10b981 (Green)
- Danger: #ef4444 (Red)

#### Logo Replacement
Replace Grav logo with ddCMS logo in admin header.

**Locations**:
- Admin header logo
- Login page logo
- Favicon (admin)
- Loading screen

---

### 2. Login Page Customization

**File**: Custom login page template or CSS overrides

**Elements**:
- ddCMS logo at top
- Custom background (optional)
- Branded colors
- Custom welcome text
- Dixon Digital footer credit

**Design Concept**:
```
┌──────────────────────────────────┐
│                                  │
│         [ddCMS Logo]             │
│                                  │
│  ┌────────────────────────┐     │
│  │  Username              │     │
│  ├────────────────────────┤     │
│  │  Password              │     │
│  ├────────────────────────┤     │
│  │  [Login Button]        │     │
│  └────────────────────────┘     │
│                                  │
│  Powered by Dixon Digital        │
└──────────────────────────────────┘
```

---

### 3. Admin Language Customization

Replace "Grav" references throughout the admin interface.

**File**: `user/languages/en.yaml` (admin overrides)

**Common Replacements**:
- "Grav" → "ddCMS"
- "Grav CMS" → "ddCMS by Dixon Digital"
- "Grav Admin" → "ddCMS Admin"
- "Grav Updates" → "System Updates"
- Links to getgrav.org → Links to Dixon Digital

**Example Strings**:
```yaml
PLUGIN_ADMIN:
  ADMIN_BETA_MSG: "This is a ddCMS admin panel"
  LOGGED_IN: "Logged into ddCMS"
  ADMIN_REPORT_ISSUE: "Report an issue to Dixon Digital"
```

---

### 4. Dashboard Customization

Create custom welcome dashboard for ddCMS.

**Elements**:
- Custom welcome message
- Dixon Digital branding
- Quick links (customized for ddCMS)
- Statistics widgets
- Latest updates section
- Support links (to Dixon Digital)

**Welcome Message**:
```
Welcome to ddCMS
A professional, modern content management system by Dixon Digital.
```

---

### 5. Admin Footer Branding

Customize the admin footer to show Dixon Digital attribution.

**Current**:
```
Grav was made with ❤ by RocketTheme
```

**New**:
```
ddCMS powered by Dixon Digital | Built on Grav
```

Or simply:
```
© 2024 Dixon Digital. All rights reserved.
```

---

### 6. Custom Admin Favicon

Replace admin panel favicon with ddCMS icon.

**File**: Custom favicon for `/admin` route

---

### 7. Plugin/Theme Pages Customization

Customize the plugins and themes management pages:
- Hide or rebrand the GPM (Grav Package Manager)
- Custom "Available Plugins" page (optional)
- Pre-configured plugin recommendations

---

## Implementation Files

### Directory Structure

```
user/
├── plugins/
│   └── admin/
│       └── themes/
│           └── grav/
│               ├── css/
│               │   └── custom-ddcms.css      # Custom admin CSS
│               └── images/
│                   └── ddcms-logo.svg        # Admin logo
│
├── languages/
│   └── en.yaml                               # Language overrides
│
├── config/
│   └── plugins/
│       └── admin.yaml                        # Admin configuration
│
└── themes/
    └── ddcms/
        └── templates/
            └── admin/                        # Custom admin templates (optional)
                ├── dashboard.html.twig
                └── login.html.twig
```

---

## Step-by-Step Implementation

### Step 1: Create Custom Admin CSS

1. Create directory structure
2. Create `custom-ddcms.css` file
3. Override admin colors and branding
4. Load custom CSS in admin panel

### Step 2: Replace Admin Logo

1. Create ddCMS logo SVG (optimized for admin header)
2. Place in admin theme images directory
3. Override logo via CSS or configuration

### Step 3: Customize Login Page

1. Create custom login template (optional)
2. Or use CSS overrides for login styling
3. Add ddCMS logo to login page
4. Customize login form colors

### Step 4: Language Overrides

1. Identify all "Grav" references in admin
2. Create language override file
3. Replace references with "ddCMS"
4. Test all admin pages

### Step 5: Dashboard Customization

1. Create custom dashboard template
2. Add ddCMS welcome message
3. Customize quick links
4. Add Dixon Digital branding

### Step 6: Footer Customization

1. Override admin footer template or CSS
2. Add Dixon Digital attribution
3. Update copyright notice

### Step 7: Documentation & Screenshots

1. Take screenshots of customized admin
2. Create before/after comparison
3. Document white-labeling process
4. Create client handoff materials

---

## Configuration Examples

### Admin Plugin Configuration

**File**: `user/config/plugins/admin.yaml`

```yaml
enabled: true

# Custom admin branding
logo_text: 'ddCMS'
body_classes: 'ddcms-admin'

# Custom CSS
add_css:
  - plugin://admin/themes/grav/css/custom-ddcms.css

# Dashboard customization
dashboard:
  show_grav_updates: false
  show_links: false

# Hide certain admin pages
pages:
  hide_gpm: true

# Custom links
sidebar:
  links:
    - text: 'Dixon Digital Support'
      url: 'https://dixondigital.com/support'
      icon: 'fa-life-ring'
```

### Language Overrides

**File**: `user/languages/en.yaml`

```yaml
PLUGIN_ADMIN:
  # Branding
  ADMIN: 'ddCMS Admin'
  ADMIN_PANEL: 'ddCMS Admin Panel'
  ADMIN_BETA_MSG: 'Welcome to ddCMS - A professional CMS by Dixon Digital'

  # Dashboard
  DASHBOARD: 'Dashboard'
  MANAGE_PAGES: 'Manage Pages'
  CONFIGURATION: 'Configuration'

  # Footer
  FOOTER_POWERED_BY: 'Powered by Dixon Digital'
  FOOTER_VERSION: 'ddCMS Version'

  # Updates (hide Grav references)
  UPDATES_AVAILABLE: 'System Updates Available'
  CHECK_FOR_UPDATES: 'Check for Updates'
```

---

## Custom CSS Examples

### Admin Header Branding

```css
/* ddCMS Admin Custom Styles */

/* Header Background */
#admin-topbar {
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
}

/* Logo Replacement */
#admin-logo {
    background-image: url('../images/ddcms-logo.svg') !important;
    background-size: contain;
    width: 150px;
}

/* Hide Grav Logo Text */
#admin-logo .admin-logo-text {
    display: none;
}

/* Primary Buttons */
.button.primary,
.button-primary {
    background-color: #2563eb;
    border-color: #2563eb;
}

.button.primary:hover {
    background-color: #1d4ed8;
}

/* Links */
a {
    color: #2563eb;
}

a:hover {
    color: #1d4ed8;
}

/* Sidebar */
#admin-sidebar {
    background: #0f172a;
}

#admin-sidebar .admin-menu a {
    color: #e2e8f0;
}

#admin-sidebar .admin-menu a:hover {
    background: #1e293b;
    color: #ffffff;
}

/* Cards/Panels */
.card,
.panel {
    border-top: 3px solid #2563eb;
}

/* Footer Branding */
#admin-footer {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

#admin-footer .footer-branding::after {
    content: 'Powered by Dixon Digital';
    display: block;
    color: #64748b;
    font-size: 0.875rem;
}
```

### Login Page Customization

```css
/* Login Page Branding */

/* Login Container */
.login-form {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    padding: 3rem;
}

/* Login Logo */
.login-logo {
    background-image: url('../images/ddcms-logo.svg') !important;
    height: 80px;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    margin-bottom: 2rem;
}

/* Login Form Background */
body.login-page {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Login Button */
.login-form button[type="submit"] {
    background: #2563eb;
    border: none;
    border-radius: 6px;
    padding: 12px 24px;
    font-weight: 600;
    transition: all 0.3s;
}

.login-form button[type="submit"]:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

/* Login Footer */
.login-footer {
    text-align: center;
    margin-top: 2rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.875rem;
}

.login-footer a {
    color: white;
    text-decoration: underline;
}
```

---

## Testing Checklist

### Visual Testing
- [ ] Admin header displays ddCMS logo
- [ ] Colors match Dixon Digital brand
- [ ] Login page shows custom branding
- [ ] Footer displays Dixon Digital attribution
- [ ] All buttons and links styled correctly
- [ ] Sidebar navigation styled properly

### Functional Testing
- [ ] Login works correctly
- [ ] All admin pages load properly
- [ ] Plugins page functional
- [ ] Themes page functional
- [ ] Configuration pages work
- [ ] Pages manager works
- [ ] Media manager works
- [ ] User management works

### Text/Language Testing
- [ ] No "Grav" references in visible areas
- [ ] All replaced with "ddCMS" or appropriate text
- [ ] Dashboard welcome message correct
- [ ] Menu items properly labeled
- [ ] Error messages don't reference Grav
- [ ] Help text updated

### Browser Testing
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile browsers

---

## Client Presentation

### Admin Panel Screenshots

Create professional screenshots showing:

1. **Login Page** - Before and after
2. **Dashboard** - Branded welcome screen
3. **Pages Manager** - Working interface
4. **Configuration** - Settings panel
5. **Media Library** - File management
6. **Overall Admin** - Header, sidebar, footer

### Before/After Comparison

Side-by-side comparison showing:
- Grav Admin (before)
- ddCMS Admin (after)

Highlights:
- ✅ Custom logo
- ✅ Brand colors
- ✅ Custom login page
- ✅ Professional appearance
- ✅ Client-ready interface

---

## Future Enhancements (Optional)

### Advanced White-Labeling

1. **Custom Admin Dashboard Widgets**
   - Recent site activity
   - Quick stats
   - Custom announcements
   - Client-specific widgets

2. **Custom Plugin Manager**
   - Pre-approved plugin list
   - One-click install for common plugins
   - Hide certain plugins from clients

3. **Custom Help System**
   - Built-in documentation
   - Video tutorials
   - Support ticket integration

4. **Multi-Client Management**
   - Client-specific dashboards
   - Per-client branding options
   - Agency management interface

5. **White-Label Updates**
   - Custom update notifications
   - Controlled update process
   - Automatic backups before updates

---

## Maintenance & Updates

### When Updating Grav

1. **Backup** custom admin files
2. **Test** updates in staging environment
3. **Re-apply** customizations if overwritten
4. **Verify** all branding intact
5. **Test** all admin functionality

### Version Control

Keep track of:
- Custom CSS files
- Language override files
- Custom templates
- Admin configuration
- Logo/image files

Store in Git repository for easy deployment to new sites.

---

## Documentation for Clients

### Admin User Guide

Create a simple guide for clients:

**Title**: "Getting Started with ddCMS"

**Sections**:
1. Logging In
2. Dashboard Overview
3. Managing Pages
4. Adding Content
5. Working with Media
6. Configuration Basics
7. Getting Support

**Branding**: Dixon Digital branded PDF

---

## Cost & Time Estimate

**Estimated Time**: 3-5 days

**Breakdown**:
- Custom CSS: 1 day
- Language overrides: 0.5 days
- Logo integration: 0.5 days
- Login page customization: 1 day
- Dashboard customization: 1 day
- Testing: 0.5 days
- Documentation & screenshots: 0.5 days

**Total**: ~5 days for complete white-labeling

---

## Success Criteria

Phase 2 is complete when:

✅ No visible "Grav" branding in admin
✅ ddCMS logo displayed throughout admin
✅ Custom login page with Dixon Digital branding
✅ Professional color scheme applied
✅ Dashboard shows custom welcome message
✅ Footer shows Dixon Digital attribution
✅ All functionality working correctly
✅ Screenshots and documentation created
✅ Client-ready presentation materials

---

## Support & Resources

- **Grav Admin Plugin Docs**: https://learn.getgrav.org/17/admin-panel
- **Grav Theming**: https://learn.getgrav.org/17/themes
- **CSS Documentation**: For styling reference
- **Dixon Digital Brand Guidelines**: (internal)

---

**Ready to transform Grav into ddCMS!** 🎨
