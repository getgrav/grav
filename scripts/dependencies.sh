#!/bin/sh

# Install vendor packages
composer install

# Install default plugins
bin/gpm install -y error
bin/gpm install -y markdown-notices
bin/gpm install -y problems

# Install other plugins
bin/gpm install -y admin
bin/gpm install -y devtools
bin/gpm install -y featherlight
bin/gpm install -y license-manager
bin/gpm install -y lightbox-gallery
bin/gpm install -y youtube
bin/gpm install -y sitemap
bin/gpm install -y private

# Install shortcode plugins
bin/gpm install -y shortcode-core
bin/gpm install -y shortcode-gallery-plusplus
bin/gpm install -y shortcode-owl-carousel
bin/gpm install -y shortcode-ui
