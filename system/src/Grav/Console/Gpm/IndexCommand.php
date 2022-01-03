<?php

/**
 * @package    Grav\Console\Gpm
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\GPM\Remote\AbstractPackageCollection;
use Grav\Common\GPM\Remote\Package;
use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Remote\Packages;
use Grav\Common\GPM\Remote\Plugins;
use Grav\Common\GPM\Remote\Themes;
use Grav\Common\Utils;
use Grav\Console\GpmCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;
use function count;

/**
 * Class IndexCommand
 * @package Grav\Console\Gpm
 */
class IndexCommand extends GpmCommand
{
    /** @var Packages */
    protected $data;
    /** @var GPM */
    protected $gpm;
    /** @var array */
    protected $options;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('index')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force re-fetching the data from remote'
            )
            ->addOption(
                'filter',
                'F',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Allows to limit the results based on one or multiple filters input. This can be either portion of a name/slug or a regex'
            )
            ->addOption(
                'themes-only',
                'T',
                InputOption::VALUE_NONE,
                'Filters the results to only Themes'
            )
            ->addOption(
                'plugins-only',
                'P',
                InputOption::VALUE_NONE,
                'Filters the results to only Plugins'
            )
            ->addOption(
                'updates-only',
                'U',
                InputOption::VALUE_NONE,
                'Filters the results to Updatable Themes and Plugins only'
            )
            ->addOption(
                'installed-only',
                'I',
                InputOption::VALUE_NONE,
                'Filters the results to only the Themes and Plugins you have installed'
            )
            ->addOption(
                'sort',
                's',
                InputOption::VALUE_REQUIRED,
                'Allows to sort (ASC) the results. SORT can be either "name", "slug", "author", "date"',
                'date'
            )
            ->addOption(
                'desc',
                'D',
                InputOption::VALUE_NONE,
                'Reverses the order of the output.'
            )
            ->addOption(
                'enabled',
                'e',
                InputOption::VALUE_NONE,
                'Filters the results to only enabled Themes and Plugins.'
            )
            ->addOption(
                'disabled',
                'd',
                InputOption::VALUE_NONE,
                'Filters the results to only disabled Themes and Plugins.'
            )
            ->setDescription('Lists the plugins and themes available for installation')
            ->setHelp('The <info>index</info> command lists the plugins and themes available for installation')
        ;
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $input = $this->getInput();
        $this->options = $input->getOptions();
        $this->gpm = new GPM($this->options['force']);
        $this->displayGPMRelease();
        $this->data = $this->gpm->getRepository();

        $data = $this->filter($this->data);

        $io = $this->getIO();

        if (count($data) === 0) {
            $io->writeln('No data was found in the GPM repository stored locally.');
            $io->writeln('Please try clearing cache and running the <green>bin/gpm index -f</green> command again');
            $io->writeln('If this doesn\'t work try tweaking your GPM system settings.');
            $io->newLine();
            $io->writeln('For more help go to:');
            $io->writeln(' -> <yellow>https://learn.getgrav.org/troubleshooting/common-problems#cannot-connect-to-the-gpm</yellow>');

            return 1;
        }

        foreach ($data as $type => $packages) {
            $io->writeln('<green>' . strtoupper($type) . '</green> [ ' . count($packages) . ' ]');

            $packages = $this->sort($packages);

            if (!empty($packages)) {
                $io->section('Packages table');
                $table = new Table($io);
                $table->setHeaders(['Count', 'Name', 'Slug', 'Version', 'Installed', 'Enabled']);

                $index = 0;
                foreach ($packages as $slug => $package) {
                    $row = [
                        'Count' => $index++ + 1,
                        'Name' => '<cyan>' . Utils::truncate($package->name, 20, false, ' ', '...') . '</cyan> ',
                        'Slug' => $slug,
                        'Version'=> $this->version($package),
                        'Installed' => $this->installed($package),
                        'Enabled' => $this->enabled($package),
                    ];

                    $table->addRow($row);
                }

                $table->render();
            }

            $io->newLine();
        }

        $io->writeln('You can either get more informations about a package by typing:');
        $io->writeln("    <green>{$this->argv} info <cyan><package></cyan></green>");
        $io->newLine();
        $io->writeln('Or you can install a package by typing:');
        $io->writeln("    <green>{$this->argv} install <cyan><package></cyan></green>");
        $io->newLine();

        return 0;
    }

    /**
     * @param Package $package
     * @return string
     */
    private function version(Package $package): string
    {
        $list      = $this->gpm->{'getUpdatable' . ucfirst($package->package_type)}();
        $package   = $list[$package->slug] ?? $package;
        $type      = ucfirst(preg_replace('/s$/', '', $package->package_type));
        $updatable = $this->gpm->{'is' . $type . 'Updatable'}($package->slug);
        $installed = $this->gpm->{'is' . $type . 'Installed'}($package->slug);
        $local     = $this->gpm->{'getInstalled' . $type}($package->slug);

        if (!$installed || !$updatable) {
            $version   = $installed ? $local->version : $package->version;
            return "v<green>{$version}</green>";
        }

        return "v<red>{$package->version}</red> <cyan>-></cyan> v<green>{$package->available}</green>";
    }

    /**
     * @param Package $package
     * @return string
     */
    private function installed(Package $package): string
    {
        $type      = ucfirst(preg_replace('/s$/', '', $package->package_type));
        $method = 'is' . $type . 'Installed';
        $installed = $this->gpm->{$method}($package->slug);

        return !$installed ? '<magenta>not installed</magenta>' : '<cyan>installed</cyan>';
    }

    /**
     * @param Package $package
     * @return string
     */
    private function enabled(Package $package): string
    {
        $type      = ucfirst(preg_replace('/s$/', '', $package->package_type));
        $method = 'is' . $type . 'Installed';
        $installed = $this->gpm->{$method}($package->slug);

        $result = '';
        if ($installed) {
            $method = 'is' . $type . 'Enabled';
            $enabled = $this->gpm->{$method}($package->slug);
            if ($enabled === true) {
                $result = '<cyan>enabled</cyan>';
            } elseif ($enabled === false) {
                $result = '<red>disabled</red>';
            }
        }

        return $result;
    }

    /**
     * @param Packages $data
     * @return Packages
     */
    public function filter(Packages $data): Packages
    {
        // filtering and sorting
        if ($this->options['plugins-only']) {
            unset($data['themes']);
        }
        if ($this->options['themes-only']) {
            unset($data['plugins']);
        }

        $filter = [
            $this->options['desc'],
            $this->options['disabled'],
            $this->options['enabled'],
            $this->options['filter'],
            $this->options['installed-only'],
            $this->options['updates-only'],
        ];

        if (count(array_filter($filter))) {
            foreach ($data as $type => $packages) {
                foreach ($packages as $slug => $package) {
                    $filter = true;

                    // Filtering by string
                    if ($this->options['filter']) {
                        $filter = preg_grep('/(' . implode('|', $this->options['filter']) . ')/i', [$slug, $package->name]);
                    }

                    // Filtering updatables only
                    if ($filter && ($this->options['installed-only'] || $this->options['enabled'] || $this->options['disabled'])) {
                        $method = ucfirst(preg_replace('/s$/', '', $package->package_type));
                        $function = 'is' . $method . 'Installed';
                        $filter = $this->gpm->{$function}($package->slug);
                    }

                    // Filtering updatables only
                    if ($filter && $this->options['updates-only']) {
                        $method = ucfirst(preg_replace('/s$/', '', $package->package_type));
                        $function = 'is' . $method . 'Updatable';
                        $filter = $this->gpm->{$function}($package->slug);
                    }

                    // Filtering enabled only
                    if ($filter && $this->options['enabled']) {
                        $method = ucfirst(preg_replace('/s$/', '', $package->package_type));

                        // Check if packaged is enabled.
                        $function = 'is' . $method . 'Enabled';
                        $filter = $this->gpm->{$function}($package->slug);
                    }

                    // Filtering disabled only
                    if ($filter && $this->options['disabled']) {
                        $method = ucfirst(preg_replace('/s$/', '', $package->package_type));

                        // Check if package is disabled.
                        $function = 'is' . $method . 'Enabled';
                        $enabled_filter = $this->gpm->{$function}($package->slug);

                        // Apply filtering results.
                        if (!( $enabled_filter === false)) {
                            $filter = false;
                        }
                    }

                    if (!$filter) {
                        unset($data[$type][$slug]);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param AbstractPackageCollection|Plugins|Themes $packages
     * @return array
     */
    public function sort(AbstractPackageCollection $packages): array
    {
        $key = $this->options['sort'];

        // Sorting only works once.
        return $packages->sort(
            function ($a, $b) use ($key) {
                switch ($key) {
                    case 'author':
                        return strcmp($a->{$key}['name'], $b->{$key}['name']);
                    default:
                        return strcmp($a->$key, $b->$key);
                }
            },
            $this->options['desc'] ? true : false
        );
    }
}
