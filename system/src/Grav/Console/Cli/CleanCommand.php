<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
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
        // Grav core scaffolding
        '.gitattributes',
        '.github/',
        '.phan/',
        'bin/build-test-update.php',
        'bin/test-selfupgrade.sh',
        'codeception.yml',
        'tests/',
        'user/config/security.yaml',
        'cache/compiled/',

        // vendor/* — packages shipped in Grav 2.0 core
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
        'vendor/doctrine/collections/UPGRADE.md',
        'vendor/doctrine/deprecations/composer.json',
        'vendor/donatj/phpuseragentparser/.git',
        'vendor/donatj/phpuseragentparser/.github',
        'vendor/donatj/phpuseragentparser/.gitignore',
        'vendor/donatj/phpuseragentparser/.editorconfig',
        'vendor/donatj/phpuseragentparser/.travis.yml',
        'vendor/donatj/phpuseragentparser/composer.json',
        'vendor/donatj/phpuseragentparser/phpunit.xml.dist',
        'vendor/donatj/phpuseragentparser/tests',
        'vendor/donatj/phpuseragentparser/Tools',
        'vendor/donatj/phpuseragentparser/examples',
        'vendor/donatj/phpuseragentparser/CONTRIBUTING.md',
        'vendor/donatj/phpuseragentparser/Makefile',
        'vendor/donatj/phpuseragentparser/.mddoc.xml',
        'vendor/dragonmantank/cron-expression/.editorconfig',
        'vendor/dragonmantank/cron-expression/composer.json',
        'vendor/dragonmantank/cron-expression/tests',
        'vendor/dragonmantank/cron-expression/CHANGELOG.md',
        'vendor/erusev/parsedown/composer.json',
        'vendor/erusev/parsedown/composer.lock',
        'vendor/erusev/parsedown/phpunit.xml.dist',
        'vendor/erusev/parsedown/.travis.yml',
        'vendor/erusev/parsedown/.git',
        'vendor/erusev/parsedown/.github',
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
        'vendor/getgrav/image/composer.json',
        'vendor/getgrav/image/phpunit.xml.dist',
        'vendor/getgrav/image/Makefile',
        'vendor/getgrav/image/.php_cs',
        'vendor/getgrav/image/.github',
        'vendor/getgrav/image/.git',
        'vendor/getgrav/image/doc',
        'vendor/getgrav/image/demo',
        'vendor/getgrav/image/tests',
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
        'vendor/multiavatar/multiavatar-php/composer.json',
        'vendor/multiavatar/multiavatar-php/tests',
        'vendor/nyholm/psr7/composer.json',
        'vendor/nyholm/psr7/phpstan.neon.dist',
        'vendor/nyholm/psr7/CHANGELOG.md',
        'vendor/nyholm/psr7/psalm.xml',
        'vendor/nyholm/psr7-server/.github',
        'vendor/nyholm/psr7-server/composer.json',
        'vendor/nyholm/psr7-server/CHANGELOG.md',
        'vendor/php-debugbar/php-debugbar/composer.json',
        'vendor/php-debugbar/php-debugbar/src/DebugBar/Resources/vendor',
        'vendor/pimple/pimple/.gitignore',
        'vendor/pimple/pimple/.github',
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
        'vendor/psr/event-dispatcher/composer.json',
        'vendor/psr/http-factory/.gitignore',
        'vendor/psr/http-factory/.pullapprove.yml',
        'vendor/psr/http-factory/composer.json',
        'vendor/psr/http-message/composer.json',
        'vendor/psr/http-message/CHANGELOG.md',
        'vendor/psr/http-message/docs',
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
        'vendor/rhukster/dom-sanitizer/tests',
        'vendor/rhukster/dom-sanitizer/.gitignore',
        'vendor/rhukster/dom-sanitizer/composer.json',
        'vendor/rhukster/dom-sanitizer/composer.lock',
        'vendor/rockettheme/toolbox/.git',
        'vendor/rockettheme/toolbox/.github',
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
        'vendor/symfony/cache/CHANGELOG.md',
        'vendor/symfony/cache/composer.json',
        'vendor/symfony/cache-contracts/CHANGELOG.md',
        'vendor/symfony/cache-contracts/composer.json',
        'vendor/symfony/console/composer.json',
        'vendor/symfony/console/phpunit.xml.dist',
        'vendor/symfony/console/.gitignore',
        'vendor/symfony/console/.git',
        'vendor/symfony/console/Tester',
        'vendor/symfony/console/Tests',
        'vendor/symfony/console/CHANGELOG.md',
        'vendor/symfony/deprecation-contracts/CHANGELOG.md',
        'vendor/symfony/deprecation-contracts/composer.json',
        'vendor/symfony/event-dispatcher/.git',
        'vendor/symfony/event-dispatcher/.gitignore',
        'vendor/symfony/event-dispatcher/composer.json',
        'vendor/symfony/event-dispatcher/phpunit.xml.dist',
        'vendor/symfony/event-dispatcher/Tests',
        'vendor/symfony/event-dispatcher/CHANGELOG.md',
        'vendor/symfony/event-dispatcher-contracts/CHANGELOG.md',
        'vendor/symfony/event-dispatcher-contracts/composer.json',
        'vendor/symfony/http-client/CHANGELOG.md',
        'vendor/symfony/http-client/composer.json',
        'vendor/symfony/http-client/Test',
        'vendor/symfony/http-client-contracts/CHANGELOG.md',
        'vendor/symfony/http-client-contracts/composer.json',
        'vendor/symfony/http-client-contracts/Test',
        'vendor/symfony/polyfill-ctype/composer.json',
        'vendor/symfony/polyfill-iconv/.git',
        'vendor/symfony/polyfill-iconv/.gitignore',
        'vendor/symfony/polyfill-iconv/composer.json',
        'vendor/symfony/polyfill-intl-grapheme/composer.json',
        'vendor/symfony/polyfill-intl-normalizer/composer.json',
        'vendor/symfony/polyfill-mbstring/.git',
        'vendor/symfony/polyfill-mbstring/.gitignore',
        'vendor/symfony/polyfill-mbstring/composer.json',
        'vendor/symfony/polyfill-php80/composer.json',
        'vendor/symfony/polyfill-php81/composer.json',
        'vendor/symfony/polyfill-php83/composer.json',
        'vendor/symfony/polyfill-php84/composer.json',
        'vendor/symfony/process/.gitignore',
        'vendor/symfony/process/composer.json',
        'vendor/symfony/process/phpunit.xml.dist',
        'vendor/symfony/process/Tests',
        'vendor/symfony/process/CHANGELOG.md',
        'vendor/symfony/service-contracts/CHANGELOG.md',
        'vendor/symfony/service-contracts/composer.json',
        'vendor/symfony/service-contracts/Test',
        'vendor/symfony/string/CHANGELOG.md',
        'vendor/symfony/string/composer.json',
        'vendor/symfony/var-dumper/.git',
        'vendor/symfony/var-dumper/.gitignore',
        'vendor/symfony/var-dumper/composer.json',
        'vendor/symfony/var-dumper/phpunit.xml.dist',
        'vendor/symfony/var-dumper/Test',
        'vendor/symfony/var-dumper/Tests',
        'vendor/symfony/var-dumper/CHANGELOG.md',
        'vendor/symfony/var-exporter/CHANGELOG.md',
        'vendor/symfony/var-exporter/composer.json',
        'vendor/symfony/yaml/composer.json',
        'vendor/symfony/yaml/phpunit.xml.dist',
        'vendor/symfony/yaml/.gitignore',
        'vendor/symfony/yaml/.git',
        'vendor/symfony/yaml/Tests',
        'vendor/symfony/yaml/CHANGELOG.md',
        'vendor/tedivm/jshrink/composer.json',
        'vendor/tedivm/jshrink/CONTRIBUTING.md',
        'vendor/tedivm/jshrink/.github',
        'vendor/tubalmartin/cssmin/composer.json',
        'vendor/tubalmartin/cssmin/phpunit.xml',
        'vendor/tubalmartin/cssmin/tests',
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
        'vendor/twig/twig/src/Test',
        'vendor/twig/twig/.gitattributes',
        'vendor/twig/twig/CHANGELOG',
        'vendor/twig/twig/drupal_test.sh',
        'vendor/willdurand/negotiation/.github',
        'vendor/willdurand/negotiation/.gitignore',
        'vendor/willdurand/negotiation/.travis.yml',
        'vendor/willdurand/negotiation/appveyor.yml',
        'vendor/willdurand/negotiation/composer.json',
        'vendor/willdurand/negotiation/phpunit.xml.dist',
        'vendor/willdurand/negotiation/tests',
        'vendor/willdurand/negotiation/CONTRIBUTING.md',
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
