<?php

namespace Grav\Common;

class DependencyUtil
{
    /**
     * Checks plugin dependencies.  Call this after all plugins have been loaded and enabled.
     *
     * @param $plugin object A Plugin or the Data result of Plugin:get() and Plugin:all()
     * @param $issues array Receives issues as strings.  If null, grav['messages'] is used.
     * @return bool true if dependencies are met.
     */
    public static function checkDependencies($plugin, &$issues = null)
    {
        $grav = Grav::instance();
        $errors = 0;
        $messages = $grav['messages'];
        $plugins = $grav['plugins'];

        // $plugin can be $this (Plugin) from a plugin, or the result of Plugins::get (Data)
        if (get_class($plugin) === 'Grav\\Common\\Data\\Data') {
            $deps = $plugin->blueprints()->dependencies;
            $thisName = $plugin->blueprints()->name;
        } else {
            $deps = $plugin->getBlueprint()->dependencies;
            $thisName = $plugin['name'];

        }

        if ($deps) {
            foreach ($deps as $dep) {
                $name = $dep['name'];
                if ($name === 'grav') {
                    //TODO check grav version
                    continue;
                }
                $version = $dep['version'];
                if (!preg_match("#^([<>=~^]+)?((\d*)(\\.\d*)?(\\.\d*)?)#", $version, $m)) {
                    continue;
                }
                //$version = $m[2];
                $major = $m[3];
                if (isset($m[4])) {
                    $minor = $m[4];
                } else {
                    $minor = 0;
                }
                if (isset($m[5])) {
                    $patch = $m[5];
                } else {
                    $patch = 0;
                }
                $version = "$major.$minor.$patch";

                if (isset($m[1])) {
                    $compare = $m[1];
                } else {
                    $compare = '=';
                }
                if (!$compare) {
                    $compare = '=';
                } else if ($compare === '~') {
                    //TODO implement '~' patch
                    $compare = '>=';
                } else if ($compare === '^') {
                    //TODO implement '^' minor
                    $compare = '>=';
                }


                $found = $plugins->get($name);
                if (!$found) {
                    $msg = "Missing dependency: '$name'";
                    if (is_array($issues)) {
                        $issues[] = $msg;
                    } else {
                        $messages->add($msg, 'error');
                    }
                    $errors++;
                    continue;
                }
                if (!$grav['config']->get("plugins.$name.enabled")) {
                    //BUG admin should always be enabled if installed
                    if ($name !== 'admin') {
                        $msg = "Dependency not enabled: '$name'";
                        if (is_array($issues)) {
                            $issues[] = $msg;
                        } else {
                            $messages->add($msg, 'error');
                        }
                        $errors++;
                        continue;
                    }
                }
                $realVersion = $found->blueprints()->version;
                if (!version_compare($realVersion, $version, $compare)) {
                    $msg = "Missing dependency: '$name' $version";
                    $msg .= ' actual ' . $realVersion;
                    if (is_array($issues)) {
                        $issues[] = $msg;
                    } else {
                        $messages->add($msg, 'error');
                    }
                    $errors++;
                    continue;
                }
            }
        }
        if ($errors > 0) {
            $msg = "Plugin '$thisName' was not loaded due to dependency issues";
            if (is_array($issues)) {
                $issues[] = $msg;
            } else {
                $messages->add($msg, 'error');
            }
        }
        return $errors === 0;
    }
}