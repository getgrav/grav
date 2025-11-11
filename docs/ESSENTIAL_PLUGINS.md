# Essential Plugins for OCEDC Website

This document lists all essential plugins that should be installed for the OCEDC website to function optimally.

## Already Installed (Core Required)

These plugins are already installed and configured:

- ✅ **admin** - Admin panel interface
- ✅ **login** - User authentication
- ✅ **form** - Form handling (contact form)
- ✅ **email** - Email functionality
- ✅ **flex-objects** - Flexible content management

## High Priority - Install Immediately

### 1. SimpleSimplesearch (Site Search)

**Purpose**: Add search functionality to the website

**Installation**:
```bash
bin/gpm install simplesearch -y
```

**Configuration** (`user/config/plugins/simplesearch.yaml`):
```yaml
enabled: true
built_in_css: true
built_in_js: true
display_button: true
min_query_length: 3
route: /search
template: simplesearch_results
filters:
    category: blog
```

**Usage**: Add search form to header template:
```twig
<form action="{{ base_url }}/search" method="get">
    <input type="text" name="query" placeholder="Search..." required>
    <button type="submit">Search</button>
</form>
```

### 2. Sitemap (XML Sitemap)

**Purpose**: Generate XML sitemap for search engines

**Installation**:
```bash
bin/gpm install sitemap -y
```

**Configuration** (`user/config/plugins/sitemap.yaml`):
```yaml
enabled: true
route: /sitemap
ignore_external: true
ignore_protected: true
ignore_redirect: true
html_support: false
urlset: 'http://www.sitemaps.org/schemas/sitemap/0.9'
short_date_format: true
include_change_freq: true
change_freq: monthly
```

**Access**: https://yoursite.com/sitemap

**Submit to**:
- Google Search Console
- Bing Webmaster Tools

### 3. Breadcrumbs

**Purpose**: Improve navigation and SEO

**Installation**:
```bash
bin/gpm install breadcrumbs -y
```

**Configuration** (`user/config/plugins/breadcrumbs.yaml`):
```yaml
enabled: true
show_all: true
built_in_css: true
include_home: true
include_current: true
icon_home: ''
icon_divider_classes: 'fa fa-angle-right'
link_trailing: false
```

**Add to template** (`user/themes/ddcms/templates/partials/breadcrumbs.html.twig`):
```twig
{% if config.plugins.breadcrumbs.enabled %}
<nav class="breadcrumbs" aria-label="Breadcrumb">
    {% include 'partials/breadcrumbs.html.twig' %}
</nav>
{% endif %}
```

## Medium Priority - Install Soon

### 4. Feed (RSS Feed)

**Purpose**: RSS/Atom feeds for blog content

**Installation**:
```bash
bin/gpm install feed -y
```

**Configuration** (`user/config/plugins/feed.yaml`):
```yaml
enabled: true
limit: 10
description: 'Latest news and updates from OCEDC'
lang: en-us
length: 500
```

**Access**: https://yoursite.com/feed.rss or /feed.atom

### 5. Pagination

**Purpose**: Paginate blog listings

**Installation**:
```bash
bin/gpm install pagination -y
```

**Configuration** (`user/config/plugins/pagination.yaml`):
```yaml
enabled: true
built_in_css: true
delta: 2
```

### 6. RelatedPages

**Purpose**: Show related content

**Installation**:
```bash
bin/gpm install relatedpages -y
```

**Configuration** (`user/config/plugins/relatedpages.yaml`):
```yaml
enabled: true
limit: 5
show_score: false
score_threshold: 20
filter:
    items:
        '@taxonomy': {category: blog}
page_in_filter: false
explicit_pages:
    process: true
    score: 100
```

### 7. Archives (Blog Archives)

**Purpose**: Archive blog posts by date

**Installation**:
```bash
bin/gpm install archives -y
```

**Configuration** (`user/config/plugins/archives.yaml`):
```yaml
enabled: true
built_in_css: true
date_display_format: 'F Y'
show_count: true
limit: 12
order:
    by: date
    dir: desc
filter_combinator: 'and'
filters:
    category: blog
```

### 8. Taxonomy List

**Purpose**: Display categories and tags

**Installation**:
```bash
bin/gpm install taxonomylist -y
```

**Configuration** (`user/config/plugins/taxonomylist.yaml`):
```yaml
enabled: true
route: /blog
```

## Security & Performance

### 9. Login Security (Recommended)

**Purpose**: Enhance admin login security

**Installation**:
```bash
bin/gpm install login-ldap -y
# Or for rate limiting:
bin/gpm install login-ratelimit -y
```

**Configuration**: Follow plugin documentation for your specific needs

### 10. Cache Warmer (Optional)

**Purpose**: Pre-cache pages for better performance

**Installation**:
```bash
bin/gpm install cache-warmer -y
```

### 11. SEO Plugin (Optional but Recommended)

**Purpose**: Advanced SEO features

**Installation**:
```bash
bin/gpm install seo -y
```

**Configuration** (`user/config/plugins/seo.yaml`):
```yaml
enabled: true
robots_txt: true
sitemap: true
meta:
    author: 'OCEDC'
    publisher: 'Ogle County Economic Development Corporation'
```

## Backup & Maintenance

### 12. Backup Plugin

**Purpose**: Automated backups

**Installation**:
```bash
bin/gpm install backup -y
```

**Configuration**: Set up automated backups

## Form Enhancements

### 13. reCAPTCHA (Spam Protection)

**Purpose**: Protect forms from spam

**Installation**:
```bash
bin/gpm install recaptcha -y
```

**Configuration** (`user/config/plugins/recaptcha.yaml`):
```yaml
enabled: true
version: 2
site_key: 'YOUR_SITE_KEY_FROM_GOOGLE'
secret_key: 'YOUR_SECRET_KEY_FROM_GOOGLE'
```

**Get Keys**: https://www.google.com/recaptcha/admin

**Update contact form** (`user/pages/06.contact/default.md`):
Add after honeypot field:
```yaml
        - name: g-recaptcha-response
          label: Captcha
          type: captcha
          recaptcha_site_key: YOUR_SITE_KEY
          recaptcha_not_validated: 'Captcha not valid!'
          validate:
            required: true
```

## Installation Commands Summary

Run all at once after initial setup:

```bash
# Essential plugins
bin/gpm install simplesearch sitemap breadcrumbs -y

# Content plugins
bin/gpm install feed pagination relatedpages archives taxonomylist -y

# Security
bin/gpm install recaptcha -y

# Optional but recommended
bin/gpm install seo backup -y
```

## Plugin Configuration Files

After installation, configure plugins by creating YAML files in:
`user/config/plugins/[plugin-name].yaml`

Basic template for any plugin:
```yaml
enabled: true
# Plugin-specific settings here
```

## Verifying Installation

1. Check installed plugins:
```bash
bin/gpm list
```

2. Check plugin status in admin panel:
- Login to `/admin`
- Navigate to Plugins
- Verify all plugins are enabled

3. Clear cache after installing plugins:
```bash
bin/grav cache-clear
```

## Troubleshooting

### Plugin not working after installation

1. Clear cache: `bin/grav cache-clear`
2. Check plugin is enabled in `user/config/plugins/[plugin-name].yaml`
3. Check error logs: `logs/grav.log`
4. Verify plugin requirements (PHP version, dependencies)

### Plugin conflicts

- Disable plugins one by one to identify conflicts
- Check plugin documentation for known conflicts
- Update all plugins to latest versions

## Updating Plugins

### Update all plugins:
```bash
bin/gpm update
```

### Update specific plugin:
```bash
bin/gpm update [plugin-name]
```

### Check for updates:
```bash
bin/gpm list --updates
```

## Custom Plugin Development

If you need custom functionality:

1. Create plugin directory: `user/plugins/custom-plugin/`
2. Create blueprint: `custom-plugin.yaml`
3. Create PHP file: `custom-plugin.php`
4. Follow Grav plugin development documentation

## Plugin Documentation

- **Official Plugin Directory**: https://getgrav.org/downloads/plugins
- **Plugin Development**: https://learn.getgrav.org/17/plugins
- **Plugin Tutorials**: https://learn.getgrav.org/17/cookbook/plugin-recipes

## Notes

- Always backup before installing/updating plugins
- Test plugins in development environment first
- Some plugins may require additional PHP extensions
- Check plugin compatibility with your Grav version
- Review plugin permissions and security implications

## Support

For plugin issues:
1. Check plugin documentation
2. Search Grav Discord/Forum
3. Check GitHub issues for the plugin
4. Contact plugin developer
