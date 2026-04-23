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

            $this->twig->registerUndefinedFunctionCallback(function (string $name) use ($config) {
                $allowed = $config->get('system.twig.safe_functions');
                if (is_array($allowed) && in_array($name, $allowed, true) && function_exists($name)) {
                    return new TwigFunction($name, $name);
                }
                if ($config->get('system.twig.undefined_functions')) {
                    if (function_exists($name)) {
                        if (!Utils::isDangerousFunction($name)) {
                            user_error("PHP function {$name}() was used as Twig function. This is deprecated in Grav 1.7. Please add it to system configuration: `system.twig.safe_functions`", E_USER_DEPRECATED);

                            return new TwigFunction($name, $name);
                        }

                        /** @var Debugger $debugger */
                        $debugger = $this->grav['debugger'];
                        $debugger->addException(new RuntimeException("Blocked potentially dangerous PHP function {$name}() being used as Twig function. If you really want to use it, please add it to system configuration: `system.twig.safe_functions`"));
                    }

                    return new TwigFunction($name, static function () {});
                }

                return false;
            });

            $this->twig->registerUndefinedFilterCallback(function (string $name) use ($config) {
                $allowed = $config->get('system.twig.safe_filters');
                if (is_array($allowed) && in_array($name, $allowed, true) && function_exists($name)) {
                    return new TwigFilter($name, $name);
                }
                if ($config->get('system.twig.undefined_filters')) {
                    if (function_exists($name)) {
                        if (!Utils::isDangerousFunction($name)) {
                            user_error("PHP function {$name}() used as Twig filter. This is deprecated in Grav 1.7. Please add it to system configuration: `system.twig.safe_filters`", E_USER_DEPRECATED);

                            return new TwigFilter($name, $name);
                        }

                        /** @var Debugger $debugger */
                        $debugger = $this->grav['debugger'];
                        $debugger->addException(new RuntimeException("Blocked potentially dangerous PHP function {$name}() being used as Twig filter. If you really want to use it, please add it to system configuration: `system.twig.safe_filters`"));
                    }

                    return new TwigFilter($name, static function () {});
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
        [$content, $filtered] = Security::cleanDangerousTwigWithStatus($content);

        // override the twig header vars for local resolution
        $this->grav->fireEvent('onTwigPageVariables', new Event(['page' => $item]));
        $twig_vars = $this->twig_vars;

        $twig_vars['page'] = $item;
        $twig_vars['media'] = $item->media();
        $twig_vars['header'] = $item->header();
        $local_twig = clone $this->twig;

        $output = '';

        try {
            // Theme modular template render (loaded from disk) — trusted,
            // not sandboxed by our SourcePolicy. Editor content arrives as
            // a `content` string variable; Twig doesn't re-evaluate strings.
            if ($item->isModule()) {
                $twig_vars['content'] = $content;
                $template = $this->getPageTwigTemplate($item);
                $output = $content = $local_twig->render($template, $twig_vars);
            }

            // In-page Twig — `content` becomes an @Page: string template;
            // the SourcePolicy sandboxes that source but still lets it
            // {% include %} trusted theme partials.
            if ($item->shouldProcess('twig')) {
                $name = '@Page:' . $item->path();
                $this->setTemplate($name, $content);
                try {
                    $output = $local_twig->render($name, $twig_vars);
                } catch (SecurityError $e) {
                    $this->logSandboxViolation($e);
                    // Soft-fail: fall back to the pre-template content so the
                    // page isn't blank. Any {{ }} / {% %} tags in it will
                    // appear as literal text — that's intentional so the site
                    // owner can see what was blocked.
                    $output = $content;
                    $filtered = true;
                }
            }

        } catch (LoaderError $e) {
            throw new RuntimeException($e->getRawMessage(), 400, $e);
        }

        if ($filtered) {
            $output = $this->appendTwigFilterAdminHint($output);
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

        [$string, $filtered] = Security::cleanDangerousTwigWithStatus($string);

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
            $output = $this->appendTwigFilterAdminHint($output);
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

            [$content, $filtered] = Security::cleanDangerousTwigWithStatus($page->content());

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
            $output = $this->appendTwigFilterAdminHint($output);
        }

        return $output;
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
     * Append a one-line HTML comment to output when the dangerous-Twig filter fired
     * and the current user is admin.super. Regular visitors see nothing.
     * Honors system config `security.twig_filter.admin_hint` (default: true).
     */
    private function appendTwigFilterAdminHint(string $output): string
    {
        $grav = $this->grav;

        /** @var Config $config */
        $config = $grav['config'];
        if (!$config->get('security.twig_filter.admin_hint', true)) {
            return $output;
        }

        if (!$grav->offsetExists('user')) {
            return $output;
        }
        $user = $grav['user'];
        if (!$user || !method_exists($user, 'authorize') || !$user->authorize('admin.super')) {
            return $output;
        }

        return $output . "\n<!-- Grav security: Twig content was filtered. See logs/security.log for details. -->\n";
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
