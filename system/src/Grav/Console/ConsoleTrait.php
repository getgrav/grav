<?php
namespace Grav\Console;

use Grav\Common\GravTrait;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

trait ConsoleTrait {
    use GravTrait;

    protected $argv;
    protected $input;
    protected $output;

    /**
     * Set colors style definition for the formatter.
     */
    public function setupConsole($input, $output) {
        self::$grav['config']->set('system.cache.driver', 'default');
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
}
