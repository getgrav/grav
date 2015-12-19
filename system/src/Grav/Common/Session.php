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

        $is_admin = false;

        $session_timeout = $config->get('system.session.timeout', 1800);
        $session_path = $config->get('system.session.path', '/' . ltrim($uri->rootUrl(false), '/'));

        // Activate admin if we're inside the admin path.
        if ($config->get('plugins.admin.enabled')) {
            $route = $config->get('plugins.admin.route');
            $base = '/' . trim($route, '/');
            if (substr($uri->route(), 0, strlen($base)) == $base) {
                $session_timeout = $config->get('plugins.admin.session.timeout', 1800);
                $is_admin = true;
            }
        }

        if ($config->get('system.session.enabled') || $is_admin) {


            // Define session service.
            parent::__construct(
                $session_timeout,
                $session_path
            );

            $unique_identifier = GRAV_ROOT;
            $this->setName($config->get('system.session.name', 'grav_site') . '-' . substr(md5($unique_identifier), 0, 7) . ($is_admin ? '-admin' : ''));
            $this->start();
            setcookie(session_name(), session_id(), time() + $session_timeout, $session_path);
        }
    }
}
