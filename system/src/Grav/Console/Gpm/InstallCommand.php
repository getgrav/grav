<?php
namespace Grav\Console\Gpm;

use Grav\Common\Grav;
use Grav\Common\Cache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class InstallCommand extends Command {

    protected $input;
    protected $ouput;
    protected $data;
    protected $argv;
    protected $destination;
    protected $file;

    public function __construct(Grav $grav){
        $this->grav = $grav;

        // just for the gpm cli we force the filesystem driver cache
        $this->grav['config']->set('system.cache.driver', 'default');
        $this->argv = $_SERVER['argv'][0];

        parent::__construct();
    }

    protected function configure() {
        $this
        ->setName("install")
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
            'The destination where the package should be installed at. By default this would be where the grav instance has been launched from',
            GRAV_ROOT
        )
        ->addArgument(
            'package',
            InputArgument::IS_ARRAY | InputArgument::REQUIRED,
            'The package of which more informations are desired. Use the "index" command for a list of packages'
        )
        ->setDescription("Lists the plugins and themes available for installation")
        ->setHelp('The <info>index</info> command lists the plugins and themes available for installation');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;
        $this->destination = realpath($this->input->getOption('destination'));

        $packages_to_install = array_map('strtolower', $this->input->getArgument('package'));

        $this->setColors();
        $this->isGravRoot($this->destination);

        $fetchCommand = $this->getApplication()->find('fetch');
        $args         = new ArrayInput(array('command' => 'fetch', '-f' => $input->getOption('force')));
        $commandExec = $fetchCommand->run($args, $output);

        if ($commandExec != 0){
            $output->writeln("<red>Error:</red> An error occured while trying to fetch data from <cyan>getgrav.org</cyan>");
            exit;
        }

        $this->data = $this->grav['cache']->fetch(md5('cli:gpm'));

        $this->output->writeln('');

        $found_packages = $this->findPackages($packages_to_install);

        $found     = array_intersect($packages_to_install, array_keys($found_packages));
        $not_found = array_diff($packages_to_install, array_keys($found_packages));

        if (count($not_found)){
            $this->output->writeln("These packages were not found on Grav: <red>".implode('</red>, <red>', $not_found)."</red>");
        }

        if (!count($found)){
            $this->output->writeln("Nothing to install.");
            $this->output->writeln('');
            exit;
        }

        $this->output->writeln('');
        foreach ($found as $package) {
            $this->output->writeln("Preparing to install <cyan>".$found_packages[$package]->name."</cyan> [v".$found_packages[$package]->version."]");

            $this->output->write("  |- Downloading package...     0%");
            $this->file = $this->downloadPackage($found_packages[$package]);

            $this->output->write("  |- Checking destination...  ");
            $checks = $this->checkDestination($found_packages[$package]);

            if (!$checks){
                $this->output->writeln("  '- <red>Installation failed or aborted. See errors above</red>");
            } else {
                $this->output->write("  |- Installing package...  ");
                $installation = $this->installPackage($found_packages[$package]);
                if (!$installation){
                    $this->output->writeln("  '- <red>Installation failed or aborted. See errors above</red>");
                    $this->output->writeln('');
                } else {
                    $this->output->writeln("  '- <green>Success!</green>  ");
                    $this->output->writeln('');
                }
            }
        }

        $this->output->writeln('');
    }

    private function setColors()
    {
        $this->output->getFormatter()->setStyle('normal', new OutputFormatterStyle('white'));
        $this->output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, array('bold')));
        $this->output->getFormatter()->setStyle('yellow', new OutputFormatterStyle('yellow', null, array('bold')));
        $this->output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan', null, array('bold')));
        $this->output->getFormatter()->setStyle('green', new OutputFormatterStyle('green', null, array('bold')));
        $this->output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, array('bold')));
        $this->output->getFormatter()->setStyle('white', new OutputFormatterStyle('white', null, array('bold')));
    }

    private function findPackages($haystack)
    {
        $found = array();

        foreach ($this->data as $type => $result) {
            $result = json_decode($result)->results;

            foreach ($result->data as $index => $package) {
                if ($this->in_arrayi($package->slug, $haystack) || $this->in_arrayi($package->name, $haystack)){
                    $found[$package->slug] = $package;
                }
            }
        }

        return $found;
    }

    private function downloadPackage($package)
    {
        $curl     = $this->getCurl($package->download);
        $tmp      = $this->destination.DS.'tmp-gpm';
        $filename = $package->slug.basename($package->download);
        $output   = curl_exec($curl);

        $this->output->write("\x0D");
        $this->output->write("  |- Downloading package...   100%");

        curl_close($curl);

        $this->output->writeln('');

        if (!file_exists($tmp)) @mkdir($tmp);
        file_put_contents($tmp.DS.$filename, $output);

        return $tmp.DS.$filename;
    }

    private function checkDestination($package)
    {
        $destination = $this->destination . DS . $package->install_path;
        $helper = $this->getHelper('question');

        if (is_dir($destination) && !is_link($destination)){
            $this->output->write("\x0D");
            $this->output->writeln("  |- Checking destination...  <yellow>exists</yellow>");

            $question = new ConfirmationQuestion("  |  '- The package has been detected as installed already, do you want to overwrite it? [y|N] ", false);
            $answer   = $helper->ask($this->input, $this->output, $question);

            if (!$answer){
                $this->output->writeln("  |     '- <red>You decided to not overwrite the already installed package.</red>");
                return false;
            }

            $this->rrmdir($destination);
            @mkdir($destination, 0777, true);
        }

        if (is_link($destination)){
            $this->output->write("\x0D");
            $this->output->writeln("  |- Checking destination...  <yellow>symbolic link</yellow>");

            $question = new ConfirmationQuestion("  |  '- Destination has been detected as symlink, delete symbolic link first? [y|N] ", false);
            $answer   = $helper->ask($this->input, $this->output, $question);

            if (!$answer){
                $this->output->writeln("  |     '- <red>You decided to not delete the symlink automatically.</red>");
                return false;
            }

            @unlink($destination);
        }

        return true;
    }

    private function installPackage($package)
    {
        $destination = $this->destination . DS . $package->install_path;
        $zip         = new \ZipArchive;
        $openZip     = $zip->open($this->file);
        $tmp         = $this->destination.DS.'tmp-gpm';


        if (!$openZip){
            $this->output->write("\x0D");
            $this->output->writeln("  |- Installing package...    <red>error</red>                             ");
            $this->output->writeln("  |  '- Unable to open the downloaded package: <yellow>".$package->download."</yellow>");

            return false;
        }

        $innerFolder = $zip->getNameIndex(0);

        $zip->extractTo($tmp);
        $zip->close();

        rename($tmp.DS.$innerFolder, $destination);

        $this->output->write("\x0D");
        $this->output->writeln("  |- Installing package...    <green>ok</green>                             ");
        return true;
    }


    private function unpackPackage($package)
    {

    }

    private function isGravRoot($path)
    {
        if (!file_exists($path)){
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination doesn't exist:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }

        if (!is_dir($path)){
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination chosen to install is not a directory:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }

        if (!file_exists($path.DS.'index.php') || !file_exists($path.DS.'.dependencies') || !file_exists($path.DS.'system'.DS.'config'.DS.'system.yaml')){
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination chosen to install does not appear to be a Grav instance:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }
    }


    private function getCurl($url)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_REFERER, 'Grav GPM v'.GRAV_VERSION);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Grav GPM v'.GRAV_VERSION);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_NOPROGRESS, false);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_PROGRESSFUNCTION, array($this, 'progress'));

        return $curl;
    }

    private function progress($curl, $download_size, $downloaded)
    {
        if ($download_size > 0)
        {
            $this->output->write("\x0D");
            $this->output->write("  |- Downloading package... " . str_pad(round($downloaded / $download_size  * 100, 2), 5, " ", STR_PAD_LEFT) . '%');
        }
    }

    private function in_arrayi($needle, $haystack)
    {
        return in_array(strtolower($needle), array_map('strtolower', $haystack));
    }

    // Recursively Delete folder - DANGEROUS! USE WITH CARE!!!!
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir") $this->rrmdir($dir."/".$object); else unlink($dir."/".$object);
                }
            }
            reset($objects);
            rmdir($dir);
            return true;
        }
    }
}
