<?php
namespace Grav\Common\GPM\Remote;

class Grav extends Collection
{
    private $repository = 'http://getgrav.org/downloads/grav.json';
    private $data;

    private $version;
    private $date;

    public function __construct($refresh = false, $callback = null)
    {
        parent::__construct($this->repository);

        $this->fetch($refresh, $callback);
        $this->data = json_decode($this->raw);

        $this->version = @$this->data->version ?: '-';
        $this->date = @$this->data->date ?: '-';

        $this->data = $this->data->assets;

        foreach ($this->data as $slug => $data) {
            $this->items[$slug] = new Package($data);
        }
    }

    /**
     * Returns the list of assets associated to the latest version of Grav
     * @return array list of assets
     */
    public function getAssets()
    {
        return $this->data;
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
