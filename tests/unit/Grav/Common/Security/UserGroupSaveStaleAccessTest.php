<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\UserGroups\UserGroupObject;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User;
use Grav\Common\Yaml;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Class UserGroupSaveStaleAccessTest
 *
 * `access` and `enabled` of a group are written by super admins only. A group object which somebody else saves was
 * read at the start of their request, and a super admin may have changed those fields since: the save must not
 * write the values back as they were read. See SimpleStorageLostUpdateTest for the other rows of the file.
 */
class UserGroupSaveStaleAccessTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    private $grav;
    /** @var string */
    private $folder;
    /** @var mixed */
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->user = $this->grav['user'] ?? null;

        // Storage resolves a plain folder against GRAV_ROOT.
        $this->folder = 'tmp/user-group-stale-access-' . bin2hex(random_bytes(4));
        mkdir(GRAV_ROOT . '/' . $this->folder, 0777, true);
    }

    protected function tearDown(): void
    {
        unset($this->grav['user']);
        if ($this->user) {
            $this->grav['user'] = $this->user;
        }
        Folder::delete(GRAV_ROOT . '/' . $this->folder);

        parent::tearDown();
    }

    /** The groups directory as a new request sees it. */
    private function directory(): FlexDirectory
    {
        return new FlexDirectory('user-groups', 'blueprints://flex/user-groups.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->folder . '/groups.yaml']]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false]]],
        ]);
    }

    private function put(array $content): void
    {
        $file = GRAV_ROOT . '/' . $this->folder . '/groups.yaml';
        file_put_contents($file, Yaml::dump($content));
        // Flex compares the modification time in whole seconds.
        touch($file, time() - 10);
        clearstatcache();
    }

    private function stored(): array
    {
        return (array)Yaml::parse((string)file_get_contents(GRAV_ROOT . '/' . $this->folder . '/groups.yaml'));
    }

    private function signIn(array $access): void
    {
        $user = new User(['username' => 'manager', 'state' => 'enabled', 'access' => $access]);
        $user->authenticated = true;
        unset($this->grav['user']);
        $this->grav['user'] = $user;
    }

    private function load(): UserGroupObject
    {
        /** @var UserGroupObject $group */
        $group = $this->directory()->getObject('staff');
        // The access is read before the change, as for an authorization check.
        $group->authorize('api.pages.write', 'test');

        return $group;
    }

    public function testStaleSaveByManagerKeepsTheStoredAccess(): void
    {
        $this->put(['staff' => ['readableName' => 'Staff', 'enabled' => true, 'access' => ['api' => ['pages' => ['write' => true]]]]]);
        $this->signIn(['admin' => ['users' => true]]);

        $stale = $this->load();

        // A super admin revokes the group's access and switches it off.
        $this->put(['staff' => ['readableName' => 'Staff', 'enabled' => false, 'access' => ['site' => ['login' => true]]]]);

        $stale->setProperty('readableName', 'Staff members');
        $stale->save();

        $stored = $this->stored()['staff'];
        self::assertSame('Staff members', $stored['readableName']);
        self::assertSame(['site' => ['login' => true]], $stored['access']);
        self::assertFalse($stored['enabled']);
        self::assertNull($stale->authorize('api.pages.write', 'test'));
    }

    public function testStaleSaveByManagerDoesNotBringBackRemovedAccess(): void
    {
        $this->put(['staff' => ['readableName' => 'Staff', 'access' => ['api' => ['pages' => ['write' => true]]]]]);
        $this->signIn(['admin' => ['users' => true]]);

        $stale = $this->load();

        $this->put(['staff' => ['readableName' => 'Staff']]);

        $stale->setProperty('readableName', 'Staff members');
        $stale->save();

        $stored = $this->stored()['staff'];
        self::assertSame('Staff members', $stored['readableName']);
        self::assertEmpty($stored['access'] ?? null);
        self::assertNull($stale->authorize('api.pages.write', 'test'));
    }

    public function testSuperAdminSavesTheAccessItSets(): void
    {
        $this->put(['staff' => ['readableName' => 'Staff', 'access' => ['site' => ['login' => true]]]]);
        $this->signIn(['admin' => ['super' => true]]);

        $group = $this->load();
        $group->setProperty('access', ['api' => ['pages' => ['write' => true]]]);
        $group->save();

        self::assertSame(['api' => ['pages' => ['write' => true]]], $this->stored()['staff']['access']);
    }

    public function testSaveWithoutSignedInUserIsLeftAlone(): void
    {
        // Code which saves a group outside of a signed-in request (CLI, scheduler, plugins).
        $this->put(['staff' => ['readableName' => 'Staff', 'access' => ['site' => ['login' => true]]]]);
        unset($this->grav['user']);

        $group = $this->load();
        $group->setProperty('access', ['api' => ['pages' => ['write' => true]]]);
        $group->save();

        self::assertSame(['api' => ['pages' => ['write' => true]]], $this->stored()['staff']['access']);
    }
}
