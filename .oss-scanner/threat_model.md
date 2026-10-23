# Threat model: Grav core

The scanner reads this file before it starts. It covers Grav core (`getgrav/grav`). Severity follows Grav's own policy, [SECURITY.md](https://github.com/getgrav/grav/blob/develop/SECURITY.md) in this repository, which rates an issue by **whether it crosses a trust boundary**, not by what a CVSS calculator says.

## What this project does and where untrusted input enters

Grav is a flat-file PHP CMS: pages are markdown files with YAML frontmatter under `user/pages`, configuration is YAML under `user/config`, and accounts are YAML files under `user/accounts`. Sites built on it are public websites, so treat all HTTP input as untrusted: the URL path (including Grav's `name:value` path parameters, the page extension and the language prefix), query strings, form posts and JSON bodies, `task` / `action` parameters, cookies, headers (`Host`, `Accept-Language`, `Referer`), uploaded files, and image URLs with processing actions.

Content one user stores and others later see is also untrusted: page markdown and frontmatter written by publishers, media files and their metadata, and account profile fields.

### Where the code is

| Path | What it is |
| --- | --- |
| `/src` | The Grav core checkout (`develop`), which is also the running site: Apache serves `/src` directly, so edits are live on the next request. Patches belong here. |
| `/src/system/src/Grav` | Core's PHP code. `Common/` (pages, media, Twig, security, GPM, users) and `Framework/` (Flex, sessions, request handling, storage). |
| `/src/system/config` | Core's default configuration, including `security.yaml` (Twig sandbox and content Twig gates). |
| `/src/.htaccess`, `/src/webserver-configs/` | The web-server deny rules, with nginx, Caddy, lighttpd and IIS equivalents. |
| `/src/user/plugins`, `/src/user/themes` | Plugins and the theme installed for the demo site. Out of scope (see below). |

The site has the plugins Grav's `grav-admin` download ships (api, admin2, login, form, email, flex-objects) so that admin and front-end paths into core can be exercised. Those plugins live in their own repositories. If a bug is in their code, say which repository owns it rather than patching it here; if a plugin reaches a core API that is unsafe, the core API is in scope.

### Trust model

- **Anonymous visitors** are untrusted.
- **Front-end members** (accounts with only `site.login`) must not reach anything beyond pages their access allows.
- **Publishers** (accounts that can edit pages) are trusted to author content, including Twig in page content when `security.twig_content` allows it. Their content running in their own scope is the feature. **Page content Twig is sandboxed** (`security.twig_sandbox`, `Twig/Sandbox/`), and that sandbox is a real boundary: escaping it to call PHP functions or methods the allowlist does not grant, or making a string callable execute inside sandboxed content, is a finding. So is publisher content that executes script in a super admin's session.
- **Admins and super admins** are trusted: editing configuration, installing plugins and themes, writing theme templates, running the scheduler, CLI tools and backups are their job.
- **The operator** is trusted: `user/config/**` (including `security-private.php`, the per-site secret), the environment, the filesystem and the web-server config.

## Components that matter most / least

Highest priority:

1. **Twig content sandbox**: `Twig/Sandbox/GravSourcePolicy.php` (which sources are sandboxed), `Security::buildTwigSandboxPolicy()`, `SandboxDefaults.php` allowlists, `Security::willProcessContentTwig()`, the callback filters in `Twig/Extension/GravExtension.php` (`filter`, `map`, `reduce` and similar), `Twig::processString()` and `processPage()`, `Data/Blueprint.php` `isSafeDynamicCall()`.
2. **Output escaping**: Twig autoescaping, markdown rendering (`Markdown/ParsedownGravTrait.php`, the `tagfilter` option), and any place core marks user-controlled data as safe HTML.
3. **Request routing and URL handling**: `Uri.php` (params, extensions, base URL, referrer), `Pages::find()` / `dispatch()`, redirects and routes config, `Language` prefixes, `Grav::fallbackUrl()` (media and file serving when no page matches), `index.php`'s plugin-asset map.
4. **Media**: uploads (`Media/Traits/MediaUploadTrait.php`, `Utils::checkFilename`), SVG sanitizing (`Security::sanitizeSVG`), image processing via URL actions (`system.images.url_actions`, off by default) and `max_pixels`, `Utils::download` (range requests).
5. **Sessions, nonces and accounts**: `Session.php` (signed flash data), `Framework/Session/Session.php`, `Utils::getNonce()` / `verifyNonce()`, `User/Authentication.php`, username validation and `account://` paths.
6. **Filesystem paths**: anywhere a request value is joined into a path (`Utils::normalizePath`, locator streams, Flex storage keys in `Framework/Flex/Storage/*`, `media_directory`, `read_file`).
7. **GPM and the installer**: zip extraction (`GPM/Installer.php`, zip-slip and zip-bomb guards), `GPM::downloadPackage` and the `official_gpm_only` host check, self-upgrade.
8. **Unserialize**: any `unserialize()` reachable with attacker-influenced data (flash and file cache are HMAC-signed; a bypass is critical).
9. **Web-server rules**: requests that reach `user/accounts`, `user/config`, `user/data`, `logs`, `backup`, `.yaml` / `.md` / `.php` files under `user/` despite `.htaccess`, or the same gap in the shipped nginx or Caddy configs.

Lower priority or out of scope:

- `vendor/` (Composer packages, including Grav's forks of Twig and Parsedown). Report these upstream unless core uses a library unsafely. A bug in Grav's Twig fork (`getgrav/Twig`) that breaks the sandbox is in scope here.
- `system/assets` bundled JavaScript (jQuery, Clockwork and similar).
- `tests/`, `bin/` scripts beyond what a web request can reach, `webserver-configs/` samples other than as deny-rule references.
- Everything in `user/plugins` and `user/themes`.
- Grav 1.7 and older.

## How to exercise it

- Run `grav-start` first (there is no init system). The site is at `http://127.0.0.1/`; Admin2 is at `/admin` and the REST API at `/api/v1/`. `grav-stop` stops Apache. Grav's log is `/src/logs/grav.log`.
- Accounts: `admin` / `Admin-pass-1` (super admin), `editor` / `Editor-pass-1` (a publisher: pages and media read/write through the API, nothing else), `member` / `Member-pass-1` (front-end login only). They are YAML files in `/src/user/accounts/`.
- Publisher actions go through the API: `curl -s -X POST -H 'Content-Type: application/json' -d '{"username":"editor","password":"Editor-pass-1"}' http://127.0.0.1/api/v1/auth/token`, then send `X-API-Token: <access_token>` to `/api/v1/pages` to create or edit pages. Pages can also be written straight to `/src/user/pages/` to test how core renders content, as long as the report says which account could have written it.
- Run Grav CLI commands as the web server user with `grav-cli`, for example `grav-cli bin/grav clear`.
- Unit tests: `vendor/bin/codecept run unit` from `/src` (about 1,700 tests, under 20 seconds; run it as `www-data` with `setpriv --reuid=www-data --regid=www-data --init-groups vendor/bin/codecept run unit` to keep cache files writable). Security regression tests are in `tests/unit/Grav/Common/Security/`. Add a regression test with each patch where you can.

## How you rate severity

| Severity | When to use |
| --- | --- |
| **Critical** | An **unauthenticated** attacker gets remote code execution, reads site data that is not public (accounts, config, private files), or gains admin-equivalent control. No Grav account needed. |
| **High** | A **cross-trust-boundary** issue: a lower-privilege actor (or an anonymous visitor through a stored payload) runs code, reads data or takes actions in a higher-privilege session or on the server. Examples: a Twig sandbox escape from page content to PHP execution, stored XSS that fires in a super admin's session, a publisher gaining admin rights, CSRF that changes privileges. |
| **Moderate** | An authenticated user does something **outside the documented scope of their role**, but the impact stays within their own session or affects only users at the same level. |
| **Low** | An admin or super admin doing something within the capabilities they already hold. Grav does not publish advisories for these; report them only as hardening. |

Rate by the starting access and the boundary crossed, not by the CVSS calculator, which assumes `PR:N` far too often.

## Anything to leave alone

These are closed without an advisory under SECURITY.md, so please do not report them as vulnerabilities:

- Anything an admin or super admin can do within their granted capabilities: editing config, installing packages, writing theme templates, running CLI or scheduler tasks, using admin-only file tools.
- Publisher-authored content executing in the publisher's own scope (Twig that stays inside the sandbox allowlist, markdown and HTML they author).
- **`Security::detectXss()` bypasses.** It is a heuristic denylist used to flag content for humans, not a boundary. Content that renders **unescaped at an output sink** is in scope; show the rendered sink.
- **`Utils::isDangerousFunction()` bypasses.** Also a denylist. The sandbox is the boundary: a function missing from the denylist is not a finding, but a string callable of any kind executing inside sandboxed content is.
- Account or email enumeration through login, registration or password-reset messages (documented default behavior).
- Self-XSS, or anything that needs the victim to paste a payload into their own browser.
- Missing hardening with no demonstrated exploit: absent security headers, missing rate limits, permissive defaults documented as such, non-constant-time comparisons without a practical timing oracle.
- Issues that only occur under a non-default setting that Grav's docs already flag as dangerous.
- Dependency CVEs without a reachable call path from Grav code.
- Anything that first needs the attacker to install a plugin or write arbitrary files on the server.

## How we would like reports

Each report should state the account the attacker starts with, what they end up with, and which trust boundary is crossed. Include a reproducer that runs against this image (curl commands or a page file plus the request that renders it) and a patch against `/src`. If the root cause is in a plugin rather than core, name that plugin's repository.
