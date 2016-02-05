<?php
namespace Grav\Console\Cli;

use Grav\Common\Filesystem\Folder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * Class CleanCommand
 * @package Grav\Console\Cli
 */
class CleanCommand extends Command
{
    /* @var InputInterface $output */
    protected $input;

    /* @var OutputInterface $output */
    protected $output;

    /**
     * @var array
     */
    protected $paths_to_remove = [
        'codeception.yml',
        'tests/',
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
        'vendor/maximebf/debugbar/src/DebugBar/Resources/vendor',
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
        'vendor/symfony/console/composer.json',
        'vendor/symfony/console/phpunit.xml.dist',
        'vendor/symfony/console/.gitignore',
        'vendor/symfony/console/.git',
        'vendor/symfony/console/Tester',
        'vendor/symfony/console/Tests',
        'vendor/symfony/event-dispatcher/.git',
        'vendor/symfony/event-dispatcher/.gitignore',
        'vendor/symfony/event-dispatcher/composer.json',
        'vendor/symfony/event-dispatcher/phpunit.xml.dist',
        'vendor/symfony/event-dispatcher/Tests',
        'vendor/symfony/polyfill-iconv/.git',
        'vendor/symfony/polyfill-iconv/.gitignore',
        'vendor/symfony/polyfill-iconv/composer.json',
        'vendor/symfony/polyfill-mbstring/.git',
        'vendor/symfony/polyfill-mbstring/.gitignore',
        'vendor/symfony/polyfill-mbstring/composer.json',
        'vendor/symfony/var-dumper/.git',
        'vendor/symfony/var-dumper/.gitignore',
        'vendor/symfony/var-dumper/composer.json',
        'vendor/symfony/var-dumper/phpunit.xml.dist',
        'vendor/symfony/var-dumper/Test',
        'vendor/symfony/var-dumper/Tests',
        'vendor/symfony/yaml/composer.json',
        'vendor/symfony/yaml/phpunit.xml.dist',
        'vendor/symfony/yaml/.gitignore',
        'vendor/symfony/yaml/.git',
        'vendor/symfony/yaml/Tests',
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
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output);

        $this->cleanPaths();
    }

    private function cleanPaths()
    {
        $this->output->writeln('');
        $this->output->writeln('<red>DELETING</red>');
        $anything = false;
        foreach ($this->paths_to_remove as $path) {
            $path = ROOT_DIR . $path;
            if (is_dir($path) && @Folder::delete($path)) {
                $anything = true;
                $this->output->writeln('<red>dir:  </red>' . $path);
            } elseif (is_file($path) && @unlink($path)) {
                $anything = true;
                $this->output->writeln('<red>file: </red>' . $path);
            }
        }
        if (!$anything) {
            $this->output->writeln('');
            $this->output->writeln('<green>Nothing to clean...</green>');
        }
    }

        /**
     * Set colors style definition for the formatter.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function setupConsole(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;

        $this->output->getFormatter()->setStyle('normal', new OutputFormatterStyle('white'));
        $this->output->getFormatter()->setStyle('yellow', new OutputFormatterStyle('yellow', null, array('bold')));
        $this->output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, array('bold')));
        $this->output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan', null, array('bold')));
        $this->output->getFormatter()->setStyle('green', new OutputFormatterStyle('green', null, array('bold')));
        $this->output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, array('bold')));
        $this->output->getFormatter()->setStyle('white', new OutputFormatterStyle('white', null, array('bold')));
    }

}
