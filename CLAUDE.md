# Quick Reference for Claude/AI Assistants

## What is this project?

**OCEDC Website** - A Grav CMS website for Ogle County Economic Development Corporation, whitelabeled as ddCMS by Dixon Digital agency.

## Key Files to Read First

1. **PROJECT.md** - Comprehensive project documentation
2. **README.md** - Quick start guide
3. **DEPLOYMENT.md** - GitHub deployment instructions

## Essential Commands

```bash
# Install dependencies
composer install
bin/grav install

# Install required plugins
bin/gpm install admin login form email flex-objects -y

# Run development server
bin/grav server --port=8000
```

## Project Structure

- **Theme**: `user/themes/ddcms/` - Custom OCEDC theme
- **Content**: `user/pages/` - All website pages
- **Config**: `user/config/` - Site and system configuration
- **Plugins**: Installed via GPM, configs in `user/config/plugins/`

## Important Notes

- PHP 8.4 deprecation warnings are suppressed in `index.php` (harmless)
- Admin requires: login, form, email, flex-objects plugins
- Theme uses OCEDC brand colors (Navy, Green, Gold)
- Modular homepage with hero, features, stats, callout sections

## Brand Colors

- Primary: `#1a4d8f` (Navy)
- Secondary: `#6cb541` (Green)  
- Accent: `#f39c12` (Gold)

## Common Tasks

### Add a new page:
Create `.md` file in `user/pages/` directory

### Modify theme:
Edit files in `user/themes/ddcms/`

### Clear cache:
```bash
bin/grav cache-clear
```

### Update plugins:
```bash
bin/gpm update
```

## Troubleshooting

- **Admin 404**: Check all required plugins are installed and enabled
- **Modular sections not showing**: Clear cache and verify `template: modular` in page frontmatter
- **PHP warnings**: Already suppressed, harmless

For detailed information, see PROJECT.md

