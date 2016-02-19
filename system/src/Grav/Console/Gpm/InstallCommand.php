<?php
namespace Grav\Console\Gpm;

use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Common\GPM\Response;
use Grav\Common\Utils;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Yaml\Yaml;

define('GIT_REGEX', '/http[s]?:\/\/(?:.*@)?(github|bitbucket)(?:.org|.com)\/.*\/(.*)/');

/**
 * Class InstallCommand
 * @package Grav\Console\Gpm
 */
class InstallCommand extends ConsoleCommand
{
    /**
     * @var
     */
    protected $data;

    /**
     * @var GPM
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

    public function setGpm($gpm)
    {
        $this->gpm = $gpm;
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $this->gpm = new GPM($this->input->getOption('force'));
        $this->destination = realpath($this->input->getOption('destination'));

        $packages = array_map('strtolower', $this->input->getArgument('package'));
        $this->data = $this->gpm->findPackages($packages);

        if (false === $this->isWindows() && @is_file(getenv("HOME") . '/.grav/config')) {
            $local_config_file = exec('eval echo ~/.grav/config');
            if (file_exists($local_config_file)) {
                $this->local_config = Yaml::parse($local_config_file);
            }
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

        try {
            $dependencies = $this->processDependencies($packages);
        } catch (\Exception $e) {
            //Error out if there are incompatible packages requirements and tell which ones, and what to do
            //Error out if there is any error in parsing the dependencies and their versions, and tell which one is broken
            $this->output->writeln("<red>" . $e->getMessage() . "</red>");
            return false;
        }

        //TODO: handle packages prepended with author slug. How to handle with currently installed packages?

        if ($dependencies) {

            //First, check for Grav dependency. If a dependency requires Grav > the current version, abort and tell.
            if (isset($dependencies['grav'])) {
                if (version_compare($this->calculateVersionNumberFromDependencyVersion($dependencies['grav']), GRAV_VERSION) === 1) {
                    //Needs a Grav update first
                    $this->output->writeln("<red>One of the package dependencies requires Grav " . $dependencies['grav'] . ". Please update Grav first with `bin/gpm selfupgrade`</red>");
                    return false;
                }
                unset($dependencies['grav']);
            }

            try {
                $this->installDependencies($dependencies, 'install', "The following dependencies need to be installed...");
                $this->installDependencies($dependencies, 'update',  "The following dependencies need to be updated...");
                $this->installDependencies($dependencies, 'ignore',  "The following dependencies can be updated as there is a newer version, but it's not mandatory...");
            } catch (\Exception $e) {
                $this->output->writeln("<red>Installation aborted</red>");
                return;
            }
        }

        //We're done installing dependencies. Install the actual packages
        foreach ($this->data as $data) {
            foreach ($data as $package) {
                $this->processPackage($package);
            }
        }

        // clear cache after successful upgrade
        $this->clearCache();
    }

    /**
     * Given a $dependencies list, filters their type according to $type and
     * shows $message prior to listing them to the user. Then asks the user a confirmation prior
     * to installing them.
     *
     * @param array $dependencies The dependencies array
     * @param string $type The type of dependency to show: install, update, ignore
     * @param string $message A message to be shown prior to listing the dependencies
     *
     * @throws \Exception
     */
    public function installDependencies($dependencies, $type, $message) {
        $packages = array_filter($dependencies, function ($action) use ($type) { return $action === $type; });
        if (count($packages) > 0) {
            $this->output->writeln($message);

            foreach ($packages as $dependencyName => $dependencyVersion) {
                $this->output->writeln("  |- Package <cyan>" . $dependencyName . "</cyan> requires a newer version");
            }

            $this->output->writeln("");

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Update these packages? [y|N] ', false);

            if ($helper->ask($this->input, $this->output, $question)) {
                foreach ($packages as $dependencyName => $dependencyVersion) {
                    $this->processPackage($dependencyName);
                }
                $this->output->writeln('');
            } else {
                throw new \Exception();
            }
        }
    }

    /**
     * Fetch the dependencies, check the installed packages and return an array with
     * the list of packages with associated an information on what to do: install, update or ignore.
     *
     * `ignore` means the package is already installed and can be safely left as-is.
     * `install` means the package is not installed and must be installed.
     * `update` means the package is already installed and must be updated as a dependency needs a higher version.
     *
     * @param array $packages
     *
     * @return mixed
     * @throws \Exception
     */
    public function processDependencies($packages) {
        $dependencies = $this->calculateMergedDependenciesOfPackages($packages);

        foreach ($dependencies as $dependencySlug => $dependencyVersion) {
            if ($this->gpm->isPluginInstalled($dependencySlug)) {
                $dependencyVersion = $this->calculateVersionNumberFromDependencyVersion($dependencyVersion);

                // check the version, if an update is not strictly required mark as 'ignore'
                $locator = self::getGrav()['locator'];
                $blueprints_path = $locator->findResource('plugins://' . $dependencySlug . DS . 'blueprints.yaml');
                $package_yaml = Yaml::parse(file_get_contents($blueprints_path));
                $currentlyInstalledVersion = $package_yaml['version'];

                //if I already have the latest release, remove the dependency
                $latestRelease = $this->gpm->getLatestVersionOfPackage($dependencySlug);

                if (version_compare($latestRelease, $dependencyVersion) == -1) {
                    //throw an exception if a required version cannot be found in the GPM yet
                    throw new \Exception('Dependency ' . $package_yaml['name'] . ' is required in a version higher than the latest release. Try running `bin/gpm -f index` to force a refresh of the GPM cache', 1);
                }

                if (version_compare($currentlyInstalledVersion, $dependencyVersion) == -1) {
                    $dependencies[$dependencySlug] = 'update';
                } else {
                    if ($currentlyInstalledVersion == $latestRelease) {
                        unset($dependencies[$dependencySlug]);
                    } else {
                        $dependencies[$dependencySlug] = 'ignore';
                    }
                }
            } else {
                $dependencies[$dependencySlug] = 'install';
            }
        }

        return $dependencies;
    }

    /**
     * Calculates and merges the dependencies of a package
     *
     * @param string $packageName  The package information
     *
     * @param array $dependencies The dependencies array
     *
     * @return array
     * @throws \Exception
     */
    private function calculateMergedDependenciesOfPackage($packageName, $dependencies)
    {
        $packageData = $this->gpm->findPackage($packageName);

        //Check for dependencies
        if (isset($packageData->dependencies_versions)) {

            foreach ($packageData->dependencies_versions as $dependency) {
                $current_package_name = $dependency['name'];
                if (isset($dependency['version'])) {
                    $current_package_version_information = $dependency['version'];
                }

                if (!isset($dependencies[$current_package_name])) {
                    // Dependency added for the first time

                    if (!isset($current_package_version_information)) {
                        $dependencies[$current_package_name] = '*';
                    } else {
                        $dependencies[$current_package_name] = $current_package_version_information;
                    }

                    //Factor in the package dependencies too
                    $dependencies = $this->calculateMergedDependenciesOfPackage($current_package_name, $dependencies);
                }
                else {
                    // Dependency already added by another package
                    //if this package requires a version higher than the currently stored one, store this requirement instead
                    if (isset($current_package_version_information) && $current_package_version_information !== '*') {

                        $currently_stored_version_information = $dependencies[$current_package_name];
                        $currently_stored_version_number = $this->calculateVersionNumberFromDependencyVersion($currently_stored_version_information);

                        $currently_stored_version_is_in_next_significant_release_format = false;
                        if ($this->versionFormatIsNextSignificantRelease($currently_stored_version_information)) {
                            $currently_stored_version_is_in_next_significant_release_format = true;
                        }

                        if (!$currently_stored_version_number) {
                            $currently_stored_version_number = '*';
                        }

                        $current_package_version_number = $this->calculateVersionNumberFromDependencyVersion($current_package_version_information);
                        if (!$current_package_version_number) {
                            throw new \Exception('Bad format for version of dependency ' . $current_package_name . ' for package ' . $packageName, 1);
                        }

                        $current_package_version_is_in_next_significant_release_format = false;
                        if ($this->versionFormatIsNextSignificantRelease($current_package_version_information)) {
                            $current_package_version_is_in_next_significant_release_format = true;
                        }

                        //If I had stored '*', change right away with the more specific version required
                        if ($currently_stored_version_number === '*') {
                            $dependencies[$current_package_name] = $current_package_version_information;
                        } else {
                            if (!$currently_stored_version_is_in_next_significant_release_format && !$current_package_version_is_in_next_significant_release_format) {
                                //Comparing versions equals or higher, a simple version_compare is enough
                                if (version_compare($currently_stored_version_number, $current_package_version_number) == -1) { //Current package version is higher
                                    $dependencies[$current_package_name] = $current_package_version_information;
                                }
                            } else {
                                $compatible = $this->checkNextSignificantReleasesAreCompatible($currently_stored_version_number, $current_package_version_number);
                                if (!$compatible) {
                                    throw new \Exception('Dependency ' . $current_package_name . ' is required in two incompatible versions', 2);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $dependencies;
    }

    /**
     * Calculates and merges the dependencies of the passed packages
     *
     * @todo handle alpha, beta, rc. not just numeric versions
     *
     * @param array $packages
     *
     * @return mixed
     * @throws \Exception
     */
    public function calculateMergedDependenciesOfPackages($packages)
    {
        $dependencies = [];

        foreach ($packages as $package) {
            $dependencies = $this->calculateMergedDependenciesOfPackage($package, $dependencies);
        }

        return $dependencies;
    }

    /**
     * Returns the actual version from a dependency version string.
     * Examples:
     *      $versionInformation == '~2.0' => returns '2.0'
     *      $versionInformation == '>=2.0.2' => returns '2.0.2'
     *      $versionInformation == '*' => returns null
     *      $versionInformation == '' => returns null
     *
     * @param $versionInformation
     *
     * @return null|string
     */
    public function calculateVersionNumberFromDependencyVersion($versionInformation)
    {
        if ($this->versionFormatIsNextSignificantRelease($versionInformation)) {
            return substr($versionInformation, 1);
        } elseif ($this->versionFormatIsEqualOrHigher($versionInformation)) {
            return substr($versionInformation, 2);
        }

        return null;
    }

    /**
     * Check if the passed version information contains next significant release (tilde) operator
     *
     * Example: returns true for $version: '~2.0'
     *
     * @param $version
     *
     * @return bool
     */
    public function versionFormatIsNextSignificantRelease($version) {
        return substr($version, 0, 1) == '~';
    }

    /**
     * Check if the passed version information contains equal or higher operator
     *
     * Example: returns true for $version: '>=2.0'
     *
     * @param $version
     *
     * @return bool
     */
    public function versionFormatIsEqualOrHigher($version) {
        return substr($version, 0, 2) == '>=';
    }

    /**
     * Check if two releases are compatible by next significant release
     *
     * ~1.2 is equivalent to >=1.2 <2.0.0
     * ~1.2.3 is equivalent to >=1.2.3 <1.3.0
     *
     * In short, allows the last digit specified to go up
     *
     * @param string $version1 the version string (e.g. '2.0.0' or '1.0')
     * @param string $version2 the version string (e.g. '2.0.0' or '1.0')
     *
     * @return bool
     */
    public function checkNextSignificantReleasesAreCompatible($version1, $version2)
    {
        $version1array = explode('.', $version1);
        $version2array = explode('.', $version2);

        if (count($version1array) > count($version2array)) {
            list($version1array, $version2array) = [$version2array, $version1array];
        }

        $i = 0;
        while ($i < count($version1array) - 1) {
            if ($version1array[$i] != $version2array[$i]) {
                return false;
            }
            $i++;
        }

        return true;
    }

    /**
     * @param $package
     */
    private function processPackage($package)
    {
        $install_options = ['GPM'];

        // if no name, not found in GPM
        if (!isset($package->version)) {
            unset($install_options[0]);
        }
        // if local config found symlink is a valid option
        if (isset($this->local_config) && $this->getSymlinkSource($package)) {
            $install_options[] = 'Symlink';
        }
        // if override set, can install via git
        if (isset($package->override_repository)) {
            $install_options[] = 'Git';
        }

        // reindex list
        $install_options = array_values($install_options);

        if (count($install_options) == 0) {
            // no valid install options - error and return
            $this->output->writeln("<red>not valid installation methods found!</red>");

            return;
        } elseif (count($install_options) == 1) {
            // only one option, use it...
            $method = $install_options[0];
        } else {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please select installation method for <cyan>' . $package->name . '</cyan> (<magenta>' . $install_options[0] . ' is default</magenta>)', array_values($install_options), 0
            );
            $question->setErrorMessage('Method %s is invalid');
            $method = $helper->ask($this->input, $this->output, $question);
        }

        $this->output->writeln('');

        $method_name = 'process' . $method;
        $this->$method_name($package);

        $this->installDemoContent($package);
    }


    /**
     * @param $package
     */
    private function installDemoContent($package)
    {
        $demo_dir = $this->destination . DS . $package->install_path . DS . '_demo';
        $dest_dir = $this->destination . DS . 'user';
        $pages_dir = $dest_dir . DS . 'pages';

        if (file_exists($demo_dir)) {
            // Demo content exists, prompt to install it.
            $this->output->writeln("<white>Attention: </white><cyan>" . $package->name . "</cyan> contains demo content");
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Do you wish to install this demo content? [y|N] ', false);

            if (!$helper->ask($this->input, $this->output, $question)) {
                $this->output->writeln("  '- <red>Skipped!</red>  ");
                $this->output->writeln('');

                return;
            }

            // if pages folder exists in demo
            if (file_exists($demo_dir . DS . 'pages')) {
                $pages_backup = 'pages.' . date('m-d-Y-H-i-s');
                $question = new ConfirmationQuestion('This will backup your current `user/pages` folder to `user/' . $pages_backup . '`, continue? [y|N]', false);

                if (!$helper->ask($this->input, $this->output, $question)) {
                    $this->output->writeln("  '- <red>Skipped!</red>  ");
                    $this->output->writeln('');

                    return;
                }

                // backup current pages folder
                if (file_exists($dest_dir)) {
                    if (rename($pages_dir, $dest_dir . DS . $pages_backup)) {
                        $this->output->writeln("  |- Backing up pages...    <green>ok</green>");
                    } else {
                        $this->output->writeln("  |- Backing up pages...    <red>failed</red>");
                    }
                }
            }

            // Confirmation received, copy over the data
            $this->output->writeln("  |- Installing demo content...    <green>ok</green>                             ");
            Folder::rcopy($demo_dir, $dest_dir);
            $this->output->writeln("  '- <green>Success!</green>  ");
            $this->output->writeln('');
        }
    }

    /**
     * @param $package
     *
     * @return array
     */
    private function getGitRegexMatches($package)
    {
        if (isset($package->override_repository)) {
            $repository = $package->override_repository;
        } elseif (isset($package->repository)) {
            $repository = $package->repository;
        } else {
            return false;
        }

        preg_match(GIT_REGEX, $repository, $matches);

        return $matches;
    }

    /**
     * @param $package
     *
     * @return bool|string
     */
    private function getSymlinkSource($package)
    {
        $matches = $this->getGitRegexMatches($package);

        foreach ($this->local_config as $path) {
            if (Utils::endsWith($matches[2], '.git')) {
                $repo_dir = preg_replace('/\.git$/', '', $matches[2]);
            } else {
                $repo_dir = $matches[2];
            }

            $from = rtrim($path, '/') . '/' . $repo_dir;

            if (file_exists($from)) {
                return $from;
            }
        }

        return false;
    }

    /**
     * @param $package
     */
    private function processSymlink($package)
    {

        exec('cd ' . $this->destination);

        $to = $this->destination . DS . $package->install_path;
        $from = $this->getSymlinkSource($package);

        $this->output->writeln("Preparing to Symlink <cyan>" . $package->name . "</cyan>");
        $this->output->write("  |- Checking source...  ");

        if (file_exists($from)) {
            $this->output->writeln("<green>ok</green>");

            $this->output->write("  |- Checking destination...  ");
            $checks = $this->checkDestination($package);

            if (!$checks) {
                $this->output->writeln("  '- <red>Installation failed or aborted.</red>");
                $this->output->writeln('');
            } else {
                if (file_exists($to)) {
                    $this->output->writeln("  '- <red>Symlink cannot overwrite an existing package, please remove first</red>");
                    $this->output->writeln('');
                } else {
                    symlink($from, $to);

                    // extra white spaces to clear out the buffer properly
                    $this->output->writeln("  |- Symlinking package...    <green>ok</green>                             ");

                    $this->output->writeln("  '- <green>Success!</green>  ");
                    $this->output->writeln('');
                }


            }

            return;
        }

        $this->output->writeln("<red>not found!</red>");
        $this->output->writeln("  '- <red>Installation failed or aborted.</red>");
    }

    /**
     * @param $package
     */
    private function processGit($package)
    {
        $matches = $this->getGitRegexMatches($package);

        $this->output->writeln("Preparing to Git clone <cyan>" . $package->name . "</cyan> from " . $matches[0]);

        $this->output->write("  |- Checking destination...  ");
        $checks = $this->checkDestination($package);

        if (!$checks) {
            $this->output->writeln("  '- <red>Installation failed or aborted.</red>");
            $this->output->writeln('');
        } else {
            $cmd = 'cd ' . $this->destination . ' && git clone ' . $matches[0] . ' ' . $package->install_path;
            exec($cmd);

            // extra white spaces to clear out the buffer properly
            $this->output->writeln("  |- Cloning package...    <green>ok</green>                             ");

            $this->output->writeln("  '- <green>Success!</green>  ");
            $this->output->writeln('');
        }
    }

    /**
     * @param $package
     */
    private function processGPM($package)
    {
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
            $installation = $this->installPackage($package);
            if (!$installation) {
                $this->output->writeln("  '- <red>Installation failed or aborted.</red>");
                $this->output->writeln('');
            } else {
                $this->output->writeln("  '- <green>Success!</green>  ");
                $this->output->writeln('');
            }
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
        $question_helper = $this->getHelper('question');
        $skip_prompt = $this->input->getOption('all-yes');

        Installer::isValidDestination($this->destination . DS . $package->install_path);

        if (Installer::lastErrorCode() == Installer::EXISTS) {
            if (!$skip_prompt) {
                $this->output->write("\x0D");
                $this->output->writeln("  |- Checking destination...  <yellow>exists</yellow>");

                $question = new ConfirmationQuestion("  |  '- The package has been detected as installed already, do you want to overwrite it? [y|N] ",
                    false);
                $answer = $question_helper->ask($this->input, $this->output, $question);

                if (!$answer) {
                    $this->output->writeln("  |     '- <red>You decided to not overwrite the already installed package.</red>");

                    return false;
                }
            }
        }

        if (Installer::lastErrorCode() == Installer::IS_LINK) {
            $this->output->write("\x0D");
            $this->output->writeln("  |- Checking destination...  <yellow>symbolic link</yellow>");

            if ($skip_prompt) {
                $this->output->writeln("  |     '- <yellow>Skipped automatically.</yellow>");

                return false;
            }

            $question = new ConfirmationQuestion("  |  '- Destination has been detected as symlink, delete symbolic link first? [y|N] ",
                false);
            $answer = $question_helper->ask($this->input, $this->output, $question);

            if (!$answer) {
                $this->output->writeln("  |     '- <red>You decided to not delete the symlink automatically.</red>");

                return false;
            } else {
                unlink($this->destination . DS . $package->install_path);
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
        $type = $package->package_type;

        Installer::install($this->file, $this->destination, ['install_path' => $package->install_path, 'theme' => (($type == 'themes'))]);
        $error_code = Installer::lastErrorCode();
        Folder::delete($this->tmp);

        if ($error_code & (Installer::ZIP_OPEN_ERROR | Installer::ZIP_EXTRACT_ERROR)) {
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
