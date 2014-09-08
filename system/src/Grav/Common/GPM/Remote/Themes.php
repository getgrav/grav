<?php
namespace Grav\Common\GPM\Remote;

class Themes extends Collection {
    private $repository = 'http://getgrav.org/downloads/themes.json';
    private $type       = 'themes';
    private $data;

    public function __construct($repository = null) {
        if ($repository) {
            $this->repository = $repository;
        }

        parent::__construct($this->repository);

        $this->fetch();
        $this->data = json_decode($this->raw)->results->data;

        foreach ($this->data as $data) {
            $this->items[$data->slug] = new Package($data, $this->type);
        }
    }
}
