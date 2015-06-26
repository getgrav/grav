<?php
namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class UpdateCommand
 * @package Grav\Console\Gpm
 */
class UpdateCommand extends Command
{
    use ConsoleTrait;

    /**
     * @var
     */
    protected $data;
    /**
     * @var
     */
    protected $extensions;
    /**
     * @var
     */
    protected $updatable;
    /**
     * @var
     */
    protected $destination;
    /**
     * @var
     */
    protected $file;
    /**
     * @var array
     */
    protected $types = array('plugins', 'themes');
    /**
     * @var GPM $gpm
     */
    protected $gpm;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("update")
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force re-fetching the data from remote'
            )
            ->addOption(
                'destination',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The grav instance location where the updates should be applied to. By default this would be where the grav cli has been launched from',
                GRAV_ROOT
            )
            ->addArgument(
                'package',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The package or packages that is desired to update. By default all available updates will be applied.'
            )
            ->setDescription("Detects and performs an update of plugins and themes when available")
            ->setHelp('The <info>update</info> command updates plugins and themes when a new version is available');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output);

        $this->gpm = new GPM($this->input->getOption('force'));
        $this->destination = realpath($this->input->getOption('destination'));

        if (!Installer::isGravInstance($this->destination)) {
            $this->output->writeln("<red>ERROR</red>: " . Installer::lastErrorMsg());
            exit;
        }

        $this->data = $this->gpm->getUpdatable();
        $onlyPackages = array_map('strtolower', $this->input->getArgument('package'));

        if (!$this->data['total']) {
            $this->output->writeln("Nothing to update.");
            exit;
        }

        $this->output->write("Found <green>" . $this->gpm->countInstalled() . "</green> extensions installed of which <magenta>" . $this->data['total'] . "</magenta> need updating");

        $limitTo = $this->userInputPackages($onlyPackages);

        $this->output->writeln('');

        unset($this->data['total']);
        unset($limitTo['total']);


        // updates review
        $slugs = [];

        $index = 0;
        foreach ($this->data as $packages) {
            foreach ($packages as $slug => $package) {
                if (count($limitTo) && !array_key_exists($slug, $limitTo)) {
                    continue;
                }

                $this->output->writeln(
                // index
                    str_pad($index++ + 1, 2, '0', STR_PAD_LEFT) . ". " .
                    // name
                    "<cyan>" . str_pad($package->name, 15) . "</cyan> " .
                    // version
                    "[v<magenta>" . $package->version . "</magenta> ➜ v<green>" . $package->available . "</green>]"
                );
                $slugs[] = $slug;
            }
        }

        // prompt to continue
        $this->output->writeln("");
        $questionHelper = $this->getHelper('question');
        $question = new ConfirmationQuestion("Continue with the update process? [Y|n] ", true);
        $answer = $questionHelper->ask($this->input, $this->output, $question);

        if (!$answer) {
            $this->output->writeln("Update aborted. Exiting...");
            exit;
        }

        // finally update
        $installCommand = $this->getApplication()->find('install');

        $args = new ArrayInput(array(
            'command' => 'install',
            'package' => $slugs,
            '-f'      => $this->input->getOption('force'),
            '-d'      => $this->destination,
            '-y'      => true
        ));
        $commandExec = $installCommand->run($args, $this->output);

        if ($commandExec != 0) {
            $this->output->writeln("<red>Error:</red> An error occurred while trying to install the extensions");
            exit;
        }

        // clear cache after successful upgrade
        $this->clearCache();
    }

    /**
     * @param $onlyPackages
     *
     * @return array
     */
    private function userInputPackages($onlyPackages)
    {
        $found = ['total' => 0];
        $ignore = [];

        if (!count($onlyPackages)) {
            $this->output->writeln('');
        } else {
            foreach ($onlyPackages as $onlyPackage) {
                $find = $this->gpm->findPackage($onlyPackage);

                if (!$find || !$this->gpm->isUpdatable($find->slug)) {
                    $name = isset($find->slug) ? $find->slug : $onlyPackage;
                    $ignore[$name] = $name;
                } else {
                    $found[$find->slug] = $find;
                    $found['total']++;
                }
            }

            if ($found['total']) {
                $list = $found;
                unset($list['total']);
                $list = array_keys($list);

                if ($found['total'] !== $this->data['total']) {
                    $this->output->write(", only <magenta>" . $found['total'] . "</magenta> will be updated");
                }

                $this->output->writeln('');
                $this->output->writeln("Limiting updates for only <cyan>" . implode('</cyan>, <cyan>',
                        $list) . "</cyan>");
            }

            if (count($ignore)) {
                $this->output->writeln("Packages not found or not requiring updates: <red>" . implode('</red>, <red>',
                        $ignore) . "</red>");
            }
        }

        return $found;
    }
}
