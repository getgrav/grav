<?php
namespace Grav\Console\Gpm;

use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class UninstallCommand
 * @package Grav\Console\Gpm
 */
class UninstallCommand extends Command
{
    use ConsoleTrait;

    /**
     * @var
     */
    protected $data;
    /**
     * @var
     */
    protected $gpm;
    /**
     * @var
     */
    protected $destination;
    /**
     * @var
     */
    protected $file;
    /**
     * @var
     */
    protected $tmp;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("uninstall")
            ->addOption(
                'all-yes',
                'y',
                InputOption::VALUE_NONE,
                'Assumes yes (or best approach) instead of prompting'
            )
            ->addArgument(
                'package',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'The package(s) that are desired to be removed. Use the "index" command for a list of packages'
            )
            ->setDescription("Performs the uninstallation of plugins and themes")
            ->setHelp('The <info>uninstall</info> command allows to uninstall plugins and themes');
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

        $this->gpm = new GPM();

        $packages = array_map('strtolower', $this->input->getArgument('package'));
        $this->data = ['total' => 0, 'not_found' => []];

        foreach ($packages as $package) {
            $plugin = $this->gpm->getInstalledPlugin($package);
            $theme = $this->gpm->getInstalledTheme($package);
            if ($plugin || $theme) {
                $this->data[strtolower($package)] = $plugin ?: $theme;
                $this->data['total']++;
            } else {
                $this->data['not_found'][] = $package;
            }
        }

        $this->output->writeln('');

        if (!$this->data['total']) {
            $this->output->writeln("Nothing to uninstall.");
            $this->output->writeln('');
            exit;
        }

        if (count($this->data['not_found'])) {
            $this->output->writeln("These packages were not found installed: <red>" . implode('</red>, <red>',
                    $this->data['not_found']) . "</red>");
        }

        unset($this->data['not_found']);
        unset($this->data['total']);

        foreach ($this->data as $slug => $package) {
            $this->output->writeln("Preparing to uninstall <cyan>" . $package->name . "</cyan> [v" . $package->version . "]");


            $this->output->write("  |- Checking destination...  ");
            $checks = $this->checkDestination($slug, $package);

            if (!$checks) {
                $this->output->writeln("  '- <red>Installation failed or aborted.</red>");
                $this->output->writeln('');
            } else {
                $this->output->write("  |- Uninstalling package...  ");
                $uninstall = $this->uninstallPackage($slug, $package);

                if (!$uninstall) {
                    $this->output->writeln("  '- <red>Uninstallation failed or aborted.</red>");
                    $this->output->writeln('');
                } else {
                    $this->output->writeln("  '- <green>Success!</green>  ");
                    $this->output->writeln('');
                }
            }
        }

        // clear cache after successful upgrade
        $this->clearCache();
    }


    /**
     * @param $slug
     * @param $package
     *
     * @return bool
     */
    private function uninstallPackage($slug, $package)
    {
        $path = self::getGrav()['locator']->findResource($package->package_type . '://' .$slug);
        Installer::uninstall($path);
        $errorCode = Installer::lastErrorCode();

        if ($errorCode && $errorCode !== Installer::IS_LINK && $errorCode !== Installer::EXISTS) {
            $this->output->write("\x0D");
            // extra white spaces to clear out the buffer properly
            $this->output->writeln("  |- Uninstalling package...  <red>error</red>                             ");
            $this->output->writeln("  |  '- " . Installer::lastErrorMsg());

            return false;
        }

        $this->output->write("\x0D");
        // extra white spaces to clear out the buffer properly
        $this->output->writeln("  |- Uninstalling package...  <green>ok</green>                             ");

        return true;
    }

    /**
     * @param $slug
     * @param $package
     *
     * @return bool
     */

    private function checkDestination($slug, $package)
    {
        $path = self::getGrav()['locator']->findResource($package->package_type . '://' . $slug);
        $questionHelper = $this->getHelper('question');
        $skipPrompt = $this->input->getOption('all-yes');

        Installer::isValidDestination($path);

        if (Installer::lastErrorCode() == Installer::IS_LINK) {
            $this->output->write("\x0D");
            $this->output->writeln("  |- Checking destination...  <yellow>symbolic link</yellow>");

            if ($skipPrompt) {
                $this->output->writeln("  |     '- <yellow>Skipped automatically.</yellow>");

                return false;
            }

            $question = new ConfirmationQuestion("  |  '- Destination has been detected as symlink, delete symbolic link first? [y|N] ",
                false);
            $answer = $questionHelper->ask($this->input, $this->output, $question);

            if (!$answer) {
                $this->output->writeln("  |     '- <red>You decided to not delete the symlink automatically.</red>");

                return false;
            }
        }

        $this->output->write("\x0D");
        $this->output->writeln("  |- Checking destination...  <green>ok</green>");

        return true;
    }
}
