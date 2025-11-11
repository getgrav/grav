# OCEDC Website - Project Documentation

## Project Overview

This is a **Grav CMS** website for the **Ogle County Economic Development Corporation (OCEDC)**, whitelabeled as **ddCMS (Dixon Digital CMS)** by Dixon Digital agency.

### Key Information

- **CMS**: Grav CMS (whitelabeled as ddCMS)
- **Client**: Ogle County Economic Development Corporation
- **Agency**: Dixon Digital
- **Theme**: Custom `ddcms` theme
- **Purpose**: Economic development website promoting Ogle County, Illinois as a business location

## Project Structure

### Core Components

1. **Custom Theme**: `user/themes/ddcms/`
   - Modern, professional theme built specifically for OCEDC
   - OCEDC brand colors: Navy Blue (#1a4d8f), Green (#6cb541), Gold (#f39c12)
   - Responsive design with mobile-first approach
   - Modular page support

2. **Content Pages**: `user/pages/`
   - Homepage with modular sections (hero, features, stats, callout)
   - About Us
   - Why Ogle County
   - Business Resources (with subpages)
   - News/Blog
   - Contact

3. **Plugins**: `user/plugins/`
   - **admin**: Admin interface (required)
   - **login**: User authentication (required by admin)
   - **form**: Form handling (required by admin)
   - **email**: Email functionality (required by admin)
   - **flex-objects**: Flex objects management (required by admin)

### Whitelabeling

The project has been whitelabeled from Grav to ddCMS:
- `composer.json`: Updated with ddCMS branding
- `README.md`: Updated with ddCMS information
- `user/languages/en.yaml`: Custom language strings
- Admin interface: Configured for ddCMS branding

## Technical Details

### Requirements

- **PHP**: 7.3.6 or higher (tested with PHP 8.4.12)
- **Web Server**: Apache, Nginx, or PHP built-in server
- **Extensions**: Standard PHP extensions required by Grav

### Installation & Setup

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd grav
   ```

2. **Install dependencies**:
   ```bash
   composer install
   bin/grav install
   ```

3. **Install required plugins** (if not already installed):
   ```bash
   bin/gpm install admin login form email flex-objects -y
   ```

4. **Enable plugins** (create config files in `user/config/plugins/`):
   - `admin.yaml`: `enabled: true`
   - `login.yaml`: `enabled: true`
   - `form.yaml`: `enabled: true`
   - `email.yaml`: `enabled: true`
   - `flex-objects.yaml`: `enabled: true`

5. **Run locally**:
   ```bash
   bin/grav server --port=8000
   ```
   Access at: `http://127.0.0.1:8000`

6. **Access admin**:
   - URL: `http://127.0.0.1:8000/admin`
   - Create admin user on first access

### Configuration Files

- **Site Config**: `user/config/site.yaml`
  - Site title: "Ogle County Economic Development Corporation"
  - Author: Ogle County EDC
  - Email: info@oglecoedg.org

- **System Config**: `user/config/system.yaml`
  - Theme: `ddcms`
  - Error display: disabled (for cleaner output)
  - Cache: enabled

- **Theme Config**: `user/themes/ddcms/ddcms.yaml`
  - Logo text: "OCEDC"
  - Tagline: "Building Economic Prosperity in Ogle County"
  - Footer copyright and social links

### Brand Colors (OCEDC)

Defined in `user/themes/ddcms/css/ocedc.css`:
- **Primary (Navy)**: `#1a4d8f`
- **Secondary (Green)**: `#6cb541`
- **Accent (Gold)**: `#f39c12`

## Development Notes

### PHP 8.4 Compatibility

The project includes a workaround for PHP 8.4 deprecation warnings in `index.php`:
```php
// Suppress PHP 8.4 deprecation warnings (harmless, will be fixed in future Grav updates)
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
```

These warnings are harmless and don't affect functionality. They'll be resolved in future Grav updates.

### Modular Pages

The homepage uses Grav's modular page system:
- `user/pages/01.home/modular.md`: Main modular page
- Child modules:
  - `_hero/`: Hero section with CTA buttons
  - `_features/`: Features grid (6 items)
  - `_stats/`: Statistics section (4 stats)
  - `_callout/`: Call-to-action section

Each module has its own template in `user/themes/ddcms/templates/modular/`.

### Theme Structure

```
user/themes/ddcms/
├── templates/
│   ├── partials/
│   │   ├── base.html.twig      # Base template
│   │   ├── header.html.twig    # Header/navigation
│   │   ├── footer.html.twig    # Footer
│   │   └── navigation.html.twig # Navigation menu
│   ├── modular/
│   │   ├── default.html.twig   # Modular page wrapper
│   │   ├── hero.html.twig      # Hero section
│   │   ├── features.html.twig  # Features grid
│   │   ├── stats.html.twig     # Statistics section
│   │   └── callout.html.twig   # CTA section
│   └── default.html.twig       # Default page template
├── css/
│   ├── main.css                # Core theme styles
│   ├── custom.css              # Custom styles
│   └── ocedc.css               # OCEDC brand colors
├── js/
│   └── main.js                 # Theme JavaScript
├── ddcms.yaml                  # Theme configuration
└── blueprints.yaml             # Admin panel blueprints
```

## Content Structure

### Pages

1. **Home** (`01.home/`)
   - Modular page with hero, features, stats, and callout sections
   - Promotes Ogle County as a business location

2. **About** (`02.about/`)
   - About Ogle County EDC

3. **Why Ogle County** (`03.why-ogle-county/`)
   - Detailed information about business advantages

4. **Business Resources** (`04.business-resources/`)
   - Starting a Business
   - Incentives
   - Available Sites
   - Key Contacts

5. **News** (`05.news/`)
   - Blog-style news section
   - Sample posts included

6. **Contact** (`06.contact/`)
   - Contact information and form

## Important Files to Track

### Must Commit
- `user/themes/ddcms/` - Custom theme
- `user/pages/` - Content pages
- `user/config/` - Configuration (except security.yaml)
- `user/languages/` - Language files
- `composer.json` - Dependencies
- `index.php` - Entry point (with PHP 8.4 fix)

### Should NOT Commit (in .gitignore)
- `cache/` - Cache files
- `logs/` - Log files
- `user/accounts/` - User accounts
- `user/data/` - Runtime data
- `vendor/` - Composer dependencies (install via composer)
- `user/plugins/` - Plugins (install via GPM)
- `user/config/security.yaml` - Security keys

## Deployment

### For Production

1. Ensure all dependencies are installed:
   ```bash
   composer install --no-dev --optimize-autoloader
   bin/grav install
   ```

2. Set proper file permissions:
   ```bash
   chmod -R 755 user/
   chmod -R 755 cache/
   chmod -R 755 logs/
   ```

3. Configure web server (Apache/Nginx) to point to project root

4. Set up admin user via `/admin` interface

### Environment Variables

No environment variables required. All configuration is in YAML files.

## Troubleshooting

### Common Issues

1. **Admin 404 Error**
   - Ensure all required plugins are installed and enabled
   - Check `user/config/plugins/admin.yaml` exists with `enabled: true`

2. **Modular Sections Not Rendering**
   - Clear cache: `bin/grav cache-clear`
   - Verify `template: modular` in `user/pages/01.home/modular.md`
   - Check module templates exist in `user/themes/ddcms/templates/modular/`

3. **PHP Deprecation Warnings**
   - Already suppressed in `index.php`
   - Harmless warnings from PHP 8.4 compatibility

4. **Missing Plugins**
   - Install via GPM: `bin/gpm install <plugin-name> -y`
   - Enable in `user/config/plugins/<plugin-name>.yaml`

## Maintenance

### Updating Grav

```bash
bin/gpm selfupgrade
```

### Updating Plugins/Themes

```bash
bin/gpm update
```

### Clearing Cache

```bash
bin/grav cache-clear
```

## Contact & Support

- **Client**: Ogle County Economic Development Corporation
- **Email**: info@oglecoedg.org
- **Agency**: Dixon Digital
- **Website**: https://dixondigital.com

## License

Grav CMS is licensed under MIT License. See LICENSE.txt for details.

