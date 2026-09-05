<?php

use Codeception\Util\Fixtures;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Processors\InitializeProcessor;
use Grav\Common\Uri;
use Nyholm\Psr7\ServerRequest;

/**
 * Class InitializeProcessorTest
 *
 * Covers the trailing-slash redirect, and specifically what it does to the redirect
 * target when `system.custom_base_url` is configured (#3822).
 *
 * The overwhelming majority of sites set no custom base at all, so the first thing
 * every scenario here establishes is what that case does -- a change to this code path
 * that altered ordinary installs would be far worse than the bug it fixes.
 */
class InitializeProcessorTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;
    /** @var Config */
    protected $config;
    /** @var Uri */
    protected $uri;
    /** @var string|null */
    protected $originalCustomBaseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $this->grav = $grav();
        $this->config = $this->grav['config'];
        $this->uri = $this->grav['uri'];

        $this->originalCustomBaseUrl = $this->config->get('system.custom_base_url');
    }

    protected function tearDown(): void
    {
        // These tests mutate shared singletons (config + the Uri instance), so put the
        // configured base back whatever happens, including on a failed assertion.
        $this->config->set('system.custom_base_url', $this->originalCustomBaseUrl);
        $this->uri->initializeWithUrl('http://localhost/')->init();

        parent::tearDown();
    }

    /**
     * Drive the real `handleRedirectRequest()`.
     *
     * @param string|null $customBaseUrl What `system.custom_base_url` is set to.
     * @param string      $physicalUrl   The URL as it actually reaches PHP.
     * @param string|null $rootPath      Physical root path, when it differs from the public one.
     * @return string|null               The Location header, or null when no redirect is issued.
     */
    protected function redirectLocation(?string $customBaseUrl, string $physicalUrl, ?string $rootPath = null): ?string
    {
        $this->config->set('system.custom_base_url', $customBaseUrl);

        if ($rootPath !== null) {
            $this->uri->initializeWithUrlAndRootPath($physicalUrl, $rootPath)->init();
        } else {
            $this->uri->initializeWithUrl($physicalUrl)->init();
        }

        $processor = new InitializeProcessor($this->grav);
        $method = new ReflectionMethod($processor, 'handleRedirectRequest');
        $method->setAccessible(true);

        $response = $method->invoke($processor, new ServerRequest('GET', $physicalUrl), 302);

        return $response ? $response->getHeaderLine('Location') : null;
    }

    /* ------------------------------------------------------------------ *
     * No custom base: the ordinary install. Nothing here may change.
     * ------------------------------------------------------------------ */

    public function testDocrootInstallStripsTrailingSlash(): void
    {
        self::assertSame(
            'http://localhost/page1',
            $this->redirectLocation(null, 'http://localhost/page1/')
        );
    }

    public function testDocrootInstallLeavesAPathWithoutTrailingSlashAlone(): void
    {
        self::assertNull($this->redirectLocation(null, 'http://localhost/page1'));
    }

    public function testDocrootInstallDoesNotRedirectTheHomepage(): void
    {
        self::assertNull($this->redirectLocation(null, 'http://localhost/'));
    }

    public function testDocrootInstallHandlesNestedPaths(): void
    {
        self::assertSame(
            'http://localhost/blog/2026/some-post',
            $this->redirectLocation(null, 'http://localhost/blog/2026/some-post/')
        );
    }

    /**
     * A path that would trip a naive prefix test if a base were involved. With no base
     * configured the new block is skipped entirely, so this must behave like any other.
     */
    public function testDocrootInstallIsUnaffectedByPrefixLookalikePaths(): void
    {
        self::assertSame(
            'http://localhost/subscribe',
            $this->redirectLocation(null, 'http://localhost/subscribe/')
        );
    }

    public function testSubfolderInstallWithoutCustomBaseIsUnchanged(): void
    {
        self::assertSame(
            'http://localhost/grav/page1',
            $this->redirectLocation(null, 'http://localhost/grav/page1/', '/grav')
        );
    }

    /* ------------------------------------------------------------------ *
     * The reported bug: base stripped by a proxy before it reaches PHP.
     * ------------------------------------------------------------------ */

    public function testCustomBaseIsRestoredWhenTheProxyStrippedIt(): void
    {
        self::assertSame(
            'http://localhost/custombaseurl/page1',
            $this->redirectLocation('http://localhost/custombaseurl', 'http://localhost/page1/')
        );
    }

    public function testCustomBaseIsNotDoubledWhenThePathAlreadyCarriesIt(): void
    {
        self::assertSame(
            'http://localhost/custombaseurl/page1',
            $this->redirectLocation('http://localhost/custombaseurl', 'http://localhost/custombaseurl/page1/', '/custombaseurl')
        );
    }

    public function testCustomBaseWithATrailingSlashDoesNotProduceADoubleSlash(): void
    {
        self::assertSame(
            'http://localhost/custombaseurl/page1',
            $this->redirectLocation('http://localhost/custombaseurl/', 'http://localhost/page1/')
        );
    }

    public function testCustomBaseAppliesToNestedPathsToo(): void
    {
        self::assertSame(
            'http://localhost/custombaseurl/blog/2026/some-post',
            $this->redirectLocation('http://localhost/custombaseurl', 'http://localhost/blog/2026/some-post/')
        );
    }

    /* ------------------------------------------------------------------ *
     * The segment-awareness bug in the first attempt at this fix.
     * ------------------------------------------------------------------ */

    /**
     * `/subscribe` starts with the text `/sub` but is not under it. A raw prefix test
     * concludes the base is already present and skips restoring it, leaving #3822
     * unfixed for every route whose first segment merely shares those letters.
     *
     * @dataProvider prefixLookalikePaths
     */
    public function testBaseIsRestoredForPathsThatOnlyLookLikeTheyCarryIt(string $path, string $expected): void
    {
        self::assertSame(
            $expected,
            $this->redirectLocation('http://localhost/sub', 'http://localhost' . $path)
        );
    }

    /** @return array<string, array{string, string}> */
    public function prefixLookalikePaths(): array
    {
        return [
            'longer word'       => ['/subscribe/',  'http://localhost/sub/subscribe'],
            'hyphenated'        => ['/sub-zero/',   'http://localhost/sub/sub-zero'],
            'underscored'       => ['/sub_menu/',   'http://localhost/sub/sub_menu'],
            'no separator'      => ['/submarine/',  'http://localhost/sub/submarine'],
        ];
    }

    /**
     * The genuine "already under the base" case must still short-circuit, including
     * when the segment after the base repeats the base's own name.
     */
    public function testAPathGenuinelyUnderTheBaseIsNotPrefixedAgain(): void
    {
        self::assertSame(
            'http://localhost/sub/subscribe',
            $this->redirectLocation('http://localhost/sub', 'http://localhost/sub/subscribe/', '/sub')
        );
    }

    public function testTheBasePathItselfCountsAsBeingUnderTheBase(): void
    {
        self::assertNull(
            $this->redirectLocation('http://localhost/sub', 'http://localhost/sub/', '/sub')
        );
    }

    /* ------------------------------------------------------------------ *
     * Full-URL custom bases, including the malformed one.
     * ------------------------------------------------------------------ */

    /**
     * `rootUrl(false)` strips scheme and host, so a full URL yields just the path and
     * no `https://` can leak into the redirect target.
     */
    public function testCustomBaseGivenAsAFullUrlContributesOnlyItsPath(): void
    {
        self::assertSame(
            'http://localhost/sub/page1',
            $this->redirectLocation('https://example.com/sub', 'http://localhost/page1/')
        );
    }

    /**
     * A host-only custom base has no path to restore, so this must behave exactly like
     * an ordinary install rather than prepending an empty string.
     */
    public function testCustomBaseWithNoPathBehavesLikeNoCustomBase(): void
    {
        self::assertSame(
            'http://localhost/page1',
            $this->redirectLocation('https://example.com', 'http://localhost/page1/')
        );
    }

    /* ------------------------------------------------------------------ *
     * Homepage, encoding, query strings and non-idempotent methods.
     * ------------------------------------------------------------------ */

    /**
     * On develop this redirected to the bare host with an empty path. Restoring the base
     * makes the path equal the root, so the homepage correctly issues no redirect.
     */
    public function testStrippedHomepageOfACustomBaseSiteDoesNotRedirectToTheBareHost(): void
    {
        self::assertNull(
            $this->redirectLocation('http://localhost/sub', 'http://localhost/')
        );
    }

    public function testPercentEncodingIsPreserved(): void
    {
        self::assertSame(
            'http://localhost/sub/caf%C3%A9',
            $this->redirectLocation('http://localhost/sub', 'http://localhost/caf%C3%A9/')
        );
    }

    public function testQueryStringSurvivesTheRedirect(): void
    {
        self::assertSame(
            'http://localhost/sub/page1?a=1&b=2',
            $this->redirectLocation('http://localhost/sub', 'http://localhost/page1/?a=1&b=2')
        );
    }

    public function testNonGetRequestsAreNeverRedirected(): void
    {
        $this->config->set('system.custom_base_url', 'http://localhost/sub');
        $this->uri->initializeWithUrl('http://localhost/page1/')->init();

        $processor = new InitializeProcessor($this->grav);
        $method = new ReflectionMethod($processor, 'handleRedirectRequest');
        $method->setAccessible(true);

        self::assertNull($method->invoke($processor, new ServerRequest('POST', 'http://localhost/page1/'), 302));
    }

    /* ------------------------------------------------------------------ *
     * The segment comparison itself.
     * ------------------------------------------------------------------ */

    /**
     * @dataProvider baseComparisons
     */
    public function testPathHasBase(string $path, string $base, bool $expected): void
    {
        $method = new ReflectionMethod(InitializeProcessor::class, 'pathHasBase');
        $method->setAccessible(true);

        self::assertSame($expected, $method->invoke(null, $path, $base));
    }

    /** @return array<string, array{string, string, bool}> */
    public function baseComparisons(): array
    {
        return [
            'exact match'                => ['/sub', '/sub', true],
            'exact match, base slashed'  => ['/sub', '/sub/', true],
            'child path'                 => ['/sub/page', '/sub', true],
            'deep child'                 => ['/sub/a/b/c', '/sub', true],
            'lookalike longer word'      => ['/subscribe', '/sub', false],
            'lookalike hyphen'           => ['/sub-zero', '/sub', false],
            'unrelated'                  => ['/other', '/sub', false],
            'root path'                  => ['/', '/sub', false],
            'base repeated below itself' => ['/sub/sub', '/sub', true],
            'multi-segment base'         => ['/a/b/page', '/a/b', true],
            'multi-segment lookalike'    => ['/a/bc/page', '/a/b', false],
        ];
    }

    /* ------------------------------------------------------------------ *
     * GRAV_CONFIG__* environment overrides: boolean literals must not
     * arrive as the strings "true"/"false" (#4277).
     * ------------------------------------------------------------------ */

    /**
     * @dataProvider environmentValues
     */
    public function testCastEnvironmentValue(mixed $input, mixed $expected): void
    {
        $method = new ReflectionMethod(InitializeProcessor::class, 'castEnvironmentValue');
        $method->setAccessible(true);

        self::assertSame($expected, $method->invoke(null, $input));
    }

    /** @return array<string, array{mixed, mixed}> */
    public function environmentValues(): array
    {
        return [
            'false literal' => ['false', false],
            'true literal' => ['true', true],
            'uppercase FALSE' => ['FALSE', false],
            'mixed-case True' => ['True', true],
            '"0" kept as string' => ['0', '0'],
            '"1" kept as string' => ['1', '1'],
            'numeric string kept' => ['42', '42'],
            'arbitrary string kept' => ['production', 'production'],
            'literal as a substring is not matched' => ['falsey', 'falsey'],
            'a padded literal is left as text' => [' true ', ' true '],
            '"yes" is not coerced' => ['yes', 'yes'],
            'empty string' => ['', ''],
            'a real bool is returned as-is' => [true, true],
            'a real false is returned as-is' => [false, false],
            'null is returned as-is' => [null, null],
            'an int is returned as-is' => [0, 0],
        ];
    }

    /* ------------------------------------------------------------------ *
     * The GRAV_CONFIG gate itself must not require getenv() (#4279).
     *
     * SAPIs such as Apache's SetEnv or nginx's fastcgi_param only populate
     * $_SERVER, never the process environment getenv() reads, so a gate
     * that checks getenv() alone silently drops every GRAV_CONFIG__*
     * override for those setups even though the body one line below it
     * already reads $_ENV + $_SERVER.
     * ------------------------------------------------------------------ */

    public function testConfigOverrideAppliesWhenGravConfigIsOnlyInServer(): void
    {
        $original = $this->config->get('test.grav_config_4279');
        $restoreServer = $this->stashServerVars(['GRAV_CONFIG', 'GRAV_CONFIG__test__grav_config_4279']);

        $_SERVER['GRAV_CONFIG'] = '1';
        $_SERVER['GRAV_CONFIG__test__grav_config_4279'] = 'from-server-only';

        try {
            $processor = new InitializeProcessor($this->grav);
            $method = new ReflectionMethod($processor, 'initializeConfig');
            $method->setAccessible(true);
            $method->invoke($processor);

            self::assertSame('from-server-only', $this->config->get('test.grav_config_4279'));
        } finally {
            $restoreServer();
            $this->config->set('test.grav_config_4279', $original);
        }
    }

    /**
     * A present-but-empty `$_SERVER['GRAV_CONFIG']` is what an unset nginx
     * `fastcgi_param` or an empty Apache `SetEnv` leaves behind. The gate
     * must fall through to `getenv()` in that case rather than treating the
     * empty value as "GRAV_CONFIG is off".
     */
    public function testConfigOverrideFallsBackToGetenvWhenServerValueIsEmpty(): void
    {
        $original = $this->config->get('test.grav_config_4279');
        $restoreServer = $this->stashServerVars(['GRAV_CONFIG', 'GRAV_CONFIG__test__grav_config_4279']);
        $hadGetenv = getenv('GRAV_CONFIG');

        $_SERVER['GRAV_CONFIG'] = '';
        $_SERVER['GRAV_CONFIG__test__grav_config_4279'] = 'from-getenv-fallback';
        putenv('GRAV_CONFIG=1');

        try {
            $processor = new InitializeProcessor($this->grav);
            $method = new ReflectionMethod($processor, 'initializeConfig');
            $method->setAccessible(true);
            $method->invoke($processor);

            self::assertSame('from-getenv-fallback', $this->config->get('test.grav_config_4279'));
        } finally {
            putenv($hadGetenv === false ? 'GRAV_CONFIG' : "GRAV_CONFIG={$hadGetenv}");
            $restoreServer();
            $this->config->set('test.grav_config_4279', $original);
        }
    }

    /**
     * Stashes the current value (or absence) of each `$_SERVER` key and
     * returns a closure that restores exactly that state, instead of
     * blindly unsetting keys a real request may already carry.
     *
     * @param list<string> $keys
     * @return callable(): void
     */
    private function stashServerVars(array $keys): callable
    {
        $stash = [];
        foreach ($keys as $key) {
            $stash[$key] = [array_key_exists($key, $_SERVER), $_SERVER[$key] ?? null];
        }

        return static function () use ($stash): void {
            foreach ($stash as $key => [$existed, $value]) {
                if ($existed) {
                    $_SERVER[$key] = $value;
                } else {
                    unset($_SERVER[$key]);
                }
            }
        };
    }
}
