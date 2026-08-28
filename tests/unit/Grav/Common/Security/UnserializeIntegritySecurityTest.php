<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Security;
use Grav\Common\Scheduler\Job;
use Grav\Common\Scheduler\JobQueue;

/**
 * Class UnserializeIntegritySecurityTest
 *
 * Covers: GHSA-vj3m-2g9h-vm4p (#1 JobQueue, #3 Session) — HMAC integrity
 * around `unserialize(..., ['allowed_classes' => true])` sinks so that a
 * tampered payload cannot smuggle in arbitrary class instantiation.
 *
 * Note: the FileCache half of this advisory (#2) has its own dedicated
 * test in FileCacheSecurityTest.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class UnserializeIntegritySecurityTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $queueDir;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->queueDir = sys_get_temp_dir() . '/grav-jobqueue-sec-' . bin2hex(random_bytes(4));
        @mkdir($this->queueDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->queueDir)) {
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->queueDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iter as $f) {
                $f->isDir() ? @rmdir((string)$f) : @unlink((string)$f);
            }
            @rmdir($this->queueDir);
        }
        parent::tearDown();
    }

    // =========================================================================
    // GHSA-vj3m-2g9h-vm4p (#1): JobQueue serialized_job HMAC integrity
    // =========================================================================

    /**
     * Drive `JobQueue::reconstructJob` with a hand-built queue item; the
     * method is protected, so step through it with reflection.
     */
    private function reconstruct(JobQueue $queue, array $item): ?Job
    {
        $m = (new ReflectionClass($queue))->getMethod('reconstructJob');
        $m->setAccessible(true);
        return $m->invoke($queue, $item);
    }

    public function testReconstructJob_GHSAvj3m_RoundTripsValidHmacSignedJob(): void
    {
        $queue = new JobQueue($this->queueDir);

        $job = new Job('echo', ['ok'], 'job-1');
        $serialized = serialize($job);
        $item = [
            'serialized_job' => base64_encode($serialized),
            'serialized_job_hmac' => hash_hmac('sha256', $serialized, Security::getNonceKey()),
            'job_id' => 'job-1',
        ];

        $reconstructed = $this->reconstruct($queue, $item);
        self::assertInstanceOf(Job::class, $reconstructed);
        self::assertSame('job-1', $reconstructed->getId());
    }

    public function testReconstructJob_GHSAvj3m_RejectsForgedSerializedJob(): void
    {
        $queue = new JobQueue($this->queueDir);

        // Attacker constructs a Job with `command='system'` and signs it with
        // their guessed key. With HMAC verification the forged blob is rejected,
        // and the unsigned `command` field is no longer a rebuild source either,
        // so nothing is reconstructed at all.
        $forgedJob = new Job('system', ['rm -rf /'], 'pwn');
        $forgedSerialized = serialize($forgedJob);
        $item = [
            'serialized_job' => base64_encode($forgedSerialized),
            'serialized_job_hmac' => hash_hmac('sha256', $forgedSerialized, 'attacker-key-guess'),
            'job_id' => 'job-2',
            'command' => 'echo',
            'arguments' => ['safe'],
        ];

        self::assertNull($this->reconstruct($queue, $item), 'forged item must not reconstruct');
    }

    public function testReconstructJob_GHSAvj3m_RejectsItemMissingHmacField(): void
    {
        $queue = new JobQueue($this->queueDir);

        // Pre-fix queue files only carried `serialized_job`. Those entries can no
        // longer trigger unserialize, and the plaintext `command` beside them is
        // not trusted either — an unsigned queue file must not be able to name
        // the callable that runs.
        $forgedJob = new Job('system', ['rm -rf /'], 'pwn');
        $item = [
            'serialized_job' => base64_encode(serialize($forgedJob)),
            'job_id' => 'job-3',
            'command' => 'echo',
            'arguments' => ['safe'],
        ];

        self::assertNull($this->reconstruct($queue, $item));
    }

    /**
     * A queue file with no signed payload at all, naming a dangerous PHP callable
     * in `command`, used to be rebuilt verbatim into `new Job('system', [...])` and
     * reach call_user_func_array() — the same gadget the HMAC was added to close.
     */
    public function testReconstructJob_GHSAfjcp_UnsignedDangerousCommandIsRefused(): void
    {
        $queue = new JobQueue($this->queueDir);

        $item = [
            'id' => 'x',
            'job_id' => 'x',
            'command' => 'system',
            'arguments' => ['id > /tmp/pwned'],
        ];

        self::assertNull($this->reconstruct($queue, $item), 'unsigned command must never become a Job');
    }

    public function testReconstructJob_GHSAvj3m_ReturnsNullOnFullyTamperedItem(): void
    {
        $queue = new JobQueue($this->queueDir);

        // No HMAC, no fallback fields — nothing to safely reconstruct from.
        $item = [
            'serialized_job' => base64_encode(serialize(new Job('system', ['x'], 'p'))),
            'job_id' => 'job-4',
        ];

        self::assertNull($this->reconstruct($queue, $item));
    }

    // =========================================================================
    // GHSA-vj3m-2g9h-vm4p (#3): Session::getFlashObject HMAC integrity
    // =========================================================================
    //
    // We can't easily exercise Session through its full PHP-session lifecycle
    // in a unit test, so we verify the wire format directly: setFlashObject
    // produces a `v2|<hmac>|<serialized>` envelope, and getFlashObject only
    // accepts payloads whose HMAC verifies against Security::getNonceKey().

    public function testSetFlashObject_GHSAvj3m_WrapsPayloadWithVersionedHmacEnvelope(): void
    {
        $session = $this->newSessionStub();
        $session->setFlashObject('payload', ['hello' => 'world']);

        $stored = $session->_storage['payload'] ?? null;
        self::assertIsString($stored);
        self::assertStringStartsWith('v2|', $stored);

        $parts = explode('|', $stored, 3);
        self::assertCount(3, $parts);
        [, $hmac, $serialized] = $parts;
        self::assertSame(
            hash_hmac('sha256', $serialized, Security::getNonceKey()),
            $hmac,
            'envelope HMAC must match Security::getNonceKey()'
        );
        self::assertSame(['hello' => 'world'], unserialize($serialized, ['allowed_classes' => false]));
    }

    public function testGetFlashObject_GHSAvj3m_RoundTripsValidValue(): void
    {
        $session = $this->newSessionStub();
        $session->setFlashObject('payload', ['hello' => 'world']);

        self::assertSame(['hello' => 'world'], $session->getFlashObject('payload'));
    }

    public function testGetFlashObject_GHSAvj3m_RejectsLegacyUnsignedPayload(): void
    {
        $session = $this->newSessionStub();
        // Pre-fix wire format: a bare serialize() blob with no envelope.
        $session->_storage['payload'] = serialize(['hello' => 'world']);

        self::assertNull($session->getFlashObject('payload'), 'legacy unsigned payload must not be unserialized');
    }

    public function testGetFlashObject_GHSAvj3m_RejectsForgedHmac(): void
    {
        $session = $this->newSessionStub();
        $serialized = serialize(['attacker' => 'payload']);
        $forgedHmac = hash_hmac('sha256', $serialized, 'wrong-key');
        $session->_storage['payload'] = "v2|{$forgedHmac}|{$serialized}";

        self::assertNull($session->getFlashObject('payload'), 'forged HMAC must be rejected');
    }

    /**
     * Minimal stub that mimics the bits of Grav\Common\Session that
     * setFlashObject/getFlashObject touch (just `__get`/`__set`/`__unset`
     * over an in-memory array). Avoids booting PHP session machinery.
     */
    private function newSessionStub(): object
    {
        return new class extends \Grav\Common\Session {
            public array $_storage = [];

            public function __construct() {}

            public function __set($name, $value): void
            {
                $this->_storage[$name] = $value;
            }

            public function __get($name)
            {
                return $this->_storage[$name] ?? null;
            }

            public function __unset($name): void
            {
                unset($this->_storage[$name]);
            }

            public function __isset($name): bool
            {
                return isset($this->_storage[$name]);
            }
        };
    }
}
