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
}
