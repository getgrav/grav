<?php
namespace Grav\Common\Config;

use Grav\Common\Grav;
use Grav\Common\Data\Data;

/**
 * The Config class contains configuration information.
 *
 * @author RocketTheme
 * @license MIT
 */
class Config extends Data
{
    protected $checksum;

    public function key()
    {
        return $this->checksum();
    }

    public function checksum($checksum = null)
    {
        if ($checksum !== null) {
            $this->checksum = $checksum;
        }

        return $this->checksum;
    }

    public function modified($modified = null)
    {
        if ($modified !== null) {
            $this->modified = $modified;
        }

        return $this->modified;
    }

    public function reload()
    {
        throw new \Exception('TODO');
        $this->items = $this->setup;
        $this->init();
        $this->debug();

        return $this;
    }

    public function debug()
    {
        $debugger = Grav::instance()['debugger'];
        $debugger->addMessage('Environment Name: ' . $this->environment);
        if ($this->modified()) {
            $debugger->addMessage('Configuration reloaded and cached.');
        }
    }

    public function init()
    {
        $setup = Grav::instance()['setup']->toArray();
        foreach ($setup as $key => $value) {
            if ($key === 'streams' || !is_array($value)) {
                // Optimized as streams and simple values are fully defined in setup.
                $this->items[$key] = $value;
            } else {
                $this->joinDefaults($key, $value);
            }
        }
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function getLanguages()
    {
        return Grav::instance()['languages'];
    }
}
