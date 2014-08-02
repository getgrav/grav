<?php
namespace Grav\Plugin;

use \Grav\Common\Cache;
use \Grav\Common\Plugin;
use \Grav\Common\Registry;
use Tracy\Debugger;

class ProblemsPlugin extends Plugin
{
    /**
     * @var bool
     */
    protected $active = false;

    protected $results = array();


    /**
     * Enable sitemap only if url matches to the configuration.
     */
    public function onAfterInitPlugins()
    {
        $cache = Registry::get('Cache');
        $validated_prefix = 'validated-';

        $this->check = CACHE_DIR . $validated_prefix .$cache->getKey();

        if(!file_exists($this->check)) {

            // Run through potential issues
            $this->active = $this->problemChecker();

            // If no issues remain, save a state file in the cache
            if (!$this->active) {
                // delete any exising validated files
                foreach (glob(CACHE_DIR . $validated_prefix. '*') as $filename) {
                     unlink($filename);
                }

                // create a file in the cache dir so it only runs on cache changes
                touch($this->check);
            }

        }


    }

    public function onAfterGetPage()
    {
        if (!$this->active) {
            return;
        }

        /** @var Grav $grav */
        $grav = Registry::get('Grav');
        $grav->page->content("# Issues Found\n##Please **Review** and **Resolve** before continuing...");

    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onAfterTwigTemplatesPaths()
    {
        if (!$this->active) {
            return;
        }

        Registry::get('Twig')->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Set needed variables to display the problems.
     */
    public function onAfterSiteTwigVars()
    {
        if (!$this->active) {
            return;
        }

        $twig = Registry::get('Twig');
        $twig->template = 'problems.html.twig';
        $twig->twig_vars['results'] = $this->results;

        if ($this->config->get('plugins.problems.built_in_css')) {
            $twig->twig_vars['stylesheets'][] = 'user/plugins/problems/problems.css';
        }
    }

    protected function problemChecker()
    {
        $min_php_version = '5.4.0';
        $problems_found = false;


        $essential_files = [
            'index.php' => false,
            '.htaccess' => false,
            'cache' => true,
            'logs' => true,
            'images' => true,
            'system' => false,
            'user/data' => true,
            'user/pages' => false,
            'user/config' => false,
            'user/plugins/error' => false,
            'user/plugins' => false,
            'user/themes' => false,
            'vendor' => false
        ];

        // Check PHP version
        if (version_compare(phpversion(), '5.4.0', '<')) {
            $problems_found = true;
            $php_version_adjective = 'lower';
            $php_version_status = false;

        } else {
            $php_version_adjective = 'greater';
            $php_version_status = true;
        }
        $this->results['php'] = [$php_version_status => 'Your PHP version (' . phpversion() . ') is '. $php_version_adjective . ' than the minimum required: <b>' . $min_php_version . '</b>'];

        // Check for GD library
        if (defined('GD_VERSION') && function_exists('gd_info')) {
            $gd_adjective = '';
            $gd_status = true;
        } else {
            $problems_found = true;
            $gd_adjective = 'not ';
            $gd_status = false;
        }
        $this->results['gd'] = [$gd_status => 'PHP GD (Image Manipulation Library) is '. $gd_adjective . 'installed'];

        // Check for essential files & perms
        $file_problems = [];
        foreach($essential_files as $file => $check_writable) {
            $file_path = ROOT_DIR . $file;
            if (!file_exists($file_path)) {
                $problems_found = true;
                $file_status = false;
                $file_adjective = 'does not exist';

            } else {
                $file_status = true;
                $file_adjective = 'exists';
                $is_writeable = is_writable($file_path);
                $is_dir = is_dir($file_path);

                if ($check_writable) {
                    if (!$is_writeable) {
                        $file_status = false;
                        $problems_found = true;
                        $file_adjective .= ' but is <b class="underline">not writeable</b>';
                    } else {
                        $file_adjective .= ' and <b class="underline">is writeable</b>';
                    }
                }
            }
            if (!$file_status || $is_dir || $check_writable) {
                $file_problems[$file_path] = [$file_status => $file_adjective];
            }
        }
        if (sizeof($file_problems) > 0) {

            $this->results['files'] = $file_problems;
        }

        return $problems_found;
    }
}
