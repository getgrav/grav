<?php
namespace Grav\Console\Cli;

use Grav\Common\Filesystem\Folder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CleanCommand
 * @package Grav\Console\Cli
 */
class CleanCommand extends Command
{

    /**
     * @var array
     */
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
        'vendor/donatj/phpuseragentparser/.git',
        'vendor/donatj/phpuseragentparser/.gitignore',
        'vendor/donatj/phpuseragentparser/.travis.yml',
        'vendor/donatj/phpuseragentparser/composer.json',
        'vendor/donatj/phpuseragentparser/phpunit.xml.dist',
        'vendor/donatj/phpuseragentparser/Tests',
        'vendor/donatj/phpuseragentparser/Tools',
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
        'vendor/erusev/parsedown-extra/composer.json',
        'vendor/erusev/parsedown-extra/phpunit.xml.dist',
        'vendor/erusev/parsedown-extra/.travis.yml',
        'vendor/erusev/parsedown-extra/.git',
        'vendor/erusev/parsedown-extra/test',
        'vendor/filp/whoops/composer.json',
        'vendor/filp/whoops/docs',
        'vendor/filp/whoops/examples',
        'vendor/filp/whoops/tests',
        'vendor/filp/whoops/.git',
        'vendor/filp/whoops/.gitignore',
        'vendor/filp/whoops/.scrutinizer.yml',
        'vendor/filp/whoops/.travis.yml',
        'vendor/filp/whoops/phpunit.xml.dist',
        'vendor/gregwar/image/Gregwar/Image/composer.json',
        'vendor/gregwar/image/Gregwar/Image/phpunit.xml',
        'vendor/gregwar/image/Gregwar/Image/.gitignore',
        'vendor/gregwar/image/Gregwar/Image/.git',
        'vendor/gregwar/image/Gregwar/Image/doc',
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
        'vendor/maximebf/debugbar/bower.json',
        'vendor/maximebf/debugbar/composer.json',
        'vendor/maximebf/debugbar/.bowerrc',
        'vendor/maximebf/debugbar/src/Debugbar/Resources/vendor',
        'vendor/maximebf/debugbar/demo',
        'vendor/maximebf/debugbar/docs',
        'vendor/maximebf/debugbar/tests',
        'vendor/maximebf/debugbar/phpunit.xml.dist',
        'vendor/monolog/monolog/composer.json',
        'vendor/monolog/monolog/doc',
        'vendor/monolog/monolog/phpunit.xml.dist',
        'vendor/monolog/monolog/tests',
        'vendor/mrclay/minify/.editorconfig',
        'vendor/mrclay/minify/.git',
        'vendor/mrclay/minify/.gitignore',
        'vendor/mrclay/minify/composer.json',
        'vendor/mrclay/minify/min_extras',
        'vendor/mrclay/minify/min_unit_tests',
        'vendor/mrclay/minify/min/.htaccess',
        'vendor/mrclay/minify/min/builder',
        'vendor/mrclay/minify/min/config-test.php',
        'vendor/mrclay/minify/min/config.php',
        'vendor/mrclay/minify/min/groupsConfig.php',
        'vendor/mrclay/minify/min/index.php',
        'vendor/mrclay/minify/min/quick-test.css',
        'vendor/mrclay/minify/min/quick-test.js',
        'vendor/mrclay/minify/min/utils.php',
        'vendor/pimple/pimple/.gitignore',
        'vendor/pimple/pimple/.travis.yml',
        'vendor/pimple/pimple/composer.json',
        'vendor/pimple/pimple/ext',
        'vendor/pimple/pimple/phpunit.xml.dist',
        'vendor/pimple/pimple/src/Pimple/Tests',
        'vendor/psr/log/composer.json',
        'vendor/psr/log/.gitignore',
        'vendor/rockettheme/toolbox/.git',
        'vendor/rockettheme/toolbox/.gitignore',
        'vendor/rockettheme/toolbox/.scrutinizer.yml',
        'vendor/rockettheme/toolbox/.travis.yml',
        'vendor/rockettheme/toolbox/composer.json',
        'vendor/rockettheme/toolbox/phpunit.xml',
        'vendor/symfony/console/Symfony/Component/Console/composer.json',
        'vendor/symfony/console/Symfony/Component/Console/phpunit.xml.dist',
        'vendor/symfony/console/Symfony/Component/Console/.gitignore',
        'vendor/symfony/console/Symfony/Component/Console/.git',
        'vendor/symfony/console/Symfony/Component/Console/Tests',
        'vendor/symfony/event-dispatcher/Symfony/Component/EventDispatcher/.git',
        'vendor/symfony/event-dispatcher/Symfony/Component/EventDispatcher/.gitignore',
        'vendor/symfony/event-dispatcher/Symfony/Component/EventDispatcher/composer.json',
        'vendor/symfony/event-dispatcher/Symfony/Component/EventDispatcher/phpunit.xml.dist',
        'vendor/symfony/event-dispatcher/Symfony/Component/EventDispatcher/Tests',
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

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("clean")
            ->setDescription("Handles cleaning chores for Grav distribution")
            ->setHelp('The <info>clean</info> clean extraneous folders and data');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
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
    /**
     * @param OutputInterface $output
     */
    private function cleanPaths(OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<red>DELETING</red>');

        $anything = false;

        foreach ($this->paths_to_remove as $path) {
            $path = ROOT_DIR . $path;

            if (is_dir($path) && @Folder::delete($path)) {
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

}
