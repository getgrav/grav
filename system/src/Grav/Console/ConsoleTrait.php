<?php
namespace Grav\Console;

use Grav\Common\GravTrait;
use Grav\Common\Cache;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArrayInput;

trait ConsoleTrait
{
    use GravTrait;

    protected $repository = 'http://getgrav.org/downloads';
    protected $argv;
    protected $input;
    protected $output;

    /**
     * Set colors style definition for the formatter.
     */
    public function setupConsole($input, $output)
    {
        self::$grav['config']->set('system.cache.driver', 'default');
        $this->argv = $_SERVER['argv'][0];

        $this->input  = $input;
        $this->output = $output;

        $this->output->getFormatter()->setStyle('normal',  new OutputFormatterStyle('white'));
        $this->output->getFormatter()->setStyle('yellow',  new OutputFormatterStyle('yellow',  null, array('bold')));
        $this->output->getFormatter()->setStyle('red',     new OutputFormatterStyle('red',     null, array('bold')));
        $this->output->getFormatter()->setStyle('cyan',    new OutputFormatterStyle('cyan',    null, array('bold')));
        $this->output->getFormatter()->setStyle('green',   new OutputFormatterStyle('green',   null, array('bold')));
        $this->output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, array('bold')));
        $this->output->getFormatter()->setStyle('white',   new OutputFormatterStyle('white',   null, array('bold')));
    }

    /**
     * Fetches the data from getgrav.org
     * @return string   Returns the data fetched or from cache in JSON format
     */
    public function fetchData()
    {
        $fetchCommand = $this->getApplication()->find('fetch');
        $args         = new ArrayInput(array('command' => 'fetch', '-f' => $this->input->getOption('force')));
        $commandExec  = $fetchCommand->run($args, $this->output);

        if ($commandExec != 0){
            $URL = parse_url($this->repository, PHP_URL_HOST);
            $this->output->writeln("<red>Error:</red> An error occured while trying to fetch data from <cyan>$URL</cyan>");
            exit;
        }

        return self::$grav['cache']->fetch(md5('cli:gpm'));
    }
}
