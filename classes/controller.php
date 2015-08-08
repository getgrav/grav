<?php
namespace Grav\Plugin;

use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\Installer;
use Grav\Common\Grav;
use Grav\Common\Themes;
use Grav\Common\Uri;
use Grav\Common\Data;
use Grav\Common\Page;
use Grav\Common\Page\Collection;
use Grav\Common\User\User;
use Grav\Common\Utils;
use Grav\Common\Backup\ZipBackup;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use RocketTheme\Toolbox\File\JsonFile;
use Symfony\Component\Yaml\Yaml;

class AdminController
{
    /**
     * @var Grav
     */
    public $grav;

    /**
     * @var string
     */
    public $view;

    /**
     * @var string
     */
    public $task;

    /**
     * @var string
     */
    public $route;

    /**
     * @var array
     */
    public $post;

    /**
     * @var Admin
     */
    protected $admin;

    /**
     * @var string
     */
    protected $redirect;

    /**
     * @var int
     */
    protected $redirectCode;

    /**
     * @param Grav   $grav
     * @param string $view
     * @param string $task
     * @param string $route
     * @param array  $post
     */
    public function __construct(Grav $grav, $view, $task, $route, $post)
    {
        $this->grav = $grav;
        $this->view = $view;
        $this->task = $task ? $task : 'display';
        $this->post = $this->getPost($post);
        $this->route = $route;
        $this->admin = $this->grav['admin'];
    }

    /**
     * Performs a task.
     *
     * @return bool True if the action was performed successfully.
     */
    public function execute()
    {
        $success = false;
        $method = 'task' . ucfirst($this->task);
        if (method_exists($this, $method)) {
            try {
                $success = call_user_func(array($this, $method));
            } catch (\RuntimeException $e) {
                $success = true;
                $this->admin->setMessage($e->getMessage(), 'error');
            }

            // Grab redirect parameter.
            $redirect = isset($this->post['_redirect']) ? $this->post['_redirect'] : null;
            unset($this->post['_redirect']);

            // Redirect if requested.
            if ($redirect) {
                $this->setRedirect($redirect);
            }
        }
        return $success;
    }

    /**
     * Redirect to the route stored in $this->redirect
     */
    public function redirect()
    {
        if (!$this->redirect) {
            return;
        }

        $base = $this->admin->base;
        $path = trim(substr($this->redirect, 0, strlen($base)) == $base ? substr($this->redirect, strlen($base)) : $this->redirect, '/');

        $this->grav->redirect($base . '/' . preg_replace('|/+|', '/', $path), $this->redirectCode);
    }

    /**
     * Handle login.
     *
     * @return bool True if the action was performed.
     */
    protected function taskLogin()
    {
        $l = $this->grav['language'];

        if ($this->admin->authenticate($this->post)) {
            // should never reach here, redirects first
        } else {
            $this->admin->setMessage($l->translate('LOGIN_FAILED'), 'error');
        }

        return true;
    }

    /**
     * Handle logout.
     *
     * @return bool True if the action was performed.
     */
    protected function taskLogout()
    {
        $l = $this->grav['language'];

        $this->admin->session()->invalidate()->start();
        $this->admin->setMessage($l->translate('LOGGED_OUT'), 'info');
        $this->setRedirect('/logout');

        return true;
    }

    /**
     * Handle the email password recovery procedure.
     *
     * @return bool True if the action was performed.
     */
    protected function taskForgot()
    {
        $l = $this->grav['language'];

        $data = $this->post;

        $username = isset($data['username']) ? $data['username'] : '';
        $user = !empty($username) ? User::load($username) : null;

        if (!isset($this->grav['Email'])) {
            $this->admin->setMessage($l->translate('FORGOT_EMAIL_NOT_CONFIGURED'), 'error');
            $this->setRedirect('/');
            return true;
        }

        if (!$user || !$user->exists()) {
            $this->admin->setMessage($l->translate(['FORGOT_USERNAME_DOES_NOT_EXIST', $username]), 'error');
            $this->setRedirect('/forgot');
            return true;
        }

        if (empty($user->email)) {
            $this->admin->setMessage($l->translate(['FORGOT_CANNOT_RESET_EMAIL_NO_EMAIL', $username]), 'error');
            $this->setRedirect('/forgot');
            return true;
        }

        $token = md5(uniqid(mt_rand(), true));
        $expire = time() + 604800; // next week

        $user->reset = $token . '::' . $expire;
        $user->save();

        $author = $this->grav['config']->get('site.author.name', '');
        $fullname = $user->fullname ?: $username;
        $reset_link = rtrim($this->grav['uri']->rootUrl(true), '/') . '/' . trim($this->admin->base, '/') . '/reset/task:reset/user:' . $username . '/token:' . $token;

        $sitename = $this->grav['config']->get('site.title', 'Website');
        $from = $this->grav['config']->get('plugins.email.from', 'noreply@getgrav.org');
        $to = $user->email;

        $subject = $l->translate(['FORGOT_EMAIL_SUBJECT', $sitename]);
        $content = $l->translate(['FORGOT_EMAIL_BODY', $fullname, $reset_link, $author, $sitename]);

        $body = $this->grav['twig']->processTemplate('email/base.html.twig', ['content' => $content]);

        $message = $this->grav['Email']->message($subject, $body, 'text/html')
            ->setFrom($from)
            ->setTo($to);

        $sent = $this->grav['Email']->send($message);

        if ($sent < 1) {
            $this->admin->setMessage($l->translate('FORGOT_FAILED_TO_EMAIL'), 'error');
        } else {
            $this->admin->setMessage($l->translate(['FORGOT_INSTRUCTIONS_SENT_VIA_EMAIL', $to]), 'info');
        }

        $this->setRedirect('/');
        return true;
    }

    /**
     * Handle the reset password action.
     *
     * @return bool True if the action was performed.
     */
    public function taskReset()
    {
        $l = $this->grav['language'];

        $data = $this->post;

        if (isset($data['password'])) {
            $username = isset($data['username']) ? $data['username'] : null;
            $user = !empty($username) ? User::load($username) : null;
            $password = isset($data['password']) ? $data['password'] : null;
            $token = isset($data['token']) ? $data['token'] : null;

            if (!empty($user) && $user->exists() && !empty($user->reset)) {
                list($good_token, $expire) = explode('::', $user->reset);

                if ($good_token === $token) {
                    if (time() > $expire) {
                        $this->admin->setMessage($l->translate('RESET_LINK_EXPIRED'), 'error');
                        $this->setRedirect('/forgot');
                        return true;
                    }

                    unset($user->hashed_password);
                    unset($user->reset);
                    $user->password = $password;

                    $user->validate();
                    $user->filter();
                    $user->save();

                    $this->admin->setMessage($l->translate('RESET_PASSWORD_RESET'), 'info');
                    $this->setRedirect('/');
                    return true;
                }
            }

            $this->admin->setMessage($l->translate('RESET_INVALID_LINK'), 'error');
            $this->setRedirect('/forgot');
            return true;

        } else {
            $user = $this->grav['uri']->param('user');
            $token = $this->grav['uri']->param('token');

            if (empty($user) || empty($token)) {
                $this->admin->setMessage($l->translate('RESET_INVALID_LINK'), 'error');
                $this->setRedirect('/forgot');
                return true;
            }

            $this->admin->forgot = [ 'username' => $user, 'token' => $token ];
        }

        return true;
    }

    /**
     * Clear the cache.
     *
     * @return bool True if the action was performed.
     */
    protected function taskClearCache()
    {
        if (!$this->authoriseTask('clear cache', ['admin.cache', 'admin.super'])) {
            return;
        }

        // get optional cleartype param
        $clear_type = $this->grav['uri']->param('cleartype');

        if ($clear_type) {
            $clear = $clear_type;
        } else {
            $clear = 'standard';
        }

        $results = Cache::clearCache($clear);
        if (count($results) > 0) {
            $this->admin->json_response = ['status' => 'success', 'message' => 'Cache cleared <br />Method: ' . $clear . ''];
        } else {
            $this->admin->json_response = ['status' => 'error', 'message' => 'Error clearing cache'];
        }

        return true;
    }

    /**
     * Handle the backup action
     *
     * @return bool True if the action was performed.
     */
    protected function taskBackup()
    {
        if (!$this->authoriseTask('backup', ['admin.maintenance', 'admin.super'])) {
            return;
        }

        $download = $this->grav['uri']->param('download');

        if ($download) {
            Utils::download(base64_decode(urldecode($download)), true);
        }

        $log = JsonFile::instance($this->grav['locator']->findResource("log://backup.log", true, true));

        try {
            $backup = ZipBackup::backup();
        } catch (\Exception $e) {
            $this->admin->json_response = [
                'status' => 'error',
                'message' => 'An error occured. '.  $e->getMessage()
            ];

            return true;
        }

        $download = urlencode(base64_encode($backup));
        $url = rtrim($this->grav['uri']->rootUrl(true), '/') . '/' . trim($this->admin->base, '/') . '/task:backup/download:' . $download;

        $log->content([
            'time' => time(),
            'location' => $backup
        ]);
        $log->save();

        $this->admin->json_response = [
            'status' => 'success',
            'message' => 'Your backup is ready for download. <a href="'.$url.'" class="button">Download backup</a>',
            'toastr' => [
                'timeOut' => 0,
                'closeButton' => true
            ]
        ];

        return true;
    }

    /**
     * Handles filtering the page by modular/visible/routable in the pages list.
     */
    protected function taskFilterPages()
    {
        if (!$this->authoriseTask('filter pages', ['admin.pages', 'admin.super'])) {
            return;
        }

        $data = $this->post;

        $flags = !empty($data['flags']) ? array_map('strtolower', explode(',', $data['flags'])) : [];
        $queries = !empty($data['query']) ? explode(',', $data['query']) : [];

        $collection = $this->grav['pages']->all();

        if (count($flags)) {
            if (in_array('modular', $flags))
                $collection = $collection->modular();

            if (in_array('visible', $flags))
                $collection = $collection->visible();

            if (in_array('routable', $flags))
                $collection = $collection->routable();
        }

        if (!empty($queries)) {
            foreach ($collection as $page) {
                foreach ($queries as $query) {
                    $query = trim($query);

                    // $page->content();
                    if (stripos($page->getRawContent(), $query) === false && stripos($page->title(), $query) === false) {
                        $collection->remove($page);
                    }
                }
            }
        }

        $results = [];
        foreach ($collection as $path => $page) {
            $results[] = $page->route();
        }

        $this->admin->json_response = [
            'status' => 'success',
            'message' => 'Pages filtered',
            'results' => $results
        ];
        $this->admin->collection = $collection;
    }

    /**
     * Determines the file types allowed to be uploaded
     *
     * @return bool True if the action was performed.
     */
    protected function taskListmedia()
    {
        if (!$this->authoriseTask('list media', ['admin.pages', 'admin.super'])) {
            return;
        }

        $page = $this->admin->page(true);

        if (!$page) {
            $this->admin->json_response = ['status' => 'error', 'message' => 'No Page found'];
            return false;
        }

        $media_list = array();
        foreach ($page->media()->all() as $name => $media) {
            $media_list[$name] = ['url' => $media->cropZoom(150, 100)->url(), 'size' => $media->get('size')];
        }
        $this->admin->json_response = ['status' => 'ok', 'results' => $media_list];

        return true;
    }

    /**
     * Handles adding a media file to a page
     */
    protected function taskAddmedia()
    {
        if (!$this->authoriseTask('add media', ['admin.pages', 'admin.super'])) {
            return;
        }

        $page = $this->admin->page(true);

        /** @var Config $config */
        $config = $this->grav['config'];

        if (!isset($_FILES['file']['error']) || is_array($_FILES['file']['error'])) {
            $this->admin->json_response = ['status' => 'error', 'message' => 'Invalid Parameters'];
            return;
        }

        // Check $_FILES['file']['error'] value.
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                $this->admin->json_response = ['status' => 'error', 'message' => 'No files sent'];
                return;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $this->admin->json_response = ['status' => 'error', 'message' => 'Exceeded filesize limit.'];
                return;
            default:
                $this->admin->json_response = ['status' => 'error', 'message' => 'Unkown errors'];
                return;
        }

        $grav_limit = $config->get('system.media.upload_limit', 0);
        // You should also check filesize here.
        if ($grav_limit > 0 && $_FILES['file']['size'] > grav_limit) {
            $this->admin->json_response = ['status' => 'error', 'message' => 'Exceeded Grav filesize limit.'];
            return;
        }


        // Check extension
        $fileParts = pathinfo($_FILES['file']['name']);
        $fileExt = strtolower($fileParts['extension']);

        // If not a supported type, return
        if (!$config->get("media.{$fileExt}")) {
            $this->admin->json_response = ['status' => 'error', 'message' => 'Unsupported file type: '.$fileExt];
            return;
        }


        // Upload it
        if (!move_uploaded_file($_FILES['file']['tmp_name'], sprintf('%s/%s', $page->path(), $_FILES['file']['name']))) {
            $this->admin->json_response = ['status' => 'error', 'message' => 'Failed to move uploaded file.'];
            return;
        }

        $this->admin->json_response = ['status' => 'success', 'message' => 'File uploaded successfully'];

        return;
    }

    /**
     * Handles deleting a media file from a page
     *
     * @return bool True if the action was performed.
     */
    protected function taskDelmedia()
    {
        if (!$this->authoriseTask('delete media', ['admin.pages', 'admin.super'])) {
            return;
        }

        $page = $this->admin->page(true);

        if (!$page) {
            $this->admin->json_response = ['status' => 'error', 'message' => 'No Page found'];
            return false;
        }

        $filename = !empty($this->post['filename']) ? $this->post['filename'] : null;
        if ($filename) {
            $targetPath = $page->path().'/'.$filename;

            if (file_exists($targetPath)) {
                if (unlink($targetPath)) {
                    $this->admin->json_response = ['status' => 'success', 'message' => 'File deleted: '.$filename];
                } else {
                    $this->admin->json_response = ['status' => 'error', 'message' => 'File could not be deleted: '.$filename];
                }
            } else {
                $this->admin->json_response = ['status' => 'error', 'message' => 'File not found: '.$filename];
            }
        } else {
            $this->admin->json_response = ['status' => 'error', 'message' => 'No file found'];
        }

        return true;
    }

    /**
     * Process the page Markdown
     */
    protected function taskProcessMarkdown()
    {
//        if (!$this->authoriseTask('process markdown', ['admin.pages', 'admin.super'])) {
//            return;
//        }

        try {
            $page = $this->admin->page(true);

            if (!$page) {
                $this->admin->json_response = ['status' => 'error', 'message' => 'No Page found'];
                return false;
            }

            $this->preparePage($page, true);
            $page->header();
            $html = $page->content();

            $this->admin->json_response = ['status' => 'success', 'message' => $html];
            return true;
        } catch (\Exception $e) {
            $this->admin->json_response = ['status' => 'error', 'message' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Enable a plugin.
     *
     * @return bool True if the action was performed.
     */
    public function taskEnable()
    {
        if (!$this->authoriseTask('enable plugin', ['admin.plugins', 'admin.super'])) {
            return;
        }

        if ($this->view != 'plugins') {
            return false;
        }

        // Filter value and save it.
        $this->post = array('enabled' => 1, '_redirect' => 'plugins');
        $obj = $this->prepareData();
        $obj->save();
        $this->admin->setMessage('Successfully enabled plugin', 'info');

        return true;
    }

    /**
     * Disable a plugin.
     *
     * @return bool True if the action was performed.
     */
    public function taskDisable()
    {
        if (!$this->authoriseTask('disable plugin', ['admin.plugins', 'admin.super'])) {
            return;
        }

        if ($this->view != 'plugins') {
            return false;
        }

        // Filter value and save it.
        $this->post = array('enabled' => 0, '_redirect' => 'plugins');
        $obj = $this->prepareData();
        $obj->save();
        $this->admin->setMessage('Successfully disabled plugin', 'info');

        return true;
    }

    /**
     * Set the default theme.
     *
     * @return bool True if the action was performed.
     */
    public function taskActivate()
    {
        if (!$this->authoriseTask('activate theme', ['admin.themes', 'admin.super'])) {
            return;
        }

        if ($this->view != 'themes') {
            return false;
        }

        $this->post = array('_redirect' => 'themes');

        // Make sure theme exists (throws exception)
        $name = $this->route;
        $this->grav['themes']->get($name);

        // Store system configuration.
        $system = $this->admin->data('system');
        $system->set('pages.theme', $name);
        $system->save();

        // Force configuration reload and save.
        /** @var Config $config */
        $config = $this->grav['config'];
        $config->reload()->save();

        // TODO: find out why reload and save doesn't always update the object itself (and remove this workaround).
        $config->set('system.pages.theme', $name);

        $this->admin->setMessage('Successfully changed default theme.', 'info');

        return true;
    }

    /**
     * Handles installing plugins and themes
     *
     * @return bool True is the action was performed
     */
    public function taskInstall()
    {
        $type = $this->view === 'plugins' ? 'plugins' : 'themes';
        if (!$this->authoriseTask('install ' . $type, ['admin.' . $type, 'admin.super'])) {
            return;
        }

        require_once __DIR__ . '/gpm.php';

        $package = $this->route;

        $result = \Grav\Plugin\Admin\Gpm::install($package, []);

        if ($result) {
            $this->admin->setMessage("Installation successful.", 'info');
        } else {
            $this->admin->setMessage("Installation failed.", 'error');
        }

        $this->post = array('_redirect' => $this->view . '/' . $this->route);

        return true;
    }

    /**
     * Handles updating Grav
     *
     * @return bool True is the action was performed
     */
    public function taskUpdategrav()
    {
        require_once __DIR__ . '/gpm.php';

        if (!$this->authoriseTask('install grav', ['admin.super'])) {
            return;
        }

        $result = \Grav\Plugin\Admin\Gpm::selfupgrade();

        if ($result) {
            $this->admin->json_response = ['status' => 'success', 'message' => 'Grav was successfully updated to '];
        } else {
            $this->admin->json_response = ['status' => 'error', 'message' => 'Grav update failed <br>' . Installer::lastErrorMsg()];
        }

        return true;
    }

    /**
     * Handles updating plugins and themes
     *
     * @return bool True is the action was performed
     */
    public function taskUpdate()
    {
        require_once __DIR__ . '/gpm.php';

        $package = $this->route;
        $permissions = [];

        // Update multi mode
        if (!$package) {
            $package = [];

            if ($this->view === 'plugins' || $this->view === 'update') {
                $package = $this->admin->gpm()->getUpdatablePlugins();
                $permissions['plugins'] = ['admin.super', 'admin.plugins'];
            }

            if ($this->view === 'themes' || $this->view === 'update') {
                $package = array_merge($package, $this->admin->gpm()->getUpdatableThemes());
                $permissions['themes'] = ['admin.super', 'admin.themes'];
            }
        }

        foreach ($permissions as $type => $p) {
            if (!$this->authoriseTask('update ' . $type , $p)) {
                return;
            }
        }

        $result = \Grav\Plugin\Admin\Gpm::update($package, []);

        if ($this->view === 'update') {

            if ($result) {
                $this->admin->json_response = ['status' => 'success', 'message' => 'Everything updated'];
            } else {
                $this->admin->json_response = ['status' => 'error', 'message' => 'Updates failed'];
            }

        } else {
            if ($result) {
                $this->admin->setMessage("Installation successful.", 'info');
            } else {
                $this->admin->setMessage("Installation failed.", 'error');
            }

            $this->post = array('_redirect' => $this->view . '/' . $this->route);
        }

        return true;
    }

    /**
     * Handles uninstalling plugins and themes
     *
     * @return bool True is the action was performed
     */
    public function taskUninstall()
    {
        $type = $this->view === 'plugins' ? 'plugins' : 'themes';
        if (!$this->authoriseTask('uninstall ' . $type, ['admin.' . $type, 'admin.super'])) {
            return;
        }

        require_once __DIR__ . '/gpm.php';

        $package = $this->route;

        $result = \Grav\Plugin\Admin\Gpm::uninstall($package, []);

        if ($result) {
            $this->admin->setMessage("Uninstall successful.", 'info');
        } else {
            $this->admin->setMessage("Uninstall failed.", 'error');
        }

        $this->post = array('_redirect' => $this->view);

        return true;
    }

    /**
     * Handles form and saves the input data if its valid.
     *
     * @return bool True if the action was performed.
     */
    public function taskSave()
    {
        if (!$this->authoriseTask('save', $this->dataPermissions())) {
            return;
        }

        $reorder = false;
        $data = $this->post;

        // Special handler for pages data.
        if ($this->view == 'pages') {
            /** @var Page\Pages $pages */
            $pages = $this->grav['pages'];

            // Find new parent page in order to build the path.
            $route = !isset($data['route']) ? dirname($this->admin->route) : $data['route'];
            $parent = $route && $route != '/' ? $pages->dispatch($route, true) : $pages->root();

            $obj = $this->admin->page(true);
            $original_slug = $obj->slug();

            // Change parent if needed and initialize move (might be needed also on ordering/folder change).
            $obj = $obj->move($parent);
            $this->preparePage($obj);

            // Reset slug and route. For now we do not support slug twig variable on save.
            $obj->slug($original_slug);

            $obj->validate();
            $obj->filter();
            $visible_after = $obj->visible();

            // force reordering
            $reorder = true;

            // rename folder based on visible
            if ($visible_after && !$obj->order()) {
                // needs to have order set
                $obj->order(1000);
            } elseif (!$visible_after && $obj->order()) {
                // needs to have order removed
                $obj->folder($obj->slug());
            }

        } else {
            // Handle standard data types.
            $obj = $this->prepareData();
            $obj->validate();
            $obj->filter();
        }

        if ($obj) {
            $obj->save($reorder);
            $this->admin->setMessage('Successfully saved', 'info');
        }

        if ($this->view != 'pages') {
            // Force configuration reload.
            /** @var Config $config */
            $config = $this->grav['config'];
            $config->reload();

            if ($this->view === 'users') {
                $this->grav['user']->merge(User::load($this->admin->route)->toArray());
            }
        }

        // Always redirect if a page route was changed, to refresh it
        if ($obj instanceof Page\Page) {
            if (method_exists($obj, 'unsetRoute')) {
                $obj->unsetRoute();
            }
            $this->setRedirect($this->view . $obj->route());
        }

        return true;
    }


    /**
     * Continue to the new page.
     *
     * @return bool True if the action was performed.
     */
    public function taskContinue()
    {
        if ($this->view == 'users') {
            $this->setRedirect("{$this->view}/{$this->post['username']}");
            return true;
        }

        if ($this->view != 'pages') {
            return false;
        }

        $data = $this->post;
        $route = $data['route'] != '/' ? $data['route'] : '';
        $folder = ltrim($data['folder'], '_');
        if (!empty($data['modular'])) {
            $folder = '_' . $folder;
        }
        $path = $route . '/' . $folder;

        $this->admin->session()->{$path} = $data;
        $this->setRedirect("{$this->view}/{$path}");

        return true;
    }

    /**
     * Save page as a new copy.
     *
     * @return bool True if the action was performed.
     * @throws \RuntimeException
     */
    protected function taskCopy()
    {
        if (!$this->authoriseTask('copy page', ['admin.pages', 'admin.super'])) {
            return;
        }

        // Only applies to pages.
        if ($this->view != 'pages') {
            return false;
        }

        try {
            /** @var Page\Pages $pages */
            $pages = $this->grav['pages'];
            $data = $this->post;

            // And then get the current page.
            $page = $this->admin->page(true);

            // Find new parent page in order to build the path.
            $parent = $page->parent() ?: $pages->root();

            // Make a copy of the current page and fill the updated information into it.
            $page = $page->copy($parent);
            $this->preparePage($page);

            // Make sure the header is loaded in case content was set through raw() (expert mode)
            $page->header();

            // Deal with folder naming conflicts, but limit number of searches to 99.
            $break = 99;
            while ($break > 0 && file_exists($page->filePath())) {
                $break--;
                $match = preg_split('/-(\d+)$/', $page->path(), 2, PREG_SPLIT_DELIM_CAPTURE);
                $page->path($match[0] . '-' . (isset($match[1]) ? (int) $match[1] + 1 : 2));
                // Reset slug and route. For now we do not support slug twig variable on save.
                $page->slug('');
            }

            $page->save();

            // Enqueue message and redirect to new location.
            $this->admin->setMessage('Successfully copied', 'info');
            $this->setRedirect($this->view . '/' . $parent->route() . '/'. $page->slug());

        } catch (\Exception $e) {
            throw new \RuntimeException('Copying page failed on error: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Reorder pages.
     *
     * @return bool True if the action was performed.
     */
    protected function taskReorder()
    {
        if (!$this->authoriseTask('reorder pages', ['admin.pages', 'admin.super'])) {
            return;
        }

        // Only applies to pages.
        if ($this->view != 'pages') {
            return false;
        }

        $this->admin->setMessage('Reordering was successful', 'info');
        return true;
    }

    /**
     * Delete page.
     *
     * @return bool True if the action was performed.
     * @throws \RuntimeException
     */
    protected function taskDelete()
    {
        if (!$this->authoriseTask('delete page', ['admin.pages', 'admin.super'])) {
            return;
        }

        // Only applies to pages.
        if ($this->view != 'pages') {
            return false;
        }

        /** @var Uri $uri */
        $uri = $this->grav['uri'];

        try {
            $page = $this->admin->page();
            Folder::delete($page->path());

            $results = Cache::clearCache('standard');

            // Set redirect to either referrer or pages list.
            $redirect = $uri->referrer();
            if ($redirect == $uri->route()) {
                $redirect = 'pages';
            }

            $this->admin->setMessage('Successfully deleted', 'info');
            $this->setRedirect($redirect);

        } catch (\Exception $e) {
            throw new \RuntimeException('Deleting page failed on error: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Prepare and return POST data.
     *
     * @param array $post
     * @return array
     */
    protected function &getPost($post)
    {
        unset($post['task']);

        // Decode JSON encoded fields and merge them to data.
        if (isset($post['_json'])) {
            $post = array_merge_recursive($post, $this->jsonDecode($post['_json']));
            unset($post['_json']);
        }
        return $post;
    }

    /**
     * Recursively JSON decode data.
     *
     * @param  array $data
     * @return array
     */
    protected function jsonDecode(array $data)
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->jsonDecode($value);
            } else {
                $value = json_decode($value, true);
            }
        }
        return $data;
    }

    /**
     * Sets the page redirect.
     *
     * @param string $path The path to redirect to
     * @param int $code The HTTP redirect code
     */
    protected function setRedirect($path, $code = 303)
    {
        $this->redirect = $path;
        $this->code = $code;
    }

    /**
     * Gets the configuration data for a given view & post
     *
     * @return object
     */
    protected function prepareData()
    {
        $type = trim("{$this->view}/{$this->admin->route}", '/');
        $data = $this->admin->data($type, $this->post);

        return $data;
    }

    /**
     * Gets the permissions needed to access a given view
     *
     * @return array An array of permissions
     */
    protected function dataPermissions()
    {
        $type = $this->view;
        $permissions = ['admin.super'];

        switch ($type) {
            case 'configuration':
            case 'system':
                $permissions[] = ['admin.configuration'];
                break;
            case 'settings':
            case 'site':
                $permissions[] = ['admin.settings'];
                break;
            case 'plugins':
                $permissions[] = ['admin.plugins'];
                break;
            case 'themes':
                $permissions[] = ['admin.themes'];
                break;
            case 'users':
                $permissions[] = ['admin.users'];
                break;
        }

        return $permissions;
    }

    /**
     * Prepare a page to be stored: update its folder, name, template, header and content
     *
     * @param \Grav\Common\Page\Page $page
     * @param bool                   $clean_header
     */
    protected function preparePage(\Grav\Common\Page\Page $page, $clean_header = false)
    {
        $input = $this->post;

        $order = max(0, (int) isset($input['order']) ? $input['order'] : $page->value('order'));
        $ordering = $order ? sprintf('%02d.', $order) : '';
        $slug = empty($input['folder']) ? $page->value('folder') : (string) $input['folder'];
        $page->folder($ordering . $slug);


        if (isset($input['type']) && !empty($input['type'])) {
            $type = (string) strtolower($input['type']);
            $name = preg_replace('|.*/|', '', $type) . '.md';
            $page->name($name);
            $page->template($type);
        }

        // Special case for Expert mode: build the raw, unset content
        if (isset($input['frontmatter']) && isset($input['content'])) {
            $page->raw("---\n" . (string) $input['frontmatter'] . "\n---\n" . (string) $input['content']);
            unset($input['content']);
        }

        if (isset($input['header'])) {
            $header = $input['header'];

            foreach($header as $key => $value) {
                if ($key == 'metadata') {
                    foreach($header['metadata'] as $key2 => $value2) {
                        if (isset($input['toggleable_header']['metadata'][$key2]) && !$input['toggleable_header']['metadata'][$key2]) {
                            $header['metadata'][$key2] = '';
                        }
                    }
                } else {
                    if (isset($input['toggleable_header'][$key]) && !$input['toggleable_header'][$key]) {
                        $header[$key] = '';
                    }
                }
            }
            if ($clean_header) {
                $header = Utils::arrayFilterRecursive($header, function($k, $v) {
                    return !(is_null($v) || $v === '');
                });
            }
            $page->header((object) $header);
            $page->frontmatter(Yaml::dump((array) $page->header()));
        }
        // Fill content last because it also renders the output.
        if (isset($input['content'])) {
            $page->rawMarkdown((string) $input['content']);
        }
    }

    /**
     * Checks if the user is allowed to perform the given task with its associated permissions
     *
     * @param string $task The task to execute
     * @param array $permissions The permissions given
     * @return bool True if authorized. False if not.
     */
    protected function authoriseTask($task = '', $permissions = [])
    {
        if (!$this->admin->authorise($permissions)) {
            if ($this->grav['uri']->extension() === 'json')
                $this->admin->json_response = ['status' => 'unauthorized', 'message' => 'You have insufficient permissions for task ' . $task . '.'];
            else
                $this->admin->setMessage('You have insufficient permissions for task ' . $task . '.', 'error');

            return false;
        }

        return true;
    }
}
