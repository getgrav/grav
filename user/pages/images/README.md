# Content Images Directory

This directory contains all content-specific images used throughout the OCEDC website pages.

## Directory Structure

```
images/
├── hero/           # Hero section background images
├── features/       # Feature section icons and images
├── team/           # Team member photos
├── properties/     # Available site/property photos
├── community/      # Community and quality of life photos
└── news/           # News article images
```

## Image Requirements by Section

### Hero Section (`hero/`)

**Purpose**: Large background images for homepage and page headers

- **Recommended images**:
  1. `ogle-county-aerial.jpg` - Aerial view of Ogle County
  2. `industrial-park.jpg` - Modern industrial facility
  3. `downtown-oregon.jpg` - Downtown Oregon, IL
  4. `business-handshake.jpg` - Professional business imagery

- **Specifications**:
  - Format: JPG (compressed)
  - Dimensions: 1920x1080px minimum (landscape orientation)
  - File size: <300KB (optimized)
  - Style: Professional, high-quality, well-lit
  - Subject: Should include relevant Ogle County landmarks, businesses, or scenery

### Features Section (`features/`)

**Purpose**: Icons and images for the 6 homepage feature cards

Required images (or use SVG icons):
1. `location-icon.svg` - Strategic location
2. `workforce-icon.svg` - Skilled workforce
3. `sites-icon.svg` - Available sites
4. `infrastructure-icon.svg` - Infrastructure
5. `incentives-icon.svg` - Tax incentives
6. `quality-life-icon.svg` - Quality of life

- **Specifications**:
  - Format: SVG (preferred) or PNG with transparency
  - Dimensions: 100x100px (if PNG)
  - Style: Simple, clean, professional
  - Colors: OCEDC brand colors (Navy, Green, Gold)

### Team Section (`team/`)

**Purpose**: Professional headshots of OCEDC staff

- **Specifications**:
  - Format: JPG
  - Dimensions: 400x400px (square aspect ratio)
  - File size: <100KB each
  - Background: Professional (solid color or blurred office)
  - Naming: `firstname-lastname.jpg` (e.g., `john-smith.jpg`)

**Example needed**:
- Executive Director photo
- Economic Development Manager photo
- Additional staff as needed

### Properties Section (`properties/`)

**Purpose**: Photos of available industrial sites, buildings, and properties

- **Specifications**:
  - Format: JPG
  - Dimensions: 1200x800px (3:2 aspect ratio)
  - Thumbnails: 400x267px
  - File size: <200KB for full-size, <50KB for thumbnails
  - Naming: `property-name.jpg` and `property-name-thumb.jpg`

**Example images needed**:
- Industrial park sites
- Available buildings
- Developed properties (showcases)
- Infrastructure (highways, rail, utilities)

### Community Section (`community/`)

**Purpose**: Showcase quality of life in Ogle County

**Recommended images**:
- Schools and education facilities
- Parks and recreation
- Downtown areas
- Cultural attractions
- Community events
- Healthcare facilities
- Housing developments

- **Specifications**:
  - Format: JPG
  - Dimensions: 800x600px (4:3 aspect ratio)
  - File size: <150KB
  - Style: Welcoming, vibrant, community-focused

### News Section (`news/`)

**Purpose**: Featured images for blog posts and news articles

- **Specifications**:
  - Format: JPG
  - Dimensions: 1200x630px (optimal for social sharing - 1.91:1 ratio)
  - File size: <200KB
  - Naming: Match article slug (e.g., `new-business-opens.jpg`)

**Best practices**:
- Use relevant, timely images
- Avoid generic stock photos when possible
- Include attribution if required
- Compress for web before uploading

## General Guidelines

### Image Optimization

All images should be optimized before uploading:
- Use tools like TinyPNG, ImageOptim, or Squoosh
- Remove EXIF data (except copyright if needed)
- Use progressive JPG format
- Resize to exact needed dimensions (don't rely on HTML/CSS resizing)

### Naming Conventions

- Use lowercase letters
- Use hyphens (not underscores or spaces)
- Be descriptive but concise
- Include relevant keywords for SEO
- Examples:
  - Good: `ogle-county-industrial-park.jpg`
  - Bad: `IMG_1234.jpg`, `photo 1.JPG`

### Accessibility

Every image should have:
- Descriptive filename
- Meaningful alt text (added in page frontmatter or markdown)
- Captions where appropriate

### Legal Considerations

- ✅ Use only images you have rights to
- ✅ Get permission for photos of people
- ✅ Credit photographers when required
- ✅ Use properly licensed stock photos if needed
- ❌ Don't use copyrighted images without permission

## Recommended Stock Photo Sources

If you need professional images before getting custom photography:

### Free (with attribution)
- Unsplash (unsplash.com)
- Pexels (pexels.com)
- Pixabay (pixabay.com)

### Paid (commercial license)
- iStock (istockphoto.com)
- Shutterstock (shutterstock.com)
- Adobe Stock (stock.adobe.com)

**Search terms for Ogle County/Economic Development**:
- "industrial park"
- "business meeting"
- "manufacturing facility"
- "small town america"
- "midwest landscape"
- "economic development"
- "warehouse exterior"
- "business handshake"

## Current Image Status

### ✅ Completed
- Directory structure created

### ⚠️ In Progress / Needed
- [ ] Hero background images (high priority)
- [ ] Feature icons (high priority)
- [ ] Team member photos
- [ ] Property photos
- [ ] Community photos
- [ ] News article images

## Adding Images to Pages

### In Markdown Files

```markdown
---
title: Page Title
media_order: 'image1.jpg,image2.jpg'
---

# Page Content

![Image description](image1.jpg "Image caption")
```

### In Modular Sections

```markdown
---
title: Hero Section
image: hero/ogle-county-aerial.jpg
---
```

### Using Theme Media Stream

```twig
{{ page.media['image.jpg'].resize(800, 600).quality(80).html }}
```

## Notes

- Always keep original, uncompressed versions backed up separately
- Consider professional photography for key images
- Update images seasonally if appropriate
- Audit and remove unused images periodically
- Use lazy loading for better performance
