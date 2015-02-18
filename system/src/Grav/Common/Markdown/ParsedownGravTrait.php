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
trait ParsedownGravTrait
{
    use GravTrait;
    protected $page;
    protected $pages;
    protected $base_url;
    protected $pages_dir;
    protected $special_chars;

    protected $twig_link_regex = '/\!*\[(?:.*)\]\(([{{|{%|{#].*[#}|%}|}}])\)/';

    /**
     * Initialiazation function to setup key variables needed by the MarkdownGravLinkTrait
     *
     * @param $page
     */
    protected function init($page)
    {
        $this->page = $page;
        $this->pages = self::getGrav()['pages'];
        $this->BlockTypes['{'] [] = "TwigTag";
        $this->base_url = rtrim(self::getGrav()['base_url'] . self::getGrav()['pages']->base(), '/');
        $this->pages_dir = self::getGrav()['locator']->findResource('page://');
        $this->special_chars = array('>' => 'gt', '<' => 'lt', '"' => 'quot');

        $defaults = self::getGrav()['config']->get('system.pages.markdown');

        $this->setBreaksEnabled($defaults['auto_line_breaks']);
        $this->setUrlsLinked($defaults['auto_url_links']);
        $this->setMarkupEscaped($defaults['escape_markup']);
        $this->setSpecialChars($defaults['special_chars']);
    }

    /**
     * Setter for special chars
     *
     * @param $special_chars
     *
     * @return $this
     */
    function setSpecialChars($special_chars)
    {
        $this->special_chars = $special_chars;

        return $this;
    }

    /**
     * Ensure Twig tags are treated as block level items with no <p></p> tags
     */
    protected function blockTwigTag($Line)
    {
        if (preg_match('/[{%|{{|{#].*[#}|}}|%}]/', $Line['body'], $matches)) {
            $Block = array(
                'markup' => $Line['body'],
            );
            return $Block;
        }
    }

    protected function inlineSpecialCharacter($Excerpt)
    {
        if ($Excerpt['text'][0] === '&' && ! preg_match('/^&#?\w+;/', $Excerpt['text'])) {
            return array(
                'markup' => '&amp;',
                'extent' => 1,
            );
        }

        if (isset($this->special_chars[$Excerpt['text'][0]])) {
            return array(
                'markup' => '&'.$this->special_chars[$Excerpt['text'][0]].';',
                'extent' => 1,
            );
        }
    }

    protected function inlineImage($excerpt)
    {
        if (preg_match($this->twig_link_regex, $excerpt['text'], $matches)) {
            $excerpt['text'] = str_replace($matches[1], '/', $excerpt['text']);
            $excerpt = parent::inlineImage($excerpt);
            $excerpt['element']['attributes']['src'] = $matches[1];
            $excerpt['extent'] = $excerpt['extent'] + strlen($matches[1]) - 1;
            return $excerpt;
        } else {
            $excerpt = parent::inlineImage($excerpt);
        }

        // Some stuff we will need
        $actions = array();
        $media = null;

        // if this is an image
        if (isset($excerpt['element']['attributes']['src'])) {

            $alt = $excerpt['element']['attributes']['alt'] ?: '';
            $title = $excerpt['element']['attributes']['title'] ?: '';

            //get the url and parse it
            $url = parse_url(htmlspecialchars_decode($excerpt['element']['attributes']['src']));

            $path_parts = pathinfo($url['path']);

            // if there is no host set but there is a path, the file is local
            if (!isset($url['host']) && isset($url['path'])) {

                // get the local path to page media if possible
                if ($path_parts['dirname'] == $this->page->url()) {
                    $url['path'] = ltrim(str_replace($this->page->url(), '', $url['path']), '/');
                    // get the media objects for this page
                    $media = $this->page->media();

                } else {

                    // see if this is an external page to this one
                    $page_route = str_replace($this->base_url, '', $path_parts['dirname']);

                    $ext_page = $this->pages->dispatch($page_route, true);
                    if ($ext_page) {
                        $media = $ext_page->media();
                        $url['path'] = $path_parts['basename'];
                    }
                }

                // if there is a media file that matches the path referenced..
                if ($media && isset($media->images()[$url['path']])) {
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

                    $data = $medium->htmlRaw();

                    // set the src element with the new generated url
                    if (!isset($actions['lightbox'])) {
                        $excerpt['element']['attributes']['src'] = $data['img_src'];

                        if ($data['img_srcset']) {
                            $excerpt['element']['attributes']['srcset'] = $data['img_srcset'];;
                            $excerpt['element']['attributes']['sizes'] = '100vw';
                        }

                    } else {
                        // Create the custom lightbox element

                        $attributes = $data['a_attributes'];
                        $attributes['href'] = $data['a_href'];

                        $img_attributes = [
                            'src' => $data['img_src'],
                            'alt' => $alt,
                            'title' => $title
                        ];

                        if ($data['img_srcset']) {
                            $img_attributes['srcset'] = $data['img_srcset'];
                            $img_attributes['sizes'] = '100vw';
                        }

                        $element = array(
                            'name' => 'a',
                            'attributes' => $attributes,
                            'handler' => 'element',
                            'text' => array(
                                'name' => 'img',
                                'attributes' => $img_attributes
                            )
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
                    $excerpt['element']['attributes']['src'] = Uri::buildUrl($url);
                }
            }
        }

        return $excerpt;
    }

    protected function inlineLink($excerpt)
    {
        // do some trickery to get around Parsedown requirement for valid URL if its Twig in there
        if (preg_match($this->twig_link_regex, $excerpt['text'], $matches)) {
            $excerpt['text'] = str_replace($matches[1], '/', $excerpt['text']);
            $excerpt = parent::inlineLink($excerpt);
            $excerpt['element']['attributes']['href'] = $matches[1];
            $excerpt['extent'] = $excerpt['extent'] + strlen($matches[1]) - 1;
            return $excerpt;
        } else {
            $excerpt = parent::inlineLink($excerpt);
        }

        // if this is a link
        if (isset($excerpt['element']['attributes']['href'])) {
            $url = parse_url(htmlspecialchars_decode($excerpt['element']['attributes']['href']));

            // if there is no scheme, the file is local
            if (!isset($url['scheme'])) {
                // convert the URl is required
                $excerpt['element']['attributes']['href'] = $this->convertUrl(Uri::buildUrl($url));
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
            return $markdown_url;
        // if its absolute and starts with /
        } elseif (strpos($markdown_url, '/') === 0) {
            return $this->base_url . $markdown_url;
        } else {
            $relative_path = $this->base_url . $this->page->route();
            $real_path = $this->page->path() . '/' . parse_url($markdown_url, PHP_URL_PATH);

            // strip numeric order from markdown path
            if (($real_path)) {
                $markdown_url = preg_replace('/^([\d]+\.)/', '', preg_replace('/\/([\d]+\.)/', '/', trim(preg_replace('/[^\/]+(\.md$)/', '', $markdown_url), '/')));
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
