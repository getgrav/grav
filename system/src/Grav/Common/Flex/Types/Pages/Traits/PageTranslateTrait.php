<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Pages\Traits;

use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Page;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use SplFileInfo;

/**
 * Implements PageTranslateInterface
 */
trait PageTranslateTrait
{
    /**
     * Return an array with the routes of other translated languages
     *
     * @param bool $onlyPublished only return published translations
     * @return array the page translated languages
     */
    public function translatedLanguages($onlyPublished = false): array
    {
        if (Utils::isAdminPlugin()) {
            return parent::translatedLanguages();
        }

        $translated = $this->getLanguageTemplates();
        if (!$translated) {
            return $translated;
        }

        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        $languages = $language->getLanguages();
        $languages[] = '';
        $defaultCode = $language->getDefault();

        if (isset($translated[$defaultCode])) {
            unset($translated['']);
        }

        foreach ($translated as $key => &$template) {
            $template .= $key !== '' ? ".{$key}.md" : '.md';
        }
        unset($template);

        $translated = array_intersect_key($translated, array_flip($languages));

        $folder = $this->getStorageFolder();
        if (!$folder) {
            return [];
        }
        $folder = $locator->isStream($folder) ? $locator->getResource($folder) : GRAV_ROOT . "/{$folder}";

        $list = array_fill_keys($languages, null);
        foreach ($translated as $languageCode => $languageFile) {
            $languageExtension = $languageCode ? ".{$languageCode}.md" : '.md';
            $path = "{$folder}/{$languageFile}";

            // FIXME: use flex, also rawRoute() does not fully work?
            $aPage = new Page();
            $aPage->init(new SplFileInfo($path), $languageExtension);
            if ($onlyPublished && !$aPage->published()) {
                continue;
            }

            $header = $aPage->header();
            // @phpstan-ignore-next-line
            $routes = $header->routes ?? [];
            $route = $routes['default'] ?? $aPage->rawRoute();
            if (!$route) {
                $route = $aPage->route();
            }

            $list[$languageCode ?: $defaultCode] = $route ?? '';
        }

        $list = array_filter($list, static function ($var) {
            return null !== $var;
        });

        // Hack to get the same result as with old pages.
        foreach ($list as &$path) {
            if ($path === '') {
                $path = null;
            }
        }

        return $list;
    }
}
