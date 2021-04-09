<?php

/**
 * @package    Grav\Console\Gpm
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Exception;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Common\GPM\Licenses;
use Grav\Common\GPM\Response;
use Grav\Common\GPM\Remote\Package;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Console\GpmCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ZipArchive;
use function array_key_exists;
use function count;
use function define;

define('GIT_REGEX', '/http[s]?:\/\/(?:.*@)?(github|bitbucket)(?:.org|.com)\/.*\/(.*)/');

/**
 * Class InstallCommand
 * @package Grav\Console\Gpm
 */
class InstallCommand extends GpmCommand
{
    /** @var array */
    protected $data;
    /** @var GPM */
    protected $gpm;
    /** @var string */
    protected $destination;
    /** @var string */
    protected $file;
    /** @var string */
    protected $tmp;
    /** @var bool */
    protected $use_symlinks;
    /** @var array */
    protected $demo_processing = [];
    /** @var string */
    protected $all_yes;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('install')
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
                'Package(s) to install. Use "bin/gpm index" to list packages. Use "bin/gpm direct-install" to install a specific version'
            )
            ->setDescription('Performs the installation of plugins and themes')
            ->setHelp('The <info>install</info> command allows to install plugins and themes');
    }

    /**
     * Allows to set the GPM object, used for testing the class
     *
     * @param GPM $gpm
     */
    public function setGpm(GPM $gpm): void
    {
        $this->gpm = $gpm;
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $input = $this->getInput();
        $io = $this->getIO();

        if (!class_exists(ZipArchive::class)) {
            $io->title('GPM Install');
            $io->error('php-zip extension needs to be enabled!');

            return 1;
        }

        $this->gpm = new GPM($input->getOption('force'));

        $this->all_yes = $input->getOption('all-yes');

        $this->displayGPMRelease();

        $this->destination = realpath($input->getOption('destination'));

        $packages = array_map('strtolower', $input->getArgument('package'));
        $this->data = $this->gpm->findPackages($packages);
        $this->loadLocalConfig();

        if (!Installer::isGravInstance($this->destination) ||
            !Installer::isValidDestination($this->destination, [Installer::EXISTS, Installer::IS_LINK])
        ) {
            $io->writeln('<red>ERROR</red>: ' . Installer::lastErrorMsg());

            return 1;
        }

        $io->newLine();

        if (!$this->data['total']) {
            $io->writeln('Nothing to install.');
            $io->newLine();

            return 0;
        }

        if (count($this->data['not_found'])) {
            $io->writeln('These packages were not found on Grav: <red>' . implode(
                '</red>, <red>',
                array_keys($this->data['not_found'])
            ) . '</red>');
        }

        unset($this->data['not_found'], $this->data['total']);

        if (null !== $this->local_config) {
            // Symlinks available, ask if Grav should use them
            $this->use_symlinks = false;
            $question = new ConfirmationQuestion('Should Grav use the symlinks if available? [y|N] ', false);

            $answer = $this->all_yes ? false : $io->askQuestion($question);

            if ($answer) {
                $this->use_symlinks = true;
            }
        }

        $io->newLine();

        try {
            $dependencies = $this->gpm->getDependencies($packages);
        } catch (Exception $e) {
            //Error out if there are incompatible packages requirements and tell which ones, and what to do
            //Error out if there is any error in parsing the dependencies and their versions, and tell which one is broken
            $io->writeln("<red>{$e->getMessage()}</red>");

            return 1;
        }

        if ($dependencies) {
            try {
                $this->installDependencies($dependencies, 'install', 'The following dependencies need to be installed...');
                $this->installDependencies($dependencies, 'update', 'The following dependencies need to be updated...');
                $this->installDependencies($dependencies, 'ignore', "The following dependencies can be updated as there is a newer version, but it's not mandatory...", false);
            } catch (Exception $e) {
                $io->writeln('<red>Installation aborted</red>');

                return 1;
            }

            $io->writeln('<green>Dependencies are OK</green>');
            $io->newLine();
        }


        //We're done installing dependencies. Install the actual packages
        foreach ($this->data as $data) {
            foreach ($data as $package_name => $package) {
                if (array_key_exists($package_name, $dependencies)) {
                    $io->writeln("<green>Package {$package_name} already installed as dependency</green>");
                } else {
                    $is_valid_destination = Installer::isValidDestination($this->destination . DS . $package->install_path);
                    if ($is_valid_destination || Installer::lastErrorCode() == Installer::NOT_FOUND) {
                        $this->processPackage($package, false);
                    } else {
                        if (Installer::lastErrorCode() == Installer::EXISTS) {
                            try {
                                $this->askConfirmationIfMajorVersionUpdated($package);
                                $this->gpm->checkNoOtherPackageNeedsThisDependencyInALowerVersion($package->slug, $package->available, array_keys($data));
                            } catch (Exception $e) {
                                $io->writeln("<red>{$e->getMessage()}</red>");

                                return 1;
                            }

                            $question = new ConfirmationQuestion("The package <cyan>{$package_name}</cyan> is already installed, overwrite? [y|N] ", false);
                            $answer = $this->all_yes ? true : $io->askQuestion($question);

                            if ($answer) {
                                $is_update = true;
                                $this->processPackage($package, $is_update);
                            } else {
                                $io->writeln("<yellow>Package {$package_name} not overwritten</yellow>");
                            }
                        } else {
                            if (Installer::lastErrorCode() == Installer::IS_LINK) {
                                $io->writeln("<red>Cannot overwrite existing symlink for </red><cyan>{$package_name}</cyan>");
                                $io->newLine();
                            }
                        }
                    }
                }
            }
        }

        if (count($this->demo_processing) > 0) {
            foreach ($this->demo_processing as $package) {
                $this->installDemoContent($package);
            }
        }

        // clear cache after successful upgrade
        $this->clearCache();

        return 0;
    }

    /**
     * If the package is updated from an older major release, show warning and ask confirmation
     *
     * @param Package $package
     * @return void
     */
    public function askConfirmationIfMajorVersionUpdated(Package $package): void
    {
        $io = $this->getIO();
        $package_name = $package->name;
        $new_version = $package->available ?: $this->gpm->getLatestVersionOfPackage($package->slug);
        $old_version = $package->version;

        $major_version_changed = explode('.', $new_version)[0] !== explode('.', $old_version)[0];

        if ($major_version_changed) {
            if ($this->all_yes) {
                $io->writeln("The package <cyan>{$package_name}</cyan> will be updated to a new major version <green>{$new_version}</green>, from <magenta>{$old_version}</magenta>");
                return;
            }

            $question = new ConfirmationQuestion("The package <cyan>{$package_name}</cyan> will be updated to a new major version <green>{$new_version}</green>, from <magenta>{$old_version}</magenta>. Be sure to read what changed with the new major release. Continue? [y|N] ", false);

            if (!$io->askQuestion($question)) {
                $io->writeln("<yellow>Package {$package_name} not updated</yellow>");
                exit;
            }
        }
    }

    /**
     * Given a $dependencies list, filters their type according to $type and
     * shows $message prior to listing them to the user. Then asks the user a confirmation prior
     * to installing them.
     *
     * @param array  $dependencies The dependencies array
     * @param string $type         The type of dependency to show: install, update, ignore
     * @param string $message      A message to be shown prior to listing the dependencies
     * @param bool   $required     A flag that determines if the installation is required or optional
     * @return void
     * @throws Exception
     */
    public function installDependencies(array $dependencies, string $type, string $message, bool $required = true): void
    {
        $io = $this->getIO();
        $packages = array_filter($dependencies, static function ($action) use ($type) {
            return $action === $type;
        });
        if (count($packages) > 0) {
            $io->writeln($message);

            foreach ($packages as $dependencyName => $dependencyVersion) {
                $io->writeln("  |- Package <cyan>{$dependencyName}</cyan>");
            }

            $io->newLine();

            if ($type === 'install') {
                $questionAction = 'Install';
            } else {
                $questionAction = 'Update';
            }

            if (count($packages) === 1) {
                $questionArticle = 'this';
            } else {
                $questionArticle = 'these';
            }

            if (count($packages) === 1) {
                $questionNoun = 'package';
            } else {
                $questionNoun = 'packages';
            }

            $question = new ConfirmationQuestion("${questionAction} {$questionArticle} {$questionNoun}? [Y|n] ", true);
            $answer = $this->all_yes ? true : $io->askQuestion($question);

            if ($answer) {
                foreach ($packages as $dependencyName => $dependencyVersion) {
                    $package = $this->gpm->findPackage($dependencyName);
                    $this->processPackage($package, $type === 'update');
                }
                $io->newLine();
            } elseif ($required) {
                throw new Exception();
            }
        }
    }

    /**
     * @param Package|null $package
     * @param bool    $is_update      True if the package is an update
     * @return void
     */
    private function processPackage(?Package $package, bool $is_update = false): void
    {
        $io = $this->getIO();

        if (!$package) {
            $io->writeln('<red>Package not found on the GPM!</red>');
            $io->newLine();
            return;
        }

        $symlink = false;
        if ($this->use_symlinks) {
            if (!isset($package->version) || $this->getSymlinkSource($package)) {
                $symlink = true;
            }
        }

        $symlink ? $this->processSymlink($package) : $this->processGpm($package, $is_update);

        $this->processDemo($package);
    }

    /**
     * Add package to the queue to process the demo content, if demo content exists
     *
     * @param Package $package
     * @return void
     */
    private function processDemo(Package $package): void
    {
        $demo_dir = $this->destination . DS . $package->install_path . DS . '_demo';
        if (file_exists($demo_dir)) {
            $this->demo_processing[] = $package;
        }
    }

    /**
     * Prompt to install the demo content of a package
     *
     * @param Package $package
     * @return void
     */
    private function installDemoContent(Package $package): void
    {
        $io = $this->getIO();
        $demo_dir = $this->destination . DS . $package->install_path . DS . '_demo';

        if (file_exists($demo_dir)) {
            $dest_dir = $this->destination . DS . 'user';
            $pages_dir = $dest_dir . DS . 'pages';

            // Demo content exists, prompt to install it.
            $io->writeln("<white>Attention: </white><cyan>{$package->name}</cyan> contains demo content");

            $question = new ConfirmationQuestion('Do you wish to install this demo content? [y|N] ', false);

            $answer = $io->askQuestion($question);

            if (!$answer) {
                $io->writeln("  '- <red>Skipped!</red>  ");
                $io->newLine();

                return;
            }

            // if pages folder exists in demo
            if (file_exists($demo_dir . DS . 'pages')) {
                $pages_backup = 'pages.' . date('m-d-Y-H-i-s');
                $question = new ConfirmationQuestion('This will backup your current `user/pages` folder to `user/' . $pages_backup . '`, continue? [y|N]', false);
                $answer = $this->all_yes ? true : $io->askQuestion($question);

                if (!$answer) {
                    $io->writeln("  '- <red>Skipped!</red>  ");
                    $io->newLine();

                    return;
                }

                // backup current pages folder
                if (file_exists($dest_dir)) {
                    if (rename($pages_dir, $dest_dir . DS . $pages_backup)) {
                        $io->writeln('  |- Backing up pages...    <green>ok</green>');
                    } else {
                        $io->writeln('  |- Backing up pages...    <red>failed</red>');
                    }
                }
            }

            // Confirmation received, copy over the data
            $io->writeln('  |- Installing demo content...    <green>ok</green>                             ');
            Folder::rcopy($demo_dir, $dest_dir);
            $io->writeln("  '- <green>Success!</green>  ");
            $io->newLine();
        }
    }

    /**
     * @param Package $package
     * @return array|false
     */
    private function getGitRegexMatches(Package $package)
    {
        if (isset($package->repository)) {
            $repository = $package->repository;
        } else {
            return false;
        }

        preg_match(GIT_REGEX, $repository, $matches);

        return $matches;
    }

    /**
     * @param Package $package
     * @return string|false
     */
    private function getSymlinkSource(Package $package)
    {
        $matches = $this->getGitRegexMatches($package);

        foreach ($this->local_config as $paths) {
            if (Utils::endsWith($matches[2], '.git')) {
                $repo_dir = preg_replace('/\.git$/', '', $matches[2]);
            } else {
                $repo_dir = $matches[2];
            }

            $paths = (array) $paths;
            foreach ($paths as $repo) {
                $path = rtrim($repo, '/') . '/' . $repo_dir;
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        return false;
    }

    /**
     * @param Package $package
     * @return void
     */
    private function processSymlink(Package $package): void
    {
        $io = $this->getIO();

        exec('cd ' . $this->destination);

        $to = $this->destination . DS . $package->install_path;
        $from = $this->getSymlinkSource($package);

        $io->writeln("Preparing to Symlink <cyan>{$package->name}</cyan>");
        $io->write('  |- Checking source...  ');

        if (file_exists($from)) {
            $io->writeln('<green>ok</green>');

            $io->write('  |- Checking destination...  ');
            $checks = $this->checkDestination($package);

            if (!$checks) {
                $io->writeln("  '- <red>Installation failed or aborted.</red>");
                $io->newLine();
            } elseif (file_exists($to)) {
                $io->writeln("  '- <red>Symlink cannot overwrite an existing package, please remove first</red>");
                $io->newLine();
            } else {
                symlink($from, $to);

                // extra white spaces to clear out the buffer properly
                $io->writeln('  |- Symlinking package...    <green>ok</green>                             ');
                $io->writeln("  '- <green>Success!</green>  ");
                $io->newLine();
            }

            return;
        }

        $io->writeln('<red>not found!</red>');
        $io->writeln("  '- <red>Installation failed or aborted.</red>");
    }

    /**
     * @param Package $package
     * @param bool $is_update
     * @return bool
     */
    private function processGpm(Package $package, bool $is_update = false)
    {
        $io = $this->getIO();

        $version = $package->available ?? $package->version;
        $license = Licenses::get($package->slug);

        $io->writeln("Preparing to install <cyan>{$package->name}</cyan> [v{$version}]");

        $io->write('  |- Downloading package...     0%');
        $this->file = $this->downloadPackage($package, $license);

        if (!$this->file) {
            $io->writeln("  '- <red>Installation failed or aborted.</red>");
            $io->newLine();

            return false;
        }

        $io->write('  |- Checking destination...  ');
        $checks = $this->checkDestination($package);

        if (!$checks) {
            $io->writeln("  '- <red>Installation failed or aborted.</red>");
            $io->newLine();
        } else {
            $io->write('  |- Installing package...  ');
            $installation = $this->installPackage($package, $is_update);
            if (!$installation) {
                $io->writeln("  '- <red>Installation failed or aborted.</red>");
                $io->newLine();
            } else {
                $io->writeln("  '- <green>Success!</green>  ");
                $io->newLine();

                return true;
            }
        }

        return false;
    }

    /**
     * @param Package $package
     * @param string|null $license
     * @return string|null
     */
    private function downloadPackage(Package $package, string $license = null)
    {
        $io = $this->getIO();

        $tmp_dir = Grav::instance()['locator']->findResource('tmp://', true, true);
        $this->tmp = $tmp_dir . '/Grav-' . uniqid();
        $filename = $package->slug . basename($package->zipball_url);
        $filename = preg_replace('/[\\\\\/:"*?&<>|]+/m', '-', $filename);
        $query = '';

        if (!empty($package->premium)) {
            $query = json_encode(array_merge(
                $package->premium,
                [
                    'slug' => $package->slug,
                    'filename' => $package->premium['filename'],
                    'license_key' => $license,
                    'sid' => md5(GRAV_ROOT)
                ]
            ));

            $query = '?d=' . base64_encode($query);
        }

        try {
            $output = Response::get($package->zipball_url . $query, [], [$this, 'progress']);
        } catch (Exception $e) {
            if (!empty($package->premium) && $e->getCode() === 401) {
                $message = '<yellow>Unauthorized Premium License Key</yellow>';
            } else {
                $message = $e->getMessage();
            }

            $error = str_replace("\n", "\n  |  '- ", $message);
            $io->write("\x0D");
            // extra white spaces to clear out the buffer properly
            $io->writeln('  |- Downloading package...    <red>error</red>                             ');
            $io->writeln("  |  '- " . $error);

            return null;
        }

        Folder::create($this->tmp);

        $io->write("\x0D");
        $io->write('  |- Downloading package...   100%');
        $io->newLine();

        file_put_contents($this->tmp . DS . $filename, $output);

        return $this->tmp . DS . $filename;
    }

    /**
     * @param Package $package
     * @return bool
     */
    private function checkDestination(Package $package): bool
    {
        $io = $this->getIO();

        Installer::isValidDestination($this->destination . DS . $package->install_path);

        if (Installer::lastErrorCode() === Installer::IS_LINK) {
            $io->write("\x0D");
            $io->writeln('  |- Checking destination...  <yellow>symbolic link</yellow>');

            if ($this->all_yes) {
                $io->writeln("  |     '- <yellow>Skipped automatically.</yellow>");

                return false;
            }

            $question = new ConfirmationQuestion(
                "  |  '- Destination has been detected as symlink, delete symbolic link first? [y|N] ",
                false
            );
            $answer = $io->askQuestion($question);

            if (!$answer) {
                $io->writeln("  |     '- <red>You decided to not delete the symlink automatically.</red>");

                return false;
            }

            unlink($this->destination . DS . $package->install_path);
        }

        $io->write("\x0D");
        $io->writeln('  |- Checking destination...  <green>ok</green>');

        return true;
    }

    /**
     * Install a package
     *
     * @param Package $package
     * @param bool $is_update True if it's an update. False if it's an install
     * @return bool
     */
    private function installPackage(Package $package, bool $is_update = false): bool
    {
        $io = $this->getIO();

        $type = $package->package_type;

        Installer::install($this->file, $this->destination, ['install_path' => $package->install_path, 'theme' => $type === 'themes', 'is_update' => $is_update]);
        $error_code = Installer::lastErrorCode();
        Folder::delete($this->tmp);

        if ($error_code) {
            $io->write("\x0D");
            // extra white spaces to clear out the buffer properly
            $io->writeln('  |- Installing package...    <red>error</red>                             ');
            $io->writeln("  |  '- " . Installer::lastErrorMsg());

            return false;
        }

        $message = Installer::getMessage();
        if ($message) {
            $io->write("\x0D");
            // extra white spaces to clear out the buffer properly
            $io->writeln("  |- {$message}");
        }

        $io->write("\x0D");
        // extra white spaces to clear out the buffer properly
        $io->writeln('  |- Installing package...    <green>ok</green>                             ');

        return true;
    }

    /**
     * @param array $progress
     * @return void
     */
    public function progress(array $progress): void
    {
        $io = $this->getIO();

        $io->write("\x0D");
        $io->write('  |- Downloading package... ' . str_pad(
            $progress['percent'],
            5,
            ' ',
            STR_PAD_LEFT
        ) . '%');
    }
}
