<?php
namespace Grav\Common\GPM\Remote;

class Plugins extends Collection
{
    private $repository = 'http://getgrav.org/downloads/plugins.json';
    private $type       = 'plugins';
    private $data;

    public function __construct($refresh = false, $callback = null)
    {
        parent::__construct($this->repository);

        $this->fetch($refresh, $callback);
        $this->data = json_decode($this->raw);

        foreach ($this->data as $slug => $data) {
            $this->items[$slug] = new Package($data, $this->type);
        }
    }
}
