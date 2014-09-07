<?php
namespace Grav\Plugin;

use Grav\Common\User\User;
use Grav\Common\User\Authentication;
use Grav\Common\Filesystem\File;
use Grav\Common\Grav;
use Grav\Common\Plugins;
use Grav\Common\Session;
use Grav\Common\Themes;
use Grav\Common\Uri;
use Grav\Common\Page\Pages;
use Grav\Common\Page\Page;
use Grav\Common\Data;
use Grav\Common\GravTrait;

class Popularity
{
    use GravTrait;

    protected $data_path;
    protected $data_file;

    const MONTHLY_FILE = 'monthly.json';
    const TOTALS_FILE = 'totals.json';

    public function __construct()
    {
        $this->data_path = LOG_DIR . 'popularity';
        $this->data_file = date('W-Y') . '.json';
    }

    public function trackHit()
    {
        $page = self::$grav['page'];
        $config = self::$grav['config'];
        $relative_url = str_replace($config->get('system.base_url_relative'), '', $page->url());

        // Don't track error pages or pages that have no route
        if ($page->template() == 'error' || !$page->route()) {
            return;
        }

        // Make sure no 'widcard-style' ignore matches this url
        foreach ((array) self::$grav['config']->get('plugins.admin.popularity.ignore') as $ignore) {
            if (fnmatch($ignore, $relative_url)) {
                return;
            }
        }

        // Used more than once, so make a variable!
        $monthly_file = $this->data_path.'/'.self::MONTHLY_FILE;
        $totals_file = $this->data_path.'/'.self::TOTALS_FILE;

        // initial creation if it doesn't exist
        if (!file_exists($this->data_path)) {
            mkdir($this->data_path);
            file_put_contents($monthly_file, array());
            file_put_contents($totals_file, array());
        }

        // Update the data we want to track
        $this->updateMonthly($monthly_file);
        $this->updateTotals($totals_file, $relative_url);

    }

    public function flushData($weeks = 52)
    {
        // flush data older than 1 year


    }

    protected function updateMonthly($path)
    {
        $data = (array) @json_decode(file_get_contents($path), true);
        $month_year = date('m-Y');

        if (array_key_exists($month_year, $data)) {
            $data[$month_year] = intval($data[$month_year]) + 1;
        } else {
            $data[$month_year] = 1;
        }

        file_put_contents($path, json_encode($data));
    }

    protected function updateTotals($path, $url)
    {
        $data = (array) @json_decode(file_get_contents($path), true);

        if (array_key_exists($url, $data)) {
            $data[$url] = intval($data[$url]) + 1;
        } else {
            $data[$url] = 1;
        }

        file_put_contents($path, json_encode($data));
    }
}
