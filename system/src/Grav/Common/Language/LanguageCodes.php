<?php

/**
 * @package    Grav\Common\Language
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Language;

/**
 * Class LanguageCodes
 * @package Grav\Common\Language
 */
class LanguageCodes
{
    /** @var array */
    protected static $codes = [
        'af'         => [ 'name' => 'Afrikaans',                 'nativeName' => 'Afrikaans' ],
        'ak'         => [ 'name' => 'Akan',                      'nativeName' => 'Akan' ], // unverified native name
        'ast'        => [ 'name' => 'Asturian',                  'nativeName' => 'Asturianu' ],
        'ar'         => [ 'name' => 'Arabic',                    'nativeName' => 'عربي', 'orientation' => 'rtl'],
        'as'         => [ 'name' => 'Assamese',                  'nativeName' => 'অসমীয়া' ],
        'be'         => [ 'name' => 'Belarusian',                'nativeName' => 'Беларуская' ],
        'bg'         => [ 'name' => 'Bulgarian',                 'nativeName' => 'Български' ],
        'bn'         => [ 'name' => 'Bengali',                   'nativeName' => 'বাংলা' ],
        'bn-BD'      => [ 'name' => 'Bengali (Bangladesh)',      'nativeName' => 'বাংলা (বাংলাদেশ)' ],
        'bn-IN'      => [ 'name' => 'Bengali (India)',           'nativeName' => 'বাংলা (ভারত)' ],
        'br'         => [ 'name' => 'Breton',                    'nativeName' => 'Brezhoneg' ],
        'bs'         => [ 'name' => 'Bosnian',                   'nativeName' => 'Bosanski' ],
        'ca'         => [ 'name' => 'Catalan',                   'nativeName' => 'Català' ],
        'ca-valencia'=> [ 'name' => 'Catalan (Valencian)',       'nativeName' => 'Català (valencià)' ], // not iso-639-1. a=l10n-drivers
        'cs'         => [ 'name' => 'Czech',                     'nativeName' => 'Čeština' ],
        'cy'         => [ 'name' => 'Welsh',                     'nativeName' => 'Cymraeg' ],
        'da'         => [ 'name' => 'Danish',                    'nativeName' => 'Dansk' ],
        'de'         => [ 'name' => 'German',                    'nativeName' => 'Deutsch' ],
        'de-AT'      => [ 'name' => 'German (Austria)',          'nativeName' => 'Deutsch (Österreich)' ],
        'de-CH'      => [ 'name' => 'German (Switzerland)',      'nativeName' => 'Deutsch (Schweiz)' ],
        'de-DE'      => [ 'name' => 'German (Germany)',          'nativeName' => 'Deutsch (Deutschland)' ],
        'dsb'        => [ 'name' => 'Lower Sorbian',             'nativeName' => 'Dolnoserbšćina' ], // iso-639-2
        'el'         => [ 'name' => 'Greek',                     'nativeName' => 'Ελληνικά' ],
        'en'         => [ 'name' => 'English',                   'nativeName' => 'English' ],
        'en-AU'      => [ 'name' => 'English (Australian)',      'nativeName' => 'English (Australian)' ],
        'en-CA'      => [ 'name' => 'English (Canadian)',        'nativeName' => 'English (Canadian)' ],
        'en-GB'      => [ 'name' => 'English (British)',         'nativeName' => 'English (British)' ],
        'en-NZ'      => [ 'name' => 'English (New Zealand)',     'nativeName' => 'English (New Zealand)' ],
        'en-US'      => [ 'name' => 'English (US)',              'nativeName' => 'English (US)' ],
        'en-ZA'      => [ 'name' => 'English (South African)',   'nativeName' => 'English (South African)' ],
        'eo'         => [ 'name' => 'Esperanto',                 'nativeName' => 'Esperanto' ],
        'es'         => [ 'name' => 'Spanish',                   'nativeName' => 'Español' ],
        'es-AR'      => [ 'name' => 'Spanish (Argentina)',       'nativeName' => 'Español (de Argentina)' ],
        'es-CL'      => [ 'name' => 'Spanish (Chile)',           'nativeName' => 'Español (de Chile)' ],
        'es-ES'      => [ 'name' => 'Spanish (Spain)',           'nativeName' => 'Español (de España)' ],
        'es-MX'      => [ 'name' => 'Spanish (Mexico)',          'nativeName' => 'Español (de México)' ],
        'et'         => [ 'name' => 'Estonian',                  'nativeName' => 'Eesti keel' ],
        'eu'         => [ 'name' => 'Basque',                    'nativeName' => 'Euskara' ],
        'fa'         => [ 'name' => 'Persian',                   'nativeName' => 'فارسی' , 'orientation' => 'rtl' ],
        'fi'         => [ 'name' => 'Finnish',                   'nativeName' => 'Suomi' ],
        'fj-FJ'      => [ 'name' => 'Fijian',                    'nativeName' => 'Vosa vaka-Viti' ],
        'fr'         => [ 'name' => 'French',                    'nativeName' => 'Français' ],
        'fr-CA'      => [ 'name' => 'French (Canada)',           'nativeName' => 'Français (Canada)' ],
        'fr-FR'      => [ 'name' => 'French (France)',           'nativeName' => 'Français (France)' ],
        'fur'        => [ 'name' => 'Friulian',                  'nativeName' => 'Furlan' ],
        'fur-IT'     => [ 'name' => 'Friulian',                  'nativeName' => 'Furlan' ],
        'fy'         => [ 'name' => 'Frisian',                   'nativeName' => 'Frysk' ],
        'fy-NL'      => [ 'name' => 'Frisian',                   'nativeName' => 'Frysk' ],
        'ga'         => [ 'name' => 'Irish',                     'nativeName' => 'Gaeilge' ],
        'ga-IE'      => [ 'name' => 'Irish (Ireland)',           'nativeName' => 'Gaeilge (Éire)' ],
        'gd'         => [ 'name' => 'Gaelic (Scotland)',         'nativeName' => 'Gàidhlig' ],
        'gl'         => [ 'name' => 'Galician',                  'nativeName' => 'Galego' ],
        'gu'         => [ 'name' => 'Gujarati',                  'nativeName' => 'ગુજરાતી' ],
        'gu-IN'      => [ 'name' => 'Gujarati',                  'nativeName' => 'ગુજરાતી' ],
        'he'         => [ 'name' => 'Hebrew',                    'nativeName' => 'עברית', 'orientation' => 'rtl' ],
        'hi'         => [ 'name' => 'Hindi',                     'nativeName' => 'हिन्दी' ],
        'hi-IN'      => [ 'name' => 'Hindi (India)',             'nativeName' => 'हिन्दी (भारत)' ],
        'hr'         => [ 'name' => 'Croatian',                  'nativeName' => 'Hrvatski' ],
        'hsb'        => [ 'name' => 'Upper Sorbian',             'nativeName' => 'Hornjoserbsce' ],
        'hu'         => [ 'name' => 'Hungarian',                 'nativeName' => 'Magyar' ],
        'hy'         => [ 'name' => 'Armenian',                  'nativeName' => 'Հայերեն' ],
        'hy-AM'      => [ 'name' => 'Armenian',                  'nativeName' => 'Հայերեն' ],
        'id'         => [ 'name' => 'Indonesian',                'nativeName' => 'Bahasa Indonesia' ],
        'is'         => [ 'name' => 'Icelandic',                 'nativeName' => 'íslenska' ],
        'it'         => [ 'name' => 'Italian',                   'nativeName' => 'Italiano' ],
        'ja'         => [ 'name' => 'Japanese',                  'nativeName' => '日本語' ],
        'ja-JP'      => [ 'name' => 'Japanese',                  'nativeName' => '日本語' ], // not iso-639-1
        'ka'         => [ 'name' => 'Georgian',                  'nativeName' => 'ქართული' ],
        'kk'         => [ 'name' => 'Kazakh',                    'nativeName' => 'Қазақ' ],
        'km'         => [ 'name' => 'Khmer',                     'nativeName' => 'Khmer' ],
        'kn'         => [ 'name' => 'Kannada',                   'nativeName' => 'ಕನ್ನಡ' ],
        'ko'         => [ 'name' => 'Korean',                    'nativeName' => '한국어' ],
        'ku'         => [ 'name' => 'Kurdish',                   'nativeName' => 'Kurdî' ],
        'la'         => [ 'name' => 'Latin',                     'nativeName' => 'Latina' ],
        'lb'         => [ 'name' => 'Luxembourgish',             'nativeName' => 'Lëtzebuergesch' ],
        'lg'         => [ 'name' => 'Luganda',                   'nativeName' => 'Luganda' ],
        'lo'         => [ 'name' => 'Lao',                       'nativeName' => 'Lao' ],
        'lt'         => [ 'name' => 'Lithuanian',                'nativeName' => 'Lietuvių' ],
        'lv'         => [ 'name' => 'Latvian',                   'nativeName' => 'Latviešu' ],
        'mai'        => [ 'name' => 'Maithili',                  'nativeName' => 'मैथिली মৈথিলী' ],
        'mg'         => [ 'name' => 'Malagasy',                  'nativeName' => 'Malagasy' ],
        'mi'         => [ 'name' => 'Maori (Aotearoa)',          'nativeName' => 'Māori (Aotearoa)' ],
        'mk'         => [ 'name' => 'Macedonian',                'nativeName' => 'Македонски' ],
        'ml'         => [ 'name' => 'Malayalam',                 'nativeName' => 'മലയാളം' ],
        'mn'         => [ 'name' => 'Mongolian',                 'nativeName' => 'Монгол' ],
        'mr'         => [ 'name' => 'Marathi',                   'nativeName' => 'मराठी' ],
        'my'         => [ 'name' => 'Myanmar (Burmese)',         'nativeName' => 'ဗမာी' ],
        'no'         => [ 'name' => 'Norwegian',                 'nativeName' => 'Norsk' ],
        'nb'         => [ 'name' => 'Norwegian',                 'nativeName' => 'Norsk' ],
        'nb-NO'      => [ 'name' => 'Norwegian (Bokmål)',        'nativeName' => 'Norsk bokmål' ],
        'ne-NP'      => [ 'name' => 'Nepali',                    'nativeName' => 'नेपाली' ],
        'nn-NO'      => [ 'name' => 'Norwegian (Nynorsk)',       'nativeName' => 'Norsk nynorsk' ],
        'nl'         => [ 'name' => 'Dutch',                     'nativeName' => 'Nederlands' ],
        'nr'         => [ 'name' => 'Ndebele, South',            'nativeName' => 'IsiNdebele' ],
        'nso'        => [ 'name' => 'Northern Sotho',            'nativeName' => 'Sepedi' ],
        'oc'         => [ 'name' => 'Occitan (Lengadocian)',     'nativeName' => 'Occitan (lengadocian)' ],
        'or'         => [ 'name' => 'Oriya',                     'nativeName' => 'ଓଡ଼ିଆ' ],
        'pa'         => [ 'name' => 'Punjabi',                   'nativeName' => 'ਪੰਜਾਬੀ' ],
        'pa-IN'      => [ 'name' => 'Punjabi',                   'nativeName' => 'ਪੰਜਾਬੀ' ],
        'pl'         => [ 'name' => 'Polish',                    'nativeName' => 'Polski' ],
        'pt'         => [ 'name' => 'Portuguese',                'nativeName' => 'Português' ],
        'pt-BR'      => [ 'name' => 'Portuguese (Brazilian)',    'nativeName' => 'Português (do Brasil)' ],
        'pt-PT'      => [ 'name' => 'Portuguese (Portugal)',     'nativeName' => 'Português (Europeu)' ],
        'ro'         => [ 'name' => 'Romanian',                  'nativeName' => 'Română' ],
        'rm'         => [ 'name' => 'Romansh',                   'nativeName' => 'Rumantsch' ],
        'ru'         => [ 'name' => 'Russian',                   'nativeName' => 'Русский' ],
        'rw'         => [ 'name' => 'Kinyarwanda',               'nativeName' => 'Ikinyarwanda' ],
        'si'         => [ 'name' => 'Sinhala',                   'nativeName' => 'සිංහල' ],
        'sk'         => [ 'name' => 'Slovak',                    'nativeName' => 'Slovenčina' ],
        'sl'         => [ 'name' => 'Slovenian',                 'nativeName' => 'Slovensko' ],
        'son'        => [ 'name' => 'Songhai',                   'nativeName' => 'Soŋay' ],
        'sq'         => [ 'name' => 'Albanian',                  'nativeName' => 'Shqip' ],
        'sr'         => [ 'name' => 'Serbian',                   'nativeName' => 'Српски' ],
        'sr-Latn'    => [ 'name' => 'Serbian',                   'nativeName' => 'Srpski' ], // follows RFC 4646
        'ss'         => [ 'name' => 'Siswati',                   'nativeName' => 'siSwati' ],
        'st'         => [ 'name' => 'Southern Sotho',            'nativeName' => 'Sesotho' ],
        'sv'         => [ 'name' => 'Swedish',                   'nativeName' => 'Svenska' ],
        'sv-SE'      => [ 'name' => 'Swedish',                   'nativeName' => 'Svenska' ],
        'sw'         => [ 'name' => 'Swahili',                   'nativeName' => 'Swahili' ],
        'ta'         => [ 'name' => 'Tamil',                     'nativeName' => 'தமிழ்' ],
        'ta-IN'      => [ 'name' => 'Tamil (India)',             'nativeName' => 'தமிழ் (இந்தியா)' ],
        'ta-LK'      => [ 'name' => 'Tamil (Sri Lanka)',         'nativeName' => 'தமிழ் (இலங்கை)' ],
        'te'         => [ 'name' => 'Telugu',                    'nativeName' => 'తెలుగు' ],
        'th'         => [ 'name' => 'Thai',                      'nativeName' => 'ไทย' ],
        'tlh'        => [ 'name' => 'Klingon',                   'nativeName' => 'Klingon' ],
        'tn'         => [ 'name' => 'Tswana',                    'nativeName' => 'Setswana' ],
        'tr'         => [ 'name' => 'Turkish',                   'nativeName' => 'Türkçe' ],
        'ts'         => [ 'name' => 'Tsonga',                    'nativeName' => 'Xitsonga' ],
        'tt'         => [ 'name' => 'Tatar',                     'nativeName' => 'Tatarça' ],
        'tt-RU'      => [ 'name' => 'Tatar',                     'nativeName' => 'Tatarça' ],
        'uk'         => [ 'name' => 'Ukrainian',                 'nativeName' => 'Українська' ],
        'ur'         => [ 'name' => 'Urdu',                      'nativeName' => 'اُردو', 'orientation' => 'rtl'  ],
        've'         => [ 'name' => 'Venda',                     'nativeName' => 'Tshivenḓa' ],
        'vi'         => [ 'name' => 'Vietnamese',                'nativeName' => 'Tiếng Việt' ],
        'wo'         => [ 'name' => 'Wolof',                     'nativeName' => 'Wolof' ],
        'xh'         => [ 'name' => 'Xhosa',                     'nativeName' => 'isiXhosa' ],
        'zh'         => [ 'name' => 'Chinese (Simplified)',      'nativeName' => '中文 (简体)' ],
        'zh-CN'      => [ 'name' => 'Chinese (Simplified)',      'nativeName' => '中文 (简体)' ],
        'zh-TW'      => [ 'name' => 'Chinese (Traditional)',     'nativeName' => '正體中文 (繁體)' ],
        'zu'         => [ 'name' => 'Zulu',                      'nativeName' => 'isiZulu' ]
    ];

    /**
     * @param string $code
     * @return string|false
     */
    public static function getName($code)
    {
        return static::get($code, 'name');
    }

    /**
     * @param string $code
     * @return string|false
     */
    public static function getNativeName($code)
    {
        if (isset(static::$codes[$code])) {
            return static::get($code, 'nativeName');
        }

        if (preg_match('/[a-zA-Z]{2}-[a-zA-Z]{2}/', $code)) {
            return static::get(substr($code, 0, 2), 'nativeName') . ' (' . substr($code, -2) . ')';
        }

        return $code;
    }

    /**
     * @param string $code
     * @return string
     */
    public static function getOrientation($code)
    {
        return static::$codes[$code]['orientation'] ?? 'ltr';
    }

    /**
     * @param string $code
     * @return bool
     */
    public static function isRtl($code)
    {
        return static::getOrientation($code) === 'rtl';
    }

    /**
     * @param array $keys
     * @return array
     */
    public static function getNames(array $keys)
    {
        $results = [];
        foreach ($keys as $key) {
            if (isset(static::$codes[$key])) {
                $results[$key] = static::$codes[$key];
            }
        }
        return $results;
    }

    /**
     * @param string $code
     * @param string $type
     * @return string|false
     */
    public static function get($code, $type)
    {
        return static::$codes[$code][$type] ?? false;
    }

    /**
     * @param bool $native
     * @return array
     */
    public static function getList($native = true)
    {
        $list = [];
        foreach (static::$codes as $key => $names) {
            $list[$key] = $native ? $names['nativeName'] : $names['name'];
        }

        return $list;
    }
}
