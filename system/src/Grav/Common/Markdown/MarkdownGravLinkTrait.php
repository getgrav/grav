<?php
namespace Grav\Common\Markdown;

use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Grav\Common\GravTrait;
use Grav\Common\Page\Medium;
use Grav\Common\Uri;

/**
 * A trait to add some custom processing to the identifyLink() method in Parsedown and ParsedownExtra
 */
trait MarkdownGravLinkTrait
{
    use GravTrait;

    /**
     * Ensure Twig tags are treated as block level items with no <p></p> tags
     */
    protected function blockTwigTag($Line)
    {
        if (preg_match('/[{%|{{|{#].*[#}|}}|%}]/', $Line['body'], $matches)) {
            $Block = array(
                'element' => array(
                    'text' => $Line['body']
                )
            );
            return $Block;
        }
    }

    protected function inlineLink($excerpt)
    {
        /** @var Config $config */
        $config = self::$grav['config'];

        // Run the parent method to get the actual results
        $excerpt = parent::inlineLink($excerpt);
        $actions = array();
        $this->base_url = self::$grav['base_url'];

        // if this is a link
        if (isset($excerpt['element']['attributes']['href'])) {

            $url = parse_url(htmlspecialchars_decode($excerpt['element']['attributes']['href']));

            // if there is no scheme, the file is local
            if (!isset($url['scheme'])) {

                // convert the URl is required
                $excerpt['element']['attributes']['href'] = $this->convertUrl(Uri::build_url($url));
            }
        }

        // if this is an image
        if (isset($excerpt['element']['attributes']['src'])) {

            $alt = isset($excerpt['element']['attributes']['alt']) ? $excerpt['element']['attributes']['alt'] : '';
            $title = isset($excerpt['element']['attributes']['title']) ? $excerpt['element']['attributes']['title'] : '';

            //get the url and parse it
            $url = parse_url(htmlspecialchars_decode($excerpt['element']['attributes']['src']));

            // if there is no host set but there is a path, the file is local
            if (!isset($url['host']) && isset($url['path'])) {
                // get the media objects for this page
                $media = $this->page->media();

                // if there is a media file that matches the path referenced..
                if (isset($media->images()[$url['path']])) {
                    // get the medium object
                    $medium = $media->images()[$url['path']];

                    // if there is a query, then parse it and build action calls
                    if (isset($url['query'])) {
                        parse_str($url['query'], $actions);
                    }

                    // loop through actions for the image and call them
                    foreach ($actions as $action => $params) {
                        // as long as it's a valid action
                        if (in_array($action, Medium::$valid_actions)) {
                            call_user_func_array(array(&$medium, $action), explode(',', $params));
                        }
                    }

                    // Get the URL for regular images, or an array of bits needed to put together
                    // the lightbox HTML
                    if (!isset($actions['lightbox'])) {
                        $src = $medium->url();
                    } else {
                        $src = $medium->lightboxRaw();
                    }

                    // set the src element with the new generated url
                    if (!isset($actions['lightbox']) && !is_array($src)) {
                        $excerpt['element']['attributes']['src'] = $src;
                    } else {
                        // Create the custom lightbox element
                        $element = array(
                            'name' => 'a',
                            'attributes' => array('rel' => $src['a_rel'], 'href' => $src['a_url']),
                            'handler' => 'element',
                            'text' => array(
                                'name' => 'img',
                                'attributes' => array('src' => $src['img_url'], 'alt' => $alt, 'title' => $title)
                            ),
                        );

                        // Set any custom classes on the lightbox element
                        if (isset($excerpt['element']['attributes']['class'])) {
                            $element['attributes']['class'] = $excerpt['element']['attributes']['class'];
                        }

                        // Set the lightbox element on the Excerpt
                        $excerpt['element'] = $element;
                    }
                } else {
                    // not a current page media file, see if it needs converting to relative
                    $excerpt['element']['attributes']['src'] = $this->convertUrl(Uri::build_url($url));
                }
            }
        }
        return $excerpt;
    }

    /**
     * Converts links from absolute '/' or relative (../..) to a grav friendly format
     * @param  string $markdown_url the URL as it was written in the markdown
     * @return string               the more friendly formatted url
     */
    protected function convertUrl($markdown_url)
    {
        // if absolute and starts with a base_url move on
        if ($this->base_url != '' && strpos($markdown_url, $this->base_url) === 0) {
            $new_url = $markdown_url;
        // if its absolute with /
        } elseif (strpos($markdown_url, '/') === 0) {
            $new_url = rtrim($this->base_url, '/') . $markdown_url;
        } else {
            $relative_path = rtrim($this->base_url, '/') . $this->page->route();

            // If this is a 'real' filepath clean it up
            if (file_exists($this->page->path() . '/' . parse_url($markdown_url, PHP_URL_PATH))) {
                $relative_path = rtrim($this->base_url, '/') . preg_replace('/\/([\d]+.)/', '/', str_replace(PAGES_DIR, '/', $this->page->path()));
                $markdown_url = preg_replace('/^([\d]+.)/', '', preg_replace('/\/([\d]+.)/', '/', trim(preg_replace('/[^\/]+(\.md$)/', '', $markdown_url), '/')));
            }

            // else its a relative path already
            $newpath = array();
            $paths = explode('/', $markdown_url);

            // remove the updirectory references (..)
            foreach ($paths as $path) {
                if ($path == '..') {
                    $relative_path = dirname($relative_path);
                } else {
                    $newpath[] = $path;
                }
            }

            // build the new url
            $new_url = rtrim($relative_path, '/') . '/' . implode('/', $newpath);
        }

        return $new_url;
    }
}
