<?php
namespace Grav\Plugin;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Plugins;
use Grav\Common\Themes;
use Grav\Common\Page\Page;
use Grav\Common\Data;
use Grav\Common\GravTrait;

class Popularity
{
    use GravTrait;

    /** @var Config */
    protected $config;
    protected $data_path;

    protected $daily_file;
    protected $monthly_file;
    protected $totals_file;
    protected $visitors_file;

    protected $daily_data;
    protected $monthly_data;
    protected $totals_data;
    protected $visitors_data;

    const DAILY_FORMAT = 'd-m-Y';
    const MONTHLY_FORMAT = 'm-Y';
    const DAILY_FILE = 'daily.json';
    const MONTHLY_FILE = 'monthly.json';
    const TOTALS_FILE = 'totals.json';
    const VISITORS_FILE = 'visitors.json';

    public function __construct()
    {
        $this->config = self::getGrav()['config'];

        $this->data_path = self::$grav['locator']->findResource('log://popularity', true, true);
        $this->daily_file = $this->data_path.'/'.self::DAILY_FILE;
        $this->monthly_file = $this->data_path.'/'.self::MONTHLY_FILE;
        $this->totals_file = $this->data_path.'/'.self::TOTALS_FILE;
        $this->visitors_file = $this->data_path.'/'.self::VISITORS_FILE;

    }

    public function trackHit()
    {
        /** @var Page $page */
        $page = self::getGrav()['page'];
        $relative_url = str_replace(self::getGrav()['base_url_relative'], '', $page->url());

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
            $this->flushPopularity();
        }

        // Update the data we want to track
        $this->updateDaily();
        $this->updateMonthly();
        $this->updateTotals($page->route());
        $this->updateVisitors(self::getGrav()['uri']->ip());

    }

    protected function updateDaily()
    {

        if (!$this->daily_data) {
            $this->daily_data = $this->getData($this->daily_file);
        }

        $day_month_year = date(self::DAILY_FORMAT);

        // get the daily access count
        if (array_key_exists($day_month_year, $this->daily_data)) {
            $this->daily_data[$day_month_year] = intval($this->daily_data[$day_month_year]) + 1;
        } else {
            $this->daily_data[$day_month_year] = 1;
        }

        // keep correct number as set by history
        $count = intval($this->config->get('plugins.admin.popularity.history.daily', 30));
        $total = count($this->daily_data);

        if ($total > $count) {
            $this->daily_data = array_slice($this->daily_data, -$count, $count, true);
        }

        file_put_contents($this->daily_file, json_encode($this->daily_data));
    }

    public function getDailyChartData()
    {
        if (!$this->daily_data) {
            $this->daily_data = $this->getData($this->daily_file);
        }

        $limit = intval($this->config->get('plugins.admin.popularity.dashboard.days_of_stats', 7));
        $chart_data = array_slice($this->daily_data, -$limit, $limit);

        $labels = array();
        $data = array();

        foreach ($chart_data as $date => $count) {
            $labels[] = date('D', strtotime($date));
            $data[] = $count;
        }

        return array('labels' => json_encode($labels), 'data' => json_encode($data));
    }

    public function getDailyTotal()
    {
        if (!$this->daily_data) {
            $this->daily_data = $this->getData($this->daily_file);
        }

        if (isset($this->daily_data[date(self::DAILY_FORMAT)])) {
            return $this->daily_data[date(self::DAILY_FORMAT)];
        } else {
            return 0;
        }
    }

    public function getWeeklyTotal()
    {
        if (!$this->daily_data) {
            $this->daily_data = $this->getData($this->daily_file);
        }

        $total = 0;
        foreach ($this->daily_data as $daily) {
            $total += $daily;
        }

        return $total;
    }

    public function getMonthlyTotal()
    {
        if (!$this->monthly_data) {
            $this->monthly_data = $this->getData($this->monthly_file);
        }
        if (isset($this->monthly_data[date(self::MONTHLY_FORMAT)])) {
            return $this->monthly_data[date(self::MONTHLY_FORMAT)];
        } else {
            return 0;
        }
    }

    protected function updateMonthly()
    {

        if (!$this->monthly_data) {
            $this->monthly_data = $this->getData($this->monthly_file);
        }

        $month_year = date(self::MONTHLY_FORMAT);

        // get the monthly access count
        if (array_key_exists($month_year, $this->monthly_data)) {
            $this->monthly_data[$month_year] = intval($this->monthly_data[$month_year]) + 1;
        } else {
            $this->monthly_data[$month_year] = 1;
        }

        // keep correct number as set by history
        $count = intval($this->config->get('plugins.admin.popularity.history.monthly', 12));
        $total = count($this->monthly_data);
        $this->monthly_data = array_slice($this->monthly_data, $total - $count, $count);


        file_put_contents($this->monthly_file, json_encode($this->monthly_data));
    }

    protected function getMonthyChartData()
    {
        if (!$this->monthly_data) {
            $this->monthly_data = $this->getData($this->monthly_file);
        }

        $labels = array();
        $data = array();

        foreach ($this->monthly_data as $date => $count) {
            $labels[] = date('M', strtotime($date));
            $data[] = $count;
        }
        return array('labels' => $labels, 'data' => $data);
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

        // update with current timestamp
        $this->visitors_data[$ip] = time();
        $visitors = $this->visitors_data;
        arsort($visitors);

        $count = intval($this->config->get('plugins.admin.popularity.history.visitors', 20));
        $this->visitors_data = array_slice($visitors, 0, $count, true);

        file_put_contents($this->visitors_file, json_encode($this->visitors_data));
    }

    protected function getData($path)
    {
        return (array) @json_decode(file_get_contents($path), true);
    }


    public function flushPopularity()
    {
        file_put_contents($this->daily_file, array());
        file_put_contents($this->monthly_file, array());
        file_put_contents($this->totals_file, array());
        file_put_contents($this->visitors_file, array());
    }
}
