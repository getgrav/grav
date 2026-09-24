<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Service\SessionServiceProvider;
use Grav\Common\Session;
use Grav\Common\Utils;
use Grav\Framework\Session\Messages;

/**
 * With system.session.lazy a visitor without a session cookie gets no session, and so no session
 * cookie, until something is stored in it. PHP never starts a real session on the command line,
 * so these tests count starts through a Session that records them instead of calling
 * session_start().
 */
class SessionLazyTest extends \PHPUnit\Framework\TestCase
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
        unset($_SESSION);
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

    public function testVisitorWithoutCookieGetsNoSessionUntilAWrite(): void
    {
        $session = $this->session(true, false);
        $session->init();

        self::assertSame(0, $session->starts);
        self::assertTrue($session->isPending());
        self::assertTrue($session->isStarted(), 'The session counts as usable');

        // Reading and removing need no session.
        self::assertNull($session->redirect_after_login);
        self::assertFalse(isset($session->user));
        self::assertSame([], $session->getAll());
        unset($session->messages);
        $session->getFlashObject('files-upload');
        self::assertSame(0, $session->starts);

        $session->cart = ['sku' => 1];
        self::assertSame(1, $session->starts, 'The first write starts the session');
        self::assertFalse($session->isPending());
        self::assertSame(['sku' => 1], $session->cart);

        $session->cart = ['sku' => 2];
        self::assertSame(1, $session->starts);
    }

    public function testVisitorWithCookieStartsAtOnce(): void
    {
        $session = $this->session(true, true);
        $session->init();

        self::assertSame(1, $session->starts);
        self::assertFalse($session->isPending());
    }

    public function testOffStartsAtOnce(): void
    {
        $session = $this->session(false, false);
        $session->init();

        self::assertSame(1, $session->starts);
        self::assertFalse($session->isPending());
    }

    public function testLogoutOfAPendingSessionStartsNothing(): void
    {
        $session = $this->session(true, false);
        $session->init();

        $session->invalidate();
        $session->close();

        self::assertSame(0, $session->starts);
        self::assertFalse($session->isStarted());
    }

    public function testLoginStartsTheSessionBeforeRotatingItsId(): void
    {
        $session = $this->session(true, false);
        $session->init();

        $session->regenerateId();

        self::assertSame(1, $session->starts);
    }

    public function testAskingForTheIdStartsTheSession(): void
    {
        $session = $this->session(true, false);
        $session->init();

        $session->getId();

        self::assertSame(1, $session->starts, 'Form uploads and per-session caches are keyed by the id');
    }

    public function testMessagesStartTheSessionOnlyWhenOneIsAdded(): void
    {
        $session = $this->session(true, false);
        $session->init();
        unset($this->grav['session'], $this->grav['messages']);
        $this->grav['session'] = $session;
        (new SessionServiceProvider())->register($this->grav);
        $this->grav['session'] = $session;

        /** @var Messages $messages */
        $messages = $this->grav['messages'];
        self::assertSame([], $messages->all());
        self::assertSame(0, $session->starts, 'Showing no messages keeps the session waiting');

        $messages->add('Saved', 'info');
        self::assertSame(1, $session->starts);
        self::assertSame($messages, $session->messages, 'The messages are kept in the session');
        self::assertCount(1, $session->messages->all());
    }

    public function testMessagesAreNotSerializedWithTheirCallback(): void
    {
        $messages = (new Messages())->onFirstAdd(static function () {});
        $messages->add('One');

        $copy = unserialize(serialize($messages));
        self::assertInstanceOf(Messages::class, $copy);
        self::assertCount(1, $copy->all());
    }

    public function testNonceStartsThePendingSession(): void
    {
        $session = $this->session(true, false);
        $session->init();
        unset($this->grav['session']);
        $this->grav['session'] = $session;

        Utils::getNonce('form');

        self::assertSame(1, $session->starts, 'A nonce is tied to the session');
    }

    private function session(bool $lazy, bool $cookie): Session
    {
        $session = new class($cookie) extends Session {
            public int $starts = 0;
            private bool $cookie;

            public function __construct(bool $cookie)
            {
                parent::__construct([]);
                $this->cookie = $cookie;
            }

            protected function startNow(): void
            {
                $this->starts++;
                $this->started = true;
                $_SESSION ??= [];
            }

            protected function hasSessionCookie(): bool
            {
                return $this->cookie;
            }
        };

        return $session->setAutoStart(true)->setLazy($lazy);
    }
}
