<?php
namespace Grav\Common;

/**
 * Wrapper for Session
 */
class Session extends \RocketTheme\Toolbox\Session\Session
{
    protected $grav;
    protected $session;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    public function init()
    {
        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $config = $this->grav['config'];

        if ($config->get('system.session.enabled')) {
            // Define session service.
            parent::__construct(
                $config->get('system.session.timeout', 1800),
                $config->get('system.session.path', '/' . ltrim($uri->rootUrl(false), '/'))
            );

            $site_identifier = $config->get('site.title', 'unkown');
            $this->setName($config->get('system.session.name', 'grav_site') . '_' . substr(md5($site_identifier), 0, 7));
            $this->start();
        }
    }
}
