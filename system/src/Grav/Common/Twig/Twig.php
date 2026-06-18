<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Config\Config;
use Grav\Common\Language\Language;
use Grav\Common\Language\LanguageCodes;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Security;
use Grav\Common\Twig\Sandbox\GravSourcePolicy;
use Grav\Common\Twig\Sandbox\SandboxConfig;
use Grav\Common\Twig\Compatibility\Twig3CompatibilityLoader;
use Grav\Common\Twig\Compatibility\Twig3CompatibilityTransformer;
use Grav\Common\Twig\Exception\TwigException;
use Grav\Common\Twig\Extension\FilesystemExtension;
use Grav\Common\Twig\Extension\GravExtension;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\Event\Event;
use RuntimeException;
use Twig\Cache\FilesystemCache;
use Twig\DeferredExtension\DeferredExtension;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\DebugExtension;
use Twig\Extension\EscaperExtension;
use Twig\Extension\SandboxExtension;
use Twig\Extension\StringLoaderExtension;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\ExistsLoaderInterface;
use Twig\Loader\FilesystemLoader;
use Twig\Profiler\Profile;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function function_exists;
use function in_array;
use function is_array;

// Twig3 compatibility
if (!class_exists('Twig_SimpleFunction')) {
    class_alias(\Twig\TwigFunction::class, 'Twig_SimpleFunction');
}
if (!class_exists('Twig_SimpleFilter')) {
    class_alias(\Twig\TwigFilter::class, 'Twig_SimpleFilter');
}
if (!class_exists('Twig_Extension')) {
    class_alias(\Twig\Extension\AbstractExtension::class, 'Twig_Extension');
}

/**
 * Class Twig
 * @package Grav\Common\Twig
 */
class Twig
{
    /** @var Environment */
    public $twig;
    /** @var array */
    public $twig_vars = [];
    /** @var array */
    public $twig_paths;
    /** @var string */
    public $template;

    /** @var array */
    public $plugins_hooked_nav = [];
    /** @var array */
    public $plugins_quick_tray = [];
    /** @var array */
    public $plugins_hooked_dashboard_widgets_top = [];
    /** @var array */
    public $plugins_hooked_dashboard_widgets_main = [];

    /** @var Grav */
    protected $grav;
    /** @var FilesystemLoader */
    protected $loader;
    /** @var ArrayLoader */
    protected $loaderArray;
    /** @var bool */
    protected $autoescape;
    /** @var Profile */
    protected $profile;
    /** @var string[]|null */
    protected ?array $sandboxDeniedConfigKeys = null;

    /**
     * Constructor
     *
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->twig_paths = [];
    }

    /**
     * Twig initialization that sets the twig loader chain, then the environment, then extensions
     * and also the base set of twig vars
     *
     * @return $this
     */
    public function init()
    {
        if (null === $this->twig) {
            /** @var Config $config */
            $config = $this->grav['config'];
            /** @var UniformResourceLocator $locator */
            $locator = $this->grav['locator'];
            /** @var Language $language */
            $language = $this->grav['language'];

            $active_language = $language->getActive();

            // handle language templates if available
            if ($language->enabled()) {
                $lang_templates = $locator->findResource('theme://templates/' . ($active_language ?: $language->getDefault()));
                if ($lang_templates) {
                    $this->twig_paths[] = $lang_templates;
                }
            }

            $this->twig_paths = array_merge($this->twig_paths, $locator->findResources('theme://templates'));

            $this->grav->fireEvent('onTwigTemplatePaths');

            // Add Grav core templates location
            $core_templates = array_merge($locator->findResources('system://templates'), $locator->findResources('system://templates/testing'));
            $this->twig_paths = array_merge($this->twig_paths, $core_templates);

            $this->loader = new FilesystemLoader($this->twig_paths);

            // Register all other prefixes as namespaces in twig
            foreach ($locator->getPaths('theme') as $prefix => $_) {
                if ($prefix === '') {
                    continue;
                }

                $twig_paths = [];

                // handle language templates if available
                if ($language->enabled()) {
                    $lang_templates = $locator->findResource('theme://'.$prefix.'templates/' . ($active_language ?: $language->getDefault()));
                    if ($lang_templates) {
                        $twig_paths[] = $lang_templates;
                    }
                }

                $twig_paths = array_merge($twig_paths, $locator->findResources('theme://'.$prefix.'templates'));

                $namespace = trim((string) $prefix, '/');
                $this->loader->setPaths($twig_paths, $namespace);
            }

            $this->grav->fireEvent('onTwigLoader');

            $this->loaderArray = new ArrayLoader([]);
            $loader_chain = new ChainLoader([$this->loaderArray, $this->loader]);

            $activeLoader = $loader_chain;
            if ($config->get('system.strict_mode.twig3_compat', false)) {
                $transformer = new Twig3CompatibilityTransformer();
                $activeLoader = new Twig3CompatibilityLoader($loader_chain, $transformer);
            }

            $params = $config->get('system.twig');
            if (!empty($params['cache'])) {
                $cachePath = $locator->findResource('cache://twig', true, true);
                $params['cache'] = new FilesystemCache($cachePath, FilesystemCache::FORCE_BYTECODE_INVALIDATION);
            }

            if (!$config->get('system.strict_mode.twig2_compat', false)) {
                // Force autoescape on for all files if in strict mode.
                $params['autoescape'] = 'html';
            } elseif (!empty($this->autoescape)) {
                $params['autoescape'] = $this->autoescape ? 'html' : false;
            }

            if (empty($params['autoescape'])) {
                user_error('Grav 2.0 will have Twig auto-escaping forced on (can be emulated by turning off \'system.strict_mode.twig2_compat\' setting in your configuration)', E_USER_DEPRECATED);
            }

            $this->twig = new TwigEnvironment($activeLoader, $params);

            // Calling a PHP function by name from a template is opt-in only.
            // Grav 2.0 dropped the old `undefined_functions` / `undefined_filters`
            // auto-allow (which exposed every non-blocklisted PHP function by
            // default). What remains is the explicit `safe_functions` /
            // `safe_filters` allow-list, empty by default, and it is now gated by
            // `Utils::isDangerousFunction()` so command/code-execution functions
            // (`system`, `exec`, `assert`, ...) can never be enabled this way.
            // This closes the precedence flaw where a `safe_functions` entry was
            // honored ahead of the blocklist (GHSA-9wg2-prc3-vx89). An unknown
            // function/filter that is not allow-listed is a hard error. These
            // allow-lists only affect trusted (unsandboxed) templates; editor
            // page content answers to the Twig content sandbox in security.yaml.
            $this->twig->registerUndefinedFunctionCallback(function (string $name) use ($config) {
                $allowed = $config->get('system.twig.safe_functions');
                if (is_array($allowed) && in_array($name, $allowed, true)
                    && function_exists($name) && !Utils::isDangerousFunction($name)) {
                    return new TwigFunction($name, $name);
                }

                // A name that maps to no PHP function carries no code-execution
                // risk. Treat it as a soft no-op (rc.5 behavior) so a plugin or
                // theme function that simply isn't registered in this context —
                // e.g. a plugin that deactivates in admin while a template still
                // references its function — renders as empty instead of throwing
                // a hard "Unknown function" error. A real PHP function that is
                // not allow-listed still falls through to a hard error below.
                if (!function_exists($name)) {
                    return new TwigFunction($name, static fn() => null);
                }

                return false;
            });

            $this->twig->registerUndefinedFilterCallback(function (string $name) use ($config) {
                $allowed = $config->get('system.twig.safe_filters');
                if (is_array($allowed) && in_array($name, $allowed, true)
                    && function_exists($name) && !Utils::isDangerousFunction($name)) {
                    return new TwigFilter($name, $name);
                }

                // See the function callback above: an undefined name that is not
                // a real PHP function is a harmless no-op rather than a fatal
                // error. A real PHP function not on the allow-list still errors.
                if (!function_exists($name)) {
                    return new TwigFilter($name, static fn($value = null) => $value);
                }

                return false;
            });

            $this->grav->fireEvent('onTwigInitialized');

            // set default date format if set in config
            if ($config->get('system.pages.dateformat.long')) {
                /** @var CoreExtension $extension */
                $extension = $this->twig->getExtension(CoreExtension::class);
                $extension->setDateFormat($config->get('system.pages.dateformat.long'));
            }
            // enable the debug extension if required
            if ($config->get('system.twig.debug')) {
                $this->twig->addExtension(new DebugExtension());
            }
            $this->twig->addExtension(new GravExtension());
            $this->twig->addExtension(new FilesystemExtension());
            $this->twig->addExtension(new DeferredExtension());
            $this->twig->addExtension(new StringLoaderExtension());

            // Content sandbox — a SourcePolicy selects per-template whether to
            // enforce. Only editor-authored string templates (@Page: / @Var:)
            // are sandboxed; theme files on disk are always trusted. This means
            // we don't need to toggle the sandbox around specific render calls,
            // and {% include %}ing a theme partial from editor content is safe.
            if ($config->get('security.twig_sandbox.enabled', true)) {
                $this->twig->addExtension(new SandboxExtension(
                    Security::buildTwigSandboxPolicy(),
                    false,
                    new GravSourcePolicy()
                ));
            }

            /** @var Debugger $debugger */
            $debugger = $this->grav['debugger'];
            $debugger->addTwigProfiler($this->twig);

            $this->grav->fireEvent('onTwigExtensions');

            /** @var Pages $pages */
            $pages = $this->grav['pages'];

            // Set some standard variables for twig
            $this->twig_vars += [
                    'config'            => $config,
                    'system'            => $config->get('system'),
                    'theme'             => $config->get('theme'),
                    'site'              => $config->get('site'),
                    'uri'               => $this->grav['uri'],
                    'assets'            => $this->grav['assets'],
                    'taxonomy'          => $this->grav['taxonomy'],
                    'browser'           => $this->grav['browser'],
                    'base_dir'          => GRAV_ROOT,
                    'home_url'          => $pages->homeUrl($active_language),
                    'base_url'          => $pages->baseUrl($active_language),
                    'base_url_absolute' => $pages->baseUrl($active_language, true),
                    'base_url_relative' => $pages->baseUrl($active_language, false),
                    'base_url_simple'   => $this->grav['base_url'],
                    'theme_dir'         => $locator->findResource('theme://'),
                    'theme_url'         => $this->grav['base_url'] . '/' . $locator->findResource('theme://', false),
                    'html_lang'         => $this->grav['language']->getActive() ?: $config->get('site.default_lang', 'en'),
                    'language_codes'    => new LanguageCodes,
                ];
        }

        return $this;
    }

    /**
     * @return Environment
     */
    public function twig()
    {
        return $this->twig;
    }

    /**
     * @return FilesystemLoader
     */
    public function loader()
    {
        return $this->loader;
    }

    /**
     * @return Profile
     */
    public function profile()
    {
        return $this->profile;
    }


    /**
     * Adds or overrides a template.
     *
     * @param string $name     The template name
     * @param string $template The template source
     */
    public function setTemplate($name, $template)
    {
        $this->loaderArray->setTemplate($name, $template);
    }

    /**
     * Twig process that renders a page item. It supports two variations:
     * 1) Handles modular pages by rendering a specific page based on its modular twig template
     * 2) Renders individual page items for twig processing before the site rendering
     *
     * @param  PageInterface   $item    The page item to render
     * @param  string|null $content Optional content override
     *
     * @return string          The rendered output
     */
    public function processPage(PageInterface $item, $content = null)
    {
        $content ??= $item->content();
        $filtered = false;

        // override the twig header vars for local resolution
        $this->grav->fireEvent('onTwigPageVariables', new Event(['page' => $item]));
        $twig_vars = $this->twig_vars;

        $twig_vars['page'] = $item;
        $twig_vars['media'] = $item->media();
        $twig_vars['header'] = $item->header();
        $local_twig = clone $this->twig;

        $output = '';

        try {
            // In-page Twig runs FIRST and in isolation. The editor-authored
            // body becomes an @Page: string template that the SourcePolicy
            // sandboxes (it can still {% include %} trusted theme partials).
            // Doing this before the modular template render keeps the two
            // concerns cleanly separated: content is always sandboxed, the
            // template never is. The body is resolved to plain HTML up front,
            // so the trusted template below only ever receives a finished
            // string — its own Twig is never re-parsed under the sandbox — and
            // the @Page: source stays stable (the raw body) rather than
            // changing with every template/media tweak.
            //
            // Modular children process their body Twig unconditionally
            // (isModule()), independent of the security.twig_content gate —
            // matching the $process_twig decision in Page::content() and
            // FlexPages::processContent(), both of which OR in modularTwig().
            // Without this, a module whose header doesn't explicitly set
            // process.twig (e.g. when system.pages.process.twig defaults to
            // false) would have its content handed to the modular template
            // raw, leaving {% include %}/{{ }} tags as literal text even
            // though the caller already decided to process them.
            if ($item->shouldProcess('twig') || $item->isModule()) {
                $name = '@Page:' . $item->path();
                $this->setTemplate($name, $content);
                // Replace `config` with a denied-path-filtered facade for the
                // sandboxed render so editors can't exfiltrate plugin secrets
                // via `config.toArray()` (GHSA-j274-39qw-32c9). The modular
                // theme render below is unsandboxed and keeps the raw Config.
                $sandbox_vars = $twig_vars;
                $sandbox_vars['config'] = $this->buildSandboxConfig();
                try {
                    $output = $content = $local_twig->render($name, $sandbox_vars);
                } catch (SecurityError $e) {
                    $this->logSandboxViolation($e);
                    // Soft-fail: fall back to the pre-render content so the
                    // page isn't blank. Any {{ }} / {% %} tags in it will
                    // appear as literal text — that's intentional so the site
                    // owner can see what was blocked.
                    $output = $content;
                    $filtered = true;
                }
            }

            // Modular theme template render (loaded from disk) — trusted, not
            // sandboxed by our SourcePolicy. The already-resolved content is
            // handed in as the `content` variable; Twig emits it as a string
            // and does not re-evaluate it.
            if ($item->isModule()) {
                $twig_vars['content'] = $content;
                $template = $this->getPageTwigTemplate($item);
                $output = $local_twig->render($template, $twig_vars);
            }

        } catch (LoaderError $e) {
            throw new RuntimeException($e->getRawMessage(), 400, $e);
        }

        if ($filtered) {
            $output = $this->appendSandboxAdminHint($output);
        }

        return $output;
    }

    /**
     * Process a Twig template directly by using a template name
     * and optional array of variables
     *
     * @param string $template template to render with
     * @param array  $vars     Optional variables
     *
     * @return string
     */
    public function processTemplate($template, $vars = [])
    {
        // override the twig header vars for local resolution
        $this->grav->fireEvent('onTwigTemplateVariables');
        $vars += $this->twig_vars;

        try {
            $output = $this->twig->render($template, $vars);
        } catch (LoaderError $e) {
            throw new RuntimeException($e->getRawMessage(), 404, $e);
        }

        return $output;
    }


    /**
     * Process a Twig template directly by using a Twig string
     * and optional array of variables
     *
     * @param string $string string to render.
     * @param array  $vars   Optional variables
     *
     * @return string
     */
    public function processString($string, array $vars = [])
    {
        // override the twig header vars for local resolution
        $this->grav->fireEvent('onTwigStringVariables');
        $vars += $this->twig_vars;

        // @Var: sources are always sandboxed (GravSourcePolicy). Replace
        // the inherited `config` with a denied-path-filtered facade so
        // editor-derivable strings can't exfiltrate plugin secrets via
        // `config.toArray()` (GHSA-j274-39qw-32c9). A caller-supplied
        // `config` is left alone — internal call sites that need a custom
        // value (e.g. tests) can still pass it through.
        if (($vars['config'] ?? null) === ($this->twig_vars['config'] ?? null)) {
            $vars['config'] = $this->buildSandboxConfig();
        }

        $filtered = false;

        $name = '@Var:' . $string;
        $this->setTemplate($name, $string);

        // SourcePolicy sandboxes @Var: sources automatically; no toggle needed.
        try {
            $output = $this->twig->render($name, $vars);
        } catch (LoaderError $e) {
            throw new RuntimeException($e->getRawMessage(), 404, $e);
        } catch (SecurityError $e) {
            $this->logSandboxViolation($e);
            // Soft-fail: return the original (unrendered) string.
            $output = $string;
            $filtered = true;
        }

        if ($filtered) {
            $output = $this->appendSandboxAdminHint($output);
        }

        return $output;
    }

    /**
     * Render a Twig string as TRUSTED (operator-authored) code — i.e. without
     * the content sandbox.
     *
     * Use this for strings that are part of a site's configuration rather than
     * its content: email subjects/bodies and other `process:`-style action
     * templates defined in page frontmatter or plugin/theme config. These are
     * authored by whoever can configure the site's server-side actions, the
     * same trust tier as a theme partial — not visitor-supplied content — so
     * they get the full Twig container, the real (unfiltered) `config`, and
     * are NOT subject to the `@Var:` source policy.
     *
     * DO NOT pass visitor-supplied or page-content strings here; those must go
     * through {@see processString()} so the sandbox applies. The trust call is
     * the caller's to make — every use site should be auditable by grepping for
     * this method.
     *
     * @param string $string string to render.
     * @param array  $vars   Optional variables
     *
     * @return string
     */
    public function processTemplateString($string, array $vars = [])
    {
        // override the twig header vars for local resolution
        $this->grav->fireEvent('onTwigStringVariables');
        $vars += $this->twig_vars;

        // Trusted render: register under a name the GravSourcePolicy does NOT
        // sandbox (anything not prefixed @Page:/@Var:). No SecurityError can be
        // raised, so there's nothing to soft-fail — a literal {% include %}
        // could only ever be returned raw when the sandbox blocks it, which is
        // exactly the case this method exists to avoid.
        $name = '@Template:' . $string;
        $this->setTemplate($name, $string);

        try {
            $output = $this->twig->render($name, $vars);
        } catch (LoaderError $e) {
            throw new RuntimeException($e->getRawMessage(), 404, $e);
        }

        return $output;
    }

    /**
     * Twig process that renders the site layout. This is the main twig process that renders the overall
     * page and handles all the layout for the site display.
     *
     * @param string|null $format Output format (defaults to HTML).
     * @param array $vars
     * @return string the rendered output
     * @throws RuntimeException
     */
    public function processSite($format = null, array $vars = [])
    {
        try {
            $grav = $this->grav;

            // set the page now it's been processed
            $grav->fireEvent('onTwigSiteVariables');

            /** @var Pages $pages */
            $pages = $grav['pages'];

            /** @var PageInterface $page */
            $page = $grav['page'];

            $content = $page->content();
            $filtered = false;

            $twig_vars = $this->twig_vars;
            $twig_vars['theme'] = $grav['config']->get('theme');
            $twig_vars['pages'] = $pages->root();
            $twig_vars['page'] = $page;
            $twig_vars['header'] = $page->header();
            $twig_vars['media'] = $page->media();
            $twig_vars['content'] = $content;

            // determine if params are set, if so disable twig cache
            $params = $grav['uri']->params(null, true);
            if (!empty($params)) {
                $this->twig->setCache(false);
            }

            // Get Twig template layout
            $template = $this->getPageTwigTemplate($page, $format);
            $page->templateFormat($format);

            $output = $this->twig->render($template, $vars + $twig_vars);
        } catch (LoaderError $e) {
            throw new RuntimeException($e->getMessage(), 400, $e);
        } catch (RuntimeError $e) {
            $prev = $e->getPrevious();
            if ($prev instanceof TwigException) {
                $code = $prev->getCode() ?: 500;
                // Fire onPageNotFound event.
                $event = new Event([
                    'page' => $page,
                    'code' => $code,
                    'message' => $prev->getMessage(),
                    'exception' => $prev,
                    'route' => $grav['route'],
                    'request' => $grav['request']
                ]);
                $event = $grav->fireEvent("onDisplayErrorPage.{$code}", $event);
                $newPage = $event['page'];
                if ($newPage && $newPage !== $page) {
                    unset($grav['page']);
                    $grav['page'] = $newPage;

                    return $this->processSite($newPage->templateFormat(), $vars);
                }
            }

            throw $e;
        }

        if ($filtered) {
            $output = $this->appendSandboxAdminHint($output);
        }

        return $output;
    }

    /**
     * Build the read-only Config facade that replaces the `config` Twig
     * variable inside sandboxed renders.
     *
     * Two modes, controlled by `security.twig_content.config_access`:
     *   - false (default) — the entire config tree is denied. Every read
     *     returns its supplied default; `toArray()` returns `[]`. This is
     *     the safest mode for editor-authored content.
     *   - true — only the prefixes listed in
     *     `security.twig_sandbox.config_denied_paths` are denied.
     *
     * Both lists are read on every call so admins can tighten the filter at
     * runtime without rebuilding the Twig environment.
     */
    private function buildSandboxConfig(): SandboxConfig
    {
        /** @var Config $config */
        $config = $this->grav['config'];

        if ((bool) $config->get('security.twig_content.config_access', false) === false) {
            // Deny every top-level subtree → `config` is effectively empty.
            $denied = $this->sandboxDeniedConfigKeys ??= array_keys($config->toArray());
        } else {
            $denied = (array) $config->get('security.twig_sandbox.config_denied_paths', []);
        }

        return new SandboxConfig($config, $denied);
    }

    /**
     * Log a sandbox SecurityError via the security log channel. Soft-fail output
     * is handled by the caller (fall back to raw content + admin hint comment).
     */
    private function logSandboxViolation(SecurityError $e): void
    {
        [$rule, $token, $class] = $this->describeSandboxViolation($e);
        Security::logTwigSandboxViolation($rule, $token, $class, $e->getMessage());
    }

    /**
     * @return array{0: string, 1: string, 2: string} [rule, token, classname]
     */
    private function describeSandboxViolation(SecurityError $e): array
    {
        if ($e instanceof SecurityNotAllowedTagError) {
            return ['tag', $e->getTagName(), ''];
        }
        if ($e instanceof SecurityNotAllowedFilterError) {
            return ['filter', $e->getFilterName(), ''];
        }
        if ($e instanceof SecurityNotAllowedFunctionError) {
            return ['function', $e->getFunctionName(), ''];
        }
        if ($e instanceof SecurityNotAllowedMethodError) {
            return ['method', $e->getMethodName(), $e->getClassName()];
        }
        if ($e instanceof SecurityNotAllowedPropertyError) {
            return ['property', $e->getPropertyName(), $e->getClassName()];
        }
        return ['security', '', ''];
    }

    /**
     * Append a one-line HTML comment to output when the Twig sandbox blocked
     * an expression and the current user is admin.super. Regular visitors see
     * nothing. Honors `security.twig_sandbox.admin_hint` (default: true).
     */
    private function appendSandboxAdminHint(string $output): string
    {
        $grav = $this->grav;

        /** @var Config $config */
        $config = $grav['config'];
        if (!$config->get('security.twig_sandbox.admin_hint', true)) {
            return $output;
        }

        if (!$grav->offsetExists('user')) {
            return $output;
        }
        $user = $grav['user'];
        if (!$user || !method_exists($user, 'authorize') || !$user->authorize('admin.super')) {
            return $output;
        }

        return $output . "\n<!-- Grav security: Twig sandbox blocked an expression. See logs/security.log for details. -->\n";
    }

    /**
     * Wraps the FilesystemLoader addPath method (should be used only in `onTwigLoader()` event
     * @param string $template_path
     * @param string $namespace
     * @throws LoaderError
     */
    public function addPath($template_path, $namespace = '__main__')
    {
        $this->loader->addPath($template_path, $namespace);
    }

    /**
     * Wraps the FilesystemLoader prependPath method (should be used only in `onTwigLoader()` event
     * @param string $template_path
     * @param string $namespace
     * @throws LoaderError
     */
    public function prependPath($template_path, $namespace = '__main__')
    {
        $this->loader->prependPath($template_path, $namespace);
    }

    /**
     * Simple helper method to get the twig template if it has already been set, else return
     * the one being passed in
     * NOTE: Modular pages that are injected should not use this pre-set template as it's usually set at the page level
     *
     * @param  string $template the template name
     * @return string           the template name
     */
    public function template(string $template): string
    {
        if (isset($this->template)) {
            $template = $this->template;
            unset($this->template);
        }
        
        return $template;
    }

    /**
     * @param PageInterface $page
     * @param string|null $format
     * @return string
     */
    public function getPageTwigTemplate($page, &$format = null)
    {
        $template = $page->template();
        $default = $page->isModule() ? 'modular/default' : 'default';
        $extension = $format ?: $page->templateFormat();
        $twig_extension = $extension ? '.'. $extension .TWIG_EXT : TEMPLATE_EXT;
        $template_file = $this->template($template . $twig_extension);

        // TODO: no longer needed in Twig 3.
        /** @var ExistsLoaderInterface $loader */
        $loader = $this->twig->getLoader();
        if ($loader->exists($template_file)) {
            // template.xxx.twig
            $page_template = $template_file;
        } elseif ($twig_extension !== TEMPLATE_EXT && $loader->exists($template . TEMPLATE_EXT)) {
            // template.html.twig
            $page_template = $template . TEMPLATE_EXT;
            $format = 'html';
        } elseif ($loader->exists($default . $twig_extension)) {
            // default.xxx.twig
            $page_template = $default . $twig_extension;
        } else {
            // default.html.twig
            $page_template = $default . TEMPLATE_EXT;
            $format = 'html';
        }

        return $page_template;

    }

    /**
     * Overrides the autoescape setting
     *
     * @param bool $state
     * @return void
     * @deprecated 1.5 Auto-escape should always be turned on to protect against XSS issues (can be disabled per template file).
     */
    public function setAutoescape($state)
    {
        if (!$state) {
            user_error(self::class . '::' . __FUNCTION__ . '(false) is deprecated since Grav 1.5', E_USER_DEPRECATED);
        }

        $this->autoescape = (bool) $state;
    }

    /**
     * Register a custom escaper strategy.
     *
     * This method handles the differences between Twig versions:
     * - Twig 1.x: Uses CoreExtension::setEscaper()
     * - Twig 2.x/3.x (< 3.9): Uses EscaperExtension::setEscaper()
     * - Twig 3.9+: Uses EscaperRuntime::setEscaper()
     *
     * @param string $strategy The escaper strategy name (e.g., 'yaml', 'json')
     * @param callable $callable The escaper callable: function($twig, $string, $charset)
     * @return void
     */
    public function setEscaper(string $strategy, callable $callable): void
    {
        // Twig 3.9+ moved setEscaper to EscaperRuntime
        if (class_exists(EscaperRuntime::class)) {
            $this->twig->getRuntime(EscaperRuntime::class)->setEscaper($strategy, $callable);
            return;
        }

        // Twig 2.x/3.x (before 3.9) uses EscaperExtension
        if (class_exists(EscaperExtension::class)) {
            $this->twig->getExtension(EscaperExtension::class)->setEscaper($strategy, $callable);
            return;
        }

        // Twig 1.x fallback (uses CoreExtension)
        $this->twig->getExtension(CoreExtension::class)->setEscaper($strategy, $callable);
    }

}
