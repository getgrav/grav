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
    r       (?i)^/user/(config|env)/
    status  403
}
# block user/accounts too, but allow avatar images to be served directly, whether
# stored at user/accounts/avatars/<file> (flatfile accounts) or user/accounts/
# <username>/<file> (Flex folder storage). SVG is excluded as a stored-XSS vector.
rewrite {
    r       (?i)^/user/accounts/
    if      {path} not_match (?i)^/user/accounts/[^/]+/[^/]+\.(jpe?g|png|gif|webp|avif|bmp|ico)$
    status  403
}
# block user/data too, but allow public media uploads (e.g. Flex Object images)
# to be served directly. SVG is intentionally excluded as a stored-XSS vector.
rewrite {
    r       (?i)^/user/data/
    if      {path} not_match (?i)\.(jpe?g|png|gif|webp|avif|bmp|ico|mp4|webm|ogg|ogv|mov|mp3|wav|m4a|flac|pdf)$
    status  403
}
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
# deny all direct access to .md files (README.md, CHANGELOG.md, ...)
rewrite {
    r       (?i)\.md$
    status  403
}
## End - Security

# global rewrite should come last.
rewrite {
    to  {path} {path}/ /index.php?_url={uri}&{query}
}
