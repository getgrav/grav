<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User;
use Grav\Common\User\DataUser\UserCollection;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class UserCollectionCountTest
 *
 * "No accounts yet" (`count() === 0`) is what opens the first-run setup, so the
 * count must be right wherever Grav is installed. Building a glob() pattern from
 * the absolute accounts path made it 0 under any folder with a `[...]` pair or a
 * backslash in its name, with every account still on disk.
 */
class UserCollectionCountTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $root;

    /** @var array */
    protected $savedSchemes;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/grav-count-' . bin2hex(random_bytes(4));

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $schemes = new ReflectionProperty($locator, 'schemes');
        $schemes->setAccessible(true);
        $this->savedSchemes = $schemes->getValue($locator);
    }

    protected function tearDown(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $schemes = new ReflectionProperty($locator, 'schemes');
        $schemes->setAccessible(true);
        $schemes->setValue($locator, $this->savedSchemes);
        $locator->clearCache();

        Folder::delete($this->root);
        parent::tearDown();
    }

    /**
     * @dataProvider providerInstallFolders
     */
    public function testCountDoesNotDependOnTheInstallFolder(string $name): void
    {
        if (DIRECTORY_SEPARATOR === '\\' && strpbrk($name, '?*') !== false) {
            self::markTestSkipped('Windows does not allow ? or * in a folder name.');
        }
        $folder = "{$this->root}/{$name}/user/accounts";
        mkdir($folder . '/avatars', 0777, true);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('account', '', $folder);
        self::assertSame($folder, $locator->findResource('account://'), 'the test folder resolves first');

        $accounts = new UserCollection(User::class);

        self::assertSame(0, $accounts->count());

        // Only `*.yaml` files count, and dot files are skipped as glob() skipped them.
        file_put_contents($folder . '/.hidden.yaml', '');
        file_put_contents($folder . '/notes.txt', '');
        file_put_contents($folder . '/admin.yaml', "email: admin@example.com\n");
        file_put_contents($folder . '/editor.yaml', "email: editor@example.com\n");

        self::assertSame(2, $accounts->count());
    }

    public static function providerInstallFolders(): array
    {
        return [
            'plain' => ['grav'],
            'bracket pair' => ['grav[staging]'],
            'bracket digits' => ['grav[1]'],
            'question mark' => ['what?'],
            'star' => ['all*'],
        ];
    }
}
