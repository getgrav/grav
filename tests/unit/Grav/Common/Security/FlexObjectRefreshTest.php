<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;

/**
 * Class FlexObjectRefreshTest
 *
 * FlexObject::refresh() reads the object again from the storage. Some properties are loaded from the stored
 * elements into a member variable when they are first used: `access` of an account or a group, `header` of a
 * page. A value which was loaded before the refresh must not stay in place of the one which was just read, or
 * the object answers with the old value and a save after the refresh writes it back, undoing the change which
 * the refresh was called to pick up. See FlexUserSaveLostUpdateTest for accounts.
 */
class FlexObjectRefreshTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    private $folder;

    protected function setUp(): void
    {
        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav();

        // Storages resolve a plain folder against GRAV_ROOT.
        $this->folder = 'tmp/flex-object-refresh-' . bin2hex(random_bytes(4));
        mkdir(GRAV_ROOT . '/' . $this->folder . '/pages/blog', 0777, true);
    }

    protected function tearDown(): void
    {
        Folder::delete(GRAV_ROOT . '/' . $this->folder);
    }

    /** The directory as a new request sees it. */
    private function directory(string $type, array $storage): FlexDirectory
    {
        return new FlexDirectory($type, "blueprints://flex/{$type}.yaml", [
            'enabled' => true,
            'data' => ['storage' => ['options' => $storage]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false]]],
        ]);
    }

    /** An object which was read from the storage in an earlier request, as the Flex cache hands it out. */
    private function loadedEarlier(FlexDirectory $directory, string $key): FlexObjectInterface
    {
        $row = $directory->getStorage()->readRows([$key => null])[$key];
        self::assertIsArray($row);

        return $this->directory($directory->getFlexType(), $directory->getConfig('data.storage.options'))
            ->createObjectFromStorage($row, $key);
    }

    public static function providerKeepMissing(): array
    {
        return ['read again' => [false], 'keep missing' => [true]];
    }

    /**
     * @dataProvider providerKeepMissing
     */
    public function testGroupAccessWhichWasAlreadyReadIsReplaced(bool $keepMissing): void
    {
        $file = GRAV_ROOT . '/' . $this->folder . '/groups.yaml';
        file_put_contents($file, "editors:\n  readableName: Editors\n  access:\n    admin:\n      super: true\n");
        // Flex compares the modification time in whole seconds.
        touch($file, time() - 10);
        clearstatcache();

        $directory = $this->directory('user-groups', ['folder' => $this->folder . '/groups.yaml']);
        $demoted = ['site' => ['login' => true]];

        $stale = $this->loadedEarlier($directory, 'editors');
        self::assertTrue($stale->authorize('admin.super', 'test'));

        $admin = $directory->getIndex()->get('editors');
        $admin->setProperty('access', $demoted);
        $admin->save();
        clearstatcache();

        self::assertTrue($stale->refresh($keepMissing));
        // Answered by the access which was read again, not by the one the first check was answered from.
        self::assertNull($stale->authorize('admin.super', 'test'));
        self::assertTrue($stale->authorize('site.login', 'test'));
        self::assertSame($demoted, $stale->getProperty('access'));

        $stale->setProperty('description', 'Saved after refresh');
        $stale->save();
        $stored = $this->directory('user-groups', ['folder' => $this->folder . '/groups.yaml'])
            ->getStorage()->readRows(['editors' => null])['editors'];
        self::assertSame('Saved after refresh', $stored['description']);
        self::assertSame($demoted, $stored['access']);
    }

    public function testPageHeaderWhichWasAlreadyReadIsReplaced(): void
    {
        $file = GRAV_ROOT . '/' . $this->folder . '/pages/blog/default.md';
        file_put_contents($file, "---\ntitle: Members\naccess:\n  site.members: true\n---\nFor members\n");
        touch($file, time() - 10);
        touch(dirname($file), time() - 10);
        clearstatcache();

        $directory = $this->directory('pages', ['folder' => $this->folder . '/pages', 'indexed' => false]);

        $stale = $this->loadedEarlier($directory, 'blog');
        self::assertSame(['site.members' => true], $stale->header()->get('access'));

        file_put_contents($file, "---\ntitle: Staff\naccess:\n  site.staff: true\n---\nFor staff\n");
        clearstatcache();

        self::assertTrue($stale->refresh());
        self::assertSame("For staff\n", $stale->getProperty('markdown'));
        self::assertSame('Staff', $stale->header()->get('title'));
        self::assertSame(['site.staff' => true], $stale->header()->get('access'));
    }
}
