<?php

use Codeception\Util\Fixtures;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Grav\Common\Filesystem\Folder;
use Grav\Framework\Collection\FileCollection;

/**
 * FileCollection::matching() filters the files it lists (the filter sees each SplFileInfo, so
 * fields are its getters such as extension) and sorts the listed entries; the ordering is read through
 * Doctrine's Criteria::orderings() (getOrderings() is deprecated).
 */
class FileCollectionMatchingTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();
        $this->dir = sys_get_temp_dir() . '/grav-file-collection-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/sub', 0777, true);
        foreach (['b.txt' => 200, 'a.txt' => 300, 'c.md' => 100] as $file => $age) {
            file_put_contents("{$this->dir}/{$file}", $file);
            touch("{$this->dir}/{$file}", time() - $age);
        }
    }

    protected function tearDown(): void
    {
        Folder::delete($this->dir);
        parent::tearDown();
    }

    public function testFilterAndSortInBothDirections(): void
    {
        $collection = new FileCollection($this->dir, FileCollection::INCLUDE_FILES);

        $asc = $collection->matching(Criteria::create()->orderBy(['key' => Order::Ascending]));
        self::assertSame(['a.txt', 'b.txt', 'c.md'], array_column(array_map(static fn($o) => (array) $o, array_values($asc->toArray())), 'key'));

        $desc = $collection->matching(Criteria::create()->orderBy(['mtime' => Order::Descending]));
        self::assertSame(['c.md', 'b.txt', 'a.txt'], array_column(array_map(static fn($o) => (array) $o, array_values($desc->toArray())), 'key'), 'Newest first');

        $text = $collection->matching(Criteria::create()->where(Criteria::expr()->eq('extension', 'txt'))->orderBy(['key' => Order::Descending]));
        self::assertSame(['b.txt', 'a.txt'], array_column(array_map(static fn($o) => (array) $o, array_values($text->toArray())), 'key'));
    }
}
