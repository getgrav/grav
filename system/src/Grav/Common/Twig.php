<?php
namespace Grav\Common;

use \Grav\Common\Page\Page;

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
    protected $twig;

    /**
     * @var Grav
     */
    protected $grav;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * @var Taxonomy
     */
    protected $taxonomy;

    /**
     * @var array
     */
    public $twig_vars;

    /**
     * @var string
     */
    public $template;

    /**
     * @var \Twig_Loader_Filesystem
     */
    protected $loader;

    /**
     * @var \Twig_Loader_Array
     */
    protected $loaderArray;

    /**
     * Twig initialization that sets the twig loader chain, then the environment, then extensions
     * and also the base set of twig vars
     */
    public function init()
    {
        if (!isset($this->twig)) {

            // get Grav and Config
            $this->grav = Registry::get('Grav');
            $this->config = $this->grav->config;
            $this->uri = Registry::get('Uri');
            $this->taxonomy = Registry::get('Taxonomy');


            $this->twig_paths = array(THEMES_DIR . $this->config->get('system.pages.theme') . '/templates');
            $this->grav->fireEvent('onAfterTwigTemplatesPaths');

            $this->loader = new \Twig_Loader_Filesystem($this->twig_paths);
            $this->loaderArray = new \Twig_Loader_Array(array());
            $loader_chain = new \Twig_Loader_Chain(array($this->loaderArray, $this->loader));

            $params = $this->config->get('system.twig');
            if (!empty($params['cache'])) {
                $params['cache'] = CACHE_DIR;
            }

            $this->twig = new \Twig_Environment($loader_chain, $params);
            $this->grav->fireEvent('onAfterTwigInit');

            // set default date format if set in config
            if ($this->config->get('system.pages.dateformat.long')) {
                $this->twig->getExtension('core')->setDateFormat($this->config->get('system.pages.dateformat.long'));
            }
            // enable the debug extension if required
            if ($this->config->get('system.twig.debug')) {
                $this->twig->addExtension(new \Twig_Extension_Debug());
            }
            $this->twig->addExtension(new TwigExtension());
            $this->grav->fireEvent('onAfterTwigExtensions');

            $baseUrlAbsolute = $this->config->get('system.base_url_absolute');
            $baseUrlRelative = $this->config->get('system.base_url_relative');
            $theme = $this->config->get('system.pages.theme');
            $themeUrl = $baseUrlRelative .'/'. USER_PATH . basename(THEMES_DIR) .'/'. $theme;

            // Set some standard variables for twig
            $this->twig_vars = array(
                'config' => $this->config,
                'uri' => $this->uri,
                'base_dir' => rtrim(ROOT_DIR, '/'),
                'base_url_absolute' => $baseUrlAbsolute,
                'base_url_relative' => $baseUrlRelative,
                'theme_dir' => THEMES_DIR . $theme,
                'theme_url' => $themeUrl,
                'site' => $this->config->get('site'),
                'stylesheets' => array(),
                'scripts' => array(),
                'taxonomy' => $this->taxonomy,
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
        $this->init();
        $content = $content !== null ? $content : $item->content();

        // override the twig header vars for local resolution
        $this->grav->fireEvent('onAfterPageTwigVars');
        $twig_vars = $this->twig_vars;

        $twig_vars['page'] = $item;
        $twig_vars['assets'] = $item->assets();
        $twig_vars['header'] = $item->header();

        // Get Twig template layout
        if ($item->modularTwig()) {
            $twig_vars['content'] = $content;
            // FIXME: this is inconsistent with main page.
            $template = $this->template('modular/' . $item->template()) . TEMPLATE_EXT;
            $output = $this->twig->render($template, $twig_vars);
        } else {
            $name = '@Page:' . $item->path();
            $this->setTemplate($name, $content);
            $output = $this->twig->render($name, $twig_vars);
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
        $this->init();

        // override the twig header vars for local resolution
        $this->grav->fireEvent('onAfterStringTwigVars');
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
     * @throws \Twig_Error_Loader
     */
    public function processSite($format = null)
    {
        $this->init();

        // set the page now its been processed
        $this->grav->fireEvent('onAfterSiteTwigVars');
        $twig_vars = $this->twig_vars;
        $pages = $this->grav->pages;
        $page = $this->grav->page;

        $twig_vars['pages'] = $pages->root();
        $twig_vars['page'] = $page;
        $twig_vars['header'] = $page->header();
        $twig_vars['content'] = $page->content();
        $ext = '.' . ($format ? $format : 'html') . TWIG_EXT;

        // Get Twig template layout
        $template = $this->template($page->template() . $ext);
        $output = $this->twig->render($template, $twig_vars);

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
