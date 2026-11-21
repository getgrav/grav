<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;

/**
 * findFirst() and reduce() come from Doctrine's collection interface. A Flex index answers them
 * from its objects like the other closure methods (exists, filter, map), instead of returning null.
 */
class FlexIndexFindFirstReduceTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;
    /** @var array */
    protected $schemes;
    /** @var string */
    protected $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $folder = 'tmp/flex-index-find-first-' . bin2hex(random_bytes(4));
        $this->accounts = GRAV_ROOT . '/' . $folder . '/accounts';
        mkdir($this->accounts, 0775, true);
        mkdir(GRAV_ROOT . '/' . $folder . '/data', 0775, true);
        file_put_contents($this->accounts . '/alice.yaml', "email: alice@example.com\nfullname: Alice\n");
        file_put_contents($this->accounts . '/bob.yaml', "email: bob@example.com\nfullname: Bob\n");
        file_put_contents($this->accounts . '/carol.yaml', "email: carol@example.com\nfullname: Carol\n");

        $locator = $this->grav['locator'];
        $property = new ReflectionProperty($locator, 'schemes');
        $this->schemes = $property->getValue($locator);
        $property->setValue($locator, [
                'account' => ['' => [$folder . '/accounts']],
                'user-data' => ['' => [$folder . '/data']],
            ] + $this->schemes);
        $locator->clearCache();
    }

    protected function tearDown(): void
    {
        $locator = $this->grav['locator'];
        $property = new ReflectionProperty($locator, 'schemes');
        $property->setValue($locator, $this->schemes);
        $locator->clearCache();
        Folder::delete(dirname($this->accounts));
        parent::tearDown();
    }

    public function testFindFirstAndReduceAnswerFromTheObjects(): void
    {
        $directory = new FlexDirectory('user-accounts', 'blueprints://flex/user-accounts.yaml', [
            'enabled' => true,
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false]]],
        ]);
        $index = $directory->getIndex();
        self::assertCount(3, $index);

        $bob = $index->findFirst(static fn($key, $user): bool => $user->getProperty('fullname') === 'Bob');
        self::assertNotNull($bob);
        self::assertSame('bob', $bob->getKey());
        self::assertNull($index->findFirst(static fn(): bool => false));

        $names = $index->reduce(static fn(array $carry, $user) => [...$carry, $user->getProperty('fullname')], []);
        sort($names);
        self::assertSame(['Alice', 'Bob', 'Carol'], $names);
        self::assertSame('start', $index->filter(static fn() => false)->reduce(static fn(): string => 'changed', 'start'));
    }
}
