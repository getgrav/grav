<?php
namespace Grav\Console;

use Grav\Common\GravTrait;
use Grav\Console\Cli\ClearCacheCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArrayInput;

trait ConsoleTrait
{
    use GravTrait;

    protected $argv;
    protected $input;
    protected $output;

    /**
     * Set colors style definition for the formatter.
     */
    public function setupConsole($input, $output)
    {
        if (self::$grav) {
            self::$grav['config']->set('system.cache.driver', 'default');
        }

        $this->argv = $_SERVER['argv'][0];

        $this->input  = $input;
        $this->output = $output;

        $this->output->getFormatter()->setStyle('normal', new OutputFormatterStyle('white'));
        $this->output->getFormatter()->setStyle('yellow', new OutputFormatterStyle('yellow', null, array('bold')));
        $this->output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, array('bold')));
        $this->output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan', null, array('bold')));
        $this->output->getFormatter()->setStyle('green', new OutputFormatterStyle('green', null, array('bold')));
        $this->output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, array('bold')));
        $this->output->getFormatter()->setStyle('white', new OutputFormatterStyle('white', null, array('bold')));
    }

    private function isGravInstance($path)
    {
        if (!file_exists($path)) {
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination doesn't exist:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }

        if (!is_dir($path)) {
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination chosen to install is not a directory:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }

        if (!file_exists($path . DS . 'index.php') || !file_exists($path . DS . '.dependencies') || !file_exists($path . DS . 'system' . DS . 'config' . DS . 'system.yaml')) {
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination chosen to install does not appear to be a Grav instance:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }
    }

    public function clearCache()
    {
        $command = new ClearCacheCommand();
        $input = new ArrayInput(array('--all' => true));
        return $command->run($input, $this->output);
    }
}
