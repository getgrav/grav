<?php

/**
 * @package    Grav\Common\Assets
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets;

use Grav\Common\Assets;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Framework\ContentBlock\HtmlBlock;
use function strlen;

/**
 * Register block assets into Grav.
 */
class BlockAssets
{
    /**
     * @param HtmlBlock $block
     * @return void
     */
    public static function registerAssets(HtmlBlock $block): void
    {
        $grav = Grav::instance();

        /** @var Assets $assets */
        $assets = $grav['assets'];

        $types = $block->getAssets();
        foreach ($types as $type => $groups) {
            switch ($type) {
                case 'frameworks':
                    static::registerFrameworks($assets, $groups);
                    break;
                case 'styles':
                    static::registerStyles($assets, $groups);
                    break;
                case 'scripts':
                    static::registerScripts($assets, $groups);
                    break;
                case 'links':
                    static::registerLinks($assets, $groups);
                    break;
                case 'html':
                    static::registerHtml($assets, $groups);
                    break;
            }
        }
    }

    /**
     * @param Assets $assets
     * @param array $list
     * @return void
     */
    protected static function registerFrameworks(Assets $assets, array $list): void
    {
        if ($list) {
            throw new \RuntimeException('Not Implemented');
        }
    }

    /**
     * @param Assets $assets
     * @param array $groups
     * @return void
     */
    protected static function registerStyles(Assets $assets, array $groups): void
    {
        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        foreach ($groups as $group => $styles) {
            foreach ($styles as $style) {
                switch ($style[':type']) {
                    case 'file':
                        $options = [
                            'priority' => $style[':priority'],
                            'group' => $group,
                            'type' => $style['type'],
                            'media' => $style['media']
                        ] + $style['element'];

                        $assets->addCss(static::getRelativeUrl($style['href'], $config->get('system.assets.css_pipeline')), $options);
                        break;
                    case 'inline':
                        $options = [
                            'priority' => $style[':priority'],
                            'group' => $group,
                            'type' => $style['type'],
                        ] + $style['element'];

                        $assets->addInlineCss($style['content'], $options);
                        break;
                }
            }
        }
    }

    /**
     * @param Assets $assets
     * @param array $groups
     * @return void
     */
    protected static function registerScripts(Assets $assets, array $groups): void
    {
        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        foreach ($groups as $group => $scripts) {
            $group = $group === 'footer' ? 'bottom' : $group;

            foreach ($scripts as $script) {
                switch ($script[':type']) {
                    case 'file':
                        $options = [
                            'group' => $group,
                            'priority' => $script[':priority'],
                            'src' => $script['src'],
                            'type' => $script['type'],
                            'loading' => $script['loading'],
                            'defer' => $script['defer'],
                            'async' => $script['async'],
                            'handle' => $script['handle']
                        ] + $script['element'];

                        $assets->addJs(static::getRelativeUrl($script['src'], $config->get('system.assets.js_pipeline')), $options);
                        break;
                    case 'inline':
                        $options = [
                            'priority' => $script[':priority'],
                            'group' => $group,
                            'type' => $script['type'],
                            'loading' => $script['loading']
                        ] + $script['element'];

                        $assets->addInlineJs($script['content'], $options);
                        break;
                }
            }
        }
    }

    /**
     * @param Assets $assets
     * @param array $groups
     * @return void
     */
    protected static function registerLinks(Assets $assets, array $groups): void
    {
        foreach ($groups as $group => $links) {
            foreach ($links as $link) {
                $href = $link['href'];
                $options = [
                    'group' => $group,
                    'priority' => $link[':priority'],
                    'rel' => $link['rel'],
                ] + $link['element'];

                $assets->addLink($href, $options);
            }
        }
    }

    /**
     * @param Assets $assets
     * @param array $groups
     * @return void
     */
    protected static function registerHtml(Assets $assets, array $groups): void
    {
        if ($groups) {
            throw new \RuntimeException('Not Implemented');
        }
    }

    /**
     * @param string $url
     * @param bool $pipeline
     * @return string
     */
    protected static function getRelativeUrl($url, $pipeline)
    {
        $grav = Grav::instance();

        $base = rtrim($grav['base_url'], '/') ?: '/';

        if (strpos($url, $base) === 0) {
            if ($pipeline) {
                // Remove file timestamp if CSS pipeline has been enabled.
                $url = preg_replace('|[?#].*|', '', $url);
            }

            return substr($url, strlen($base) - 1);
        }
        return $url;
    }
}
