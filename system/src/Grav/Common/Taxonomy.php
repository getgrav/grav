<?php
namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Page;

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
 *
 * @author RocketTheme
 * @license MIT
 */
class Taxonomy
{
    protected $taxonomy_map;
    protected $grav;

    /**
     * Constructor that resets the map
     */
    public function __construct(Grav $grav)
    {
        $this->taxonomy_map = array();
        $this->grav = $grav;
    }

    /**
     * Takes an individual page and processes the taxonomies configured in its header. It
     * then adds those taxonomies to the map
     *
     * @param Page $page the page to process
     * @param array $page_taxonomy
     */
    public function addTaxonomy(Page $page, $page_taxonomy = null)
    {
        if (!$page_taxonomy) {
            $page_taxonomy = $page->taxonomy();
        }

        if (!$page->published() || empty($page_taxonomy)) {
            return;
        }

        /** @var Config $config */
        $config = $this->grav['config'];
        if ($config->get('site.taxonomies')) {
            foreach ((array) $config->get('site.taxonomies') as $taxonomy) {
                if (isset($page_taxonomy[$taxonomy])) {
                    foreach ((array) $page_taxonomy[$taxonomy] as $item) {
                        // TODO: move to pages class?
                        $this->taxonomy_map[$taxonomy][(string) $item][$page->path()] = array('slug' => $page->slug());
                    }
                }
            }
        }
    }

    /**
     * Returns a new Page object with the sub-pages containing all the values set for a
     * particular taxonomy.
     *
     * @param  array $taxonomies taxonomies to search, eg ['tag'=>['animal','cat']]
     * @param  string $operator can be 'or' or 'and' (defaults to 'or')
     * @return Collection       Collection object set to contain matches found in the taxonomy map
     */
    public function findTaxonomy($taxonomies, $operator = 'and')
    {
        $matches = [];
        $results = [];

        foreach ((array)$taxonomies as $taxonomy => $items) {
            foreach ((array) $items as $item) {
                if (isset($this->taxonomy_map[$taxonomy][$item])) {
                    $matches[] = $this->taxonomy_map[$taxonomy][$item];
                }
            }
        }

        if (strtolower($operator) == 'or') {
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
     * @param  array $var the taxonomy map
     * @return array      the taxonomy map
     */
    public function taxonomy($var = null)
    {
        if ($var) {
            $this->taxonomy_map = $var;
        }
        return $this->taxonomy_map;
    }
}
