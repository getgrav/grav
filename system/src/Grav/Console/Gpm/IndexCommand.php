<?php
namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IndexCommand
 * @package Grav\Console\Gpm
 */
class IndexCommand extends Command
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
            ->setDescription("Lists the plugins and themes available for installation")
            ->setHelp('The <info>index</info> command lists the plugins and themes available for installation');
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

        $this->data = $this->gpm->getRepository();

        $this->output->writeln('');

        foreach ($this->data as $type => $packages) {
            $this->output->writeln("<green>" . ucfirst($type) . "</green> [ " . count($packages) . " ]");

            $index = 0;
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
        $list = $this->gpm->{'getUpdatable' . ucfirst($package->package_type)}();
        $package = isset($list[$package->slug]) ? $list[$package->slug] : $package;
        $type = ucfirst(preg_replace("/s$/", '', $package->package_type));
        $updatable = $this->gpm->{'is' . $type . 'Updatable'}($package->slug);
        $installed = $this->gpm->{'is' . $type . 'Installed'}($package->slug);
        $local = $this->gpm->{'getInstalled' . $type}($package->slug);

        if (!$installed || !$updatable) {
            $version = $installed ? $local->version : $package->version;
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
}
