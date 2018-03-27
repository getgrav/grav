<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Common\Utils;
use Grav\Console\ConsoleCommand;
use League\CLImate\CLImate;
use Symfony\Component\Console\Input\InputOption;

class IndexCommand extends ConsoleCommand
{
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
    protected $options;

    /**
     * @var array
     */
    protected $sortKeys = ['name', 'slug', 'author', 'date'];

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("index")
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
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Allows to sort (ASC) the results based on one or multiple keys. SORT can be either "name", "slug", "author", "date"',
                ['date']
            )
            ->addOption(
                'desc',
                'D',
                InputOption::VALUE_NONE,
                'Reverses the order of the output.'
            )
            ->setDescription("Lists the plugins and themes available for installation")
            ->setHelp('The <info>index</info> command lists the plugins and themes available for installation')
        ;
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $this->options = $this->input->getOptions();
        $this->gpm = new GPM($this->options['force']);
        $this->displayGPMRelease();
        $this->data = $this->gpm->getRepository();

        $data = $this->filter($this->data);

        $climate = new CLImate;
        $climate->extend('Grav\Console\TerminalObjects\Table');

        if (!$data) {
            $this->output->writeln('No data was found in the GPM repository stored locally.');
            $this->output->writeln('Please try clearing cache and running the <green>bin/gpm index -f</green> command again');
            $this->output->writeln('If this doesn\'t work try tweaking your GPM system settings.');
            $this->output->writeln('');
            $this->output->writeln('For more help go to:');
            $this->output->writeln(' -> <yellow>https://learn.getgrav.org/troubleshooting/common-problems#cannot-connect-to-the-gpm</yellow>');

            die;
        }

        foreach ($data as $type => $packages) {
            $this->output->writeln("<green>" . strtoupper($type) . "</green> [ " . count($packages) . " ]");
            $packages = $this->sort($packages);

            if (!empty($packages)) {

                $table = [];
                $index    = 0;

                foreach ($packages as $slug => $package) {
                    $row = [
                        'Count' => $index++ + 1,
                        'Name' => "<cyan>" . Utils::truncate($package->name, 20, false, ' ', '...') . "</cyan> ",
                        'Slug' => $slug,
                        'Version'=> $this->version($package),
                        'Installed' => $this->installed($package)
                    ];
                    $table[] = $row;
                }

                $climate->table($table);
            }

            $this->output->writeln('');
        }

        $this->output->writeln('You can either get more informations about a package by typing:');
        $this->output->writeln('    <green>' . $this->argv . ' info <cyan><package></cyan></green>');
        $this->output->writeln('');
        $this->output->writeln('Or you can install a package by typing:');
        $this->output->writeln('    <green>' . $this->argv . ' install <cyan><package></cyan></green>');
        $this->output->writeln('');
    }

    /**
     * @param $package
     *
     * @return string
     */
    private function version($package)
    {
        $list      = $this->gpm->{'getUpdatable' . ucfirst($package->package_type)}();
        $package   = isset($list[$package->slug]) ? $list[$package->slug] : $package;
        $type      = ucfirst(preg_replace("/s$/", '', $package->package_type));
        $updatable = $this->gpm->{'is' . $type . 'Updatable'}($package->slug);
        $installed = $this->gpm->{'is' . $type . 'Installed'}($package->slug);
        $local     = $this->gpm->{'getInstalled' . $type}($package->slug);

        if (!$installed || !$updatable) {
            $version   = $installed ? $local->version : $package->version;
            return "v<green>" . $version . "</green>";
        }

        if ($updatable) {
            return "v<red>" . $package->version . "</red> <cyan>-></cyan> v<green>" . $package->available . "</green>";
        }

        return '';
    }

    /**
     * @param $package
     *
     * @return string
     */
    private function installed($package)
    {
        $package   = isset($list[$package->slug]) ? $list[$package->slug] : $package;
        $type      = ucfirst(preg_replace("/s$/", '', $package->package_type));
        $installed = $this->gpm->{'is' . $type . 'Installed'}($package->slug);

        return !$installed ? '<magenta>not installed</magenta>' : '<cyan>installed</cyan>';
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    public function filter($data)
    {
        // filtering and sorting
        if ($this->options['plugins-only']) {
            unset($data['themes']);
        }
        if ($this->options['themes-only']) {
            unset($data['plugins']);
        }

        $filter = [
            $this->options['filter'],
            $this->options['installed-only'],
            $this->options['updates-only'],
            $this->options['desc']
        ];

        if (count(array_filter($filter))) {
            foreach ($data as $type => $packages) {
                foreach ($packages as $slug => $package) {
                    $filter = true;

                    // Filtering by string
                    if ($this->options['filter']) {
                        $filter = preg_grep('/(' . (implode('|', $this->options['filter'])) . ')/i', [$slug, $package->name]);
                    }

                    // Filtering updatables only
                    if ($this->options['installed-only'] && $filter) {
                        $method = ucfirst(preg_replace("/s$/", '', $package->package_type));
                        $filter = $this->gpm->{'is' . $method . 'Installed'}($package->slug);
                    }

                    // Filtering updatables only
                    if ($this->options['updates-only'] && $filter) {
                        $method = ucfirst(preg_replace("/s$/", '', $package->package_type));
                        $filter = $this->gpm->{'is' . $method . 'Updatable'}($package->slug);
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
     * @param $packages
     */
    public function sort($packages)
    {
        foreach ($this->options['sort'] as $key) {
            $packages = $packages->sort(function ($a, $b) use ($key) {
                switch ($key) {
                    case 'author':
                        return strcmp($a->{$key}['name'], $b->{$key}['name']);
                        break;
                    default:
                        return strcmp($a->$key, $b->$key);
                }
            }, $this->options['desc'] ? true : false);
        }

        return $packages;
    }
}
