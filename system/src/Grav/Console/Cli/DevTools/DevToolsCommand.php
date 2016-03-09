<?php
namespace Grav\Console\Cli\DevTools;

use Grav\Common\Grav;
use Grav\Common\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\GPM;
use Grav\Common\Inflector;
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
            exit('FATAL: CLI requires PHP Curl module to be installed');
        }

        $this->grav = Grav::instance(array('loader' => $autoload));
        $this->grav['config']->init();
        $this->grav['uri']->init();
        $this->grav['streams'];
        $this->inflector    = $this->grav['inflector'];
        $this->locator      = $this->grav['locator'];
        $this->gpm = new GPM(true);
    }
    /**
     *
     */
    protected function copyComponent()
    {
        $name       = $this->component['name'];
        $folderName = $this->inflector->hyphenize($name);
        $type       = $this->component['type'];
        $template   = $this->component['template'];


        $templateFolder     = __DIR__ . '/components/' . $type . DS . $template;
        $componentFolder    = $this->locator->findResource($type . 's://') . DS . $folderName;

        Folder::copy($templateFolder, $componentFolder);
        return;
    }

    protected function renameComponent()
    {
        $name       = $this->component['name'];
        $className  = $this->inflector->camelize($name);
        $folderName = $this->inflector->hyphenize($name);
        $titleName  = $this->inflector->titleize($name);
        $description = $this->component['description'];
        $type       = $this->component['type'];
        $template   = $this->component['template'];

        unset($this->component['type']);
        unset($this->component['template']);

        $componentFolder = $this->locator->findResource($type . 's://') . DS . $folderName;

        rename($componentFolder . '/' . $type . '.php'   , $componentFolder . DS . $folderName . PLUGIN_EXT);
        rename($componentFolder . '/' . $type . '.yaml'  , $componentFolder . DS . $folderName . YAML_EXT);

        //PHP File

        $data = file_get_contents($componentFolder . DS . $folderName . PLUGIN_EXT);

        $data = str_replace('@@CLASSNAME@@', $className, $data); // @todo dynamic renaming
        $data = str_replace('@@HYPHENNAME@@', $folderName, $data);

        file_put_contents($componentFolder . DS . $folderName . PLUGIN_EXT, $data);

        //README File

        $data = file_get_contents($componentFolder . DS . 'README.md');

        $data = str_replace('@@NAME@@', $titleName, $data); // @todo dynamic renaming
        $data = str_replace('@@DESCRIPTION@@', $description, $data);

        file_put_contents($componentFolder . DS . 'README.md', $data);

        //Blueprints File
        $filename = $componentFolder . '/blueprints' . YAML_EXT;
        $file = CompiledYamlFile::instance($filename);

        $blueprints = new Data\Blueprints($type . 's://');
        $blueprint = $blueprints->get($folderName . '/blueprints');

        $obj = new Data\Data([], $blueprint);
        $obj->merge($this->component);
        $obj->file($file);
        $obj->save();

        return;
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
                    throw new \RuntimeException('Plugin Name cannot be empty');
                }
                if (false != $this->gpm->findPackage($value) || false != $this->gpm->isPluginInstalled($value) || false != $this->gpm->isThemeInstalled($value)) {
                    throw new \RuntimeException('Package exists (either as theme or plugin)');
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
