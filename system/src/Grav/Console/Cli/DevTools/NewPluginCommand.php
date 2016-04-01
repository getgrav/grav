<?php
namespace Grav\Console\Cli\DevTools;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class NewPluginCommand
 * @package Grav\Console\Cli\DevTools
 */
class NewPluginCommand extends DevToolsCommand
{

    /**
     * @var array
     */
    protected $options = [];

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('new-plugin')
            ->setAliases(['newplugin'])
            ->addOption(
                'name',
                'pn',
                InputOption::VALUE_OPTIONAL,
                'The name of your new Grav plugin'
            )
            ->addOption(
                'description',
                'd',
                InputOption::VALUE_OPTIONAL,
                'A description of your new Grav plugin'
            )
            ->addOption(
                'developer',
                'dv',
                InputOption::VALUE_OPTIONAL,
                'The name/username of the developer'
            )
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_OPTIONAL,
                'The developer\'s email'
            )
            ->setDescription('Creates a new Grav plugin with the basic required files')
            ->setHelp('The <info>new-plugin</info> command creates a new Grav instance and performs the creation of a plugin.');
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $this->init();

        /**
         * @var array DevToolsCommand $component
         */
        $this->component['type']        = 'plugin';
        $this->component['template']    = 'blank';
        $this->component['version']     = '0.1.0'; // @todo add optional non prompting version argument

        $this->options = [
            'name'          => $this->input->getOption('name'),
            'description'   => $this->input->getOption('description'),
            'author'        => [
                'name'      => $this->input->getOption('developer'),
                'email'     => $this->input->getOption('email')
            ]
        ];

        $this->validateOptions();

        $this->component = array_replace($this->component, $this->options);

        $helper = $this->getHelper('question');

        if (!$this->options['name']) {
            $question = new Question('Enter <yellow>Plugin Name</yellow>: ');
            $question->setValidator(function ($value) {
                return $this->validate('name', $value);
            });

            $this->component['name'] = $helper->ask($this->input, $this->output, $question);
        }

        if (!$this->options['description']) {
            $question = new Question('Enter <yellow>Plugin Description</yellow>: ');
            $question->setValidator(function ($value) {
                return $this->validate('description', $value);
            });

            $this->component['description'] = $helper->ask($this->input, $this->output, $question);
        }

        if (!$this->options['author']['name']) {
            $question = new Question('Enter <yellow>Developer Name</yellow>: ');
            $question->setValidator(function ($value) {
                return $this->validate('developer', $value);
            });

            $this->component['author']['name'] = $helper->ask($this->input, $this->output, $question);
        }

        if (!$this->options['author']['email']) {
            $question = new Question('Enter <yellow>Developer Email</yellow>: ');
            $question->setValidator(function ($value) {
                return $this->validate('email', $value);
            });

            $this->component['author']['email'] = $helper->ask($this->input, $this->output, $question);
        }

        $this->component['template'] = 'blank';

        $this->createComponent();
    }

}
