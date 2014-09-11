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
            $path = trim($path, '/');
            if (strstr($path, '://')) {
                $list = array_merge($list, $this->find($path, true, false));
            } else {
                $list[] = $path;
            }
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
        return $this->find($uri, false, true);
    }

    /**
     * @param  string $uri
     * @param  bool   $absolute
     * @return string|bool
     */
    public function findResource($uri, $absolute = true)
    {
        return $this->find($uri, false, $absolute);
    }

    /**
     * @param  string $uri
     * @param  bool   $absolute
     * @return array
     */
    public function findResources($uri, $absolute = true)
    {
        return $this->find($uri, true, $absolute);
    }

    /**
     * @param  string $uri
     * @param  bool   $absolute
     * @param  bool $array
     *
     * @throws \InvalidArgumentException
     * @return array|string|bool
     */
    protected function find($uri, $array, $absolute)
    {
        $segments = explode('://', $uri, 2);
        $file = array_pop($segments);
        $scheme = array_pop($segments);

        if (!$scheme) {
            $scheme = 'file';
        }

        if (!isset($this->schemes[$scheme])) {
            throw new \InvalidArgumentException("Invalid resource {$scheme}://");
        }
        if (!$file && $scheme == 'file') {
            $file = getcwd();
        }

        $results = $array ? [] : false;
        foreach ($this->schemes[$scheme] as $prefix => $paths) {
            if ($prefix && strpos($file, $prefix) !== 0) {
                continue;
            }

            foreach ($paths as $path) {
                $filename = $path . '/' . ltrim(substr($file, strlen($prefix)), '\/');
                $lookup = ROOT_DIR . '/' . $filename;

                if (file_exists($lookup)) {
                    if (!$array) {
                        return $absolute ? $lookup : $filename;
                    }
                    $results[] = $absolute ? $lookup : $filename;
                }
            }
        }

        return $results;
    }
}
