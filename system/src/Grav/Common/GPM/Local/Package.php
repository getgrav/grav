<?php
namespace Grav\Common\GPM\Local;

use Grav\Common\Data\Data;
use Grav\Common\GPM\Common\Package as BasePackage;

class Package extends BasePackage
{
    protected $settings;

    public function __construct(Data $package, $package_type = null)
    {
        $data = new Data($package->blueprints()->toArray());
        parent::__construct($data, $package_type);

        $this->settings = $package->toArray();

        $html_description = \Parsedown::instance()->line($this->description);
        $this->data->set('slug', $package->slug);
        $this->data->set('description_html', $html_description);
        $this->data->set('description_plain', strip_tags($html_description));
        $this->data->set('symlink', is_link(USER_DIR . $package_type . DS . $this->name));
    }

    /**
     * @return mixed
     */
    public function isEnabled()
    {
        return $this->settings['enabled'];
    }
}
