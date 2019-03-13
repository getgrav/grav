<?php
namespace Grav\Common\Page\Interfaces;

interface PageTranslateInterface
{
    /**
     * Return an array with the routes of other translated languages
     *
     * @param bool $onlyPublished only return published translations
     *
     * @return array the page translated languages
     */
    public function translatedLanguages($onlyPublished = false);

    /**
     * Return an array listing untranslated languages available
     *
     * @param bool $includeUnpublished also list unpublished translations
     *
     * @return array the page untranslated languages
     */
    public function untranslatedLanguages($includeUnpublished = false);

    /**
     * Get page language
     *
     * @param string $var
     *
     * @return mixed
     */
    public function language($var = null);
}
