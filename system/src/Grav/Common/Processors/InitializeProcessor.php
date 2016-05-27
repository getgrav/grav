<?php
namespace Grav\Common\Processors;

class InitializeProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = 'init';
    public $title = 'Initialize';

    public function process() {
        $this->container['config']->debug();

        $this->container['output_buffer_level'] = ob_get_level();

        // mod_php + zlib.output_compression do sutff differently
        if (php_sapi_name() === 'apache2handler' && ini_get('zlib.output_compression')) {
            // disable Grav's gzip option as it conflicts with zlib.output_compression
            $this->container['config']->set('system.cache.gzip', false);
            $this->container['config']->set('system.apache_zlib_fix', true);
        }

        // Use output buffering to prevent headers from being sent too early.
        ob_start();
        if ($this->container['config']->get('system.cache.gzip')) {
            // Enable zip/deflate with a fallback in case of if browser does not support compressing.
            if (!ob_start("ob_gzhandler")) {
                ob_start();
            }
        }

        // Initialize the timezone.
        if ($this->container['config']->get('system.timezone')) {
            date_default_timezone_set($this->container['config']->get('system.timezone'));
        }

        // Initialize uri, session.
        $this->container['session']->init();
        $this->container['uri']->init();

        $this->container->setLocale();
    }

}
