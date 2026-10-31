<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User;

/**
 * Class MultiavatarHashPathTest
 *
 * `avatar_hash` is a free text field of the account blueprint, so the account owner chooses it.
 * UserTrait::generateMultiavatar() names the generated SVG after it: the file has to stay in
 * image://multiavatar whatever the field holds.
 */
class MultiavatarHashPathTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;
    /** @var string */
    protected $storage;
    /** @var string[] */
    protected $files = [];

    protected function setUp(): void
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['config']->set('system.accounts.avatar', 'multiavatar');

        $this->storage = $this->grav['locator']->findResource('image://multiavatar', true, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        // Do not leave the folders behind if the test created them.
        foreach ([$this->storage, dirname($this->storage)] as $folder) {
            if (is_dir($folder) && count(scandir($folder)) === 2) {
                rmdir($folder);
            }
        }
    }

    public function testPlainHashNamesTheFile(): void
    {
        $this->files[] = $file = $this->storage . '/multiavatar-test.svg';

        self::assertStringEndsWith('/multiavatar/multiavatar-test.svg', $this->avatarUrl('multiavatar-test'));
        self::assertFileExists($file);
    }

    public function testMissingHashUsesEmailDigest(): void
    {
        $hash = md5('joe@example.com');
        $this->files[] = $file = $this->storage . '/' . $hash . '.svg';

        self::assertStringEndsWith('/multiavatar/' . $hash . '.svg', $this->avatarUrl(null));
        self::assertFileExists($file);
    }

    /**
     * @dataProvider providerTraversalHashes
     */
    public function testHashCannotLeaveTheFolder(string $hash, int $levels): void
    {
        // Where the unchecked path join would have put the file.
        $outside = dirname($this->storage, $levels) . '/multiavatar-test-outside.svg';
        $this->files[] = $outside;
        $this->files[] = $file = $this->storage . '/' . md5($hash) . '.svg';

        self::assertStringEndsWith('/multiavatar/' . md5($hash) . '.svg', $this->avatarUrl($hash));
        self::assertFileDoesNotExist($outside);
        self::assertFileExists($file);
    }

    public static function providerTraversalHashes(): array
    {
        return [
            'parent folder' => ['../multiavatar-test-outside', 1],
            'grandparent folder' => ['../../multiavatar-test-outside', 2],
        ];
    }

    /**
     * @dataProvider providerUnusableFilenames
     */
    public function testHashWhichIsNotAFilenameDoesNotFail(string $hash): void
    {
        $this->files[] = $file = $this->storage . '/' . md5($hash) . '.svg';

        self::assertStringEndsWith('/multiavatar/' . md5($hash) . '.svg', $this->avatarUrl($hash));
        self::assertFileExists($file);
    }

    public static function providerUnusableFilenames(): array
    {
        return [
            'missing folder' => ['no-such-folder/x'],
            'too long' => [str_repeat('a', 300)],
            'null byte' => ["a\0b"],
            'trailing newline' => ["abc\n"],
            'spaces and punctuation' => ['Joe Bloggs!'],
        ];
    }

    private function avatarUrl(?string $hash): string
    {
        $user = new User(['username' => 'joe', 'email' => 'joe@example.com', 'avatar_hash' => $hash]);

        return $user->getAvatarUrl();
    }
}
