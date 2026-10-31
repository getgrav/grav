<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Security;
use Grav\Common\Service\SessionServiceProvider;
use Grav\Framework\Session\Session;

/**
 * Session storage is shared by every site the same PHP runs, and the client picks the cookie
 * name it sends an id under, so a stored session has to say which site created it: a session
 * opened on another Grav install (or another subsite) must not be resumed as a logged-in
 * session here. PHP never starts a real session on the command line, so these tests exercise
 * the check start() makes and the mark it leaves instead of calling session_start().
 */
class SessionSiteBindingTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var array|null */
    protected $sessionBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->sessionBefore = $_SESSION ?? null;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if ($this->sessionBefore === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->sessionBefore;
        }
        parent::tearDown();
    }

    public function testSessionOfThisSiteIsResumed(): void
    {
        $session = $this->session('this-site');
        $session->bind();
        $_SESSION['user'] = 'admin';

        self::assertTrue($session->belongsToSite());
    }

    public function testSessionOfAnotherSiteIsRefused(): void
    {
        $this->session('other-site')->bind();
        $_SESSION['user'] = 'admin';

        self::assertFalse($this->session('this-site')->belongsToSite());
    }

    public function testSessionWhichNamesNoSiteIsRefused(): void
    {
        $_SESSION['user'] = 'admin';

        self::assertFalse($this->session('this-site')->belongsToSite(), 'A session from before sessions were bound');
    }

    /**
     * A session PHP has just created is empty too, so an empty one is not sent back for a new id:
     * there is nothing in it to take over.
     */
    public function testEmptySessionIsAsGoodAsNew(): void
    {
        self::assertTrue($this->session('this-site')->belongsToSite());
    }

    public function testForgedSiteValueIsRefused(): void
    {
        $_SESSION['user'] = 'admin';
        $_SESSION['session_site'] = ['this-site'];

        self::assertFalse($this->session('this-site')->belongsToSite());
    }

    public function testClearedSessionStaysBound(): void
    {
        $session = $this->session('this-site');
        $session->bind();
        $session->clear();

        self::assertSame(['session_site' => 'this-site'], $_SESSION);
        self::assertTrue($session->belongsToSite());
    }

    public function testNothingIsCheckedWithoutSite(): void
    {
        $_SESSION['user'] = 'admin';

        self::assertTrue($this->session(null)->belongsToSite());
        $this->session(null)->bind();
        self::assertArrayNotHasKey('session_site', $_SESSION);
    }

    /**
     * The site value comes from the cookie name and the site's private key, so two installs
     * (or two subsites with their own user folder) never produce the same one.
     */
    public function testServiceBindsSessionsToCookieNameAndPrivateKey(): void
    {
        unset($this->grav['session']);
        (new SessionServiceProvider())->register($this->grav);
        $session = $this->grav['session'];

        $property = new ReflectionProperty(Session::class, 'site');
        $property->setAccessible(true);
        $site = $property->getValue($session);

        $name = 'grav-site-' . substr(md5(GRAV_ROOT), 0, 7);
        self::assertSame(hash_hmac('sha256', $name, Security::getNonceKey()), $site);
        self::assertNotSame(hash_hmac('sha256', $name . '-admin', Security::getNonceKey()), $site);
    }

    private function session(?string $site)
    {
        $session = new class extends Session {
            public function belongsToSite(): bool
            {
                return $this->isSiteSession();
            }

            public function bind(): void
            {
                $this->bindToSite();
            }
        };

        return $session->setSite($site);
    }
}
