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
            // Only activate admin if we're inside the admin path.
            $is_admin = false;
            $route = $config->get('plugins.admin.route');
            $base = '/' . trim($route, '/');
            if (substr($uri->route(), 0, strlen($base)) == $base) {
                $is_admin = true;
            }

            $session_timeout = $config->get('system.session.timeout', 1800);
            $session_path = $config->get('system.session.path', '/' . ltrim($uri->rootUrl(false), '/'));

            // Define session service.
            parent::__construct(
                $session_timeout,
                $session_path
            );

            $site_identifier = $config->get('site.title', 'unknown');
            $this->setName($config->get('system.session.name', 'grav_site') . '_' . substr(md5($site_identifier), 0, 7) . ($is_admin ? '_admin' : ''));
            $this->start();
            setcookie(session_name(), session_id(), time() + $session_timeout, $session_path);
        }
    }
}
