<?php
namespace Grav\Plugin;

use Grav\Common\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Themes;
use Grav\Common\Uri;
use Grav\Common\Data;
use Grav\Common\Page;

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
     */
    public function execute()
    {
        // Grab redirect parameter.
        $redirect = isset($this->post['_redirect']) ? $this->post['_redirect'] : null;
        unset($this->post['_redirect']);

        $success = false;
        $method = 'task' . ucfirst($this->task);
        if (method_exists($this, $method)) {
            try {
                $success = call_user_func(array($this, $method));
            } catch (\RuntimeException $e) {
                $success = true;
                $this->admin->setMessage($e->getMessage());
            }
            // Redirect if requested.
            if ($redirect) {
                $this->setRedirect($redirect);
            }
        }
        return $success;
    }

    public function redirect()
    {
        if (!$this->redirect) {
            return;
        }

        $base = $this->admin->base;
        $path = trim(substr($this->redirect, 0, strlen($base)) == $base
            ? substr($this->redirect, strlen($base)) : $this->redirect, '/');

        $this->grav->redirect($base . '/' . preg_replace('|/+|', '/', $path), $this->redirectCode);
    }

    /**
     * Handle login.
     *
     * @return bool True if the action was performed.
     */
    protected function taskLogin()
    {
        if ($this->admin->authenticate($this->post)) {
            $this->admin->setMessage('You have been logged in.');
        } else {
            $this->admin->setMessage('Login failed.');
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
        $this->admin->session()->invalidate()->start();
        $this->admin->setMessage('You have been logged out.');
        $this->setRedirect('/');

        return true;
    }

    protected function taskListmedia()
    {
        $page = $this->admin->page(true);

        if (!$page) {
            $this->admin->json_response = ['error', 'No Page found'];
            return;
        }

        $media_list = array();
        foreach ($page->media()->all() as $name=> $media) {
            $media_list[$name] = ['url'=>$media->cropZoom(150,100)->url(),'size'=>$media->get('size')];
        }
        $this->admin->media = $media_list;

        return true;
    }

    protected function taskAddmedia()
    {
        $page = $this->admin->page(true);
        $config = $this->grav['config'];

        if (!empty($_FILES)) {
            $tempFile = $_FILES['file']['tmp_name'];
            $targetName = $_FILES['file']['name'];

            $fileParts = pathinfo($targetName);
            $fileExt = $fileParts['extension'];

            // If not a supported type, return
            if (!$config->get("media.{$fileExt}")) {
                $this->admin->json_response = ['error', 'Unsupported file type: '.$fileExt];
                return;
            }

            // Valid file type, so save it.
            $targetPath = $page->path();
            $targetFile =  $targetPath.'/'.$targetName;
            move_uploaded_file($tempFile,$targetFile);
            $this->admin->json_response = ['success', 'File uploaded successfully'];
        } else {
            $this->admin->json_response = ['error', 'No file found'];
        }
        return;
    }

    protected function taskDelmedia()
    {
        $page = $this->admin->page(true);
        $uri = $this->grav['uri'];

        if (!$page) {
            $this->admin->json_response = ['error', 'No Page found'];
            return;
        }

        $filename = !empty($this->post['filename']) ? $this->post['filename'] : null;
        if ($filename) {
            $targetPath = $page->path().'/'.$filename;

            if (unlink($targetPath)) {
                $this->admin->json_response = ['success', 'File deleted: '.$filename];
            } else {
                $this->admin->json_response = ['error', 'File could not be deleted: '.$filename];
            }
        } else {
            $this->admin->json_response = ['error', 'No file found'];
        }
        return true;
    }

    /**
     * Enable plugin.
     *
     * @return bool True if the action was performed.
     */
    public function taskEnable()
    {
        if ($this->view != 'plugins') {
            return false;
        }

        // Filter value and save it.
        $this->post = array('enabled' => !empty($this->post['enabled']));
        $obj = $this->prepareData();
        $obj->save();
        $this->admin->setMessage('Successfully saved');

        return true;
    }

    /**
     * Set default theme.
     *
     * @return bool True if the action was performed.
     */
    public function taskSet_theme()
    {
        if ($this->view != 'themes') {
            return false;
        }

        // Make sure theme exists (throws exception)
        $name = !empty($this->post['theme']) ? $this->post['theme'] : '';
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

        $this->admin->setMessage('Successfully changed default theme.');

        return true;
    }

    /**
     * Handles form and saves the input data if its valid.
     *
     * @return bool True if the action was performed.
     */
    public function taskSave()
    {
        $data = $this->post;

        // Special handler for pages data.
        if ($this->view == 'pages') {
            /** @var Page\Pages $pages */
            $pages = $this->grav['pages'];

            // Find new parent page in order to build the path.
            $route = !isset($data['route']) ? dirname($this->admin->route) : $data['route'];
            $parent = $route ? $pages->dispatch($route, true) : $pages->root();
            $obj = $this->admin->page(true);

            // Change parent if needed and initialize move (might be needed also on ordering/folder change).
            $obj = $obj->move($parent);
            $this->preparePage($obj);

            // Reset slug and route. For now we do not support slug twig variable on save.
            $obj->slug('');


        } else {
            // Handle standard data types.
            $obj = $this->prepareData();
        }
        if ($obj) {
            $obj->validate();
            $obj->filter();
            $obj->save();
            $this->admin->setMessage('Successfully saved');
        }

        // Redirect to new location.
        if ($obj instanceof Page\Page && $obj->route() != $this->admin->route()) {
            $this->setRedirect($this->view . '/' . $obj->route());
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
        // Only applies to pages.
        if ($this->view != 'pages') {
            return false;
        }

        $data = $this->post;
        $route = $data['route'] != '/' ? $data['route'] : '';
        $folder = $data['folder'];
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
        // Only applies to pages.
        if ($this->view != 'pages') {
            return false;
        }

        try {
            /** @var Page\Pages $pages */
            $pages = $this->grav['pages'];
            $data = $this->post;

            // Find new parent page in order to build the path.
            $parent = empty($data['route']) ? $pages->root() : $pages->dispatch($data['route'], true);

            // And then get the current page.
            $page = $this->admin->page(true);

            // Make a copy of the current page and fill the updated information into it.
            $page = $page->copy($parent);
            $this->preparePage($page);

            // Deal with folder naming conflicts, but limit number of searches to 99.
            $break = 99;
            while ($break > 0 && file_exists($page->filePath())) {
                $break--;
                $match = preg_split('/-(\d+)$/', $page->path(), 2, PREG_SPLIT_DELIM_CAPTURE);
                $page->path($match[0] . '-' . (isset($match[1]) ? (int) $match[1] + 1 : 2));
                // Reset slug and route. For now we do not support slug twig variable on save.
                $page->slug('');
            }

            // Validation, type filtering and saving the changes.
            $page->validate();
            $page->filter();
            $page->save();

            // Enqueue message and redirect to new location.
            $this->admin->setMessage('Successfully copied');
            $this->setRedirect($this->view . '/' . $page->route());

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
        // Only applies to pages.
        if ($this->view != 'pages') {
            return false;
        }

        $this->admin->setMessage('Reordering was successful');
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
        // Only applies to pages.
        if ($this->view != 'pages') {
            return false;
        }

        /** @var Uri $uri */
        $uri = $this->grav['uri'];

        try {
            $page = $this->admin->page();
            Folder::delete($page->path());

            // Set redirect to either referrer or one level up.
            $redirect = $uri->referrer();
            if ($redirect == $uri->route()) {
                $redirect = dirname($redirect);
            }

            $this->admin->setMessage('Successfully deleted');
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

    protected function setRedirect($path, $code = 303) {
        $this->redirect = $path;
        $this->code = $code;
    }

    protected function prepareData()
    {
        $type = trim("{$this->view}/{$this->admin->route}", '/');
        $data = $this->admin->data($type, $this->post);

        return $data;
    }

    protected function preparePage(\Grav\Common\Page\Page $page)
    {
        $input = $this->post;

        $order = max(0, (int) isset($input['order']) ? $input['order'] : $page->value('order'));
        $ordering = $order ? sprintf('%02d.', $order) : '';
        $slug = empty($input['folder']) ? $page->value('folder') : (string) $input['folder'];
        $page->folder($ordering . $slug);

        if (isset($input['type'])) {
            $page->name(((string) $input['type']) . '.md');
        }

        // special case for Expert mode build the raw, unset content
        if (isset($input['frontmatter']) && isset($input['content'])) {
            $page->raw("---\n" . (string) $input['frontmatter'] . "\n---\n" . (string) $input['content']);
            unset($input['content']);
        }

        if (isset($input['header'])) {
            $page->header((object) $input['header']);
        }
        // Fill content last because of it also renders the output.
        if (isset($input['content'])) {
            $page->content((string) $input['content']);
        }
    }
}
