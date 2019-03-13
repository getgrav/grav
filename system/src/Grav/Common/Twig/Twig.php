<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Grav\Common\Grav;
use Grav\Common\Config\Config;
use Grav\Common\Language\Language;
use Grav\Common\Language\LanguageCodes;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\Event\Event;
use Phive\Twig\Extensions\Deferred\DeferredExtension;

class Twig
{
    /**
     * @var \Twig_Environment
     */
    public $twig;

    /**
     * @var array
     */
    public $twig_vars = [];

    /**
     * @var array
     */
    public $twig_paths;

    /**
     * @var string
     */
    public $template;

    /**
     * @var Grav
     */
    protected $grav;

    /**
     * @var \Twig_Loader_Filesystem
     */
    protected $loader;

    /**
     * @var \Twig_Loader_Array
     */
    protected $loaderArray;


    protected $autoescape;

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
                $lang_templates = $locator->findResource('theme://templates/' . ($active_language ? $active_language : $language->getDefault()));
                if ($lang_templates) {
                    $this->twig_paths[] = $lang_templates;
                }
            }

            $this->twig_paths = array_merge($this->twig_paths, $locator->findResources('theme://templates'));

            $this->grav->fireEvent('onTwigTemplatePaths');

            // Add Grav core templates location
            $core_templates = array_merge($locator->findResources('system://templates'), $locator->findResources('system://templates/testing'));
            $this->twig_paths = array_merge($this->twig_paths, $core_templates);

            $this->loader = new \Twig_Loader_Filesystem($this->twig_paths);

            // Register all other prefixes as namespaces in twig
            foreach ($locator->getPaths('theme') as $prefix => $_) {
                if ($prefix === '') {
                    continue;
                }

                $twig_paths = [];

                // handle language templates if available
                if ($language->enabled()) {
                    $lang_templates = $locator->findResource('theme://'.$prefix.'templates/' . ($active_language ? $active_language : $language->getDefault()));
                    if ($lang_templates) {
                        $twig_paths[] = $lang_templates;
                    }
                }

                $twig_paths = array_merge($twig_paths, $locator->findResources('theme://'.$prefix.'templates'));

                $namespace = trim($prefix, '/');
                $this->loader->setPaths($twig_paths, $namespace);
            }

            $this->grav->fireEvent('onTwigLoader');

            $this->loaderArray = new \Twig_Loader_Array([]);
            $loader_chain = new \Twig_Loader_Chain([$this->loaderArray, $this->loader]);

            $params = $config->get('system.twig');
            if (!empty($params['cache'])) {
                $cachePath = $locator->findResource('cache://twig', true, true);
                $params['cache'] = new \Twig_Cache_Filesystem($cachePath, \Twig_Cache_Filesystem::FORCE_BYTECODE_INVALIDATION);
            }

            if (!$config->get('system.strict_mode.twig_compat', true)) {
                // Force autoescape on for all files if in strict mode.
                $params['autoescape'] = 'html';
            } elseif (!empty($this->autoescape)) {
                $params['autoescape'] = $this->autoescape ? 'html' : false;
            }

            if (empty($params['autoescape'])) {
                user_error('Grav 2.0 will have Twig auto-escaping forced on (can be emulated by turning off \'system.strict_mode.twig_compat\' setting in your configuration)', E_USER_DEPRECATED);
            }

            $this->twig = new TwigEnvironment($loader_chain, $params);

            if ($config->get('system.twig.undefined_functions')) {
                $this->twig->registerUndefinedFunctionCallback(function ($name) {
                    if (function_exists($name)) {
                        return new \Twig_SimpleFunction($name, $name);
                    }

                    return new \Twig_SimpleFunction($name, function () {
                    });
                });
            }

            if ($config->get('system.twig.undefined_filters')) {
                $this->twig->registerUndefinedFilterCallback(function ($name) {
                    if (function_exists($name)) {
                        return new \Twig_SimpleFilter($name, $name);
                    }

                    return new \Twig_SimpleFilter($name, function () {
                    });
                });
            }

            $this->grav->fireEvent('onTwigInitialized');

            // set default date format if set in config
            if ($config->get('system.pages.dateformat.long')) {
                /** @var \Twig_Extension_Core $extension */
                $extension = $this->twig->getExtension('Twig_Extension_Core');
                $extension->setDateFormat($config->get('system.pages.dateformat.long'));
            }
            // enable the debug extension if required
            if ($config->get('system.twig.debug')) {
                $this->twig->addExtension(new \Twig_Extension_Debug());
            }
            $this->twig->addExtension(new TwigExtension());
            $this->twig->addExtension(new DeferredExtension());

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
                    'base_dir'          => rtrim(ROOT_DIR, '/'),
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
     * @return \Twig_Environment
     */
    public function twig()
    {
        return $this->twig;
    }

    /**
     * @return \Twig_Loader_Filesystem
     */
    public function loader()
    {
        return $this->loader;
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
     * @param  string $content Optional content override
     *
     * @return string          The rendered output
     * @throws \Twig_Error_Loader
     */
    public function processPage(PageInterface $item, $content = null)
    {
        $content = $content ?? $item->content();

        // override the twig header vars for local resolution
        $this->grav->fireEvent('onTwigPageVariables', new Event(['page' => $item]));
        $twig_vars = $this->twig_vars;

        $twig_vars['page'] = $item;
        $twig_vars['media'] = $item->media();
        $twig_vars['header'] = $item->header();

        $local_twig = clone $this->twig;

        $output = '';
        try {
            // Process Modular Twig
            if ($item->modularTwig()) {
                $twig_vars['content'] = $content;
                $extension = $item->templateFormat();
                $extension = $extension ? ".{$extension}.twig" : TEMPLATE_EXT;
                $template = $item->template() . $extension;
                $output = $content = $local_twig->render($template, $twig_vars);
            }

            // Process in-page Twig
            if ($item->shouldProcess('twig')) {
                $name = '@Page:' . $item->path();
                $this->setTemplate($name, $content);
                $output = $local_twig->render($name, $twig_vars);
            }

        } catch (\Twig_Error_Loader $e) {
            throw new \RuntimeException($e->getRawMessage(), 404, $e);
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
        } catch (\Twig_Error_Loader $e) {
            throw new \RuntimeException($e->getRawMessage(), 404, $e);
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

        $name = '@Var:' . $string;
        $this->setTemplate($name, $string);

        try {
            $output = $this->twig->render($name, $vars);
        } catch (\Twig_Error_Loader $e) {
            throw new \RuntimeException($e->getRawMessage(), 404, $e);
        }

        return $output;
    }

    /**
     * Twig process that renders the site layout. This is the main twig process that renders the overall
     * page and handles all the layout for the site display.
     *
     * @param string $format Output format (defaults to HTML).
     *
     * @return string the rendered output
     * @throws \RuntimeException
     */
    public function processSite($format = null, array $vars = [])
    {
        // set the page now its been processed
        $this->grav->fireEvent('onTwigSiteVariables');
        $pages = $this->grav['pages'];
        $page = $this->grav['page'];
        $content = $page->content();

        $twig_vars = $this->twig_vars;

        $twig_vars['theme'] = $this->grav['config']->get('theme');
        $twig_vars['pages'] = $pages->root();
        $twig_vars['page'] = $page;
        $twig_vars['header'] = $page->header();
        $twig_vars['media'] = $page->media();
        $twig_vars['content'] = $content;
        $ext = '.' . ($format ?: 'html') . TWIG_EXT;

        // determine if params are set, if so disable twig cache
        $params = $this->grav['uri']->params(null, true);
        if (!empty($params)) {
            $this->twig->setCache(false);
        }

        // Get Twig template layout
        $template = $this->template($page->template() . $ext);

        try {
            $output = $this->twig->render($template, $vars + $twig_vars);
        } catch (\Twig_Error_Loader $e) {
            $error_msg = $e->getMessage();
            // Try html version of this template if initial template was NOT html
            if ($ext !== '.html' . TWIG_EXT) {
                try {
                    $page->templateFormat('html');
                    $output = $this->twig->render($page->template() . '.html' . TWIG_EXT, $vars + $twig_vars);
                } catch (\Twig_Error_Loader $e) {
                    throw new \RuntimeException($error_msg, 400, $e);
                }
            } else {
                throw new \RuntimeException($error_msg, 400, $e);
            }
        }

        return $output;
    }

    /**
     * Wraps the Twig_Loader_Filesystem addPath method (should be used only in `onTwigLoader()` event
     * @param string $template_path
     * @param string $namespace
     */
    public function addPath($template_path, $namespace = '__main__')
    {
        $this->loader->addPath($template_path, $namespace);
    }

    /**
     * Wraps the Twig_Loader_Filesystem prependPath method (should be used only in `onTwigLoader()` event
     * @param string $template_path
     * @param string $namespace
     */
    public function prependPath($template_path, $namespace = '__main__')
    {
        $this->loader->prependPath($template_path, $namespace);
    }

    /**
     * Simple helper method to get the twig template if it has already been set, else return
     * the one being passed in
     *
     * @param  string $template the template name
     *
     * @return string           the template name
     */
    public function template($template)
    {
        return $this->template ?? $template;
    }

    /**
     * Overrides the autoescape setting
     *
     * @param bool $state
     * @deprecated 1.5 Auto-escape should always be turned on to protect against XSS issues (can be disabled per template file).
     */
    public function setAutoescape($state)
    {
        if (!$state) {
            user_error(__CLASS__ . '::' . __FUNCTION__ . '(false) is deprecated since Grav 1.5', E_USER_DEPRECATED);
        }

        $this->autoescape = (bool) $state;
    }
}
