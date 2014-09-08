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


    protected $config;
    protected $data_path;

    protected $monthly_file;
    protected $totals_file;
    protected $visitors_file;

    protected $monthly_data;
    protected $totals_data;
    protected $visitors_data;

    const MONTHLY_FILE = 'monthly.json';
    const TOTALS_FILE = 'totals.json';
    const VISITORS_FILE = 'visitors.json';

    public function __construct()
    {
        $this->config = self::$grav['config'];

        $this->data_path = LOG_DIR . 'popularity';
        $this->monthly_file = $this->data_path.'/'.self::MONTHLY_FILE;
        $this->totals_file = $this->data_path.'/'.self::TOTALS_FILE;
        $this->visitors_file = $this->data_path.'/'.self::VISITORS_FILE;

    }

    public function trackHit()
    {
        $page = self::$grav['page'];
        $relative_url = str_replace($this->config->get('system.base_url_relative'), '', $page->url());

        // Don't track error pages or pages that have no route
        if ($page->template() == 'error' || !$page->route()) {
            return;
        }

        // Make sure no 'widcard-style' ignore matches this url
        foreach ((array) $this->config->get('plugins.admin.popularity.ignore') as $ignore) {
            if (fnmatch($ignore, $relative_url)) {
                return;
            }
        }

        // initial creation if it doesn't exist
        if (!file_exists($this->data_path)) {
            mkdir($this->data_path);
            file_put_contents($this->monthly_file, array());
            file_put_contents($this->totals_file, array());
            file_put_contents($this->visitors_file, array());
        }

        // Update the data we want to track
        $this->updateMonthly();
        $this->updateTotals($page->route());
        $this->updateVisitors(self::$grav['uri']->ip());

    }

    protected function updateMonthly()
    {

        if (!$this->monthly_data) {
            $this->monthly_data = $this->getData($this->monthly_file);
        }

        $month_year = date('m-Y');

        // get the monthly access count
        if (array_key_exists($month_year, $this->monthly_data)) {
            $this->monthly_data[$month_year] = intval($this->monthly_data[$month_year]) + 1;
        } else {
            $this->monthly_data[$month_year] = 1;
        }

        file_put_contents($this->monthly_file, json_encode($this->monthly_data));
    }

    protected function updateTotals($url)
    {
        if (!$this->totals_data) {
            $this->totals_data = $this->getData($this->totals_file);
        }

        // get the totals for this url
        if (array_key_exists($url, $this->totals_data)) {
            $this->totals_data[$url] = intval($this->totals_data[$url]) + 1;
        } else {
            $this->totals_data[$url] = 1;
        }

        file_put_contents($this->totals_file, json_encode($this->totals_data));
    }

    protected function updateVisitors($ip)
    {
        if (!$this->visitors_data) {
            $this->visitors_data = $this->getData($this->visitors_file);
        }

        $count = intval($this->config->get('plugins.admin.popularity.visitors', 20));

        // update with current timestamp
        $this->visitors_data[$ip] = time();

        $visitors = $this->visitors_data;
        arsort($visitors);

        $this->visitors_data = array_slice($visitors, 0, $count);

        file_put_contents($this->visitors_file, json_encode($this->visitors_data));
    }

    protected function getData($path)
    {
        return (array) @json_decode(file_get_contents($path), true);
    }


    public function flushMonthly($months = 12)
    {
        // flush data older than 1 year
        if (!$this->monthly_data) {
            $this->monthly_data = $this->getData($this->monthly_file);
        }

        // If there are more than $months worth of data remove the old
        if (count($this->monthly_data) > $months) {
            $new_monthly = array();
            for ($x = 0; $x < intval($months); $x++) {
                $date = date('m-Y', strtotime("now - $x month"));
                if (isset($this->monthly_data[$date])) {
                    $new_monthly[$date] = $this->monthly_data[$date];
                }
            }

            $this->monthly_data = $new_monthly;
            file_put_contents($this->monthly_file, json_encode($this->monthly_data));
        }
    }

    public function flushTotals()
    {
        // flush all totals
        file_put_contents($this->totals_file, json_encode(array()));
    }

    public function flushVisitors()
    {
        // flush all the visitor data
        file_put_contents($this->visitors_file, json_encode(array()));
    }
}
