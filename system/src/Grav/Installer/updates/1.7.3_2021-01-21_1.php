<?php

use Grav\Installer\InstallException;
use Grav\Installer\VersionUpdate;
use Grav\Installer\YamlUpdater;

return [
    'preflight' => null,
    'postflight' =>
        function () {
            // Only reset GPM releases value if upgrading from Grav 1.7 RC.
            if (version_compare(GRAV_VERSION, '1.7', '<')) {
                return;
            }

            /** @var VersionUpdate $this */
            try {
                // Keep old defaults for backwards compatibility.
                $yaml = YamlUpdater::instance(GRAV_ROOT . '/user/config/system.yaml');
                if (!$yaml->isHandWritten()) {
                    $yaml->undefine('gpm.releases');
                    $yaml->save();
                }
            } catch (\Exception $e) {
                throw new InstallException('Could not update system configuration to maintain backwards compatibility', $e);
            }
        }
];
