<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\Data\Data;
use Grav\Common\Utils;
use function array_key_exists;
use function strpos;
use function substr;

/**
 * Class Languages
 *
 * Holds the translations of every language, keyed by language code. When it is created by
 * CompiledLanguages, each language is loaded the first time something reads it, so a request
 * that only translates English never loads the other languages. Reading a whole language
 * (`get('fr')`, `flattenByLang('fr')`) loads that language; reading everything (`toArray()`,
 * `get('')`, `count()`) loads them all. Merges into a language that has not been loaded yet
 * are kept and applied on top of it when it loads, in the order they were made.
 *
 * @package Grav\Common\Config
 */
class Languages extends Data
{
    /** @var string|null */
    protected $checksum;

    /** @var bool */
    protected $modified = false;

    /** @var int */
    protected $timestamp = 0;

    /** @var callable(string): array|null Loads one language, returns [code => data] or [] if no file has it. */
    protected $loader;

    /** @var array<string,int> Language codes the loader knows, in the order a full merge adds them. */
    protected $codes = [];

    /** @var array<string,true> Languages that have been loaded (or found to be missing). */
    protected $loaded = [];

    /** @var array<string,array> Merges waiting for their language to be loaded. */
    protected $pending = [];

    /**
     * Load languages on demand.
     *
     * @param callable(string): array $loader
     * @param string[] $codes Language codes the loader knows.
     * @return void
     * @internal Used by CompiledLanguages.
     */
    public function setLoader(callable $loader, array $codes): void
    {
        $this->loader = $loader;
        $this->codes = [];
        foreach ($codes as $code) {
            $this->codes[(string)$code] = count($this->codes);
        }
        $this->loaded = [];
    }

    /**
     * Make sure a language is loaded.
     *
     * @param string $code
     * @return void
     */
    public function loadLanguage(string $code): void
    {
        if ($this->loader === null || isset($this->loaded[$code])) {
            return;
        }

        $this->loaded[$code] = true;

        if (isset($this->codes[$code])) {
            $data = ($this->loader)($code);
            if (array_key_exists($code, $data)) {
                $this->items[$code] = $data[$code];
            }
        }

        if (isset($this->pending[$code])) {
            foreach ($this->pending[$code] as $value) {
                $this->items = Utils::arrayMergeRecursiveUnique($this->items, [$code => $value]);
            }
            unset($this->pending[$code]);
        }
    }

    /**
     * Load every language.
     *
     * @return void
     */
    public function loadAll(): void
    {
        if ($this->loader === null) {
            return;
        }

        foreach ($this->codes as $code => $order) {
            $this->loadLanguage((string)$code);
        }
        foreach ($this->pending as $code => $values) {
            $this->loadLanguage((string)$code);
        }

        // Put the languages in the order a full merge would have: languages from the files
        // first, then languages that only exist in later merges, in the order they came.
        $items = [];
        foreach ($this->codes as $code => $order) {
            if (array_key_exists($code, $this->items)) {
                $items[$code] = $this->items[$code];
            }
        }
        $this->items = $items + $this->items;

        $this->loader = null;
        $this->pending = [];
    }

    /**
     * @param string $name
     * @param string|null $separator
     * @return void
     */
    protected function loadFor($name, $separator = null): void
    {
        if ($this->loader === null) {
            return;
        }

        $name = (string)$name;
        if ($name === '') {
            $this->loadAll();

            return;
        }

        $pos = strpos($name, $separator ?: $this->nestedSeparator);
        $this->loadLanguage($pos === false ? $name : substr($name, 0, $pos));
    }

    /**
     * @param string $name
     * @param mixed $default
     * @param string|null $separator
     * @return mixed
     */
    public function get($name, $default = null, $separator = null)
    {
        $this->loadFor($name, $separator);

        return parent::get($name, $default, $separator);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param string|null $separator
     * @return $this
     */
    public function set($name, $value, $separator = null)
    {
        $this->loadFor($name, $separator);

        return parent::set($name, $value, $separator);
    }

    /**
     * @param string $name
     * @param string|null $separator
     * @return $this
     */
    public function undef($name, $separator = null)
    {
        $this->loadFor($name, $separator);

        return parent::undef($name, $separator);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if (null === $offset) {
            $this->loadAll();
        }

        parent::offsetSet($offset, $value);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $this->loadAll();

        return parent::toArray();
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $this->loadAll();

        return parent::jsonSerialize();
    }

    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        $this->loadAll();

        return parent::count();
    }

    /**
     * @param array $data
     * @return $this
     */
    public function merge(array $data)
    {
        $this->loadAll();

        return parent::merge($data);
    }

    /**
     * @param array $data
     * @return $this
     */
    public function setDefaults(array $data)
    {
        $this->loadAll();

        return parent::setDefaults($data);
    }

    /**
     * @return $this
     */
    public function validate()
    {
        $this->loadAll();

        return parent::validate();
    }

    /**
     * @return $this
     */
    public function filter()
    {
        $this->loadAll();

        return parent::filter(...func_get_args());
    }

    /**
     * @return array
     */
    public function extra()
    {
        $this->loadAll();

        return parent::extra();
    }

    /**
     * @return void
     */
    public function save()
    {
        $this->loadAll();

        parent::save();
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        $this->loadAll();

        return array_keys((array)$this);
    }

    /**
     * @param string|null $checksum
     * @return string|null
     */
    public function checksum($checksum = null)
    {
        if ($checksum !== null) {
            $this->checksum = $checksum;
        }

        return $this->checksum;
    }

    /**
     * @param bool|null $modified
     * @return bool
     */
    public function modified($modified = null)
    {
        if ($modified !== null) {
            $this->modified = $modified;
        }

        return $this->modified;
    }

    /**
     * @param int|null $timestamp
     * @return int
     */
    public function timestamp($timestamp = null)
    {
        if ($timestamp !== null) {
            $this->timestamp = $timestamp;
        }

        return $this->timestamp;
    }

    /**
     * @return void
     */
    public function reformat()
    {
        $this->loadAll();

        if (isset($this->items['plugins'])) {
            $this->items = array_merge_recursive($this->items, $this->items['plugins']);
            unset($this->items['plugins']);
        }
    }

    /**
     * Merge translations in. A language that has not been loaded yet gets the merge when it loads.
     *
     * @param array $data
     * @return void
     */
    public function mergeRecursive(array $data)
    {
        if ($this->loader !== null) {
            foreach ($data as $code => $value) {
                $code = (string)$code;
                if (!isset($this->loaded[$code])) {
                    $this->pending[$code][] = $value;
                    unset($data[$code]);
                }
            }
            if (!$data) {
                return;
            }
        }

        $this->items = Utils::arrayMergeRecursiveUnique($this->items, $data);
    }

    /**
     * @param string $lang
     * @return array
     */
    public function flattenByLang($lang)
    {
        $this->loadLanguage((string)$lang);

        $language = $this->items[$lang];
        return Utils::arrayFlattenDotNotation($language);
    }

    /**
     * @param array $array
     * @return array
     */
    public function unflatten($array)
    {
        return Utils::arrayUnflattenDotNotation($array);
    }
}
