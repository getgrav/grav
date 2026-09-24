<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Utils;
use RocketTheme\Toolbox\File\YamlFile;
use Throwable;
use function array_key_exists;
use function array_reverse;
use function is_array;
use function is_file;
use function json_encode;
use function md5;
use function preg_match;
use function strtolower;
use function substr;

/**
 * Class CompiledLanguages
 *
 * Languages are compiled one at a time, when a request first needs them. The compiled file
 * named after the environment (master-{env}.php) only lists the language codes the files
 * contain; each language has its own compiled file (master-{env}-lang-{code}.php) with its
 * own checksum, so a request that only translates English never reads the other languages,
 * and editing one language file only rebuilds that language.
 *
 * @package Grav\Common\Config
 */
class CompiledLanguages extends CompiledBase
{
    /** @var string[] Language codes found in the files, in the order a full merge adds them. */
    protected $codes = [];

    /** @var array<string,array> Parsed multi-language files (languages.yaml), kept for this request. */
    protected $multiLanguageFiles = [];

    /**
     * CompiledLanguages constructor.
     * @param string $cacheFolder
     * @param array $files
     * @param string $path
     */
    public function __construct($cacheFolder, array $files, $path)
    {
        parent::__construct($cacheFolder, $files, $path);

        // Version 2: the compiled file lists the languages, each language is compiled on its own.
        $this->version = 2;
    }

    /**
     * Create configuration object.
     *
     * @param  array  $data
     * @return void
     */
    protected function createObject(array $data = [])
    {
        if (isset($data['codes']) && is_array($data['codes'])) {
            $this->codes = $data['codes'];
        }

        $this->object = new Languages();
        $this->object->setLoader(fn(string $code): array => $this->loadLanguage($code), $this->codes);
    }

    /**
     * Finalize configuration object.
     *
     * @return void
     */
    protected function finalizeObject()
    {
        $this->object->checksum($this->checksum());
        $this->object->timestamp($this->timestamp());
    }

    /**
     * Function gets called when cached configuration is saved.
     *
     * @return void
     */
    public function modified()
    {
        $this->object->modified(true);
    }

    /**
     * Find the language codes in the files.
     *
     * Multi-language files (languages.yaml) have to be read whole to know which languages
     * they hold; the other files are named after their language.
     *
     * @return bool
     * @internal
     */
    protected function loadFiles()
    {
        $codes = [];
        foreach (array_reverse($this->files) as $files) {
            foreach ($files as $name => $item) {
                $filename = $this->path . $item['file'];
                if ($this->isMultiLanguageFile($filename)) {
                    foreach ($this->multiLanguageFile($filename) as $code => $value) {
                        $codes[(string)$code] = true;
                    }
                } else {
                    $codes[(string)$name] = true;
                }
            }
        }
        $this->codes = array_map('strval', array_keys($codes));

        $this->createObject();
        $this->finalizeObject();

        return true;
    }

    /**
     * Languages are merged one at a time by loadLanguage(), never file by file.
     *
     * @param  string  $name  Name of the position.
     * @param  string  $filename  File to be loaded.
     * @return void
     */
    protected function loadFile($name, $filename)
    {
    }

    /**
     * @return array
     */
    protected function getState()
    {
        return ['codes' => $this->codes];
    }

    /**
     * Load one language from its compiled file, compiling it first if needed.
     *
     * @param string $code
     * @return array [code => translations], or [] when no file has the language.
     * @internal
     */
    public function loadLanguage(string $code): array
    {
        $filename = $this->languageFilename($code);
        $checksum = $this->languageChecksum($code);

        if (is_file($filename)) {
            try {
                $cache = include $filename;
            } catch (Throwable) {
                // A broken compiled file is rebuilt below.
                $cache = null;
            }

            if (is_array($cache)
                && ($cache['@class'] ?? null) === static::class
                && ($cache['code'] ?? null) === $code
                && ($cache['checksum'] ?? null) === $checksum
                && isset($cache['data'])
                && is_array($cache['data'])
            ) {
                return $cache['data'];
            }
        }

        // Merge the files in the same order as a full merge, keeping only this language.
        $data = [];
        foreach (array_reverse($this->files) as $files) {
            foreach ($files as $name => $item) {
                $filename2 = $this->path . $item['file'];
                if ($this->isMultiLanguageFile($filename2)) {
                    $content = $this->multiLanguageFile($filename2);
                    if (array_key_exists($code, $content)) {
                        $data = Utils::arrayMergeRecursiveUnique($data, [$code => $content[$code]]);
                    }
                } elseif ((string)$name === $code) {
                    // A single-language file is only read when its language is compiled, so it
                    // skips the per-file compiled cache: writing one per file cost more on a cold
                    // request than it saved on the rare rebuild after that language changes.
                    $file = YamlFile::instance($filename2);
                    $data = Utils::arrayMergeRecursiveUnique($data, [$code => $file->content()]);
                    $file->free();
                }
            }
        }

        $this->writeCompiledFile($filename, static fn() => [
            '@class' => static::class,
            'code' => $code,
            'timestamp' => time(),
            'checksum' => $checksum,
            'data' => $data,
        ]);

        return $data;
    }

    /**
     * @param string $code
     * @return string
     */
    protected function languageFilename(string $code): string
    {
        // Codes differ only by case on disk too (zh-cn, zh-CN), and filesystems may ignore case.
        $suffix = strtolower($code);
        if ($suffix !== $code || !preg_match('/^[a-z0-9_-]{1,32}$/', $suffix)) {
            $suffix = (preg_match('/^[a-z0-9_-]{1,32}$/', $suffix) ? $suffix . '-' : '') . substr(md5($code), 0, 12);
        }

        return "{$this->cacheFolder}/{$this->name()->name}-lang-{$suffix}.php";
    }

    /**
     * Checksum of the files that can hold a language.
     *
     * @param string $code
     * @return string
     */
    protected function languageChecksum(string $code): string
    {
        $files = [];
        foreach ($this->files as $group => $list) {
            foreach ($list as $name => $item) {
                if ((string)$name === $code || $this->isMultiLanguageFile($item['file'])) {
                    $files[$group][$name] = $item;
                }
            }
        }

        return md5(json_encode($files) . $code . $this->version);
    }

    /**
     * @param string $filename
     * @return bool
     */
    protected function isMultiLanguageFile(string $filename): bool
    {
        return (bool)preg_match('|languages\.yaml$|', $filename);
    }

    /**
     * @param string $filename
     * @return array
     */
    protected function multiLanguageFile(string $filename): array
    {
        // Every language compile reads the multi-language files, so they keep the per-file
        // compiled cache and are held in memory for the rest of the request.
        if (!isset($this->multiLanguageFiles[$filename])) {
            $file = CompiledYamlFile::instance($filename);
            $this->multiLanguageFiles[$filename] = (array)$file->content();
            $file->free();
        }

        return $this->multiLanguageFiles[$filename];
    }
}
