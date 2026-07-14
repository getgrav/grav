<?php

use Grav\Common\Data\Blueprint;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Guards the `data-*@` dynamic-field directive against arbitrary callable
 * execution. Two dispatch paths resolve the same directive and must enforce
 * the same guard:
 *   - Blueprint::dynamicData()            (GHSA-fj2p-qj2f-74v5)
 *   - FlexDirectory::dynamicDataField()   (GHSA-c4wf-2xxc-68qm, the bypass)
 *
 * Both now delegate to Blueprint::isSafeDynamicCall().
 */
class DynamicDataCallGuardTest extends \PHPUnit\Framework\TestCase
{
    /** @var array<string,bool> Baseline allowlist, restored after each test. */
    private $allowlistSnapshot = [];

    /**
     * The allowlist is private static and persists across tests, so a test that
     * registers a provider must not leak it into the next test's expectations.
     * Snapshot before, restore after.
     */
    protected function setUp(): void
    {
        $prop = new ReflectionProperty(Blueprint::class, 'allowedDynamicCallables');
        $prop->setAccessible(true);
        $this->allowlistSnapshot = $prop->getValue();
    }

    protected function tearDown(): void
    {
        $prop = new ReflectionProperty(Blueprint::class, 'allowedDynamicCallables');
        $prop->setAccessible(true);
        $prop->setValue(null, $this->allowlistSnapshot);
    }

    /** A legitimate provider: a static method returning an option array. */
    public static function provideOptions(): array
    {
        return ['one' => 'One', 'two' => 'Two'];
    }

    public function testGuardRejectsCommandExecutionFunctions(): void
    {
        self::assertFalse(Blueprint::isSafeDynamicCall('exec', ['id']));
        self::assertFalse(Blueprint::isSafeDynamicCall('system', ['id']));
        self::assertFalse(Blueprint::isSafeDynamicCall('passthru', ['id']));
        self::assertFalse(Blueprint::isSafeDynamicCall('shell_exec', ['id']));
        self::assertFalse(Blueprint::isSafeDynamicCall('proc_open', ['id']));
    }

    public function testGuardRejectsTrampolineCallableSmuggledAsParam(): void
    {
        // The gadget the original GHSA exploited: a benign helper handed a
        // dangerous callable to invoke on attacker input.
        self::assertFalse(
            Blueprint::isSafeDynamicCall('\Grav\Common\Utils::arrayFilterRecursive', [['x'], 'system'])
        );
        // ...including when nested deeper in the argument tree.
        self::assertFalse(
            Blueprint::isSafeDynamicCall('array_map', [['nested' => ['exec']]])
        );
    }

    public function testGuardAllowsAllowlistedStaticProvider(): void
    {
        // Only `Class::method` providers on the allowlist are permitted
        // (GHSA-7pgq-cr25-xvc8, GHSA-cxv3-5jj3-cpgr).
        self::assertTrue(
            Blueprint::isSafeDynamicCall('\Grav\Common\Page\Pages::pageTypes', ['standard'])
        );
    }

    public function testGuardRejectsArbitraryUnlistedStaticProvider(): void
    {
        // An arbitrary public static method not on the allowlist is refused,
        // even though it is a perfectly valid callable — this is the bypass the
        // fix closes (GHSA-7pgq-cr25-xvc8, GHSA-cxv3-5jj3-cpgr).
        self::assertFalse(
            Blueprint::isSafeDynamicCall(self::class . '::provideOptions', [])
        );
        self::assertFalse(
            Blueprint::isSafeDynamicCall('\Grav\Common\Utils::download', ['user/config/security.yaml'])
        );
    }

    public function testAllowlistRegistrationPermitsPluginProvider(): void
    {
        $provider = self::class . '::provideOptions';
        self::assertFalse(Blueprint::isSafeDynamicCall($provider, []));

        // Plugins opt their own raw `Class::method` provider in once at init.
        Blueprint::addAllowedDynamicCallable($provider);
        self::assertTrue(Blueprint::isSafeDynamicCall($provider, []));
    }

    /**
     * Reproduction of the GHSA-c4wf-2xxc-68qm PoC (Part 1): the real,
     * unmodified FlexDirectory::dynamicDataField() must refuse to run exec.
     * Before the fix this wrote the proof file; it must no longer.
     */
    public function testFlexDirectoryDataFieldRefusesExec(): void
    {
        $proofFile = sys_get_temp_dir() . '/grav_rce_proof_' . getmypid() . '.txt';
        @unlink($proofFile);

        $field = ['myfield' => ['type' => 'text']];
        $call = [
            'action' => 'data',
            'params' => ['exec', "id > $proofFile 2>&1"],
            'object' => null,
        ];

        $this->invokeDataField($field, 'myfield', $call);

        self::assertFileDoesNotExist($proofFile, 'dynamicDataField executed exec()');
        // The field must be left untouched when the call is refused.
        self::assertSame(['type' => 'text'], $field['myfield']);

        @unlink($proofFile);
    }

    /**
     * A legitimate provider still resolves through the Flex path.
     */
    public function testFlexDirectoryDataFieldAllowsLegitimateProvider(): void
    {
        // An allowlisted provider still resolves through the Flex dispatch path.
        Blueprint::addAllowedDynamicCallable(self::class . '::provideOptions');

        $field = ['myfield' => ['type' => 'select']];
        $call = [
            'action' => 'data',
            'params' => [self::class . '::provideOptions'],
            'object' => null,
        ];

        $this->invokeDataField($field, 'myfield', $call);

        // The provider's options are merged into the existing field (+=).
        self::assertSame(['type' => 'select'] + self::provideOptions(), $field['myfield']);
    }

    /**
     * Invoke the real, unmodified protected FlexDirectory::dynamicDataField()
     * without booting a full directory, mirroring the advisory PoC.
     *
     * @param array $field
     * @param string $property
     * @param array $call
     * @return void
     */
    private function invokeDataField(array &$field, string $property, array $call): void
    {
        $ref = new ReflectionClass(FlexDirectory::class);
        $instance = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('dynamicDataField');
        $method->setAccessible(true);
        $method->invokeArgs($instance, [&$field, $property, $call]);
    }
}
