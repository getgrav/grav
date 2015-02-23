<?php
namespace Grav\Common;

use DebugBar\JavascriptRenderer;
use DebugBar\StandardDebugBar;

/**
 * Class Debugger
 * @package Grav\Common
 */
class Debugger
{
    protected $grav;
    protected $debugbar;
    protected $renderer;
    protected $enabled;

    public function __construct()
    {
        $this->debugbar = new StandardDebugBar();
        $this->debugbar['time']->addMeasure('Loading', $this->debugbar['time']->getRequestStartTime(), microtime(true));
    }

    public function init()
    {
        $this->grav = Grav::instance();

        if ($this->enabled()) {
            $this->debugbar->addCollector(new \DebugBar\DataCollector\ConfigCollector((array)$this->grav['config']->get('system')));
        }
        return $this;
    }

    public function enabled($state = null)
    {
        if (isset($state)) {
            $this->enabled = $state;
        } else {
            if (!isset($this->enabled)) {
                $this->enabled = $this->grav['config']->get('system.debugger.enabled');
            }
        }
        return $this->enabled;
    }

    public function addAssets()
    {
        if ($this->enabled()) {
            $assets = $this->grav['assets'];

            // Add jquery library
            $assets->add('jquery', 101);

            $this->renderer = $this->debugbar->getJavascriptRenderer();
            $this->renderer->setIncludeVendors(false);

            // Get the required CSS files
            list($css_files, $js_files) = $this->renderer->getAssets(null, JavascriptRenderer::RELATIVE_URL);
            foreach ($css_files as $css) {
                $assets->addCss($css);
            }

            $assets->addCss('/system/assets/debugger.css');

            foreach ($js_files as $js) {
                $assets->addJs($js);
            }
        }
        return $this;
    }

    public function addCollector($collector)
    {
        $this->debugbar->addCollector($collector);
        return $this;
    }

    public function getCollector($collector)
    {
        return $this->debugbar->getCollector($collector);
    }

    public function render()
    {
        if ($this->enabled()) {
            echo $this->renderer->render();
        }
        return $this;
    }

    public function sendDataInHeaders()
    {
        $this->debugbar->sendDataInHeaders();
        return $this;
    }

    public function startTimer($name, $desription = null)
    {
        if ($name[0] == '_' || $this->grav['config']->get('system.debugger.enabled')) {
            $this->debugbar['time']->startMeasure($name, $desription);
        }
        return $this;
    }

    public function stopTimer($name)
    {
        if ($name[0] == '_' || $this->grav['config']->get('system.debugger.enabled')) {
            $this->debugbar['time']->stopMeasure($name);
        }
        return $this;
    }


    public function addMessage($message, $label = 'info', $isString = true)
    {
        if ($this->enabled()) {
            $this->debugbar['messages']->addMessage($message, $label, $isString);
        }
        return $this;
    }
}
