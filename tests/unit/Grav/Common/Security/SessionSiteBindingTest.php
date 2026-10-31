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
     * Sessions stored before the update carry no site value; for one session lifetime after the
     * update they are resumed (and bound on start), so the update signs nobody out.
     */
    public function testUnboundSessionIsResumedDuringChangeover(): void
    {
        $_SESSION['user'] = 'admin';

        self::assertTrue($this->session('this-site', time() + 60)->belongsToSite());
    }

    public function testUnboundSessionIsRefusedAfterChangeover(): void
    {
        $_SESSION['user'] = 'admin';

        self::assertFalse($this->session('this-site', time() - 1)->belongsToSite());
    }

    public function testSessionOfAnotherSiteIsRefusedDuringChangeover(): void
    {
        $this->session('other-site')->bind();
        $_SESSION['user'] = 'admin';

        self::assertFalse($this->session('this-site', time() + 60)->belongsToSite());
    }

    public function testForgedSiteValueIsRefusedDuringChangeover(): void
    {
        $_SESSION['user'] = 'admin';
        $_SESSION['session_site'] = null;

        self::assertFalse($this->session('this-site', time() + 60)->belongsToSite());
    }

    /**
     * The changeover starts when the first request after the update records it, and lasts one
     * session lifetime, kept between one hour and 30 days.
     */
    public function testChangeoverIsRecordedOnceAndLastsOneSessionLifetime(): void
    {
        $folder = $this->grav['locator']->findResource('user-data://', true, true);
        $file = $folder . '/session-binding.json';
        $existing = is_file($file) ? file_get_contents($file) : null;
        @unlink($file);

        try {
            $before = time();
            $until = SessionServiceProvider::unboundSessionsUntil(1800);
            $since = json_decode((string)file_get_contents($file), true)['since'];

            self::assertGreaterThanOrEqual($before, $since);
            self::assertSame($since + 3600, $until);
            self::assertSame($since + 7200, SessionServiceProvider::unboundSessionsUntil(7200));
            self::assertSame($since + 30 * 86400, SessionServiceProvider::unboundSessionsUntil(9999999999));
        } finally {
            if ($existing === null) {
                @unlink($file);
            } else {
                file_put_contents($file, $existing);
            }
        }
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

    private function session(?string $site, int $unboundUntil = 0)
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

        return $session->setSite($site, $unboundUntil);
    }
}
