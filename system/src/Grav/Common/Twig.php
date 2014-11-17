<?php
namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Twig object handles all the Twig template rendering for Grav. It's a singleton object
 * that is optimized so that it only needs to be initialized once and can be reused for individual
 * page template rendering as well as the main site template rendering.
 *
 * @author RocketTheme
 * @license MIT
 */
class Twig
{
    /**
     * @var \Twig_Environment
     */
    public $twig;

    /**
     * @var array
     */
    public $twig_vars;

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


    /**
     * Constructor
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    /**
     * Twig initialization that sets the twig loader chain, then the environment, then extensions
     * and also the base set of twig vars
     */
    public function init()
    {
        if (!isset($this->twig)) {
            /** @var Config $config */
            $config = $this->grav['config'];
            /** @var UniformResourceLocator $locator */
            $locator = $this->grav['locator'];
            $debugger = $this->grav['debugger'];

            $this->twig_paths = $locator->findResources('theme://templates');
            $this->grav->fireEvent('onTwigTemplatePaths');

            $this->loader = new \Twig_Loader_Filesystem($this->twig_paths);
            $this->loaderArray = new \Twig_Loader_Array(array());
            $loader_chain = new \Twig_Loader_Chain(array($this->loaderArray, $this->loader));

            $params = $config->get('system.twig');
            if (!empty($params['cache'])) {
                $params['cache'] = $locator->findResource('cache://twig', true, true);
            }

            $this->twig = new \Twig_Environment($loader_chain, $params);
            if ($debugger->enabled() && $config->get('system.debugger.twig')) {
                $this->twig = new \DebugBar\Bridge\Twig\TraceableTwigEnvironment($this->twig);
                $collector = new \DebugBar\Bridge\Twig\TwigCollector($this->twig);
                $debugger->addCollector($collector);
            }
            $this->grav->fireEvent('onTwigInitialized');

            // set default date format if set in config
            if ($config->get('system.pages.dateformat.long')) {
                $this->twig->getExtension('core')->setDateFormat($config->get('system.pages.dateformat.long'));
            }
            // enable the debug extension if required
            if ($config->get('system.twig.debug')) {
                $this->twig->addExtension(new \Twig_Extension_Debug());
            }
            $this->twig->addExtension(new TwigExtension());

            $this->grav->fireEvent('onTwigExtensions');

            $theme = $config->get('system.pages.theme');
            $themeUrl = $this->grav['base_url'] .'/'. USER_PATH . basename(THEMES_DIR) .'/'. $theme;

            // Set some standard variables for twig
            $this->twig_vars = array(
                'grav' => $this->grav,
                'config' => $config,
                'uri' => $this->grav['uri'],
                'base_dir' => rtrim(ROOT_DIR, '/'),
                'base_url' => $this->grav['base_url'],
                'base_url_absolute' => $this->grav['base_url_absolute'],
                'base_url_relative' => $this->grav['base_url_relative'],
                'theme_dir' => $locator->findResource('theme://'),
                'theme_url' => $themeUrl,
                'site' => $config->get('site'),
                'assets' => $this->grav['assets'],
                'taxonomy' => $this->grav['taxonomy'],
                'browser' => $this->grav['browser'],
            );
        }
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
     * @param  Page   $item    The page item to render
     * @param  string $content Optional content override
     * @return string          The rendered output
     * @throws \Twig_Error_Loader
     */
    public function processPage(Page $item, $content = null)
    {
        $content = $content !== null ? $content : $item->content();

        // override the twig header vars for local resolution
        $this->grav->fireEvent('onTwigPageVariables');
        $twig_vars = $this->twig_vars;

        $twig_vars['page'] = $item;
        $twig_vars['media'] = $item->media();
        $twig_vars['header'] = $item->header();

        $local_twig = clone($this->twig);

        // Get Twig template layout
        if ($item->modularTwig()) {
            $twig_vars['content'] = $content;
            $template = $item->template() . TEMPLATE_EXT;
            $output = $local_twig->render($template, $twig_vars);
        } else {
            $name = '@Page:' . $item->path();
            $this->setTemplate($name, $content);
            $output = $local_twig->render($name, $twig_vars);
        }

        return $output;
    }

    /**
     * @param string $string  string to render.
     * @param array $vars     Optional variables
     * @return string
     */
    public function processString($string, array $vars = array())
    {
        // override the twig header vars for local resolution
        $this->grav->fireEvent('onTwigStringVariables');
        $vars += $this->twig_vars;

        $name = '@Var:' . $string;
        $this->setTemplate($name, $string);
        $output = $this->twig->render($name, $vars);

        return $output;
    }

    /**
     * Twig process that renders the site layout. This is the main twig process that renders the overall
     * page and handles all the layout for the site display.
     *
     * @param string $format Output format (defaults to HTML).
     * @return string the rendered output
     * @throws \RuntimeException
     */
    public function processSite($format = null)
    {
        // set the page now its been processed
        $this->grav->fireEvent('onTwigSiteVariables');
        $pages = $this->grav['pages'];
        $page = $this->grav['page'];

        $twig_vars = $this->twig_vars;

        $twig_vars['pages'] = $pages->root();
        $twig_vars['page'] = $page;
        $twig_vars['header'] = $page->header();
        $twig_vars['content'] = $page->content();
        $ext = '.' . ($format ? $format : 'html') . TWIG_EXT;

        // Get Twig template layout
        $template = $this->template($page->template() . $ext);

        try {
            $output = $this->twig->render($template, $twig_vars);
        } catch (\Twig_Error_Loader $e) {
            throw new \RuntimeException($e->getRawMessage(), 404, $e);
        }

        return $output;
    }

    /**
     * Simple helper method to get the twig template if it has already been set, else return
     * the one being passed in
     *
     * @param  string $template the template name
     * @return string           the template name
     */
    public function template($template)
    {
        if (isset($this->template)) {
            return $this->template;
        } else {
            return $template;
        }
    }
}
