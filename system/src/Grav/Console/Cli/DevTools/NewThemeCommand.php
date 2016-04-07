<?php
namespace Grav\Console\Cli\DevTools;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class NewThemeCommand
 * @package Grav\Console\Cli\DevTools
 */
class NewThemeCommand extends DevToolsCommand
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
            ->setName('new-theme')
            ->setAliases(['newtheme'])
            ->addOption(
                'name',
                'pn',
                InputOption::VALUE_OPTIONAL,
                'The name of your new Grav theme'
            )
            ->addOption(
                'description',
                'd',
                InputOption::VALUE_OPTIONAL,
                'A description of your new Grav theme'
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
            ->setDescription('Creates a new Grav theme with the basic required files')
            ->setHelp('The <info>new-theme</info> command creates a new Grav instance and performs the creation of a theme.');
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
        $this->component['type']        = 'theme';
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
            $question = new Question('Enter <yellow>Theme Name</yellow>: ');
            $question->setValidator(function ($value) {
                return $this->validate('name', $value);
            });

            $this->component['name'] = $helper->ask($this->input, $this->output, $question);
        }

        if (!$this->options['description']) {
            $question = new Question('Enter <yellow>Theme Description</yellow>: ');
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

        $question = new ChoiceQuestion(
            'Please choose a template type',
            array('pure-blank', 'inheritence')
        );
        $this->component['template'] = $helper->ask($this->input, $this->output, $question);

        if ($this->component['template'] == 'inheritence') {
            $themes = $this->gpm->getInstalledThemes();
            $installedThemes = [];
            foreach($themes as $key => $theme) {
                array_push($installedThemes, $key);
            }
            $question = new ChoiceQuestion(
                'Please choose a theme to extend: ',
                $installedThemes
            );
            $this->component['extends'] = $helper->ask($this->input, $this->output, $question);
        }
        $this->createComponent();
    }

}
