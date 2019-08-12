<?php
namespace Grav\Common\Page\Interfaces;

interface PageRoutableInterface
{
    /**
     * Returns the page extension, got from the page `url_extension` config and falls back to the
     * system config `system.pages.append_url_extension`.
     *
     * @return string      The extension of this page. For example `.html`
     */
    public function urlExtension();

    /**
     * Gets and Sets whether or not this Page is routable, ie you can reach it
     * via a URL.
     * The page must be *routable* and *published*
     *
     * @param  bool $var true if the page is routable
     *
     * @return bool      true if the page is routable
     */
    public function routable($var = null);

    /**
     * Gets the URL for a page - alias of url().
     *
     * @param bool $include_host
     *
     * @return string the permalink
     */
    public function link($include_host = false);

    /**
     * Gets the URL with host information, aka Permalink.
     * @return string The permalink.
     */
    public function permalink();

    /**
     * Returns the canonical URL for a page
     *
     * @param bool $include_lang
     *
     * @return string
     */
    public function canonical($include_lang = true);

    /**
     * Gets the url for the Page.
     *
     * @param bool $include_host Defaults false, but true would include http://yourhost.com
     * @param bool $canonical true to return the canonical URL
     * @param bool $include_lang
     * @param bool $raw_route
     *
     * @return string The url.
     */
    public function url($include_host = false, $canonical = false, $include_lang = true, $raw_route = false);

    /**
     * Gets the route for the page based on the route headers if available, else from
     * the parents route and the current Page's slug.
     *
     * @param  string $var Set new default route.
     *
     * @return string  The route for the Page.
     */
    public function route($var = null);

    /**
     * Helper method to clear the route out so it regenerates next time you use it
     */
    public function unsetRouteSlug();

    /**
     * Gets and Sets the page raw route
     *
     * @param string|null $var
     *
     * @return string
     */
    public function rawRoute($var = null);

    /**
     * Gets the route aliases for the page based on page headers.
     *
     * @param  array $var list of route aliases
     *
     * @return array  The route aliases for the Page.
     */
    public function routeAliases($var = null);

    /**
     * Gets the canonical route for this page if its set. If provided it will use
     * that value, else if it's `true` it will use the default route.
     *
     * @param string|null $var
     *
     * @return bool|string
     */
    public function routeCanonical($var = null);

    /**
     * Gets the redirect set in the header.
     *
     * @param  string $var redirect url
     *
     * @return string
     */
    public function redirect($var = null);

    /**
     * Returns the clean path to the page file
     */
    public function relativePagePath();

    /**
     * Gets and sets the path to the folder where the .md for this Page object resides.
     * This is equivalent to the filePath but without the filename.
     *
     * @param  string $var the path
     *
     * @return string|null      the path
     */
    public function path($var = null);

    /**
     * Get/set the folder.
     *
     * @param string $var Optional path
     *
     * @return string|null
     */
    public function folder($var = null);

    /**
     * Gets and Sets the parent object for this page
     *
     * @param  PageInterface $var the parent page object
     *
     * @return PageInterface|null the parent page object if it exists.
     */
    public function parent(PageInterface $var = null);

    /**
     * Gets the top parent object for this page
     *
     * @return PageInterface|null the top parent page object if it exists.
     */
    public function topParent();

    /**
     * Returns the item in the current position.
     *
     * @return int   the index of the current page.
     */
    public function currentPosition();

    /**
     * Returns whether or not this page is the currently active page requested via the URL.
     *
     * @return bool True if it is active
     */
    public function active();

    /**
     * Returns whether or not this URI's URL contains the URL of the active page.
     * Or in other words, is this page's URL in the current URL
     *
     * @return bool True if active child exists
     */
    public function activeChild();

    /**
     * Returns whether or not this page is the currently configured home page.
     *
     * @return bool True if it is the homepage
     */
    public function home();

    /**
     * Returns whether or not this page is the root node of the pages tree.
     *
     * @return bool True if it is the root
     */
    public function root();
}
