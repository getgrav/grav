<?php
namespace Grav\Console\Gpm;

use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\ProgressBar;

class FetchCommand extends Command {
    use ConsoleTrait;

    protected $cache;
    protected $progress;
    protected $pkg_types = array('plugins', 'themes');

    protected function configure()
    {
        $this
        ->setName("fetch")
        ->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force fetching the new data remotely'
        )
        ->setDescription("Fetches the data for plugins and themes available")
        ->setHelp('The <info>fetch</info> command downloads the list of plugins and themes available');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output);

        $this->cache = self::$grav['cache']->fetch(md5('cli:gpm'));
        $this->fetch($this->output);
    }

    private function fetch()
    {
        if (!$this->cache || $this->input->getOption('force')){
            $data = $this->fetchData();
            $date = new \DateTime();
            self::$grav['cache']->save(md5('cli:gpm'), $data, 86400);
            $date = $date->modify('+1 day')->format('D, d M Y H:i:s');
            $this->cache = $data;
            $this->output->writeln("Data cached until <cyan>".$date."</cyan>\n");
        }
    }

    private function fetchData()
    {
        $this->output->writeln("");
        $this->output->writeln('Fetching data from <cyan>getgrav.org</cyan>');
        $this->output->writeln("");
        $curl = $this->getCurl();
        $response = array();

        $this->progress = new ProgressBar($this->output, count($this->pkg_types));
        $this->progress->setFormat("<normal>%message%</normal>\n<cyan>%current%</cyan><normal>/</normal><cyan>%max%</cyan> <white>[%bar%]</white> <green>%percent:3s%%</green>");

        $this->progress->setMessage('Task in progress');
        $this->progress->start();
        foreach($this->pkg_types as $pkg_type) {
            $this->progress->setMessage('Fetching "'.$pkg_type.'"...');
            $url = $this->repository . '/' . $pkg_type . '.json';

            curl_setopt($curl, CURLOPT_URL, $url);
            $response[$pkg_type] = curl_exec($curl);

            $this->progress->advance();
        }

        curl_close($curl);
        $this->progress->setMessage("Fetch completed");
        $this->progress->finish();
        $this->output->writeln("");

        return $response;
    }

    private function getCurl($progress = false)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_REFERER, 'Grav GPM v'.GRAV_VERSION);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Grav GPM v'.GRAV_VERSION);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        if ($progress)
        {
            curl_setopt($curl, CURLOPT_NOPROGRESS, false);
            curl_setopt($curl, CURLOPT_HEADER, 1);
            curl_setopt($curl, CURLOPT_PROGRESSFUNCTION, array($this, 'progress'));
        }

        return $curl;
    }

    private function progress($download_size, $downloaded)
    {
        if ($download_size > 0)
        {
            $this->output->writeln($downloaded / $download_size  * 100);
        }
    }
}
