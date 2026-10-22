# Grav core, for Anthropic's OSS Scanner (https://github.com/anthropics/oss-scanner).
#
# /src     the Grav core checkout (getgrav/grav, develop), which is also the running site: Apache serves /src
#          directly, so a patch made in /src is live on the next request.
#
# The site has the plugins Grav's "grav-admin" download ships (api, admin2, login, form, email, flex-objects)
# installed from GPM, plus the default theme, so the admin and API paths into core can be exercised. Those plugins
# live in their own repositories and are out of scope for this project. Start Apache with `grav-start`; the site
# listens on http://127.0.0.1/.
FROM php:8.4-apache

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1

RUN apt-get update && apt-get install -y --no-install-recommends \
      ca-certificates git unzip curl procps \
      libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd zip intl exif \
    && pecl install apcu && docker-php-ext-enable apcu \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Production-like PHP: errors are logged, not shown. OPcache picks up edited files immediately so a patch can be
# checked straight away.
RUN { echo 'memory_limit=256M'; \
      echo 'upload_max_filesize=64M'; \
      echo 'post_max_size=64M'; \
      echo 'error_reporting=E_ALL'; \
      echo 'display_errors=Off'; \
      echo 'log_errors=On'; \
      echo 'error_log=/dev/stderr'; \
      echo 'opcache.validate_timestamps=1'; \
      echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/oss-scanner.ini

# Apache serves /src with .htaccess enabled, so Grav's own rewrite and deny rules apply as they do in production.
RUN a2enmod rewrite headers expires \
    && sed -ri 's!/var/www/html!/src!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /src>\n  Options -MultiViews +FollowSymLinks\n  AllowOverride All\n  Require all granted\n</Directory>\nServerName 127.0.0.1\n' \
         > /etc/apache2/conf-available/grav.conf \
    && a2enconf grav

# grav-start / grav-stop start and stop Apache (there is no init system in the container). grav-cli runs a Grav CLI
# command as www-data, the user Apache runs as, so cache and account files stay writable by both:
#   grav-cli bin/grav clear     grav-cli bin/gpm index     grav-cli bin/plugin login new-user
RUN printf '%s\n' '#!/bin/sh' 'pgrep -x apache2 >/dev/null || apachectl start' > /usr/local/bin/grav-start \
    && printf '%s\n' '#!/bin/sh' 'apachectl stop 2>/dev/null; true' > /usr/local/bin/grav-stop \
    && printf '%s\n' '#!/bin/sh' 'cd /src && exec setpriv --reuid=www-data --regid=www-data --init-groups env HOME=/tmp php "$@"' \
         > /usr/local/bin/grav-cli \
    && chmod +x /usr/local/bin/grav-start /usr/local/bin/grav-stop /usr/local/bin/grav-cli

# scanner contract: the Grav core checkout lives inside the image, at /src
COPY . /src
WORKDIR /src

# The default plugins and theme from .dependencies, then the plugins the grav-admin download bundles. All of them land
# in user/plugins and user/themes, which core's .gitignore already ignores. `bin/grav install` installs core's
# dependencies without dev tools, so the last step puts Codeception and PHPUnit back for the test suite. vendor/ is
# ignored by core's .gitignore.
RUN composer install --no-progress --no-dev \
    && bin/grav install \
    && bin/gpm install -y api admin2 login form email flex-objects \
    && composer install --no-progress

# Three accounts for access-control reproductions (user/accounts is ignored by core's .gitignore):
#   admin  / Admin-pass-1    super user: admin2 and the API with every permission
#   editor / Editor-pass-1   publisher: API access with pages and media read/write only, no config, users or system
#   member / Member-pass-1   front-end login only, no admin or API access
RUN php -r ' \
      $accounts = [ \
        "admin"  => ["Admin-pass-1",  "Site Admin",  "access:\n  admin:\n    login: true\n    super: true\n  api:\n    access: true\n    super: true\n  site:\n    login: true\n"], \
        "editor" => ["Editor-pass-1", "Page Editor", "access:\n  api:\n    access: true\n    pages:\n      read: true\n      write: true\n    media:\n      read: true\n      write: true\n  site:\n    login: true\n"], \
        "member" => ["Member-pass-1", "Site Member", "access:\n  site:\n    login: true\n"], \
      ]; \
      foreach ($accounts as $user => [$password, $name, $access]) { \
        file_put_contents("/src/user/accounts/$user.yaml", \
          "state: enabled\nemail: $user@example.com\nfullname: \x27$name\x27\nlanguage: en\n" . $access \
          . "hashed_password: " . password_hash($password, PASSWORD_DEFAULT) . "\n"); \
      }' \
    && chown -R www-data:www-data /src

# Smoke-test the site, the admin, the API and one deny rule, and run the unit suite, without letting a failing test
# abort the build.
RUN grav-start \
    && curl -fsS -o /dev/null http://127.0.0.1/ \
    && curl -fsS -o /dev/null http://127.0.0.1/admin \
    && curl -fsS -o /dev/null -X POST -H 'Content-Type: application/json' \
         -d '{"username":"admin","password":"Admin-pass-1"}' http://127.0.0.1/api/v1/auth/token \
    && test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/user/accounts/admin.yaml)" = 403 \
    && grav-stop \
    && (setpriv --reuid=www-data --regid=www-data --init-groups env HOME=/tmp vendor/bin/codecept run unit --no-colors \
          || echo "WARNING: core unit tests reported failures")

# /src belongs to www-data so the site can write its cache; let root's git work in it too. Keep build products out of
# the checkout's `git status`.
RUN git config --system --add safe.directory '*' \
    && (printf '.oss-scanner/\n' >> /src/.git/info/exclude || true)

WORKDIR /src
