<?php

/**
 * @package    Grav\Framework\ContentBlock
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\ContentBlock;

use RuntimeException;
use function is_array;
use function is_string;

/**
 * HtmlBlock
 *
 * @package Grav\Framework\ContentBlock
 */
class HtmlBlock extends ContentBlock implements HtmlBlockInterface
{
    /** @var int */
    protected $version = 1;
    /** @var array */
    protected $frameworks = [];
    /** @var array */
    protected $styles = [];
    /** @var array */
    protected $scripts = [];
    /** @var array */
    protected $html = [];

    /**
     * @return array
     */
    public function getAssets()
    {
        $assets = $this->getAssetsFast();

        $this->sortAssets($assets['styles']);
        $this->sortAssets($assets['scripts']);
        $this->sortAssets($assets['html']);

        return $assets;
    }

    /**
     * @return array
     */
    public function getFrameworks()
    {
        $assets = $this->getAssetsFast();

        return array_keys($assets['frameworks']);
    }

    /**
     * @param string $location
     * @return array
     */
    public function getStyles($location = 'head')
    {
        return $this->getAssetsInLocation('styles', $location);
    }

    /**
     * @param string $location
     * @return array
     */
    public function getScripts($location = 'head')
    {
        return $this->getAssetsInLocation('scripts', $location);
    }

    /**
     * @param string $location
     * @return array
     */
    public function getHtml($location = 'bottom')
    {
        return $this->getAssetsInLocation('html', $location);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        if ($this->frameworks) {
            $array['frameworks'] = $this->frameworks;
        }
        if ($this->styles) {
            $array['styles'] = $this->styles;
        }
        if ($this->scripts) {
            $array['scripts'] = $this->scripts;
        }
        if ($this->html) {
            $array['html'] = $this->html;
        }

        return $array;
    }

    /**
     * @param array $serialized
     * @return void
     * @throws RuntimeException
     */
    public function build(array $serialized)
    {
        parent::build($serialized);

        $this->frameworks = isset($serialized['frameworks']) ? (array) $serialized['frameworks'] : [];
        $this->styles = isset($serialized['styles']) ? (array) $serialized['styles'] : [];
        $this->scripts = isset($serialized['scripts']) ? (array) $serialized['scripts'] : [];
        $this->html = isset($serialized['html']) ? (array) $serialized['html'] : [];
    }

    /**
     * @param string $framework
     * @return $this
     */
    public function addFramework($framework)
    {
        $this->frameworks[$framework] = 1;

        return $this;
    }

    /**
     * @param string|array $element
     * @param int $priority
     * @param string $location
     * @return bool
     *
     * @example $block->addStyle('assets/js/my.js');
     * @example $block->addStyle(['href' => 'assets/js/my.js', 'media' => 'screen']);
     */
    public function addStyle($element, $priority = 0, $location = 'head')
    {
        if (!is_array($element)) {
            $element = ['href' => (string) $element];
        }
        if (empty($element['href'])) {
            return false;
        }
        if (!isset($this->styles[$location])) {
            $this->styles[$location] = [];
        }

        $id = !empty($element['id']) ? ['id' => (string) $element['id']] : [];
        $href = $element['href'];
        $type = !empty($element['type']) ? (string) $element['type'] : 'text/css';
        $media = !empty($element['media']) ? (string) $element['media'] : null;
        unset(
            $element['tag'],
            $element['id'],
            $element['rel'],
            $element['content'],
            $element['href'],
            $element['type'],
            $element['media']
        );

        $this->styles[$location][md5($href) . sha1($href)] = [
                ':type' => 'file',
                ':priority' => (int) $priority,
                'href' => $href,
                'type' => $type,
                'media' => $media,
                'element' => $element
            ] + $id;

        return true;
    }

    /**
     * @param string|array $element
     * @param int $priority
     * @param string $location
     * @return bool
     */
    public function addInlineStyle($element, $priority = 0, $location = 'head')
    {
        if (!is_array($element)) {
            $element = ['content' => (string) $element];
        }
        if (empty($element['content'])) {
            return false;
        }
        if (!isset($this->styles[$location])) {
            $this->styles[$location] = [];
        }

        $content = (string) $element['content'];
        $type = !empty($element['type']) ? (string) $element['type'] : 'text/css';

        $this->styles[$location][md5($content) . sha1($content)] = [
            ':type' => 'inline',
            ':priority' => (int) $priority,
            'content' => $content,
            'type' => $type
        ];

        return true;
    }

    /**
     * @param string|array $element
     * @param int $priority
     * @param string $location
     * @return bool
     */
    public function addScript($element, $priority = 0, $location = 'head')
    {
        if (!is_array($element)) {
            $element = ['src' => (string) $element];
        }
        if (empty($element['src'])) {
            return false;
        }
        if (!isset($this->scripts[$location])) {
            $this->scripts[$location] = [];
        }

        $src = $element['src'];
        $type = !empty($element['type']) ? (string) $element['type'] : 'text/javascript';
        $defer = isset($element['defer']);
        $async = isset($element['async']);
        $handle = !empty($element['handle']) ? (string) $element['handle'] : '';

        $this->scripts[$location][md5($src) . sha1($src)] = [
            ':type' => 'file',
            ':priority' => (int) $priority,
            'src' => $src,
            'type' => $type,
            'defer' => $defer,
            'async' => $async,
            'handle' => $handle
        ];

        return true;
    }

    /**
     * @param string|array $element
     * @param int $priority
     * @param string $location
     * @return bool
     */
    public function addInlineScript($element, $priority = 0, $location = 'head')
    {
        if (!is_array($element)) {
            $element = ['content' => (string) $element];
        }
        if (empty($element['content'])) {
            return false;
        }
        if (!isset($this->scripts[$location])) {
            $this->scripts[$location] = [];
        }

        $content = (string) $element['content'];
        $type = !empty($element['type']) ? (string) $element['type'] : 'text/javascript';

        $this->scripts[$location][md5($content) . sha1($content)] = [
            ':type' => 'inline',
            ':priority' => (int) $priority,
            'content' => $content,
            'type' => $type
        ];

        return true;
    }

    /**
     * @param string $html
     * @param int $priority
     * @param string $location
     * @return bool
     */
    public function addHtml($html, $priority = 0, $location = 'bottom')
    {
        if (empty($html) || !is_string($html)) {
            return false;
        }
        if (!isset($this->html[$location])) {
            $this->html[$location] = [];
        }

        $this->html[$location][md5($html) . sha1($html)] = [
            ':priority' => (int) $priority,
            'html' => $html
        ];

        return true;
    }

    /**
     * @return array
     */
    protected function getAssetsFast()
    {
        $assets = [
            'frameworks' => $this->frameworks,
            'styles' => $this->styles,
            'scripts' => $this->scripts,
            'html' => $this->html
        ];

        foreach ($this->blocks as $block) {
            if ($block instanceof self) {
                $blockAssets = $block->getAssetsFast();
                $assets['frameworks'] += $blockAssets['frameworks'];

                foreach ($blockAssets['styles'] as $location => $styles) {
                    if (!isset($assets['styles'][$location])) {
                        $assets['styles'][$location] = $styles;
                    } elseif ($styles) {
                        $assets['styles'][$location] += $styles;
                    }
                }

                foreach ($blockAssets['scripts'] as $location => $scripts) {
                    if (!isset($assets['scripts'][$location])) {
                        $assets['scripts'][$location] = $scripts;
                    } elseif ($scripts) {
                        $assets['scripts'][$location] += $scripts;
                    }
                }

                foreach ($blockAssets['html'] as $location => $htmls) {
                    if (!isset($assets['html'][$location])) {
                        $assets['html'][$location] = $htmls;
                    } elseif ($htmls) {
                        $assets['html'][$location] += $htmls;
                    }
                }
            }
        }

        return $assets;
    }

    /**
     * @param string $type
     * @param string $location
     * @return array
     */
    protected function getAssetsInLocation($type, $location)
    {
        $assets = $this->getAssetsFast();

        if (empty($assets[$type][$location])) {
            return [];
        }

        $styles = $assets[$type][$location];
        $this->sortAssetsInLocation($styles);

        return $styles;
    }

    /**
     * @param array $items
     * @return void
     */
    protected function sortAssetsInLocation(array &$items)
    {
        $count = 0;
        foreach ($items as &$item) {
            $item[':order'] = ++$count;
        }
        unset($item);

        uasort(
            $items,
            static function ($a, $b) {
                return $a[':priority'] <=> $b[':priority'] ?: $a[':order'] <=> $b[':order'];
            }
        );
    }

    /**
     * @param array $array
     * @return void
     */
    protected function sortAssets(array &$array)
    {
        foreach ($array as $location => &$items) {
            $this->sortAssetsInLocation($items);
        }
    }
}
