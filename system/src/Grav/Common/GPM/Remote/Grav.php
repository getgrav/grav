<?php
namespace Grav\Common\GPM\Remote;

use \Doctrine\Common\Cache\FilesystemCache;

class Grav extends AbstractPackageCollection
{
    protected $repository = 'http://getgrav.org/downloads/grav.json';
    private $data;

    private $version;
    private $date;

    /**
     * @param bool $refresh
     * @param null $callback
     */
    public function __construct($refresh = false, $callback = null)
    {
        $cache_dir      = self::getGrav()['locator']->findResource('cache://gpm', true, true);
        $this->cache    = new FilesystemCache($cache_dir);
        $this->raw      = $this->cache->fetch(md5($this->repository));

        $this->fetch($refresh, $callback);

        $this->data = json_decode($this->raw, true);
        $this->version = isset($this->data['version']) ? $this->data['version'] : '-';
        $this->date = isset($this->data['date']) ? $this->data['date'] : '-';

        foreach ($this->data['assets'] as $slug => $data) {
            $this->items[$slug] = new Package($data);
        }
    }

    /**
     * Returns the list of assets associated to the latest version of Grav
     * @return array list of assets
     */
    public function getAssets()
    {
        return $this->data['assets'];
    }

    /**
     * Returns the changelog list for each version of Grav
     * @param string $diff the version number to start the diff from
     *
     * @return array changelog list for each version
     */
    public function getChangelog($diff = null)
    {
        if (!$diff) {
            return $this->data['changelog'];
        }

        $diffLog = [];
        foreach ($this->data['changelog'] as $version => $changelog) {
            preg_match("/[\d\.]+/", $version, $cleanVersion);

            if (!$cleanVersion || version_compare($diff, $cleanVersion[0], ">=")) { continue; }

            $diffLog[$version] = $changelog;
        }

        return $diffLog;
    }

    /**
     * Returns the latest version of Grav available remotely
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Return the release date of the latest Grav
     * @return string
     */
    public function getDate()
    {
        return $this->date;
    }

    public function isUpdatable()
    {
        return version_compare(GRAV_VERSION, $this->getVersion(), '<');
    }
}
