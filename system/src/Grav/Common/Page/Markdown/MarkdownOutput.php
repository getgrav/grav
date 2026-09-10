<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Markdown;

use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Security;
use Grav\Common\Uri;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use function count;
use function is_array;
use function is_string;
use function strlen;

/**
 * Renders a page as Markdown for AI agents and other text clients.
 *
 * Any routable page can be fetched as `<route>.md`, or with an
 * `Accept: text/markdown` request header. The output is the page's rendered
 * HTML converted back to Markdown rather than the raw source file, so
 * shortcodes, content Twig, resolved image paths, page-relative links and
 * modular assembly all come out the way a browser would see them.
 *
 * The document has three parts, each switchable in `system.pages.markdown_output`:
 *
 *   1. A YAML frontmatter block: title, url, date, description, taxonomy
 *   2. The body: `# Title`, the page content, then each module of a modular
 *      page under its own `## Title`
 *   3. A navigation section linking the parent, neighbouring and child pages
 *      by their own `.md` URLs, so an agent can walk the site without ever
 *      leaving Markdown
 *
 * Conversion is the only expensive step (a DOM parse of the rendered HTML),
 * so its result is cached per page under the same rules `Page::content()`
 * uses: a page whose content Twig runs on every request is never cached.
 *
 * @package Grav\Common\Page\Markdown
 */
class MarkdownOutput
{
    public const FORMAT = 'md';
    public const MIME = 'text/markdown';

    /** @var Grav */
    protected $grav;

    /** @var HtmlConverter|null */
    protected $converter;

    /**
     * @param Grav|null $grav
     */
    public function __construct(?Grav $grav = null)
    {
        $this->grav = $grav ?? Grav::instance();
    }

    /**
     * Whether Markdown output is switched on for this site.
     *
     * @return bool
     */
    public static function enabled(): bool
    {
        $grav = Grav::instance();
        if (!isset($grav['config'])) {
            return false;
        }

        return (bool)$grav['config']->get('system.pages.markdown_output.enabled', false);
    }

    /**
     * Whether the `X-Markdown-Tokens` response header is switched on.
     *
     * @return bool
     */
    public static function tokenHeaderEnabled(): bool
    {
        return static::enabled() && (bool)Grav::instance()['config']->get('system.pages.markdown_output.token_header', true);
    }

    /**
     * Rough token count for a piece of text, the same estimate Cloudflare's
     * `x-markdown-tokens` header gives: roughly four characters per token.
     *
     * @param string $text
     * @return int
     */
    public static function estimateTokens(string $text): int
    {
        return (int)ceil(strlen($text) / 4);
    }

    /**
     * The full Markdown document for a page.
     *
     * @param PageInterface|null $page
     * @return string
     */
    public function render(?PageInterface $page = null): string
    {
        $page = $page ?? $this->grav['page'];

        $parts = [];
        if ($this->option('frontmatter', true)) {
            $parts[] = $this->frontmatter($page);
        }
        $parts[] = $this->body($page);
        if ($this->option('links', true)) {
            $links = $this->links($page);
            if ($links !== '') {
                $parts[] = $links;
            }
        }

        return implode("\n\n", array_filter($parts, static fn($part) => $part !== '')) . "\n";
    }

    /**
     * The YAML frontmatter block for a page.
     *
     * @param PageInterface|null $page
     * @return string
     */
    public function frontmatter(?PageInterface $page = null): string
    {
        $page = $page ?? $this->grav['page'];

        /** @var Language $language */
        $language = $this->grav['language'];

        $data = [
            'title' => $page->title(),
            'url' => $page->canonical(),
            'markdown' => $this->url($page),
        ];

        if ($language->enabled()) {
            $data['lang'] = $page->language() ?: $language->getActive() ?: $language->getDefault();
        }

        $date = $page->date();
        if ($date) {
            $data['date'] = date('Y-m-d', $date);
        }

        $description = $this->description($page);
        if ($description !== '') {
            $data['description'] = $description;
        }

        $taxonomy = $page->taxonomy();
        if (is_array($taxonomy) && $taxonomy) {
            $data['taxonomy'] = $taxonomy;
        }

        return "---\n" . Yaml::dump($data, 4, 2) . "---";
    }

    /**
     * The Markdown body of a page: its title, its content and, for a modular
     * page, every module in the order the page lists them.
     *
     * @param PageInterface|null $page
     * @return string
     */
    public function body(?PageInterface $page = null): string
    {
        $page = $page ?? $this->grav['page'];

        $parts = [];
        $content = $this->convertPage($page);

        $title = trim((string)$page->title());
        if ($title !== '' && !preg_match('/^# /', $content)) {
            $parts[] = '# ' . $title;
        }
        if ($content !== '') {
            $parts[] = $content;
        }

        foreach ($this->modules($page) as $module) {
            $moduleContent = $this->convertPage($module);
            if ($moduleContent === '') {
                continue;
            }

            // A module that opens with its own H1 or H2 has already named itself.
            $moduleTitle = trim((string)$module->title());
            if ($moduleTitle !== '' && !preg_match('/^#{1,2} /', $moduleContent)) {
                $moduleContent = '## ' . $moduleTitle . "\n\n" . $moduleContent;
            }

            $parts[] = $moduleContent;
        }

        return implode("\n\n", $parts);
    }

    /**
     * The navigation section: parent, previous and next siblings, children.
     * Every link points at the page's `.md` URL.
     *
     * @param PageInterface|null $page
     * @return string
     */
    public function links(?PageInterface $page = null): string
    {
        $page = $page ?? $this->grav['page'];

        $lines = [];

        $parent = $page->parent();
        if ($parent instanceof PageInterface && $parent->routable() && !$parent->root() && $parent->route() !== $page->route()) {
            $lines[] = '- Parent: ' . $this->link($parent);
        }

        $prev = $this->sibling($page, -1);
        if ($prev) {
            $lines[] = '- Previous: ' . $this->link($prev);
        }

        $next = $this->sibling($page, 1);
        if ($next) {
            $lines[] = '- Next: ' . $this->link($next);
        }

        $children = $page->children();
        if ($children instanceof Collection) {
            $children = $children->pages()->published()->routable();
            $max = (int)$this->option('max_links', 100);
            $total = count($children);
            if ($total > 0) {
                $lines[] = '- Children:';
                $shown = 0;
                foreach ($children as $child) {
                    if ($max > 0 && $shown >= $max) {
                        $lines[] = '  - ' . ($total - $shown) . ' more pages not listed';
                        break;
                    }
                    $lines[] = '  - ' . $this->link($child);
                    $shown++;
                }
            }
        }

        if (!$lines) {
            return '';
        }

        return "---\n\n## Navigation\n\n" . implode("\n", $lines);
    }

    /**
     * The absolute `.md` URL of a page.
     *
     * The home page is linked by its real route (`/home.md` rather than
     * `/.md`), because a path segment starting with a dot is refused by every
     * web server config Grav ships.
     *
     * @param PageInterface|null $page
     * @return string
     */
    public function url(?PageInterface $page = null): string
    {
        $page = $page ?? $this->grav['page'];

        $home = $page->route() === '/' || $page->home();
        $url = $home ? $page->url(true, false, true, true) : $page->url(true);

        // A page with no route of its own (a plugin's stand-in page at the
        // site root) has nothing to append the extension to.
        $path = (string)parse_url($url, PHP_URL_PATH);
        if (trim($path, '/') === '') {
            return $url;
        }

        return rtrim($url, '/') . '.' . self::FORMAT;
    }

    /**
     * Convert a fragment of rendered HTML to Markdown.
     *
     * @param string $html
     * @return string
     */
    public function convert(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Inline SVG (icons, heading anchors) has no Markdown form, and an
        // anchor left empty by that removal would come out as `[](#id)`.
        $html = preg_replace('/<svg\b[^>]*>.*?<\/svg>/is', '', $html) ?? $html;
        $html = preg_replace('/<a\b[^>]*>(?:\s|&nbsp;)*<\/a>/i', '', $html) ?? $html;

        if ($this->option('absolute_urls', true)) {
            $html = $this->absoluteUrls($html);
        }

        try {
            $markdown = $this->converter()->convert($html);
        } catch (Throwable $e) {
            $this->grav['log']->warning('Markdown output: could not convert HTML: ' . $e->getMessage());

            return trim(strip_tags($html));
        }

        // Lines holding only whitespace, and runs of blank lines, say nothing a
        // single blank line does not. Outside fenced code, a heading that
        // picked up a stray leading space from the HTML loses it.
        $markdown = preg_replace('/^[ \t]+$/m', '', $markdown) ?? $markdown;
        $markdown = $this->outsideFences($markdown, static fn(string $text): string => preg_replace('/^ {1,3}(?=#{1,6} )/m', '', $text) ?? $text);
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;

        return trim($markdown);
    }

    /**
     * A Markdown link to a page: `[Title](https://site/route.md)`.
     *
     * @param PageInterface $page
     * @return string
     */
    protected function link(PageInterface $page): string
    {
        $title = trim((string)$page->title());
        $title = str_replace(['[', ']'], ['\\[', '\\]'], $title);

        return '[' . ($title !== '' ? $title : $page->slug()) . '](' . $this->url($page) . ')';
    }

    /**
     * The neighbouring page in reading order: the one listed just before or
     * just after this page in the parent's own collection when the parent
     * defines one (a blog's posts in blog order), else in the parent's child
     * list.
     *
     * "Previous" here is the entry above this page in that listing and
     * "Next" the one below it, which is how a reader scanning the parent
     * page sees them. Page::prevSibling() answers the opposite way round.
     *
     * @param PageInterface $page
     * @param int $direction -1 for previous, 1 for next
     * @return PageInterface|null
     */
    protected function sibling(PageInterface $page, int $direction): ?PageInterface
    {
        $parent = $page->parent();
        if (!$parent instanceof PageInterface) {
            return null;
        }

        $path = $page->path();
        $ordered = null;

        try {
            $collection = $parent->collection('content', false);
            if ($collection instanceof Collection && $collection->offsetExists($path)) {
                $ordered = $collection;
            }
        } catch (Throwable $e) {
            $ordered = null;
        }

        if ($ordered === null) {
            $children = $parent->children();
            if (!$children instanceof Collection) {
                return null;
            }
            $ordered = $children->pages()->published()->routable();
        }

        $paths = array_keys($ordered->toArray());
        $index = array_search($path, $paths, true);
        if ($index === false || !isset($paths[$index + $direction])) {
            return null;
        }

        $sibling = $ordered->offsetGet($paths[$index + $direction]);
        if (!$sibling instanceof PageInterface || !$sibling->routable() || $sibling->route() === $page->route()) {
            return null;
        }

        return $sibling;
    }

    /**
     * A page's description: its metadata description if it has one, else its
     * summary as plain text.
     *
     * @param PageInterface $page
     * @return string
     */
    protected function description(PageInterface $page): string
    {
        $header = $page->header();
        $description = $header->metadata['description'] ?? null;
        if (is_string($description) && trim($description) !== '') {
            return trim($description);
        }

        $summary = (string)$page->summary(300, true);
        $summary = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5)) ?? '');

        return $summary;
    }

    /**
     * The modules a modular page lists, in the page's own order.
     *
     * @param PageInterface $page
     * @return PageInterface[]
     */
    protected function modules(PageInterface $page): array
    {
        if ($page->isModule()) {
            return [];
        }

        try {
            $collection = $page->collection('content', false);
        } catch (Throwable $e) {
            return [];
        }

        $modules = [];
        foreach ($collection as $item) {
            if ($item instanceof PageInterface && $item->isModule()) {
                $modules[] = $item;
            }
        }

        return $modules;
    }

    /**
     * Convert a page's rendered content, from cache when the page allows it.
     *
     * @param PageInterface $page
     * @return string
     */
    protected function convertPage(PageInterface $page): string
    {
        $cacheable = $this->cacheable($page);
        $cache_id = null;

        if ($cacheable) {
            /** @var Config $config */
            $config = $this->grav['config'];

            $cache_id = md5(implode(':', [
                'markdown-output',
                GRAV_VERSION,
                $page->id(),
                (string)$page->modified(),
                (string)$config->checksum(),
                json_encode($config->get('system.pages.markdown_output')),
            ]));

            /** @var Cache $cache */
            $cache = $this->grav['cache'];
            $cached = $cache->fetch($cache_id);
            if (is_string($cached)) {
                return $cached;
            }
        }

        $markdown = $this->convert((string)$page->content());

        if ($cache_id !== null) {
            $this->grav['cache']->save($cache_id, $markdown);
        }

        return $markdown;
    }

    /**
     * Mirror of the rules `Page::content()` applies before it caches: the
     * site cache must be on, the page must not opt out, and content Twig
     * that runs per request keeps its output out of the cache.
     *
     * @param PageInterface $page
     * @return bool
     */
    protected function cacheable(PageInterface $page): bool
    {
        /** @var Config $config */
        $config = $this->grav['config'];
        $header = $page->header();

        $cache_enable = $header->cache_enable ?? $config->get('system.cache.enabled', true);
        if (!$cache_enable) {
            return false;
        }

        $never_cache_twig = $header->never_cache_twig ?? $config->get('system.pages.never_cache_twig', false);
        if ($never_cache_twig) {
            return false;
        }

        if (Security::willProcessContentTwig($page) && !$page->isModule()) {
            return false;
        }

        return true;
    }

    /**
     * Turn root-relative `href` and `src` attributes into absolute URLs so an
     * agent can follow them from wherever it stored the document.
     *
     * @param string $html
     * @return string
     */
    protected function absoluteUrls(string $html): string
    {
        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $base = rtrim((string)$uri->base(), '/');
        if ($base === '') {
            return $html;
        }

        return preg_replace('/\b(href|src)=(["\'])\/(?!\/)/i', '$1=$2' . $base . '/', $html) ?? $html;
    }

    /**
     * Apply a text transform to everything except fenced code blocks, whose
     * whitespace is content.
     *
     * @param string $markdown
     * @param callable $transform
     * @return string
     */
    protected function outsideFences(string $markdown, callable $transform): string
    {
        $chunks = preg_split('/(^[ \t]*(?:```|~~~)[^\n]*\n.*?^[ \t]*(?:```|~~~)[ \t]*$)/ms', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($chunks)) {
            return $transform($markdown);
        }

        foreach ($chunks as $i => $chunk) {
            // Odd offsets are the captured fences.
            if ($i % 2 === 0) {
                $chunks[$i] = $transform($chunk);
            }
        }

        return implode('', $chunks);
    }

    /**
     * @return HtmlConverter
     */
    protected function converter(): HtmlConverter
    {
        if ($this->converter === null) {
            $this->converter = new HtmlConverter([
                'header_style' => 'atx',
                'strip_tags' => true,
                'hard_break' => true,
                'suppress_errors' => true,
                'preserve_comments' => false,
                'strip_placeholder_links' => true,
                'remove_nodes' => 'script style noscript template form button input select textarea iframe svg canvas video audio object embed',
            ]);
            $this->converter->getEnvironment()->addConverter(new TableConverter());
            $this->converter->getEnvironment()->addConverter(new PlainTextConverter());
        }

        return $this->converter;
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function option(string $name, $default = null)
    {
        return $this->grav['config']->get('system.pages.markdown_output.' . $name, $default);
    }
}
