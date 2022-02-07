<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Application;

use Grav\Console\Gpm\DirectInstallCommand;
use Grav\Console\Gpm\IndexCommand;
use Grav\Console\Gpm\InfoCommand;
use Grav\Console\Gpm\InstallCommand;
use Grav\Console\Gpm\SelfupgradeCommand;
use Grav\Console\Gpm\UninstallCommand;
use Grav\Console\Gpm\UpdateCommand;
use Grav\Console\Gpm\VersionCommand;

/**
 * Class GpmApplication
 * @package Grav\Console\Application
 */
class GpmApplication extends Application
{
    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);

        $this->addCommands([
            new IndexCommand(),
            new VersionCommand(),
            new InfoCommand(),
            new InstallCommand(),
            new UninstallCommand(),
            new UpdateCommand(),
            new SelfupgradeCommand(),
            new DirectInstallCommand(),
        ]);
    }
}
