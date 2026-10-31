<?php

use Codeception\Util\Fixtures;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Common\Uri;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class SiteRedirectTargetTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Config */
    protected $config;

    /** @var Uri */
    protected $uri;

    /** @var Pages */
    protected $pages;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->config = $this->grav['config'];
        $this->uri = $this->grav['uri'];
        $this->pages = $this->grav['pages'];

        // A language prefix in front of the route would hide the problem.
        $this->grav['language']->setLanguages([]);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/simple-site/user/pages', false);
        $this->pages->init();
    }

    protected function tearDown(): void
    {
        $this->config->set('site.redirects', null);
        $this->uri->initializeWithUrl('http://localhost/')->init();

        parent::tearDown();
    }

    /**
     * @return string|null The `Location` a request is answered with, null if no rule applied.
     */
    private function locationOf(string $pattern, string $target, string $request): ?string
    {
        $this->config->set('site.redirects', [$pattern => $target]);
        $this->uri->initializeWithUrl('http://testing.dev' . $request)->init();

        try {
            $this->pages->dispatch($this->uri->path());
        } catch (RuntimeException $e) {
            // Grav::close() throws in CLI instead of sending the response.
            self::assertSame(1, preg_match('#\(redirect to (.*)\)$#', $e->getMessage(), $matches), $e->getMessage());

            return $matches[1];
        }

        return null;
    }

    /**
     * @dataProvider providerInternalTarget
     */
    public function testInternalTargetStaysOnTheSite(string $pattern, string $target, string $request, ?string $expected): void
    {
        self::assertSame($expected, $this->locationOf($pattern, $target, $request));
    }

    public static function providerInternalTarget(): array
    {
        return [
            'exact match' => ['/redirect-test', '/', '/redirect-test', '/'],
            'self redirect after normalization' => ['/self', 'self', '/self', null],
            'query is kept' => ['/redirect-test', '/', '/redirect-test?x=1', '/?x=1'],
            'capture' => ['/old/(.*)', '/new/$1', '/old/my-page', '/new/my-page'],
            'no leading slash' => ['/old/(.*)', '$1', '/old/my-page', '/my-page'],
            'redirect code' => ['/redirect-test', '/[301]', '/redirect-test', '/'],

            'rest of the url' => ['/redirect-test', '/', '/redirect-test/evil.example', '/evil.example'],
            'rest of the url, more slashes' => ['/redirect-test', '/', '/redirect-test//evil.example', '/evil.example'],
            'captured slashes' => ['/old/(.*)', '/$1', '/old//evil.example', '/evil.example'],
            'captured url' => ['/old/(.*)', '$1', '/old/https://evil.example', '/https://evil.example'],
            'behind the redirect code' => ['/redirect-test', '/[301]', '/redirect-test/evil.example', '/evil.example'],
            'behind the redirect code, more slashes' => ['/redirect-test', '/[301]', '/redirect-test//evil.example', '/evil.example'],
        ];
    }

    /**
     * @dataProvider providerExternalTarget
     */
    public function testExternalTargetKeepsItsHost(string $target, string $request, ?string $expected): void
    {
        self::assertSame($expected, $this->locationOf('/shop', $target, $request));
    }

    public static function providerExternalTarget(): array
    {
        return [
            'exact match' => ['https://shop.example.com', '/shop', 'https://shop.example.com'],
            'path and query are kept' => ['https://shop.example.com', '/shop/a?b=1', 'https://shop.example.com/a?b=1'],
            'redirect code' => ['https://shop.example.com[301]', '/shop/a', 'https://shop.example.com/a'],
            'target with a path' => ['https://shop.example.com/x', '/shop.evil.example', 'https://shop.example.com/x.evil.example'],
            'protocol relative' => ['//shop.example.com', '/shop/a', '//shop.example.com/a'],

            'longer host' => ['https://shop.example.com', '/shop.evil.example/x', null],
            'host turned into user info' => ['https://shop.example.com', '/shop@evil.example', null],
            'port' => ['https://shop.example.com', '/shop:8080/x', null],
            'longer host, redirect code' => ['https://shop.example.com[301]', '/shop.evil.example/x', null],
            'longer host, protocol relative' => ['//shop.example.com', '/shop.evil.example/x', null],
        ];
    }

    public function testHostBuiltFromACaptureIsLeftToTheRule(): void
    {
        self::assertSame(
            'https://abc.example.com/x',
            $this->locationOf('/u/(\w+)/(.*)', 'https://$1.example.com/$2', '/u/abc/x')
        );
    }
}
