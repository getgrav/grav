# GitHub Deployment Guide

## Step 1: Create a New GitHub Repository

1. Go to GitHub and create a new repository
2. Name it something like `ocedc-website` or `ogle-county-edc`
3. **DO NOT** initialize with README, .gitignore, or license (we already have these)
4. Copy the repository URL (e.g., `https://github.com/yourusername/ocedc-website.git`)

## Step 2: Update Git Remote

```bash
# Remove the old Grav remote
git remote remove origin

# Add your new GitHub repository as origin
git remote add origin https://github.com/yourusername/your-repo-name.git

# Verify the remote
git remote -v
```

## Step 3: Stage and Commit Changes

```bash
# Stage all changes
git add .

# Commit with descriptive message
git commit -m "Initial OCEDC website setup

- Custom ddCMS theme with OCEDC branding
- Modular homepage with hero, features, stats, and callout sections
- Business resources pages
- News/blog section
- Contact page
- Required plugins installed and configured
- PHP 8.4 compatibility fixes
- Whitelabeled from Grav to ddCMS"

# Push to GitHub
git push -u origin develop
```

## Step 4: Create Main Branch (Optional)

If you want a `main` branch instead of `develop`:

```bash
# Create and switch to main branch
git checkout -b main

# Push main branch
git push -u origin main

# Set main as default branch in GitHub settings
```

## Step 5: Verify Deployment

1. Check your GitHub repository - all files should be there
2. Verify `.gitignore` is working (cache, logs, vendor should NOT be committed)
3. Test cloning the repository in a new location to ensure it works

## Important Notes

### Files NOT Committed (by .gitignore)
- `cache/` - Cache files (regenerated on first run)
- `logs/` - Log files
- `vendor/` - Composer dependencies (install via `composer install`)
- `user/plugins/` - Plugins (install via `bin/gpm install`)
- `user/accounts/` - User accounts (create via admin)
- `user/data/` - Runtime data
- `user/config/security.yaml` - Security keys (generated automatically)

### Files TO Commit
- All theme files (`user/themes/ddcms/`)
- All content pages (`user/pages/`)
- Configuration files (`user/config/` except security.yaml)
- Language files (`user/languages/`)
- `composer.json` and `composer.lock`
- `index.php` (with PHP 8.4 fix)
- `README.md` and `PROJECT.md`
- `.gitignore`

## For Claude/AI Assistants

When working with this project in a new environment:

1. **Read PROJECT.md first** - Contains comprehensive project documentation
2. **Install dependencies**: `composer install && bin/grav install`
3. **Install plugins**: `bin/gpm install admin login form email flex-objects -y`
4. **Enable plugins**: Create config files in `user/config/plugins/` with `enabled: true`
5. **Run server**: `bin/grav server --port=8000`

## Troubleshooting

### If push fails:
```bash
# Check remote URL
git remote -v

# If wrong, update it:
git remote set-url origin https://github.com/yourusername/your-repo-name.git
```

### If you need to force push (be careful!):
```bash
git push -u origin develop --force
```

### To clone in a new location:
```bash
git clone https://github.com/yourusername/your-repo-name.git
cd your-repo-name
composer install
bin/grav install
bin/gpm install admin login form email flex-objects -y
```


