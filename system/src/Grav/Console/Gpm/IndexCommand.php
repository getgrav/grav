<?php
namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class IndexCommand
 *
 * @package Grav\Console\Gpm
 */
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

        $this->data = $this->gpm->getRepository();

        $this->output->writeln('');

        $data = $this->filter($this->data);

        foreach ($data as $type => $packages) {
            $this->output->writeln("<green>" . ucfirst($type) . "</green> [ " . count($packages) . " ]");

            $index    = 0;
            $packages = $this->sort($packages);
            foreach ($packages as $slug => $package) {
                $this->output->writeln(
                // index
                    str_pad($index++ + 1, 2, '0', STR_PAD_LEFT) . ". " .
                    // package name
                    "<cyan>" . str_pad($package->name, 20) . "</cyan> " .
                    // slug
                    "[" . str_pad($slug, 20, ' ', STR_PAD_BOTH) . "] " .
                    // version details
                    $this->versionDetails($package)
                );
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
    private function versionDetails($package)
    {
        $list      = $this->gpm->{'getUpdatable' . ucfirst($package->package_type)}();
        $package   = isset($list[$package->slug]) ? $list[$package->slug] : $package;
        $type      = ucfirst(preg_replace("/s$/", '', $package->package_type));
        $updatable = $this->gpm->{'is' . $type . 'Updatable'}($package->slug);
        $installed = $this->gpm->{'is' . $type . 'Installed'}($package->slug);
        $local     = $this->gpm->{'getInstalled' . $type}($package->slug);

        if (!$installed || !$updatable) {
            $version   = $installed ? $local->version : $package->version;
            $installed = !$installed ? ' (<magenta>not installed</magenta>)' : ' (<cyan>installed</cyan>)';

            return str_pad(" [v<green>" . $version . "</green>]", 35) . $installed;
        }

        if ($updatable) {
            $installed = !$installed ? ' (<magenta>not installed</magenta>)' : ' (<cyan>installed</cyan>)';

            return str_pad(" [v<red>" . $package->version . "</red> <cyan>➜</cyan> v<green>" . $package->available . "</green>]",
                61) . $installed;
        }

        return '';
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
