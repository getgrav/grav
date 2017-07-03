<?php
/**
 * @package    Grav.Common.Errors
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Errors;

use Whoops\Handler\Handler;
use Whoops\Util\Misc;
use Whoops\Util\TemplateHelper;

class SimplePageHandler extends Handler
{
    private $searchPaths = array();
    private $resourceCache = array();

    public function __construct()
    {
        // Add the default, local resource search path:
        $this->searchPaths[] = __DIR__ . "/Resources";
    }

    /**
     * @return int|null
     */
    public function handle()
    {
        $inspector = $this->getInspector();

        $helper = new TemplateHelper();
        $templateFile = $this->getResource("layout.html.php");
        $cssFile      = $this->getResource("error.css");

        $code = $inspector->getException()->getCode();
        $message = $inspector->getException()->getMessage();

        if ($inspector->getException() instanceof \ErrorException) {
            $code = Misc::translateErrorCode($code);
        }

        $vars = array(
            "stylesheet" => file_get_contents($cssFile),
            "code"        => $code,
            "message"     => filter_var(rawurldecode($message), FILTER_SANITIZE_STRING),
        );

        $helper->setVariables($vars);
        $helper->render($templateFile);

        return Handler::QUIT;
    }

    /**
     * @param $resource
     *
     * @return string
     */
    protected function getResource($resource)
    {
        // If the resource was found before, we can speed things up
        // by caching its absolute, resolved path:
        if (isset($this->resourceCache[$resource])) {
            return $this->resourceCache[$resource];
        }

        // Search through available search paths, until we find the
        // resource we're after:
        foreach ($this->searchPaths as $path) {
            $fullPath = $path . "/$resource";

            if (is_file($fullPath)) {
                // Cache the result:
                $this->resourceCache[$resource] = $fullPath;
                return $fullPath;
            }
        }

        // If we got this far, nothing was found.
        throw new \RuntimeException(
            "Could not find resource '$resource' in any resource paths."
            . "(searched: " . join(", ", $this->searchPaths). ")"
        );
    }

    public function addResourcePath($path)
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(
                "'$path' is not a valid directory"
            );
        }

        array_unshift($this->searchPaths, $path);
    }

    public function getResourcePaths()
    {
        return $this->searchPaths;
    }
}
