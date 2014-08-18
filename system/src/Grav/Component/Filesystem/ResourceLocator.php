<?php

namespace Grav\Component\Filesystem;

/**
 * Implements Uniform Resource Location
 *
 * @link http://webmozarts.com/2013/06/19/the-power-of-uniform-resource-location-in-php/
 */
class ResourceLocator
{
    /**
     * @var array
     */
    protected $schemes = [];

    /**
     * @param string $scheme
     * @param string $prefix
     * @param string|array $paths
     */
    public function addPath($scheme, $prefix, $paths)
    {
        $list = [];
        foreach((array) $paths as $path) {
            $list[] = trim($path, '/');
        }

        if (isset($this->schemes[$scheme][$prefix])) {
            $list = array_merge($list, $this->schemes[$scheme][$prefix]);
        }

        $this->schemes[$scheme][$prefix] = $list;

        // Sort in reverse order to get longer prefixes to be matched first.
        krsort($this->schemes[$scheme]);
    }

    /**
     * @param $uri
     * @return string|bool
     */
    public function __invoke($uri)
    {
        return $this->find($uri, false);
    }

    /**
     * @param  string $uri
     * @return string|bool
     */
    public function findResource($uri)
    {
        return $this->find($uri, false);
    }

    /**
     * @param  string $uri
     * @return array
     */
    public function findResources($uri)
    {
        return $this->find($uri, true);
    }

    /**
     * @param  string $uri
     * @param  bool $array
     * @throws \InvalidArgumentException
     * @return array|string|bool
     */
    protected function find($uri, $array)
    {
        $segments = explode('://', $uri, 2);
        $file = array_pop($segments);
        $scheme = array_pop($segments);

        if (!$scheme) {
            $scheme = 'file';
        }

        if (!$file || $uri[0] == ':') {
            throw new \InvalidArgumentException('Invalid resource URI');
        }
        if (!isset($this->schemes[$scheme])) {
            throw new \InvalidArgumentException("Invalid resource {$scheme}://");
        }

        $paths = $array ? [] : false;
        foreach ($this->schemes[$scheme] as $prefix => $paths) {
            if ($prefix && strpos($file, $prefix) !== 0) {
                continue;
            }

            foreach ($paths as $path) {
                $filename = ROOT_DIR . '/' . $path . '/' . ltrim(substr($file, strlen($prefix)), '\/');

                if (file_exists($filename)) {
                    if (!$array) {
                        return $filename;
                    }
                    $paths[] = $filename;
                }
            }
        }

        return $paths;
    }
}
