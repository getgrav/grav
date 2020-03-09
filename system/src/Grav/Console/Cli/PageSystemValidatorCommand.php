<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Console\ConsoleCommand;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\Console\Input\InputOption;

class PageSystemValidatorCommand extends ConsoleCommand
{
    /** @var array */
    protected $tests = [
        // Content
        'header' => [[]],
        'summary' => [[], [200], [200, true]],
        'content' => [[]],
        'getRawContent' => [[]],
        'rawMarkdown' => [[]],
        'value' => [['content'], ['route'], ['order'], ['ordering'], ['folder'], ['slug'], ['name'], /*['frontmatter'],*/ ['header.menu'], ['header.slug']],
        'title' => [[]],
        'menu' => [[]],
        'visible' => [[]],
        'published' => [[]],
        'publishDate' => [[]],
        'unpublishDate' => [[]],
        'process' => [[]],
        'slug' => [[]],
        'order' => [[]],
        //'id' => [[]],
        'modified' => [[]],
        'lastModified' => [[]],
        'folder' => [[]],
        'date' => [[]],
        'dateformat' => [[]],
        'taxonomy' => [[]],
        'shouldProcess' => [['twig'], ['markdown']],
        'isPage' => [[]],
        'isDir' => [[]],
        'exists' => [[]],

        // Forms
        'forms' => [[]],

        // Routing
        'urlExtension' => [[]],
        'routable' => [[]],
        'link' => [[], [false], [true]],
        'permalink' => [[]],
        'canonical' => [[], [false], [true]],
        'url' => [[], [true], [true, true], [true, true, false], [false, false, true, false]],
        'route' => [[]],
        'rawRoute' => [[]],
        'routeAliases' => [[]],
        'routeCanonical' => [[]],
        'redirect' => [[]],
        'relativePagePath' => [[]],
        'path' => [[]],
        //'folder' => [[]],
        'parent' => [[]],
        'topParent' => [[]],
        'currentPosition' => [[]],
        'active' => [[]],
        'activeChild' => [[]],
        'home' => [[]],
        'root' => [[]],

        // Translations
        'translatedLanguages' => [[], [false], [true]],
        'untranslatedLanguages' => [[], [false], [true]],
        'language' => [[]],

        // Legacy
        'raw' => [[]],
        'frontmatter' => [[]],
        'httpResponseCode' => [[]],
        'httpHeaders' => [[]],
        'blueprintName' => [[]],
        'name' => [[]],
        'childType' => [[]],
        'template' => [[]],
        'templateFormat' => [[]],
        'extension' => [[]],
        'expires' => [[]],
        'cacheControl' => [[]],
        'ssl' => [[]],
        'metadata' => [[]],
        'eTag' => [[]],
        'filePath' => [[]],
        'filePathClean' => [[]],
        'orderDir' => [[]],
        'orderBy' => [[]],
        'orderManual' => [[]],
        'maxCount' => [[]],
        'modular' => [[]],
        'modularTwig' => [[]],
        //'children' => [[]],
        'isFirst' => [[]],
        'isLast' => [[]],
        'prevSibling' => [[]],
        'nextSibling' => [[]],
        'adjacentSibling' => [[]],
        'ancestor' => [[]],
        //'inherited' => [[]],
        //'inheritedField' => [[]],
        'find' => [['/']],
        //'collection' => [[]],
        //'evaluate' => [[]],
        'folderExists' => [[]],
        //'getOriginal' => [[]],
        //'getAction' => [[]],
    ];

    /** @var Grav */
    protected $grav;

    protected function configure()
    {
        $this
            ->setName('page-system-validator')
            ->setDescription('Page validator can be used to compare site before/after update and when migrating to Flex Pages.')
            ->addOption('record', 'r', InputOption::VALUE_NONE, 'Record results')
            ->addOption('check', 'c', InputOption::VALUE_NONE, 'Compare site against previously recorded results')
            ->setHelp('The <info>page-system-validator</info> command can be used to test the pages before and after upgrade');
    }

    protected function serve()
    {
        $this->setLanguage('en');
        $this->initializePages();

        $this->output->writeln('');

        $this->grav = $grav = Grav::instance();

        $grav->fireEvent('onPageInitialized', new Event(['page' => $grav['page']]));

        /** @var Config $config */
        $config = $grav['config'];

        if ($this->input->getOption('record')) {
            $this->output->writeln('Pages: ' . $config->get('system.pages.type', 'page'));

            $this->output->writeln('<magenta>Record tests</magenta>');
            $this->output->writeln('');

            $results = $this->record();
            $file = $this->getFile('pages-old');
            $file->save($results);

            $this->output->writeln('Recorded tests to ' . $file->filename());
        } elseif ($this->input->getOption('check')) {
            $this->output->writeln('Pages: ' . $config->get('system.pages.type', 'page'));

            $this->output->writeln('<magenta>Run tests</magenta>');
            $this->output->writeln('');

            $new = $this->record();
            $file = $this->getFile('pages-new');
            $file->save($new);
            $this->output->writeln('Recorded tests to ' . $file->filename());

            $file = $this->getFile('pages-old');
            $old = $file->content();

            $results = $this->check($old, $new);
            $file = $this->getFile('diff');
            $file->save($results);
            $this->output->writeln('Recorded results to ' . $file->filename());
        } else {
            $this->output->writeln('<green>page-system-validator [-r|--record] [-c|--check]</green>');
        }
        $this->output->writeln('');
    }

    private function record()
    {
        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $all = $pages->all();

        $results = [];
        $results[''] = $this->recordRow($pages->root());
        foreach ($all as $path => $page) {
            if (null === $page) {
                $this->output->writeln('<red>Error on page ' . $path . '</red>');
                continue;
            }

            $results[$page->rawRoute()] = $this->recordRow($page);
        }

        return json_decode(json_encode($results), true);
    }

    /**
     * @param PageInterface $page
     * @return array
     */
    private function recordRow(PageInterface $page)
    {
        $results = [];

        foreach ($this->tests as $method => $params) {
            $params = $params ?: [[]];
            foreach ($params as $p) {
                $result = $page->$method(...$p);
                if (in_array($method, ['summary', 'content', 'getRawContent'], true)) {
                    $result = preg_replace('/name="(form-nonce|__unique_form_id__)" value="[^"]+"/',
                        'name="\\1" value="DYNAMIC"', $result);
                    $result = preg_replace('`src=("|\'|&quot;)/images/./././././[^"]+\\1`',
                        'src="\\1images/GENERATED\\1', $result);
                    $result = preg_replace('/\?\d{10}/', '?1234567890', $result);
                } elseif ($method === 'httpHeaders' && isset($result['Expires'])) {
                    $result['Expires'] = 'Thu, 19 Sep 2019 13:10:24 GMT (REPLACED AS DYNAMIC)';
                } elseif ($result instanceof PageInterface) {
                    $result = $result->rawRoute();
                } elseif (is_object($result)) {
                    $result = json_decode(json_encode($result), true);
                }

                $ps = [];
                foreach ($p as $val) {
                    $ps[] = (string)var_export($val, true);
                }
                $pstr = implode(', ', $ps);
                $call = "->{$method}({$pstr})";
                $results[$call] = $result;
            }
        }

        return $results;
    }

    private function check(array $old, array $new)
    {
        $errors = [];
        foreach ($old as $path => $page) {
            if (!isset($new[$path])) {
                $errors[$path] = 'PAGE REMOVED';
                continue;
            }
            foreach ($page as $method => $test) {
                if (($new[$path][$method] ?? null) !== $test) {
                    $errors[$path][$method] = ['old' => $test, 'new' => $new[$path][$method]];
                }
            }
        }

        return $errors;
    }

    /**
     * @param string $name
     * @return CompiledYamlFile
     */
    private function getFile(string $name)
    {
        return CompiledYamlFile::instance('cache://tests/' . $name . '.yaml');
    }
}
