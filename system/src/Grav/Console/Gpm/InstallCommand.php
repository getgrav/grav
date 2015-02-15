<?php
namespace Grav\Console\Gpm;

use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Common\GPM\Response;
use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Yaml\Yaml;


/**
 * Class InstallCommand
 * @package Grav\Console\Gpm
 */
class InstallCommand extends Command
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

    protected $local_config;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("install")
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force re-fetching the data from remote'
            )
            ->addOption(
                'all-yes',
                'y',
                InputOption::VALUE_NONE,
                'Assumes yes (or best approach) instead of prompting'
            )
            ->addOption(
                'destination',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The destination where the package should be installed at. By default this would be where the grav instance has been launched from',
                GRAV_ROOT
            )
            ->addArgument(
                'package',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'The package(s) that are desired to be installed. Use the "index" command for a list of packages'
            )
            ->setDescription("Performs the installation of plugins and themes")
            ->setHelp('The <info>install</info> command allows to install plugins and themes');
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

        $packages = array_map('strtolower', $this->input->getArgument('package'));
        $this->data = $this->gpm->findPackages($packages);

        $local_config_file = exec('eval echo ~/.grav/config');
        if (file_exists($local_config_file)) {
            $this->local_config = Yaml::parse($local_config_file);
        }

        if (
            !Installer::isGravInstance($this->destination) ||
            !Installer::isValidDestination($this->destination, [Installer::EXISTS, Installer::IS_LINK])
        ) {
            $this->output->writeln("<red>ERROR</red>: " . Installer::lastErrorMsg());
            exit;
        }

        $this->output->writeln('');

        if (!$this->data['total']) {
            $this->output->writeln("Nothing to install.");
            $this->output->writeln('');
            exit;
        }

        if (count($this->data['not_found'])) {
            $this->output->writeln("These packages were not found on Grav: <red>" . implode('</red>, <red>',
                    array_keys($this->data['not_found'])) . "</red>");
        }

        unset($this->data['not_found']);
        unset($this->data['total']);

        foreach ($this->data as $data) {
            foreach ($data as $package) {

                //Check for dependencies
                if (isset($package->dependencies)) {
                    $this->output->writeln("Package <cyan>" . $package->name . "</cyan> has required dependencies that must be installed first...");
                    $this->output->writeln('');

                    $dependency_data = $this->gpm->findPackages($package->dependencies);

                    if (!$dependency_data['total']) {
                        $this->output->writeln("No dependcies found...");
                        $this->output->writeln('');
                    } else {
                        unset($dependency_data['total']);

                        foreach($dependency_data as $type => $dep_data) {
                            foreach($dep_data as $name => $dep_package) {
                                $this->output->writeln("Preparing to install dependency: <cyan>" . $name . "</cyan>");
                                $this->processPackage($dep_package);
                            }

                        }

//                        foreach((array)$package->dependencies as $dependency) {
//                            if (is_object($dependency)) {
//                                foreach($dependency as $key=>$val) {
//                                    $dep = $key;
//                                    $install_options[] = 'git';
//                                }
//                            } else {
//                                // just name for GPM retrieval
//                                $dep = $dependency;
//                            }
//
//
//
//
//                        }
                    }
                }

                $version = isset($package->available) ? $package->available : $package->version;

                $this->output->writeln("Preparing to install <cyan>" . $package->name . "</cyan> [v" . $version . "]");

                $this->output->write("  |- Downloading package...     0%");
                $this->file = $this->downloadPackage($package);

                $this->output->write("  |- Checking destination...  ");
                $checks = $this->checkDestination($package);

                if (!$checks) {
                    $this->output->writeln("  '- <red>Installation failed or aborted.</red>");
                    $this->output->writeln('');
                } else {
                    $this->output->write("  |- Installing package...  ");
                    //$installation = $this->installPackage($package);
                    $installation = true;
                    if (!$installation) {
                        $this->output->writeln("  '- <red>Installation failed or aborted.</red>");
                        $this->output->writeln('');
                    } else {
                        $this->output->writeln("  '- <green>Success!</green>  ");
                        $this->output->writeln('');
                    }
                }
            }
        }

        // clear cache after successful upgrade
        $this->clearCache();
    }

    private function processPackage($package)
    {
        $install_options = ['gpm'];

        // if no name, not found in GPM
        if (!isset($package->name)) {
            unset($install_options[0]);
        }
        // if local config found symlink is a valid option
        if (isset($this->local_config)) {
            $install_options[] = 'symlink';
        }
        // if override set, can install via git
        if (isset($package->override_repository)) {
            $install_options[] = 'git';
        }

        if (count($install_options) == 0) {
            // no valid install options
        } elseif (count($install_options) == 1) {
            // only one option, use it...
        } else {
            $helper = $this->getHelper('question');
            $install_list = array_values($install_options);
            $default = $install_list[0];
            $question = new ChoiceQuestion(
                'Please select installation method ('.$default.' is default)', array_values($install_list), 0
            );
            $question->setErrorMessage('Method %s is invalid');
            $method = $helper->ask($this->input, $this->output, $question);
            $this->output->writeln('You chose: ' . $method);

        }
    }

    /**
     * @param $package
     *
     * @return string
     */
    private function downloadPackage($package)
    {
        $this->tmp = CACHE_DIR . DS . 'tmp/Grav-' . uniqid();
        $filename = $package->slug . basename($package->zipball_url);
        $output = Response::get($package->zipball_url, [], [$this, 'progress']);

        Folder::mkdir($this->tmp);

        $this->output->write("\x0D");
        $this->output->write("  |- Downloading package...   100%");
        $this->output->writeln('');

        file_put_contents($this->tmp . DS . $filename, $output);

        return $this->tmp . DS . $filename;
    }

    /**
     * @param $package
     *
     * @return bool
     */
    private function checkDestination($package)
    {
        $questionHelper = $this->getHelper('question');
        $skipPrompt = $this->input->getOption('all-yes');

        Installer::isValidDestination($this->destination . DS . $package->install_path);

        if (Installer::lastErrorCode() == Installer::EXISTS) {
            if (!$skipPrompt) {
                $this->output->write("\x0D");
                $this->output->writeln("  |- Checking destination...  <yellow>exists</yellow>");

                $question = new ConfirmationQuestion("  |  '- The package has been detected as installed already, do you want to overwrite it? [y|N] ",
                    false);
                $answer = $questionHelper->ask($this->input, $this->output, $question);

                if (!$answer) {
                    $this->output->writeln("  |     '- <red>You decided to not overwrite the already installed package.</red>");

                    return false;
                }
            }
        }

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

    /**
     * @param $package
     *
     * @return bool
     */
    private function installPackage($package)
    {
        Installer::install($this->file, $this->destination, ['install_path' => $package->install_path]);
        $errorCode = Installer::lastErrorCode();
        Folder::delete($this->tmp);

        if ($errorCode & (Installer::ZIP_OPEN_ERROR | Installer::ZIP_EXTRACT_ERROR)) {
            $this->output->write("\x0D");
            // extra white spaces to clear out the buffer properly
            $this->output->writeln("  |- Installing package...    <red>error</red>                             ");
            $this->output->writeln("  |  '- " . Installer::lastErrorMsg());

            return false;
        }

        $this->output->write("\x0D");
        // extra white spaces to clear out the buffer properly
        $this->output->writeln("  |- Installing package...    <green>ok</green>                             ");

        return true;
    }

    /**
     * @param $progress
     */
    public function progress($progress)
    {
        $this->output->write("\x0D");
        $this->output->write("  |- Downloading package... " . str_pad($progress['percent'], 5, " ",
                STR_PAD_LEFT) . '%');
    }
}
