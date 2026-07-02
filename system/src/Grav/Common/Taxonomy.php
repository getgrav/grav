<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Language\Language;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageInterface;
use function is_string;

/**
 * The Taxonomy object is a singleton that holds a reference to a 'taxonomy map'. This map is
 * constructed as a multidimensional array.
 *
 * uses the taxonomy defined in the site.yaml file and is built when the page objects are recursed.
 * Basically every time a page is found that has taxonomy references, an entry to the page is stored in
 * the taxonomy map.  The map has the following format:
 *
 * [taxonomy_type][taxonomy_value][page_path]
 *
 * For example:
 *
 * [category][blog][path/to/item1]
 * [tag][grav][path/to/item1]
 * [tag][grav][path/to/item2]
 * [tag][dog][path/to/item3]
 */
class Taxonomy
{
    /** @var array */
    protected $taxonomy_map;
    /** @var callable|null Lazy provider for a language's full taxonomy map. */
    protected $loader;
    /** @var callable|null Targeted provider for a single type/value pair. */
    protected $loader_query;
    /** @var string|false|null Language the pending loader's data belongs to, exactly as Language::getLanguage() returns it (false when languages are not enabled); null means no loader. */
    protected $loader_language;
    /** @var Grav */
    protected $grav;
    /** @var Language */
    protected $language;

    /**
     * Constructor that resets the map
     *
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->language = $grav['language'];
        $this->taxonomy_map[$this->language->getLanguage()] = [];
    }

    /**
     * Set (or clear) a lazy provider for a language's taxonomy map.
     *
     * When set, the map is fetched on first use instead of being carried in
     * the pages cache; requests that never touch taxonomy skip it entirely.
     * The optional query provider serves findTaxonomy() lookups with targeted
     * per-type/value reads so common taxonomy collections never need the full
     * map at all.
     *
     * @param callable|null $loader Full-map provider: fn(): array
     * @param callable|null $query Targeted provider: fn(string $type, string $value): array
     * @param string|false|null $language Language the provided data belongs to, as returned by Language::getLanguage() - must not be coerced, since false (languages disabled) and '' are different array keys (defaults to the active language)
     * @return void
     */
    public function setLoader(?callable $loader, ?callable $query = null, $language = null): void
    {
        $this->loader = $loader;
        $this->loader_query = $loader ? $query : null;
        $this->loader_language = $loader ? ($language ?? $this->language->getLanguage()) : null;
    }

    /**
     * Run the pending loader before the map is read or modified.
     *
     * The data is assigned to the language the loader was created for, so a
     * mid-request language switch reads an empty slice (as it always has)
     * instead of another language's data.
     *
     * @return void
     */
    protected function ensureLoaded(): void
    {
        if ($this->loader) {
            $loader = $this->loader;
            $language = $this->loader_language ?? $this->language->getLanguage();
            $this->loader = null;
            $this->loader_query = null;
            $this->loader_language = null;
            $this->taxonomy_map[$language] = (array)$loader();
        }
    }

    /**
     * Takes an individual page and processes the taxonomies configured in its header. It
     * then adds those taxonomies to the map
     *
     * @param PageInterface  $page the page to process
     * @param array|null $page_taxonomy
     */
    public function addTaxonomy(PageInterface $page, $page_taxonomy = null)
    {
        if (!$page->published()) {
            return;
        }

        if (!$page_taxonomy) {
            $page_taxonomy = $page->taxonomy();
        }

        if (empty($page_taxonomy)) {
            return;
        }

        /** @var Config $config */
        $config = $this->grav['config'];
        $taxonomies = (array)$config->get('site.taxonomies');
        foreach ($taxonomies as $taxonomy) {
            // Skip invalid taxonomies.
            if (!\is_string($taxonomy)) {
                continue;
            }
            $current = $page_taxonomy[$taxonomy] ?? null;
            foreach ((array)$current as $item) {
                $this->iterateTaxonomy($page, $taxonomy, '', $item);
            }
        }
    }

    /**
     * Iterate through taxonomy fields
     *
     * Reduces [taxonomy_type] to dot-notation where necessary
     *
     * @param PageInterface   $page     The Page to process
     * @param string          $taxonomy Taxonomy type to add
     * @param string          $key      Taxonomy type to concatenate
     * @param iterable|string $value    Taxonomy value to add or iterate
     * @return void
     */
    public function iterateTaxonomy(PageInterface $page, string $taxonomy, string $key, $value)
    {
        if (is_iterable($value)) {
            foreach ($value as $identifier => $item) {
                $identifier = "{$key}.{$identifier}";
                $this->iterateTaxonomy($page, $taxonomy, $identifier, $item);
            }
        } elseif (is_string($value)) {
            if (!empty($key)) {
                $taxonomy .= $key;
            }
            // Load any stored map first so this addition extends it instead of
            // being overwritten by a later lazy load.
            $this->ensureLoaded();
            $active = $this->language->getLanguage();
            $this->taxonomy_map[$active][$taxonomy][(string) $value][$page->path()] = ['slug' => $page->slug()];
        }
    }

    /**
     * Returns a new Page object with the sub-pages containing all the values set for a
     * particular taxonomy.
     *
     * @param  array  $taxonomies taxonomies to search, eg ['tag'=>['animal','cat']]
     * @param  string $operator   can be 'or' or 'and' (defaults to 'and')
     * @return Collection       Collection object set to contain matches found in the taxonomy map
     */
    public function findTaxonomy($taxonomies, $operator = 'and')
    {
        $matches = [];
        $results = [];
        $active = $this->language->getLanguage();

        if ($this->loader && $this->loader_query && $active === $this->loader_language) {
            // The stored map is untouched (no runtime additions have forced a full
            // load), so fetch only the requested type/value pairs instead of
            // materializing the whole map.
            foreach ((array)$taxonomies as $taxonomy => $items) {
                foreach ((array)$items as $item) {
                    $matches[] = (array)($this->loader_query)((string)$taxonomy, (string)$item);
                }
            }
        } else {
            $this->ensureLoaded();

            foreach ((array)$taxonomies as $taxonomy => $items) {
                foreach ((array)$items as $item) {
                    $matches[] = $this->taxonomy_map[$active][$taxonomy][$item] ?? [];
                }
            }
        }

        if (strtolower($operator) === 'or') {
            foreach ($matches as $match) {
                $results = array_merge($results, $match);
            }
        } else {
            $results = $matches ? array_pop($matches) : [];
            foreach ($matches as $match) {
                $results = array_intersect_key($results, $match);
            }
        }

        return new Collection($results, ['taxonomies' => $taxonomies]);
    }

    /**
     * Gets and Sets the taxonomy map
     *
     * @param  array|null $var the taxonomy map
     * @return array      the taxonomy map
     */
    public function taxonomy($var = null)
    {
        $active = $this->language->getLanguage();

        if ($var) {
            // An explicit set replaces whatever a pending loader would provide
            // for the same language; a loader for another language stays pending.
            if ($this->loader_language === null || $this->loader_language === $active) {
                $this->loader = null;
                $this->loader_query = null;
                $this->loader_language = null;
            }
            $this->taxonomy_map[$active] = $var;
        } else {
            $this->ensureLoaded();
        }

        return $this->taxonomy_map[$active] ?? [];
    }

    /**
     * Gets item keys per taxonomy
     *
     * @param  string $taxonomy       taxonomy name
     * @return array                  keys of this taxonomy
     */
    public function getTaxonomyItemKeys($taxonomy)
    {
        $this->ensureLoaded();

        $active = $this->language->getLanguage();
        return isset($this->taxonomy_map[$active][$taxonomy]) ? array_keys($this->taxonomy_map[$active][$taxonomy]) : [];
    }
}
