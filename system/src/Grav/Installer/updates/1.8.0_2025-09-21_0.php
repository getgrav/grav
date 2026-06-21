<?php

use Grav\Installer\InstallException;
use Grav\Installer\VersionUpdate;
use Grav\Installer\YamlUpdater;

return [
    'preflight' => null,
    'postflight' =>
        function () {
            /** @var VersionUpdate $this */
            try {
                $yaml = YamlUpdater::instance(GRAV_ROOT . '/user/config/system.yaml');

                if ($yaml->exists('strict_mode.twig_compat')) {
                    $value = $yaml->get('strict_mode.twig_compat');
                    $yaml->undefine('strict_mode.twig_compat');
                    $yaml->define('strict_mode.twig2_compat', $value);
                }

                if (!$yaml->exists('strict_mode.twig2_compat')) {
                    $yaml->define('strict_mode.twig2_compat', false);
                }

                if (!$yaml->exists('strict_mode.twig3_compat')) {
                    $yaml->define('strict_mode.twig3_compat', true);
                }

                $yaml->save();
            } catch (\Exception $e) {
                throw new InstallException('Could not update system configuration for Twig compatibility settings', $e);
            }
        }
];
