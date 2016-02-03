<?php
namespace Grav\Common\Markdown;

use Grav\Common\GravTrait;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Uri;
use RocketTheme\Toolbox\Event\Event;

/**
 * A trait to add some custom processing to the identifyLink() method in Parsedown and ParsedownExtra
 */
trait ParsedownGravTrait
{
    use GravTrait;

    /** @var Page $page */
    protected $page;

    /** @var Pages $pages */
    protected $pages;

    /** @var  Uri $uri */
    protected $uri;

    protected $pages_dir;
    protected $special_chars;
    protected $twig_link_regex = '/\!*\[(?:.*)\]\((\{([\{%#])\s*(.*?)\s*(?:\2|\})\})\)/';
    protected $special_protocols = ['xmpp', 'mailto', 'tel', 'sms'];

    public $completable_blocks = [];
    public $continuable_blocks = [];

    /**
     * Initialization function to setup key variables needed by the MarkdownGravLinkTrait
     *
     * @param $page
     * @param $defaults
     */
    protected function init($page, $defaults)
    {
        $grav = self::getGrav();

        $this->page = $page;
        $this->pages = $grav['pages'];
        $this->uri = $grav['uri'];
        $this->BlockTypes['{'] [] = "TwigTag";
        $this->pages_dir = self::getGrav()['locator']->findResource('page://');
        $this->special_chars = ['>' => 'gt', '<' => 'lt', '"' => 'quot'];

        if ($defaults === null) {
            $defaults = self::getGrav()['config']->get('system.pages.markdown');
        }

        $this->setBreaksEnabled($defaults['auto_line_breaks']);
        $this->setUrlsLinked($defaults['auto_url_links']);
        $this->setMarkupEscaped($defaults['escape_markup']);
        $this->setSpecialChars($defaults['special_chars']);

        $grav->fireEvent('onMarkdownInitialized', new Event(['markdown' => $this]));

    }

    /**
     * Be able to define a new Block type or override an existing one
     *
     * @param $type
     * @param $tag
     */
    public function addBlockType($type, $tag, $continuable = false, $completable = false)
    {
        $this->BlockTypes[$type] [] = $tag;

        if ($continuable) {
            $this->continuable_blocks[] = $tag;
        }

        if ($completable) {
            $this->completable_blocks[] = $tag;
        }
    }

    /**
     * Be able to define a new Inline type or override an existing one
     *
     * @param $type
     * @param $tag
     */
    public function addInlineType($type, $tag)
    {
        $this->InlineTypes[$type] [] = $tag;
        $this->inlineMarkerList .= $type;
    }

    /**
     * Overrides the default behavior to allow for plugin-provided blocks to be continuable
     *
     * @param $Type
     *
     * @return bool
     */
    protected function isBlockContinuable($Type)
    {
        $continuable = in_array($Type, $this->continuable_blocks) || method_exists($this, 'block' . $Type . 'Continue');

        return $continuable;
    }

    /**
     *  Overrides the default behavior to allow for plugin-provided blocks to be completable
     *
     * @param $Type
     *
     * @return bool
     */
    protected function isBlockCompletable($Type)
    {
        $completable = in_array($Type, $this->completable_blocks) || method_exists($this, 'block' . $Type . 'Complete');

        return $completable;
    }


    /**
     * Make the element function publicly accessible, Medium uses this to render from Twig
     *
     * @param  array $Element
     *
     * @return string markup
     */
    public function elementToHtml(array $Element)
    {
        return $this->element($Element);
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
        if (preg_match('/(?:{{|{%|{#)(.*)(?:}}|%}|#})/', $Line['body'], $matches)) {
            $Block = [
                'markup' => $Line['body'],
            ];

            return $Block;
        }
    }

    protected function inlineSpecialCharacter($Excerpt)
    {
        if ($Excerpt['text'][0] === '&' && !preg_match('/^&#?\w+;/', $Excerpt['text'])) {
            return [
                'markup' => '&amp;',
                'extent' => 1,
            ];
        }

        if (isset($this->special_chars[$Excerpt['text'][0]])) {
            return [
                'markup' => '&' . $this->special_chars[$Excerpt['text'][0]] . ';',
                'extent' => 1,
            ];
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
            $excerpt['type'] = 'image';
            $excerpt = parent::inlineImage($excerpt);
        }

        // Some stuff we will need
        $actions = [];
        $media = null;

        // if this is an image
        if (isset($excerpt['element']['attributes']['src'])) {
            $alt = $excerpt['element']['attributes']['alt'] ?: '';
            $title = $excerpt['element']['attributes']['title'] ?: '';
            $class = isset($excerpt['element']['attributes']['class']) ? $excerpt['element']['attributes']['class'] : '';

            //get the url and parse it
            $url = parse_url(htmlspecialchars_decode($excerpt['element']['attributes']['src']));

            $this_host = isset($url['host']) && $url['host'] == $this->uri->host();

            // if there is no host set but there is a path, the file is local
            if ((!isset($url['host']) || $this_host) && isset($url['path'])) {
                $path_parts = pathinfo($url['path']);

                // get the local path to page media if possible
                if ($path_parts['dirname'] == $this->page->url(false, false, false)) {
                    // get the media objects for this page
                    $media = $this->page->media();
                } else {
                    // see if this is an external page to this one
                    $base_url = rtrim(self::getGrav()['base_url_relative'] . self::getGrav()['pages']->base(), '/');
                    $page_route = '/' . ltrim(str_replace($base_url, '', $path_parts['dirname']), '/');

                    $ext_page = $this->pages->dispatch($page_route, true);
                    if ($ext_page) {
                        $media = $ext_page->media();
                    }
                }

                // if there is a media file that matches the path referenced..
                if ($media && isset($media->all()[$path_parts['basename']])) {
                    // get the medium object
                    $medium = $media->all()[$path_parts['basename']];

                    // if there is a query, then parse it and build action calls
                    if (isset($url['query'])) {
                        $actions = array_reduce(explode('&', $url['query']), function ($carry, $item) {
                            $parts = explode('=', $item, 2);
                            $value = isset($parts[1]) ? $parts[1] : null;
                            $carry[] = ['method' => $parts[0], 'params' => $value];

                            return $carry;
                        }, []);
                    }

                    // loop through actions for the image and call them
                    foreach ($actions as $action) {
                        $medium = call_user_func_array([$medium, $action['method']],
                            explode(',', urldecode($action['params'])));
                    }

                    if (isset($url['fragment'])) {
                        $medium->urlHash($url['fragment']);
                    }

                    $excerpt['element'] = $medium->parseDownElement($title, $alt, $class, true);

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
        if (isset($excerpt['type'])) {
            $type = $excerpt['type'];
        } else {
            $type = 'link';
        }

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

            // if there is a query, then parse it and build action calls
            if (isset($url['query'])) {
                $actions = array_reduce(explode('&', $url['query']), function ($carry, $item) {
                    $parts = explode('=', $item, 2);
                    $value = isset($parts[1]) ? $parts[1] : true;
                    $carry[$parts[0]] = $value;

                    return $carry;
                }, []);

                // valid attributes supported
                $valid_attributes = ['rel', 'target', 'id', 'class', 'classes'];

                // Unless told to not process, go through actions
                if (array_key_exists('noprocess', $actions)) {
                    unset($actions['noprocess']);
                } else {
                    // loop through actions for the image and call them
                    foreach ($actions as $attrib => $value) {
                        $key = $attrib;

                        if (in_array($attrib, $valid_attributes)) {
                            // support both class and classes
                            if ($attrib == 'classes') {
                                $attrib = 'class';
                            }
                            $excerpt['element']['attributes'][$attrib] = str_replace(',', ' ', $value);
                            unset($actions[$key]);
                        }
                    }
                }

                $url['query'] = http_build_query($actions, null, '&', PHP_QUERY_RFC3986);
            }

            // if no query elements left, unset query
            if (empty($url['query'])) {
                unset ($url['query']);
            }

            // set path to / if not set
            if (empty($url['path'])) {
                $url['path'] = '';
            }

            // if special scheme, just return
            if(isset($url['scheme']) && in_array($url['scheme'], $this->special_protocols)) {
                return $excerpt;
            }

            // handle paths and such
            $url = Uri::convertUrl($this->page, $url, $type);

            // build the URL from the component parts and set it on the element
            $excerpt['element']['attributes']['href'] = Uri::buildUrl($url);
        }

        return $excerpt;
    }

    // For extending this class via plugins
    public function __call($method, $args)
    {
        if (isset($this->$method) === true) {
            $func = $this->$method;

            return call_user_func_array($func, $args);
        }
    }
}
