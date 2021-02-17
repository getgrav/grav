<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Application;

use Grav\Console\Cli\BackupCommand;
use Grav\Console\Cli\CleanCommand;
use Grav\Console\Cli\ClearCacheCommand;
use Grav\Console\Cli\ComposerCommand;
use Grav\Console\Cli\InstallCommand;
use Grav\Console\Cli\LogViewerCommand;
use Grav\Console\Cli\NewProjectCommand;
use Grav\Console\Cli\PageSystemValidatorCommand;
use Grav\Console\Cli\SandboxCommand;
use Grav\Console\Cli\SchedulerCommand;
use Grav\Console\Cli\SecurityCommand;
use Grav\Console\Cli\ServerCommand;
use Grav\Console\Cli\YamlLinterCommand;

/**
 * Class GravApplication
 * @package Grav\Console\Application
 */
class GravApplication extends Application
{
    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);

        $this->addCommands([
            new InstallCommand(),
            new ComposerCommand(),
            new SandboxCommand(),
            new CleanCommand(),
            new ClearCacheCommand(),
            new BackupCommand(),
            new NewProjectCommand(),
            new SchedulerCommand(),
            new SecurityCommand(),
            new LogViewerCommand(),
            new YamlLinterCommand(),
            new ServerCommand(),
            new PageSystemValidatorCommand(),
        ]);
    }
}
