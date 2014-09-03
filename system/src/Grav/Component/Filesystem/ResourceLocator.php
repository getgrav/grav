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

    protected $cache = [];

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

        $this->cache = [];
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
     * Parse resource.
     *
     * @param $uri
     * @return array
     * @throws \InvalidArgumentException
     * @internal
     */
    protected function parseResource($uri)
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

        return [$file, $scheme];
    }

    /**
     * @param  string $uri
     * @param  bool   $absolute
     * @param  bool $array
     *
     * @throws \InvalidArgumentException
     * @return array|string|bool
     * @internal
     */
    protected function find($uri, $array, $absolute)
    {
        // Local caching: make sure that the function gets only called at once for each file.
        $key = $uri .'@'. (int) $array . (int) $absolute;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        list ($file, $scheme) = $this->parseResource($uri);

        $results = $array ? [] : false;
        foreach ($this->schemes[$scheme] as $prefix => $paths) {
            if ($prefix && strpos($file, $prefix) !== 0) {
                continue;
            }

            foreach ($paths as $path) {
                $filename = $path . '/' . ltrim(substr($file, strlen($prefix)), '\/');
                $lookup = GRAV_ROOT . '/' . $filename;

                if (file_exists($lookup)) {
                    if (!$array) {
                        $results = $absolute ? $lookup : $filename;
                        break;
                    }
                    $results[] = $absolute ? $lookup : $filename;
                }
            }
        }

        $this->cache[$key] = $results;
        return $results;
    }
}
