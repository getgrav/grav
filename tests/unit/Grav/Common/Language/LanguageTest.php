<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Language\Language;

/**
 * Class LanguageTest
 */
class LanguageTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var array */
    protected $server;

    /** @var array */
    protected $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        $this->query = $_GET;

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = $this->query;

        parent::tearDown();
    }

    /**
     * @dataProvider providerHttpAcceptLanguageFallback
     */
    public function testHttpAcceptLanguageFallback(
        string $acceptLanguage,
        array $fallbacks,
        string $expectedLanguage
    ): void {
        $config = $this->grav['config'];
        $config->set('system.languages.supported', ['en', 'bs', 'de', 'cs']);
        $config->set('system.languages.default_lang', 'en');
        $config->set('system.languages.http_accept_language', true);
        $config->set('system.languages.http_accept_language_fallback', $fallbacks);
        $config->set('system.languages.session_store_active', false);

        $language = new Language($this->grav);
        unset($this->grav['language']);
        $this->grav['language'] = $language;

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $acceptLanguage;

        self::assertSame('/', $language->setActiveFromUri('/'));
        self::assertSame($expectedLanguage, $language->getActive());
    }

    public static function providerHttpAcceptLanguageFallback(): array
    {
        return [
            'regional browser language uses base fallback' => [
                'hr-HR',
                ['hr' => ['bs']],
                'bs',
            ],
            'mixed-case source matches normalized browser language' => [
                'sr-Latn',
                ['sr-Latn' => ['bs']],
                'bs',
            ],
            'malformed source is ignored' => [
                'hr',
                ['hr<script>' => ['bs']],
                'en',
            ],
            'unsupported target falls back to default' => [
                'hr',
                ['hr' => ['xx']],
                'en',
            ],
            'unsupported target is skipped' => [
                'hr',
                ['hr' => ['xx', 'bs']],
                'bs',
            ],
            'higher-quality fallback source wins' => [
                'hr;q=1.0, de;q=0.8',
                ['hr' => ['bs']],
                'bs',
            ],
            'direct supported language wins on equal quality' => [
                'hr;q=0.8, de;q=0.8',
                ['hr' => ['bs']],
                'de',
            ],
        ];
    }
}
