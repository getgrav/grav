<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Config\Config;
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
    /** @var Grav */
    protected $grav;

    /**
     * Constructor that resets the map
     *
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->taxonomy_map = [];
        $this->grav = $grav;
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
            $this->taxonomy_map[$taxonomy][(string) $value][$page->path()] = ['slug' => $page->slug()];
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

        foreach ((array)$taxonomies as $taxonomy => $items) {
            foreach ((array)$items as $item) {
                if (isset($this->taxonomy_map[$taxonomy][$item])) {
                    $matches[] = $this->taxonomy_map[$taxonomy][$item];
                } else {
                    $matches[] = [];
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
        if ($var) {
            $this->taxonomy_map = $var;
        }

        return $this->taxonomy_map;
    }

    /**
     * Gets item keys per taxonomy
     *
     * @param  string $taxonomy       taxonomy name
     * @return array                  keys of this taxonomy
     */
    public function getTaxonomyItemKeys($taxonomy)
    {
        return isset($this->taxonomy_map[$taxonomy]) ? array_keys($this->taxonomy_map[$taxonomy]) : [];
    }
}
