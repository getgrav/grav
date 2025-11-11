# OCEDC Website - Ogle County Economic Development Corporation

A modern, professional website built with **Grav CMS** (whitelabeled as **ddCMS**) for the Ogle County Economic Development Corporation.

## Quick Start

```bash
# Clone repository
git clone <repository-url>
cd grav

# Install dependencies
composer install
bin/grav install

# Install required plugins
bin/gpm install admin login form email flex-objects -y

# Run development server
bin/grav server --port=8000
```

Access the site at: `http://127.0.0.1:8000`  
Access admin at: `http://127.0.0.1:8000/admin`

## Project Overview

This website promotes Ogle County, Illinois as a prime business location, highlighting:
- Strategic location on I-39 and US Route 20
- Skilled workforce
- Available industrial sites
- Business support services
- Tax incentives & financing
- Quality of life

## Technology Stack

- **CMS**: Grav CMS (whitelabeled as ddCMS by Dixon Digital)
- **PHP**: 7.3.6+ (tested with PHP 8.4.12)
- **Theme**: Custom `ddcms` theme
- **Templating**: Twig
- **Content**: Markdown + YAML

## Features

- ✅ Responsive, modern design
- ✅ Modular homepage sections
- ✅ Business resources section
- ✅ News/Blog functionality
- ✅ Contact forms
- ✅ Admin interface for content management
- ✅ OCEDC brand colors and styling

## Project Structure

```
grav/
├── user/
│   ├── themes/ddcms/          # Custom theme
│   ├── pages/                  # Content pages
│   ├── config/                 # Configuration
│   └── plugins/               # Installed plugins
├── system/                     # Grav core (don't modify)
├── vendor/                     # Composer dependencies
└── index.php                   # Entry point
```

## Requirements

- PHP 7.3.6 or higher
- Composer
- Web server (Apache, Nginx, or PHP built-in server)

## Installation

See [PROJECT.md](PROJECT.md) for detailed installation and setup instructions.

## Configuration

- **Site Config**: `user/config/site.yaml`
- **System Config**: `user/config/system.yaml`
- **Theme Config**: `user/themes/ddcms/ddcms.yaml`

## Brand Colors

- **Primary (Navy)**: `#1a4d8f`
- **Secondary (Green)**: `#6cb541`
- **Accent (Gold)**: `#f39c12`

## Documentation

For detailed project documentation, development notes, and troubleshooting, see [PROJECT.md](PROJECT.md).

## License

MIT License - See LICENSE.txt for details.

## Credits

- **CMS**: Grav CMS (https://getgrav.org)
- **Client**: Ogle County Economic Development Corporation
- **Agency**: Dixon Digital (https://dixondigital.com)
- **Theme**: Custom ddCMS theme
