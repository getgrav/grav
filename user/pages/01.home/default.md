---
title: Home
body_classes: title-center title-h1h2
---

# Say Hello to Grav 2.0!
## installation successful...

Congratulations! You have installed the **Base Grav 2.0 Package** that provides a **simple page** and the new default **Quark 2** theme to get you started — built on [Blades CSS](https://blades.ninja/) (the actively maintained successor to Pico CSS), Font Awesome 7, and a refined typographic system with light/dark/auto appearance.

> [!WARNING]
> If you see a **404 Error** when you click `Typography` in the menu, please refer to the [troubleshooting guide](http://learn.getgrav.org/troubleshooting/page-not-found).

### Find out all about Grav

* Learn about **Grav** by checking out our dedicated [Learn Grav](https://learn.getgrav.org) site.
* Download **plugins**, **themes**, and Grav **skeleton** packages from the [Grav Downloads](https://getgrav.org/downloads) page.
* Follow the [Grav Development Blog](https://getgrav.org/blog) to keep up with the latest happenings in the Grav-verse.
* Browse the source on [GitHub](https://github.com/getgrav/grav) and file issues or pull requests there.

> [!TIP]
> If you want a more **full-featured** base install, check out the [**Skeleton** packages available in the downloads](https://getgrav.org/downloads) — several have already been updated for Grav 2.0.

### Edit this Page

To edit this page, navigate to the folder you installed **Grav** into, and browse to the `user/pages/01.home` folder. Open `default.md` in your [editor of choice](https://learn.getgrav.org/basics/requirements). You'll see the content of this page written in [Markdown format](https://learn.getgrav.org/content/markdown).

### Create a New Page

Creating a new page is a simple affair in **Grav**. Follow these steps:

1. Navigate to your pages folder — `user/pages/` — and create a new folder. In this example, we'll use [explicit default ordering](https://learn.getgrav.org/content/content-pages) and call the folder `03.mypage`.
2. Launch your text editor and paste in the following sample content:

        ---
        title: My New Page
        ---
        # My New Page!

        This is the body of **my new page** and I can easily use _Markdown_ syntax here.

3. Save this file in `user/pages/03.mypage/` as `default.md`. That tells **Grav** to render the page using the **default** template from Quark 2.
4. Reload your browser to see your new page appear in the menu.

> [!NOTE]
> The page will automatically show up in the menu after the **Typography** item. If you want a different label, add `menu: My Page` between the dashes in the front matter — this is the YAML header block, where all per-page options are configured.

### What's new in Grav 2.0

Grav 2.0 is the biggest release in the project's history — a modernized core, a rebuilt admin, a fresh default theme, and an updated dependency stack. Most of what you already know still works, but everything underneath has been sharpened.

* **PHP 8.3+** baseline, with a modernized core and typed internals throughout.
* **Symfony 7 + Twig 3** dependency stack, bringing the latest framework improvements and long-term support.
* **Quark 2** as the default theme — [Blades CSS](https://blades.ninja/), Font Awesome 7, auto/light/dark mode with `localStorage` persistence, locally-hosted Cal Sans + Inter fonts, and a refined Cal.com-inspired design system.
* **Admin 2.0** — a fully API-powered SPA interface that's dramatically faster, more responsive, and decoupled from page renders. Navigation, form saves, and media operations no longer trigger full reloads.
* **Public REST API** — the same endpoints that power Admin 2.0 are available to your own tools and integrations.
* **GitHub Markdown Alerts** — the legacy `markdown-notices` plugin has been replaced by `github-markdown-alerts`, using the familiar `> [!NOTE]` / `[!TIP]` / `[!IMPORTANT]` / `[!WARNING]` / `[!CAUTION]` syntax.
* **Refined caching and asset pipeline** — cleaner invalidation, faster cold starts, and smarter dependency fingerprinting.
* **Hardened security defaults** — tightened CSP headers, modern session handling, and improved input sanitization across core plugins.
* **Better developer ergonomics** — stricter type hints, cleaner event signatures, and clearer deprecation paths.

> [!IMPORTANT]
> Upgrading from Grav 1.x requires a migration. Follow the step-by-step guide at [getgrav.org/migrate-to-2](https://getgrav.org/migrate-to-2) — it covers pre-flight checks, the migration itself, and what to verify afterward.
