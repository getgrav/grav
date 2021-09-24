<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Filesystem\Folder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class CleanCommand
 * @package Grav\Console\Cli
 */
class CleanCommand extends Command
{
    /** @var InputInterface */
    protected $input;
    /** @var SymfonyStyle */
    protected $io;

    /** @var array */
    protected $paths_to_remove = [
        'codeception.yml',
        'tests/',
        'user/plugins/admin/vendor/bacon/bacon-qr-code/tests',
        'user/plugins/admin/vendor/bacon/bacon-qr-code/.gitignore',
        'user/plugins/admin/vendor/bacon/bacon-qr-code/.travis.yml',
        'user/plugins/admin/vendor/bacon/bacon-qr-code/composer.json',
        'user/plugins/admin/vendor/robthree/twofactorauth/demo',
        'user/plugins/admin/vendor/robthree/twofactorauth/.vs',
        'user/plugins/admin/vendor/robthree/twofactorauth/tests',
        'user/plugins/admin/vendor/robthree/twofactorauth/.gitignore',
        'user/plugins/admin/vendor/robthree/twofactorauth/.travis.yml',
        'user/plugins/admin/vendor/robthree/twofactorauth/composer.json',
        'user/plugins/admin/vendor/robthree/twofactorauth/composer.lock',
        'user/plugins/admin/vendor/robthree/twofactorauth/logo.png',
        'user/plugins/admin/vendor/robthree/twofactorauth/multifactorauthforeveryone.png',
        'user/plugins/admin/vendor/robthree/twofactorauth/TwoFactorAuth.phpproj',
        'user/plugins/admin/vendor/robthree/twofactorauth/TwoFactorAuth.sin',
        'user/plugins/admin/vendor/zendframework/zendxml/tests',
        'user/plugins/admin/vendor/zendframework/zendxml/.gitignore',
        'user/plugins/admin/vendor/zendframework/zendxml/.travis.yml',
        'user/plugins/admin/vendor/zendframework/zendxml/composer.json',
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
        'vendor/antoligy/dom-string-iterators/composer.json',
        'vendor/composer/ca-bundle/composer.json',
        'vendor/composer/ca-bundle/phpstan.neon.dist',
        'vendor/composer/semver/CHANGELOG.md',
        'vendor/composer/semver/composer.json',
        'vendor/composer/semver/phpstan.neon.dist',
        'vendor/doctrine/cache/.travis.yml',
        'vendor/doctrine/cache/build.properties',
        'vendor/doctrine/cache/build.xml',
        'vendor/doctrine/cache/composer.json',
        'vendor/doctrine/cache/phpunit.xml.dist',
        'vendor/doctrine/cache/.coveralls.yml',
        'vendor/doctrine/cache/.gitignore',
        'vendor/doctrine/cache/.git',
        'vendor/doctrine/cache/tests',
        'vendor/doctrine/cache/UPGRADE.md',
        'vendor/doctrine/collections/docs',
        'vendor/doctrine/collections/.doctrine-project.json',
        'vendor/doctrine/collections/CONTRIBUTING.md',
        'vendor/doctrine/collections/psalm.xml.dist',
        'vendor/doctrine/collections/composer.json',
        'vendor/doctrine/collections/phpunit.xml.dist',
        'vendor/doctrine/collections/tests',
        'vendor/donatj/phpuseragentparser/.git',
        'vendor/donatj/phpuseragentparser/.github',
        'vendor/donatj/phpuseragentparser/.gitignore',
        'vendor/donatj/phpuseragentparser/.editorconfig',
        'vendor/donatj/phpuseragentparser/.travis.yml',
        'vendor/donatj/phpuseragentparser/composer.json',
        'vendor/donatj/phpuseragentparser/phpunit.xml.dist',
        'vendor/donatj/phpuseragentparser/tests',
        'vendor/donatj/phpuseragentparser/Tools',
        'vendor/donatj/phpuseragentparser/CONTRIBUTING.md',
        'vendor/donatj/phpuseragentparser/Makefile',
        'vendor/donatj/phpuseragentparser/.mddoc.xml',
        'vendor/dragonmantank/cron-expression/.editorconfig',
        'vendor/dragonmantank/cron-expression/composer.json',
        'vendor/dragonmantank/cron-expression/tests',
        'vendor/dragonmantank/cron-expression/CHANGELOG.md',
        'vendor/rhukster/dom-sanitizer/tests',
        'vendor/rhukster/dom-sanitizer/.gitignore',
        'vendor/rhukster/dom-sanitizer/composer.json',
        'vendor/rhukster/dom-sanitizer/composer.lock',
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
        'vendor/filp/whoops/.github',
        'vendor/filp/whoops/.gitignore',
        'vendor/filp/whoops/.scrutinizer.yml',
        'vendor/filp/whoops/.travis.yml',
        'vendor/filp/whoops/phpunit.xml.dist',
        'vendor/filp/whoops/CHANGELOG.md',
        'vendor/gregwar/image/Gregwar/Image/composer.json',
        'vendor/gregwar/image/Gregwar/Image/phpunit.xml',
        'vendor/gregwar/image/Gregwar/Image/phpunit.xml.dist',
        'vendor/gregwar/image/Gregwar/Image/Makefile',
        'vendor/gregwar/image/Gregwar/Image/.editorconfig',
        'vendor/gregwar/image/Gregwar/Image/.php_cs',
        'vendor/gregwar/image/Gregwar/Image/.styleci.yml',
        'vendor/gregwar/image/Gregwar/Image/.travis.yml',
        'vendor/gregwar/image/Gregwar/Image/.gitignore',
        'vendor/gregwar/image/Gregwar/Image/.git',
        'vendor/gregwar/image/Gregwar/Image/doc',
        'vendor/gregwar/image/Gregwar/Image/demo',
        'vendor/gregwar/image/Gregwar/Image/tests',
        'vendor/gregwar/cache/Gregwar/Cache/composer.json',
        'vendor/gregwar/cache/Gregwar/Cache/phpunit.xml',
        'vendor/gregwar/cache/Gregwar/Cache/.travis.yml',
        'vendor/gregwar/cache/Gregwar/Cache/.gitignore',
        'vendor/gregwar/cache/Gregwar/Cache/.git',
        'vendor/gregwar/cache/Gregwar/Cache/demo',
        'vendor/gregwar/cache/Gregwar/Cache/tests',
        'vendor/guzzlehttp/psr7/composer.json',
        'vendor/guzzlehttp/psr7/.editorconfig',
        'vendor/guzzlehttp/psr7/CHANGELOG.md',
        'vendor/itsgoingd/clockwork/.gitattributes',
        'vendor/itsgoingd/clockwork/CHANGELOG.md',
        'vendor/itsgoingd/clockwork/composer.json',
        'vendor/league/climate/composer.json',
        'vendor/league/climate/CHANGELOG.md',
        'vendor/league/climate/CONTRIBUTING.md',
        'vendor/league/climate/Dockerfile',
        'vendor/league/climate/CODE_OF_CONDUCT.md',
        'vendor/matthiasmullie/minify/.github',
        'vendor/matthiasmullie/minify/bin',
        'vendor/matthiasmullie/minify/composer.json',
        'vendor/matthiasmullie/minify/docker-compose.yml',
        'vendor/matthiasmullie/minify/Dockerfile',
        'vendor/matthiasmullie/minify/CONTRIBUTING.md',
        'vendor/matthiasmullie/path-converter/composer.json',
        'vendor/maximebf/debugbar/.github',
        'vendor/maximebf/debugbar/bower.json',
        'vendor/maximebf/debugbar/composer.json',
        'vendor/maximebf/debugbar/.bowerrc',
        'vendor/maximebf/debugbar/src/DebugBar/Resources/vendor',
        'vendor/maximebf/debugbar/demo',
        'vendor/maximebf/debugbar/docs',
        'vendor/maximebf/debugbar/tests',
        'vendor/maximebf/debugbar/phpunit.xml.dist',
        'vendor/miljar/php-exif/.coveralls.yml',
        'vendor/miljar/php-exif/.gitignore',
        'vendor/miljar/php-exif/.travis.yml',
        'vendor/miljar/php-exif/composer.json',
        'vendor/miljar/php-exif/composer.lock',
        'vendor/miljar/php-exif/phpunit.xml.dist',
        'vendor/miljar/php-exif/Resources',
        'vendor/miljar/php-exif/tests',
        'vendor/miljar/php-exif/CHANGELOG.rst',
        'vendor/monolog/monolog/composer.json',
        'vendor/monolog/monolog/doc',
        'vendor/monolog/monolog/phpunit.xml.dist',
        'vendor/monolog/monolog/.php_cs',
        'vendor/monolog/monolog/tests',
        'vendor/monolog/monolog/CHANGELOG.md',
        'vendor/monolog/monolog/phpstan.neon.dist',
        'vendor/nyholm/psr7/composer.json',
        'vendor/nyholm/psr7/phpstan.neon.dist',
        'vendor/nyholm/psr7/CHANGELOG.md',
        'vendor/nyholm/psr7/psalm.xml',
        'vendor/nyholm/psr7-server/.github',
        'vendor/nyholm/psr7-server/composer.json',
        'vendor/nyholm/psr7-server/CHANGELOG.md',
        'vendor/phive/twig-extensions-deferred/.gitignore',
        'vendor/phive/twig-extensions-deferred/.travis.yml',
        'vendor/phive/twig-extensions-deferred/composer.json',
        'vendor/phive/twig-extensions-deferred/phpunit.xml.dist',
        'vendor/phive/twig-extensions-deferred/tests',
        'vendor/php-http/message-factory/composer.json',
        'vendor/php-http/message-factory/puli.json',
        'vendor/php-http/message-factory/CHANGELOG.md',
        'vendor/pimple/pimple/.gitignore',
        'vendor/pimple/pimple/.travis.yml',
        'vendor/pimple/pimple/composer.json',
        'vendor/pimple/pimple/ext',
        'vendor/pimple/pimple/phpunit.xml.dist',
        'vendor/pimple/pimple/src/Pimple/Tests',
        'vendor/pimple/pimple/.php_cs.dist',
        'vendor/pimple/pimple/CHANGELOG',
        'vendor/psr/cache/CHANGELOG.md',
        'vendor/psr/cache/composer.json',
        'vendor/psr/container/composer.json',
        'vendor/psr/container/.gitignore',
        'vendor/psr/http-factory/.gitignore',
        'vendor/psr/http-factory/.pullapprove.yml',
        'vendor/psr/http-factory/composer.json',
        'vendor/psr/http-message/composer.json',
        'vendor/psr/http-message/CHANGELOG.md',
        'vendor/psr/http-server-handler/composer.json',
        'vendor/psr/http-server-middleware/composer.json',
        'vendor/psr/simple-cache/.editorconfig',
        'vendor/psr/simple-cache/composer.json',
        'vendor/psr/log/composer.json',
        'vendor/psr/log/.gitignore',
        'vendor/ralouphie/getallheaders/.gitignore',
        'vendor/ralouphie/getallheaders/.travis.yml',
        'vendor/ralouphie/getallheaders/composer.json',
        'vendor/ralouphie/getallheaders/phpunit.xml',
        'vendor/ralouphie/getallheaders/tests/',
        'vendor/rockettheme/toolbox/.git',
        'vendor/rockettheme/toolbox/.gitignore',
        'vendor/rockettheme/toolbox/.scrutinizer.yml',
        'vendor/rockettheme/toolbox/.travis.yml',
        'vendor/rockettheme/toolbox/composer.json',
        'vendor/rockettheme/toolbox/phpunit.xml',
        'vendor/rockettheme/toolbox/CHANGELOG.md',
        'vendor/rockettheme/toolbox/Blueprints/tests',
        'vendor/rockettheme/toolbox/ResourceLocator/tests',
        'vendor/rockettheme/toolbox/Session/tests',
        'vendor/rockettheme/toolbox/tests',
        'vendor/seld/cli-prompt/composer.json',
        'vendor/seld/cli-prompt/.gitignore',
        'vendor/seld/cli-prompt/.github',
        'vendor/seld/cli-prompt/phpstan.neon.dist',
        'vendor/symfony/console/composer.json',
        'vendor/symfony/console/phpunit.xml.dist',
        'vendor/symfony/console/.gitignore',
        'vendor/symfony/console/.git',
        'vendor/symfony/console/Tester',
        'vendor/symfony/console/Tests',
        'vendor/symfony/console/CHANGELOG.md',
        'vendor/symfony/contracts/Cache/.gitignore',
        'vendor/symfony/contracts/Cache/composer.json',
        'vendor/symfony/contracts/EventDispatcher/.gitignore',
        'vendor/symfony/contracts/EventDispatcher/composer.json',
        'vendor/symfony/contracts/HttpClient/.gitignore',
        'vendor/symfony/contracts/HttpClient/composer.json',
        'vendor/symfony/contracts/HttpClient/Test',
        'vendor/symfony/contracts/Service/.gitignore',
        'vendor/symfony/contracts/Service/composer.json',
        'vendor/symfony/contracts/Service/Test',
        'vendor/symfony/contracts/Tests',
        'vendor/symfony/contracts/Translation/.gitignore',
        'vendor/symfony/contracts/Translation/composer.json',
        'vendor/symfony/contracts/Translation/Test',
        'vendor/symfony/contracts/.gitignore',
        'vendor/symfony/contracts/composer.json',
        'vendor/symfony/contracts/phpunit.xml.dist',
        'vendor/symfony/event-dispatcher/.git',
        'vendor/symfony/event-dispatcher/.gitignore',
        'vendor/symfony/event-dispatcher/composer.json',
        'vendor/symfony/event-dispatcher/phpunit.xml.dist',
        'vendor/symfony/event-dispatcher/Tests',
        'vendor/symfony/event-dispatcher/CHANGELOG.md',
        'vendor/symfony/http-client/CHANGELOG.md',
        'vendor/symfony/http-client/composer.json',
        'vendor/symfony/polyfill-ctype/composer.json',
        'vendor/symfony/polyfill-iconv/.git',
        'vendor/symfony/polyfill-iconv/.gitignore',
        'vendor/symfony/polyfill-iconv/composer.json',
        'vendor/symfony/polyfill-mbstring/.git',
        'vendor/symfony/polyfill-mbstring/.gitignore',
        'vendor/symfony/polyfill-mbstring/composer.json',
        'vendor/symfony/polyfill-php72/composer.json',
        'vendor/symfony/polyfill-php73/composer.json',
        'vendor/symfony/process/.gitignore',
        'vendor/symfony/process/composer.json',
        'vendor/symfony/process/phpunit.xml.dist',
        'vendor/symfony/process/Tests',
        'vendor/symfony/process/CHANGELOG.md',
        'vendor/symfony/var-dumper/.git',
        'vendor/symfony/var-dumper/.gitignore',
        'vendor/symfony/var-dumper/composer.json',
        'vendor/symfony/var-dumper/phpunit.xml.dist',
        'vendor/symfony/var-dumper/Test',
        'vendor/symfony/var-dumper/Tests',
        'vendor/symfony/var-dumper/CHANGELOG.md',
        'vendor/symfony/yaml/composer.json',
        'vendor/symfony/yaml/phpunit.xml.dist',
        'vendor/symfony/yaml/.gitignore',
        'vendor/symfony/yaml/.git',
        'vendor/symfony/yaml/Tests',
        'vendor/symfony/yaml/CHANGELOG.md',
        'vendor/twig/twig/.editorconfig',
        'vendor/twig/twig/.php_cs.dist',
        'vendor/twig/twig/.travis.yml',
        'vendor/twig/twig/.gitignore',
        'vendor/twig/twig/.git',
        'vendor/twig/twig/.github',
        'vendor/twig/twig/composer.json',
        'vendor/twig/twig/phpunit.xml.dist',
        'vendor/twig/twig/doc',
        'vendor/twig/twig/ext',
        'vendor/twig/twig/test',
        'vendor/twig/twig/.gitattributes',
        'vendor/twig/twig/CHANGELOG',
        'vendor/twig/twig/drupal_test.sh',
        'vendor/willdurand/negotiation/.gitignore',
        'vendor/willdurand/negotiation/.travis.yml',
        'vendor/willdurand/negotiation/appveyor.yml',
        'vendor/willdurand/negotiation/composer.json',
        'vendor/willdurand/negotiation/phpunit.xml.dist',
        'vendor/willdurand/negotiation/tests',
        'vendor/willdurand/negotiation/CONTRIBUTING.md',
        'user/config/security.yaml',
        'cache/compiled/',
    ];

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('clean')
            ->setDescription('Handles cleaning chores for Grav distribution')
            ->setHelp('The <info>clean</info> clean extraneous folders and data');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setupConsole($input, $output);

        return $this->cleanPaths() ? 0 : 1;
    }

    /**
     * @return bool
     */
    private function cleanPaths(): bool
    {
        $success = true;

        $this->io->writeln('');
        $this->io->writeln('<red>DELETING</red>');
        $anything = false;
        foreach ($this->paths_to_remove as $path) {
            $path = GRAV_ROOT . DS . $path;
            try {
                if (is_dir($path) && Folder::delete($path)) {
                    $anything = true;
                    $this->io->writeln('<red>dir:  </red>' . $path);
                } elseif (is_file($path) && @unlink($path)) {
                    $anything = true;
                    $this->io->writeln('<red>file: </red>' . $path);
                }
            } catch (\Exception $e) {
                $success = false;
                $this->io->error(sprintf('Failed to delete %s: %s', $path, $e->getMessage()));
            }
        }
        if (!$anything) {
            $this->io->writeln('');
            $this->io->writeln('<green>Nothing to clean...</green>');
        }

        return $success;
    }

    /**
     * Set colors style definition for the formatter.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return void
     */
    public function setupConsole(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->io = new SymfonyStyle($input, $output);

        $this->io->getFormatter()->setStyle('normal', new OutputFormatterStyle('white'));
        $this->io->getFormatter()->setStyle('yellow', new OutputFormatterStyle('yellow', null, ['bold']));
        $this->io->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, ['bold']));
        $this->io->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan', null, ['bold']));
        $this->io->getFormatter()->setStyle('green', new OutputFormatterStyle('green', null, ['bold']));
        $this->io->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, ['bold']));
        $this->io->getFormatter()->setStyle('white', new OutputFormatterStyle('white', null, ['bold']));
    }
}
