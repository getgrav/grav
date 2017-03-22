<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use DebugBar\DataCollector\ConfigCollector;
use DebugBar\JavascriptRenderer;
use DebugBar\StandardDebugBar;
use Grav\Common\Config\Config;

class Debugger
{
    /** @var Grav $grav */
    protected $grav;

    /** @var Config $config */
    protected $config;

    /** @var JavascriptRenderer $renderer */
    protected $renderer;

    /** @var StandardDebugBar $debugbar */
    protected $debugbar;

    protected $enabled;

    protected $timers = [];

    /**
     * Debugger constructor.
     */
    public function __construct()
    {
        $this->debugbar = new StandardDebugBar();
        $this->debugbar['time']->addMeasure('Loading', $this->debugbar['time']->getRequestStartTime(), microtime(true));
    }

    /**
     * Initialize the debugger
     *
     * @return $this
     * @throws \DebugBar\DebugBarException
     */
    public function init()
    {
        $this->grav = Grav::instance();
        $this->config = $this->grav['config'];

        if ($this->enabled()) {
            $this->debugbar->addCollector(new ConfigCollector((array)$this->config->get('system'), 'Config'));
            $this->debugbar->addCollector(new ConfigCollector((array)$this->config->get('plugins'), 'Plugins'));
        }

        return $this;
    }

    /**
     * Set/get the enabled state of the debugger
     *
     * @param bool $state If null, the method returns the enabled value. If set, the method sets the enabled state
     *
     * @return null
     */
    public function enabled($state = null)
    {
        if (isset($state)) {
            $this->enabled = $state;
        } else {
            if (!isset($this->enabled)) {
                $this->enabled = $this->config->get('system.debugger.enabled');
            }
        }

        return $this->enabled;
    }

    /**
     * Add the debugger assets to the Grav Assets
     *
     * @return $this
     */
    public function addAssets()
    {
        if ($this->enabled()) {

            // Only add assets if Page is HTML
            $page = $this->grav['page'];
            if ($page->templateFormat() != 'html') {
                $this->enabled = false;
                return $this;
            }

            /** @var Assets $assets */
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

    /**
     * Adds a data collector
     *
     * @param $collector
     *
     * @return $this
     * @throws \DebugBar\DebugBarException
     */
    public function addCollector($collector)
    {
        $this->debugbar->addCollector($collector);

        return $this;
    }

    /**
     * Returns a data collector
     *
     * @param $collector
     *
     * @return \DebugBar\DataCollector\DataCollectorInterface
     * @throws \DebugBar\DebugBarException
     */
    public function getCollector($collector)
    {
        return $this->debugbar->getCollector($collector);
    }

    /**
     * Displays the debug bar
     *
     * @return $this
     */
    public function render()
    {
        if ($this->enabled()) {
            echo $this->renderer->render();
        }

        return $this;
    }

    /**
     * Sends the data through the HTTP headers
     *
     * @return $this
     */
    public function sendDataInHeaders()
    {
        $this->debugbar->sendDataInHeaders();

        return $this;
    }

    /**
     * Start a timer with an associated name and description
     *
     * @param             $name
     * @param string|null $description
     *
     * @return $this
     */
    public function startTimer($name, $description = null)
    {
        if ($name[0] == '_' || $this->config->get('system.debugger.enabled')) {
            $this->debugbar['time']->startMeasure($name, $description);
            $this->timers[] = $name;
        }

        return $this;
    }

    /**
     * Stop the named timer
     *
     * @param string $name
     *
     * @return $this
     */
    public function stopTimer($name)
    {
        if (in_array($name, $this->timers) && ($name[0] == '_' || $this->config->get('system.debugger.enabled'))) {
            $this->debugbar['time']->stopMeasure($name);
        }

        return $this;
    }

    /**
     * Dump variables into the Messages tab of the Debug Bar
     *
     * @param        $message
     * @param string $label
     * @param bool   $isString
     *
     * @return $this
     */
    public function addMessage($message, $label = 'info', $isString = true)
    {
        if ($this->enabled()) {
            $this->debugbar['messages']->addMessage($message, $label, $isString);
        }

        return $this;
    }

    /**
     * Dump exception into the Messages tab of the Debug Bar
     *
     * @param \Exception $e
     * @return Debugger
     */
    public function addException(\Exception $e)
    {
        if ($this->enabled()) {
            $this->debugbar['exceptions']->addException($e);
        }

        return $this;
    }
}
