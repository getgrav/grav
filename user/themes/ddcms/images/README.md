# Theme Images Directory

This directory contains all static images used by the ddCMS theme.

## Directory Structure

```
images/
├── logos/              # Brand logos
├── favicons/           # Favicon files (various sizes)
├── placeholders/       # Placeholder images for development
└── screenshots/        # Theme screenshots for portfolio
```

## Required Images

### Logos (`logos/`)

#### ddCMS Logo (Dixon Digital Agency Branding)
- **File**: `logo-ddcms.svg` (or `logo-ddcms.png`)
- **Recommended format**: SVG (scalable) or PNG with transparent background
- **Dimensions**:
  - SVG: Scalable
  - PNG: 400x100px minimum (can be larger)
- **Purpose**: Used in admin panel and theme documentation
- **Color**: Your agency brand colors

#### OCEDC Logo (Client Branding)
- **File**: `logo-ocedc.svg` (or `logo-ocedc.png`)
- **Recommended format**: SVG (scalable) or PNG with transparent background
- **Dimensions**:
  - SVG: Scalable (preferred)
  - PNG: 400x100px minimum, 800x200px recommended
- **Purpose**: Main site logo in header
- **Color**: OCEDC brand colors (Navy #1a4d8f, Green #6cb541)
- **Variants needed**:
  - `logo-ocedc.svg` - Full color logo
  - `logo-ocedc-white.svg` - White version for dark backgrounds (optional)
  - `logo-ocedc-icon.svg` - Icon only version (optional)

### Favicons (`favicons/`)

Required favicon files (generate from single source image):

1. **favicon.ico** - 32x32px, multi-resolution ICO file
2. **favicon-16x16.png** - 16x16px PNG
3. **favicon-32x32.png** - 32x32px PNG
4. **apple-touch-icon.png** - 180x180px PNG (iOS devices)
5. **android-chrome-192x192.png** - 192x192px PNG (Android devices)
6. **android-chrome-512x512.png** - 512x512px PNG (Android devices)

**Source image requirements**:
- Minimum 512x512px
- Square aspect ratio
- Simple, recognizable design
- Works well at small sizes
- OCEDC brand colors

**Recommended tool**: https://realfavicongenerator.net/

### Screenshots (`screenshots/`)

For theme portfolio and documentation:

1. **homepage-desktop.png** - Full homepage screenshot (1920x1080px)
2. **homepage-mobile.png** - Mobile view (375x812px)
3. **admin-dashboard.png** - Admin panel screenshot
4. **contact-form.png** - Contact form functionality
5. **blog-listing.png** - Blog/news section
6. **responsive-showcase.png** - Multi-device mockup

### Placeholders (`placeholders/`)

Temporary placeholder images for development (to be replaced with real photos):

- `hero-placeholder.jpg` - Hero section background (1920x1080px)
- `feature-placeholder.svg` - Feature icons (100x100px)
- `team-placeholder.jpg` - Team member photos (400x400px)
- `property-placeholder.jpg` - Property photos (800x600px)

## Image Specifications

### General Guidelines

- **Format**:
  - Logos: SVG (preferred) or PNG with transparency
  - Photos: JPG (compressed, optimized)
  - Icons: SVG (preferred) or PNG with transparency
- **Optimization**: All images should be optimized for web
  - Use tools like TinyPNG, ImageOptim, or Squoosh
  - Target: <200KB for photos, <50KB for icons/logos
- **Naming**: Use lowercase, hyphens (not underscores), descriptive names
  - Good: `logo-ocedc.svg`, `hero-background.jpg`
  - Bad: `Logo_OCEDC.SVG`, `image1.jpg`

### Responsive Images

For content images that need multiple sizes, use this naming convention:
- `image-name.jpg` - Original/largest size
- `image-name@2x.jpg` - Retina/high-DPI version
- `image-name-mobile.jpg` - Mobile-optimized version
- `image-name-thumbnail.jpg` - Thumbnail version

## Using Images in Templates

### Logo in Header
```twig
{% if config.themes.ddcms.header.logo %}
    <img src="{{ url('theme://images/logos/' ~ config.themes.ddcms.header.logo) }}" alt="{{ site.title }}">
{% else %}
    <h1>{{ config.themes.ddcms.header.logo_text }}</h1>
{% endif %}
```

### Content Images
```markdown
![Alt text](image://logos/logo-ocedc.svg "OCEDC Logo")
```

## TODO: Images Needed

### High Priority
- [ ] OCEDC logo (SVG or high-res PNG)
- [ ] Favicon source image (512x512px minimum)
- [ ] Hero section background (professional photo of Ogle County)

### Medium Priority
- [ ] Feature section icons (6 icons, 100x100px each)
- [ ] Team member photos (if team section is added)
- [ ] Property/site photos (for business resources section)

### Low Priority
- [ ] Community photos (quality of life section)
- [ ] Office building photo
- [ ] Event photos (for news section)

## Notes

- Keep source files (AI, PSD, etc.) in a separate directory outside the web root
- Always maintain backups of original, uncompressed images
- Update this README when adding new image categories
- Consider using a CDN for production (Cloudflare, CloudFront, etc.)
