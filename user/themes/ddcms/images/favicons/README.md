# Favicons for OCEDC Website

## Quick Start

The easiest way to generate all required favicon sizes is to use an online tool:

### Recommended: RealFaviconGenerator.net

1. Go to https://realfavicongenerator.net/
2. Upload `favicon-source.svg` (or a 512x512px PNG)
3. Customize colors and appearance for each platform
4. Generate and download the package
5. Extract all files to this directory
6. Update the HTML head tags in the theme template

## Required Favicon Files

### Standard Favicons
- `favicon.ico` - 16x16, 32x32, 48x48 multi-resolution ICO file
- `favicon-16x16.png` - 16x16px PNG
- `favicon-32x32.png` - 32x32px PNG

### Apple Touch Icons (iOS)
- `apple-touch-icon.png` - 180x180px
- Optionally: `apple-touch-icon-precomposed.png`

### Android Chrome
- `android-chrome-192x192.png` - 192x192px
- `android-chrome-512x512.png` - 512x512px

### Microsoft Windows
- `mstile-150x150.png` - 150x150px
- `browserconfig.xml` - Windows tile configuration

### Web App Manifest
- `site.webmanifest` - PWA manifest file

## Manual Generation (if needed)

If you prefer to generate manually:

### Using ImageMagick (command line)

```bash
# Install ImageMagick first
# On macOS: brew install imagemagick
# On Ubuntu: sudo apt-get install imagemagick

# Generate from SVG
convert favicon-source.svg -resize 16x16 favicon-16x16.png
convert favicon-source.svg -resize 32x32 favicon-32x32.png
convert favicon-source.svg -resize 180x180 apple-touch-icon.png
convert favicon-source.svg -resize 192x192 android-chrome-192x192.png
convert favicon-source.svg -resize 512x512 android-chrome-512x512.png

# Create multi-resolution ICO
convert favicon-source.svg -resize 16x16 -resize 32x32 -resize 48x48 -colors 256 favicon.ico
```

### Using Online Tools

- **Favicon Generator**: https://www.favicon-generator.org/
- **RealFaviconGenerator**: https://realfavicongenerator.net/ (best option)
- **Favicon.io**: https://favicon.io/

## HTML Implementation

Add this to `user/themes/ddcms/templates/partials/base.html.twig` in the `<head>` section:

```html
<!-- Favicons -->
<link rel="icon" type="image/x-icon" href="{{ url('theme://images/favicons/favicon.ico') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ url('theme://images/favicons/favicon-16x16.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ url('theme://images/favicons/favicon-32x32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ url('theme://images/favicons/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ url('theme://images/favicons/site.webmanifest') }}">

<!-- Microsoft Tiles -->
<meta name="msapplication-TileColor" content="#1a4d8f">
<meta name="msapplication-config" content="{{ url('theme://images/favicons/browserconfig.xml') }}">

<!-- Theme Color -->
<meta name="theme-color" content="#1a4d8f">
```

## Design Guidelines

### Colors
- **Primary**: #1a4d8f (Navy) - OCEDC brand color
- **Secondary**: #6cb541 (Green) - OCEDC brand color
- **Accent**: #f39c12 (Gold) - OCEDC brand color

### Design Tips
- Keep it simple - favicons are displayed at very small sizes
- Use high contrast
- Avoid fine details that won't be visible at 16x16px
- Test at multiple sizes
- Consider how it looks in:
  - Browser tabs (light and dark mode)
  - Bookmarks bar
  - Mobile home screen
  - Windows taskbar

### Testing
1. Add favicons to your site
2. Clear browser cache
3. Test in multiple browsers:
   - Chrome/Edge
   - Firefox
   - Safari
   - Mobile browsers
4. Check appearance in:
   - Browser tab
   - Bookmarks
   - Mobile home screen
   - Windows Start Menu (if applicable)

## Current Status

- [x] Source SVG created (`favicon-source.svg`)
- [ ] Generate all required sizes
- [ ] Test on multiple browsers
- [ ] Update theme template with favicon links

## Notes

- The current `favicon-source.svg` is a placeholder
- Replace with professional OCEDC logo design
- All generated files should be optimized for web
- Keep source files backed up separately
- Update when branding changes
