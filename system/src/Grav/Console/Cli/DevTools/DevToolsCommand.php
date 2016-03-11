<?php
namespace Grav\Console\Cli\DevTools;

use Grav\Common\Grav;
use Grav\Common\Data;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\GPM;
use Grav\Common\Inflector;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils;
use RocketTheme\Toolbox\File\File;
use Grav\Console\ConsoleCommand;

/**
 * Class DevToolsCommand
 * @package Grav\Console\Cli\
 */
class DevToolsCommand extends ConsoleCommand
{

    /**
     * @var array
     */
    protected $component = [];

    /**
     * @Grav
     */
    protected $grav;

    /**
     * @var Inflector
     */
    protected $inflector;

    /**
     * @var Locator
     */
    protected $locator;

    /**
     * @var Twig
     */
    protected $twig;

    protected $data;

    /**
     * @var gpm
     */
    protected $gpm;


    /**
     *
     */
    protected function init()
    {
        $autoload = require_once GRAV_ROOT . '/vendor/autoload.php';
        if (!function_exists('curl_version')) {
            exit('FATAL: DEVTOOLS requires PHP Curl module to be installed');
        }

        $this->grav = Grav::instance(array('loader' => $autoload));
        $this->grav['config']->init();
        $this->grav['uri']->init();
        $this->grav['streams'];
        $this->inflector    = $this->grav['inflector'];
        $this->locator      = $this->grav['locator'];
        $this->twig         = new Twig($this->grav);
        $this->gpm          = new GPM(true);

        //Add `theme://` to prevent fail
        $this->locator->addPath('theme', '', []);
    }

    /**
     *
     */
    protected function createComponent()
    {
        $name       = $this->component['name'];
        $folderName = $this->inflector->hyphenize($name);
        $type       = $this->component['type'];

        $template   = $this->component['template'];
        $templateFolder     = __DIR__ . '/components/' . $type . DS . $template;
        $componentFolder    = $this->locator->findResource($type . 's://') . DS . $folderName;

        //Copy All files to component folder
        Folder::copy($templateFolder, $componentFolder);

        //Add Twig vars and templates then initialize
        $this->twig->twig_vars['component'] = $this->component;
        $this->twig->twig_paths[] = $templateFolder;
        $this->twig->init();

        //Get all templates of component then process each with twig and save
        $templates = Folder::all($componentFolder);
        foreach($templates as $templateFile) {
            if (Utils::endsWith($templateFile, '.twig') && !Utils::endsWith($templateFile, '.html.twig')) {
                $content = $this->twig->processTemplate($templateFile);
                $file = File::instance($componentFolder . DS . str_replace('.twig', '', $templateFile));
                $file->content($content);
                $file->save();

                //Delete twig template
                $file = File::instance($componentFolder . DS . $templateFile);
                $file->delete();
            }
        }
    }

    /**
     *
     */
    protected function validateOptions()
    {
        foreach (array_filter($this->options) as $type => $value) {
            $this->validate($type, $value);
        }
    }

    /**
     * @param        $type
     * @param        $value
     * @param string $extra
     *
     * @return mixed
     */
    protected function validate($type, $value, $extra = '')
    {
        switch ($type) {
            case 'name':
                //Check If name
                if ($value == null || trim($value) == '') {
                    throw new \RuntimeException('Name cannot be empty');
                }
                if (false != $this->gpm->findPackage($value)) {
                    throw new \RuntimeException('Package name exists in GPM');
                }

                break;

            case 'description':
                if($value == null || trim($value) == '') {
                    throw new \RuntimeException('Description cannot be empty');
                }

                break;

            case 'developer':
                if ($value === null || trim($value) == '') {
                    throw new \RuntimeException('Developer\'s Name cannot be empty');
                }

                break;

            case 'email':
                if (!preg_match('/^([a-z0-9_\.-]+)@([\da-z\.-]+)\.([a-z\.]{2,6})$/', $value)) {
                    throw new \RuntimeException('Not a valid email address');
                }

                break;
        }

        return $value;
    }
}
