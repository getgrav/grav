<?php

use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User as DataUser;

/**
 * A group's `access` map is granted to every member of the group, so only a
 * super-admin may write it. The guard is the `security@: admin.super` on the
 * field, which Blueprint::dynamicSecurity() turns into `validate.ignore` for
 * everyone else so the field is dropped from the submitted data.
 *
 * Without it an `admin.users` operator could save `admin.super: true` onto a
 * group they belong to and escalate to super-admin (GHSA-xhfv-7758-r9hx).
 */
class GroupBlueprintSecurityTest extends \PHPUnit\Framework\TestCase
{
    public function testSuperAdminMayWriteGroupAccess(): void
    {
        $filtered = $this->filterAs(['admin.super' => true]);

        self::assertArrayHasKey('access', $filtered);
        self::assertTrue($filtered['access']['admin']['super']);
    }

    public function testAccountOperatorCannotWriteGroupAccess(): void
    {
        $filtered = $this->filterAs(['admin.users' => true, 'admin.users.update' => true]);

        self::assertArrayNotHasKey('access', $filtered);
    }

    /**
     * Stripping `access` must not take the rest of the form with it — a
     * delegated operator can still rename a group, they just can't re-permission it.
     */
    public function testAccountOperatorMayStillWriteOtherGroupFields(): void
    {
        $filtered = $this->filterAs(['admin.users' => true, 'admin.users.update' => true]);

        self::assertSame('ops', $filtered['groupname']);
        self::assertSame('Operations', $filtered['readableName']);
    }

    /**
     * Parity with the account form, whose groups/access fields carry the same
     * guard. If this fails the two user blueprints have drifted apart again.
     */
    public function testAccountBlueprintCarriesTheSameGuard(): void
    {
        $account = $this->loadBlueprint('account')->toArray();
        $group = $this->loadBlueprint('group')->toArray();

        self::assertSame(
            'admin.super',
            $account['form']['fields']['security']['fields']['access']['security@'] ?? null
        );
        self::assertSame(
            'admin.super',
            $group['form']['fields']['access']['security@'] ?? null
        );
    }

    /**
     * The guard resolves a rule by the field's own item path, so a FLAT dot-notation
     * key addressed no rule at all and fell through to the loose-mode extra-key
     * branch that keeps it. FlexObject::update() then expanded it again via
     * setNestedProperty(), writing straight into the guarded subtree.
     *
     * (GHSA-mwjj-r7vm-pgqm)
     */
    public function testAccountOperatorCannotWriteGroupAccessViaFlatKey(): void
    {
        $filtered = $this->filterAs(
            ['admin.users' => true, 'admin.users.update' => true],
            ['groupname' => 'ops', 'access.admin.super' => true]
        );

        self::assertArrayNotHasKey('access.admin.super', $filtered);
        self::assertArrayNotHasKey('access', $filtered);
    }

    public function testSuperAdminMayStillWriteGroupAccessViaFlatKey(): void
    {
        $filtered = $this->filterAs(
            ['admin.super' => true],
            ['groupname' => 'ops', 'access.admin.super' => true]
        );

        self::assertTrue($filtered['access.admin.super']);
    }

    /**
     * A dotted key whose ancestors are NOT guarded must survive, or the fix would
     * break every legitimate flat submission.
     */
    public function testUnguardedFlatKeysAreStillKept(): void
    {
        $filtered = $this->filterAs(
            ['admin.users' => true, 'admin.users.update' => true],
            ['groupname' => 'ops', 'readableName.extra' => 'kept']
        );

        self::assertSame('kept', $filtered['readableName.extra']);
    }

    /**
     * @param array<string,bool> $permissions
     * @param array<string,mixed>|null $data
     * @return array<string,mixed>
     */
    private function filterAs(array $permissions, ?array $data = null): array
    {
        $user = new class extends DataUser {
            /** @var array<string,bool> */
            public array $permissions = [];

            public function authorize(string $action, ?string $scope = null): ?bool
            {
                return (bool)($this->permissions[$action] ?? false);
            }
        };
        $user->permissions = $permissions;

        Grav::instance()['user'] = $user;

        $data ??= [
            'groupname' => 'ops',
            'readableName' => 'Operations',
            'access' => ['admin' => ['super' => true, 'login' => true]],
        ];

        return (array)$this->loadBlueprint('group')->filter($data, true, true);
    }

    private function loadBlueprint(string $name): Blueprint
    {
        $blueprint = new Blueprint($name);
        $blueprint->setContext(dirname(__DIR__, 5) . '/system/blueprints/user');
        $blueprint->load()->init();

        return $blueprint;
    }
}
