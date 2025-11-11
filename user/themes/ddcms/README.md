# ddCMS Theme

Modern, professional theme for ddCMS (Dixon Digital CMS).

## Features

- Modern, responsive design
- Mobile-first approach
- Professional typography
- Customizable colors and branding
- Modular page support
- SEO-friendly structure
- Accessibility compliant (WCAG 2.1 AA)
- Performance optimized

## Installation

This theme is included with ddCMS. To activate it:

1. Go to Admin Panel → Configuration → System
2. Set the theme to `ddcms`
3. Save configuration

## Customization

### Theme Settings

Customize the theme through the Admin Panel:

- **Content Tab**: Logo, logo text, tagline
- **Style Tab**: Primary color, secondary color, accent color, font family
- **Footer Tab**: Copyright text, social links

### Adding a Logo

1. Upload your logo image to `user/themes/ddcms/images/`
2. In Admin Panel → Themes → ddCMS → Content
3. Select your logo file

### Custom Colors

The theme uses CSS custom properties (variables) for easy color customization. Edit `css/main.css` to change the color scheme:

```css
:root {
    --primary-color: #2563eb;
    --secondary-color: #64748b;
    --accent-color: #f59e0b;
}
```

## Modular Templates

The theme includes templates for modular sections:

- `modular/hero.html.twig` - Hero section with title, subtitle, and buttons
- `modular/features.html.twig` - Features grid layout
- `modular/callout.html.twig` - Call-to-action section

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## License

MIT License - See LICENSE file for details

## Support

For support, visit [Dixon Digital](https://dixondigital.com)

