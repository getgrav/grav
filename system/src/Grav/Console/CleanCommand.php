<?php
namespace Grav\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class CleanCommand extends Command {

    protected $destination_dir = 'distribution';

    protected $paths_to_remove = [
        'user/plugins/email/vendor/swiftmailer/swiftmailer/.travis.yml',
        'user/plugins/email/vendor/swiftmailer/swiftmailer/build.xml',
        'user/plugins/email/vendor/swiftmailer/swiftmailer/composer.json',
        'user/plugins/email/vendor/swiftmailer/swiftmailer/create_pear_package.php',
        'user/plugins/email/vendor/swiftmailer/swiftmailer/package.xml.tpl',
        'user/plugins/email/vendor/swiftmailer/swiftmailer/.gitattributes',
        'user/plugins/email/vendor/swiftmailer/swiftmailer/.gitignore',
        'user/plugins/email/vendor/swiftmailer/swiftmailer/README.git',
        'user/plugins/email/vendor/swiftmailer/swiftmailer/tests',
        'user/plugins/email/vendor/swiftmailer/swiftmailer/test-suite',
        'user/plugins/email/vendor/swiftmailer/swiftmailer/notes',
        'user/plugins/email/vendor/swiftmailer/swiftmailer/doc',
        'user/themes/antimatter/.sass-cache',
        'vendor/doctrine/cache/.travis.yml',
        'vendor/doctrine/cache/build.properties',
        'vendor/doctrine/cache/build.xml',
        'vendor/doctrine/cache/composer.json',
        'vendor/doctrine/cache/phpunit.xml.dist',
        'vendor/doctrine/cache/.coveralls.yml',
        'vendor/doctrine/cache/.gitignore',
        'vendor/doctrine/cache/.git',
        'vendor/doctrine/cache/tests',
        'vendor/erusev/parsedown/composer.json',
        'vendor/erusev/parsedown/phpunit.xml.dist',
        'vendor/erusev/parsedown/.travis.yml',
        'vendor/erusev/parsedown/.git',
        'vendor/erusev/parsedown/test',
        'vendor/gregwar/image/Gregwar/Image/composer.json',
        'vendor/gregwar/image/Gregwar/Image/phpunit.xml',
        'vendor/gregwar/image/Gregwar/Image/.gitignore',
        'vendor/gregwar/image/Gregwar/Image/.git',
        'vendor/gregwar/image/Gregwar/Image/demo',
        'vendor/gregwar/image/Gregwar/Image/tests',
        'vendor/gregwar/cache/Gregwar/Cache/composer.json',
        'vendor/gregwar/cache/Gregwar/Cache/phpunit.xml',
        'vendor/gregwar/cache/Gregwar/Cache/.gitignore',
        'vendor/gregwar/cache/Gregwar/Cache/.git',
        'vendor/gregwar/cache/Gregwar/Cache/demo',
        'vendor/gregwar/cache/Gregwar/Cache/tests',
        'vendor/ircmaxell/password-compat/composer.json',
        'vendor/ircmaxell/password-compat/phpunit.xml.dist',
        'vendor/ircmaxell/password-compat/version-test.php',
        'vendor/ircmaxell/password-compat/.travis.yml',
        'vendor/ircmaxell/password-compat/test',
        'vendor/symfony/console/Symfony/Component/Console/composer.json',
        'vendor/symfony/console/Symfony/Component/Console/phpunit.xml.dist',
        'vendor/symfony/console/Symfony/Component/Console/.gitignore',
        'vendor/symfony/console/Symfony/Component/Console/.git',
        'vendor/symfony/console/Symfony/Component/Console/Tests',
        'vendor/symfony/yaml/Symfony/Component/Yaml/composer.json',
        'vendor/symfony/yaml/Symfony/Component/Yaml/phpunit.xml.dist',
        'vendor/symfony/yaml/Symfony/Component/Yaml/.gitignore',
        'vendor/symfony/yaml/Symfony/Component/Yaml/.git',
        'vendor/symfony/yaml/Symfony/Component/Yaml/Tests',
        'vendor/tracy/tracy/.gitattributes',
        'vendor/tracy/tracy/.travis.yml',
        'vendor/tracy/tracy/composer.json',
        'vendor/tracy/tracy/.gitignore',
        'vendor/tracy/tracy/.git',
        'vendor/tracy/tracy/examples',
        'vendor/tracy/tracy/tests',
        'vendor/twig/twig/.editorconfig',
        'vendor/twig/twig/.travis.yml',
        'vendor/twig/twig/.gitignore',
        'vendor/twig/twig/.git',
        'vendor/twig/twig/composer.json',
        'vendor/twig/twig/phpunit.xml.dist',
        'vendor/twig/twig/doc',
        'vendor/twig/twig/ext',
        'vendor/twig/twig/test',
    ];

    protected function configure() {
        $this
        ->setName("clean")
        ->setDescription("Handles cl chores for Grav")
        ->setHelp('The <info>clean</info> clean extraneous folders and data');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {


        // Create a red output option
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
        $output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan'));
        $output->getFormatter()->setStyle('green', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta'));

        $this->cleanPaths($output);


    }

    // loops over the array of paths and deletes the files/folders
    private function cleanPaths($output)
    {
        $output->writeln('');
        $output->writeln('<red>DELETING</red>');

        $anything = false;

        foreach($this->paths_to_remove as $path) {
            $path = ROOT_DIR . $path;

            if (is_dir($path) && @$this->rrmdir($path)) {
                $anything = true;
                $output->writeln('<red>dir:  </red>' . $path);
            } elseif (is_file($path) && @unlink($path)) {
                $anything = true;
                $output->writeln('<red>file: </red>' . $path);
            }
        }

        if (!$anything) {
            $output->writeln('');
            $output->writeln('<green>Nothing to clean...</green>');
        }

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
