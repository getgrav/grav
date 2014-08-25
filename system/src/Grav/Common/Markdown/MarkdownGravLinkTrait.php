<?php
namespace Grav\Common\Markdown;

use Grav\Common\Debugger;

/**
 * A trait to add some custom processing to the identifyLink() method in Parsedown and ParsedownExtra
 */
trait MarkdownGravLinkTrait
{

    protected function identifyLink($Excerpt)
    {
        // Run the parent method to get the actual results
        $Excerpt = parent::identifyLink($Excerpt);
        $actions = array();
        $command = '';

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
