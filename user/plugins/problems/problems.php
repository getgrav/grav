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

    public function onFatalException($e)
    {
        // Run through potential issues
        if ($this->problemChecker()) {
            $this->renderProblems();
        }
    }

    public function onAfterInitPlugins()
    {
        $cache = Registry::get('Cache');
        $validated_prefix = 'validated-';

        $this->check = CACHE_DIR . $validated_prefix .$cache->getKey();

        if(!file_exists($this->check)) {

            // If no issues remain, save a state file in the cache
            if (!$this->problemChecker()) {
                // delete any exising validated files
                foreach (glob(CACHE_DIR . $validated_prefix. '*') as $filename) {
                     unlink($filename);
                }

                // create a file in the cache dir so it only runs on cache changes
                touch($this->check);

            } else {
                $this->renderProblems();
            }

        }


    }

    protected function renderProblems()
    {
        $theme = 'antimatter';
        $baseUrlRelative = rtrim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), '/'); //make this dynamic
        $themeUrl = $baseUrlRelative .'/'. USER_PATH . basename(THEMES_DIR) .'/'. $theme;
        $problemsUrl = $baseUrlRelative . '/user/plugins/problems';

        $html = file_get_contents(__DIR__ . '/html/problems.html');

        $problems = '';
        foreach ($this->results as $key => $result) {

            if ($key == 'files') {
                foreach ($result as $filename => $file_result) {
                    foreach ($file_result as $status => $text) {
                        $problems .= $this->getListRow($status, '<b>' . $filename . '</b> ' . $text);
                    }
                }
            } else {
                foreach ($result as $status => $text) {
                    $problems .= $this->getListRow($status, $text);
                }
            }
        }

        $html = str_replace('%%BASE_URL%%', $baseUrlRelative, $html);
        $html = str_replace('%%THEME_URL%%', $themeUrl, $html);
        $html = str_replace('%%PROBLEMS_URL%%', $problemsUrl, $html);
        $html = str_replace('%%PROBLEMS%%', $problems, $html);

        echo $html;

        exit();


    }

    protected function getListRow($status, $text)
    {
        $output = "\n";
        $output .= '<li class="' . ($status ? 'success' : 'error') . ' clearfix"><i class="fa fa-' . ($status ? 'check' : 'times') . '"></i><p>'. $text . '</p></li>';
        return $output;
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



        // Check if Root is writeable and essential folders Exist
        if (is_writable(ROOT_DIR)) {
            // Create Required Folders if they don't exist
            if (!is_dir(LOG_DIR)) mkdir(LOG_DIR);
            if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR);
            if (!is_dir(IMAGES_DIR)) mkdir(IMAGES_DIR);
            if (!is_dir(DATA_DIR)) mkdir(DATA_DIR);
            $root_status = true;
            $root_adjective = '';
        } else {
            $problems_found = true;
            $root_status = false;
            $root_adjective = 'not ';
        }
        $this->results['root'] = [$root_status => '<b>' . ROOT_DIR . '</b> is '. $root_adjective . 'writable'];

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
