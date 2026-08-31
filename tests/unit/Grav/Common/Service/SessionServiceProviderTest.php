<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Inflector;
use Grav\Common\Service\SessionServiceProvider;

/**
 * Class SessionServiceProviderTest
 */
class SessionServiceProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var Inflector $inflector */
    protected $inflector;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();
        $this->inflector = $grav['inflector'];
    }

    public function testSecureCookiePrefixIsPreserved(): void
    {
        self::assertSame(
            '__Secure-session-cookie',
            SessionServiceProvider::resolveSessionPrefix($this->inflector, '__Secure-session-cookie')
        );
    }

    public function testHostCookiePrefixIsPreserved(): void
    {
        self::assertSame(
            '__Host-session-cookie',
            SessionServiceProvider::resolveSessionPrefix($this->inflector, '__Host-session-cookie')
        );
    }

    public function testDefaultSessionNameIsUnchanged(): void
    {
        self::assertSame(
            'grav-site',
            SessionServiceProvider::resolveSessionPrefix($this->inflector, 'grav-site')
        );
    }

    public function testInvalidCharactersAreStillHyphenized(): void
    {
        self::assertSame(
            'my-site-cookie',
            SessionServiceProvider::resolveSessionPrefix($this->inflector, 'My Site Cookie')
        );
    }

    /**
     * A prefixed name whose remainder needs cleaning up must keep its prefix -- dropping
     * it loses exactly the protection the name was asking for.
     */
    public function testPrefixSurvivesAnUncleanRemainder(): void
    {
        self::assertSame(
            '__Secure-my-site',
            SessionServiceProvider::resolveSessionPrefix($this->inflector, '__Secure-My Site')
        );
        self::assertSame(
            '__Host-my-site',
            SessionServiceProvider::resolveSessionPrefix($this->inflector, '__Host-my.site')
        );
    }

    /**
     * Dots stay hyphenized. PHP mangles them in `$_COOKIE` keys, so a session named
     * `my.site` could never be resumed and every request would start a new one.
     */
    public function testDotsAreStillHyphenized(): void
    {
        self::assertSame(
            'my-site',
            SessionServiceProvider::resolveSessionPrefix($this->inflector, 'my.site')
        );
    }

    /**
     * `$` matches before a trailing newline, so the token test is anchored with \A..\z.
     */
    public function testTrailingNewlineIsNotTreatedAsValid(): void
    {
        self::assertSame(
            'grav-site',
            SessionServiceProvider::resolveSessionPrefix($this->inflector, "grav-site\n")
        );
    }

    /**
     * Names that are already valid cookie tokens now pass through verbatim rather than
     * being lowercased and hyphenized. This changes the cookie name on upgrade for the
     * sites that use them, which signs those users out once -- an accepted, one-time
     * cost for making the prefixes work at all.
     *
     * @dataProvider verbatimNames
     */
    public function testAlreadyValidNamesArePreservedVerbatim(string $name): void
    {
        self::assertSame($name, SessionServiceProvider::resolveSessionPrefix($this->inflector, $name));
    }

    /** @return array<string, array{string}> */
    public function verbatimNames(): array
    {
        return [
            'uppercase' => ['MySite'],
            'camelCase' => ['myCompanySite'],
            'underscore' => ['my_site'],
            'leading hyphen' => ['-leading'],
            'trailing hyphen' => ['trailing-'],
        ];
    }
}
