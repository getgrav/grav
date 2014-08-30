<?php
namespace Grav\Common\Markdown;

use Grav\Common\Debugger;
use Grav\Common\GravTrait;

/**
 * A trait to add some custom processing to the identifyLink() method in Parsedown and ParsedownExtra
 */
trait MarkdownGravLinkTrait
{
    use GravTrait;

    protected function identifyLink($Excerpt)
    {
        // Run the parent method to get the actual results
        $Excerpt = parent::identifyLink($Excerpt);
        $actions = array();
        $command = '';
        $config = self::$grav['config'];
        $base_url = trim($config->get('system.base_url_relative'));
        $base_url_full = trim($config->get('system.base_url_absolute'));

        // if this is a link
        if (isset($Excerpt['element']['attributes']['href'])) {

            $url = parse_url(htmlspecialchars_decode($Excerpt['element']['attributes']['href']));

            // if there is no host set but there is a path, the file is local
            if (!isset($url['host']) && isset($url['path'])) {

                $markdown_url = $url['path'];
                $not_relative_urls = ['/','http://','https://'];
                $valid = true;

                // make sure the url is relative
                foreach ($not_relative_urls as $needle) {
                    if (strpos($markdown_url, $needle) === 0) {
                        $valid = false;
                        break;
                    }
                }

                // if it is a valid relative url being the transformation
                if ($valid) {

                    $relative_path = rtrim($base_url, '/') . $this->page->route();

                    // If this is a 'real' filepath clean it up
                    if (file_exists($this->page->path().'/'.$markdown_url)) {
                        $relative_path = rtrim($base_url, '/') .
                                         preg_replace('/\/([\d]+.)/', '/',
                                         str_replace(PAGES_DIR, '/', $this->page->path()));
                        $markdown_url = preg_replace('/^([\d]+.)/', '',
                                        preg_replace('/\/([\d]+.)/', '/', $markdown_url));
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
                    $new_url = $relative_path . '/' . implode('/', $newpath);

                    // set the new url back on the Excerpt
                    $Excerpt['element']['attributes']['href'] = $new_url;
                }
            }
        }

        // if this is an image
        if (isset($Excerpt['element']['attributes']['src'])) {

            $alt = isset($Excerpt['element']['attributes']['alt']) ? $Excerpt['element']['attributes']['alt'] : '';
            $title = isset($Excerpt['element']['attributes']['title']) ? $Excerpt['element']['attributes']['title'] : '';

            //get the url and parse it
            $url = parse_url(htmlspecialchars_decode($Excerpt['element']['attributes']['src']));

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
                        // as long as it's not an html, url or ligtbox action
                        if (!in_array($action, ['html','url','lightbox'])) {
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
                        $Excerpt['element']['attributes']['src'] = $src;
                    } else {

                        // Create the custom lightbox element
                        $Element = array(
                            'name' => 'a',
                            'attributes' => array('rel' => $src['a_rel'], 'href' => $src['a_url']),
                            'handler' => 'element',
                            'text' => array(
                                'name' => 'img',
                                'attributes' => array('src' => $src['img_url'], 'alt' => $alt, 'title' => $title)
                            ),
                        );

                        // Set the lightbox element on the Excerpt
                        $Excerpt['element'] = $Element;
                    }
                }
            }
        }
        return $Excerpt;
    }
}
