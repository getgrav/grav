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

    public function __construct()
    {
        $this->data_path = LOG_DIR . 'popularity';
        $this->data_file = date('W-Y') . '.json';
    }

    public function trackHit()
    {
        $page = self::$grav['page'];

        // make sure this is not an error or an ignored route
        if ($page->template() == 'error' ||
            in_array($page->route(), (array) self::$grav['config']->get('plugins.admin.popularity.ignore'))) {
            return;
        }

        $url = $page->url();
        $data_filepath = $this->data_path.'/'.$this->data_file;

        // initial creation if it doesn't exist
        if (!file_exists($this->data_path)) {
            mkdir($this->data_path);
            file_put_contents($data_filepath, array());
        }

        // Get the JSON data
        $data = (array) @json_decode(file_get_contents($data_filepath), true);

        if (array_key_exists($url, $data)) {
            $data[$url] = intval($data[$url]) + 1;
        } else {
            $data[$url] = 1;
        }

        // Store the JSON data again
        file_put_contents($data_filepath, json_encode($data));

    }

    function flushData($weeks = 52)
    {
        // flush data older than 1 year


    }
}
