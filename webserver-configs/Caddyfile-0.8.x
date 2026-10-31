# Caddyfile for Caddy 0.8.x (the `status` and `if` rewrite subdirectives
# used below need Caddy 0.8.3). For Caddy 2 use webserver-configs/Caddyfile.

:8080
gzip
fastcgi / 127.0.0.1:9000 php

# Begin - Security
#
# Each `rewrite` block below is tried in file order against the request path
# (never the query string); the first one that matches answers 403. Every
# pattern is case-insensitive and anchored at the site root, the same as the
# rules in webserver-configs/Caddyfile.

# deny all direct access for these folders
rewrite {
    r       (?i)^/(\.git|cache|bin|logs|backups?|tmp|webserver-configs|tests)/
    status  403
}
# deny all direct access to these sensitive user folders, whatever the file type
rewrite {
    r       (?i)^/user/(env/[^/]+/)?config/
    status  403
}
# block user/accounts too, but allow avatar images to be served directly, whether
# stored at user/accounts/avatars/<file> (flatfile accounts) or user/accounts/
# <username>/<file> (Flex folder storage). SVG is excluded as a stored-XSS vector.
rewrite {
    r       (?i)^/user/(env/[^/]+/)?accounts/
    if      {path} not_match (?i)^/user/(env/[^/]+/)?accounts/[^/]+/[^/]+\.(jpe?g|png|gif|webp|avif|bmp|ico)$
    status  403
}
# block user/data too, but allow public media uploads (e.g. Flex Object images)
# to be served directly. SVG is intentionally excluded as a stored-XSS vector.
rewrite {
    r       (?i)^/user/(env/[^/]+/)?data/
    if      {path} not_match (?i)\.(jpe?g|png|gif|webp|avif|bmp|ico|mp4|webm|ogg|ogv|mov|mp3|wav|m4a|flac|pdf)$
    status  403
}
# Serve page media only through the page route, so plugins can apply the page's
# `access` rules to it. DO NOT uncomment without first setting
# `pages.media_route_urls: true` in user/config/system.yaml, or every media URL
# on the site becomes a 403.
# rewrite {
#     r       (?i)^/user/pages/
#     status  403
# }
# deny running scripts in the public cache folders (image derivatives and combined assets)
rewrite {
    r       (?i)^/(images|assets)/.*\.(php|php2|php3|php4|php5|php7|php8|phar|phtml|pht|phtm|phps|pl|py|cgi|sh|bat)$
    status  403
}
# deny running scripts inside core system folders
rewrite {
    r       (?i)^/(system|vendor)/.*\.(txt|xml|md|html|htm|shtml|shtm|json|yaml|yml|php|php2|php3|php4|php5|phar|phtml|pl|py|cgi|twig|sh|bat)$
    status  403
}
# deny running scripts inside user folder
rewrite {
    r       (?i)^/user/.*\.(txt|md|json|yaml|yml|php|php2|php3|php4|php5|phar|phtml|pl|py|cgi|twig|sh|bat)$
    status  403
}
# deny access to specific files in the root folder
rewrite {
    r       (?i)^/(LICENSE\.txt|composer\.lock|composer\.json|nginx\.conf|web\.config|htaccess\.txt)$
    status  403
}
# deny hidden files and directories at any depth (.htaccess, .env, .env.local,
# nested .git repositories), except ACME challenges and other /.well-known/ files
rewrite {
    r       (^|/)\.
    if      {path} not_match ^/\.well-known(/[^./][^/]*)+$
    status  403
}
# a URL ending in .md goes to Grav whether or not a file of that name exists,
# so page routes ending in .md get Grav's Markdown output and Markdown source
# files such as README.md and CHANGELOG.md are never served as they are
# (Caddy 0.8 has no condition on whether a file exists)
rewrite {
    r       (?i)\.md$
    to      /index.php?_url={uri}&{query}
}
## End - Security

# global rewrite should come last.
rewrite {
    to  {path} {path}/ /index.php?_url={uri}&{query}
}
