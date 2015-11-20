<?php
namespace Grav\Console\Cli;

use Grav\Console\ConsoleCommand;

/**
 * Class CleanCommand
 *
 * @package Grav\Console\Cli
 */
class NewUserCommand extends ConsoleCommand
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('newuser')
            ->setDescription('DEPRECATED: Creates a new user')
            ->setHelp('The <info>newuser</info> from `bin/grav` has been deprecated. Please refer to `bin/plugin login new-user')
        ;
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $this->output->writeln('');
        $this->output->writeln('<red>DEPRECATED COMMAND</red>');
        $this->output->writeln('  <white>`bin/grav new-user`</white> has been <red>deprecated</red> in favor of the new <white>`bin/plugin login new-user`</white>');
    }
}
