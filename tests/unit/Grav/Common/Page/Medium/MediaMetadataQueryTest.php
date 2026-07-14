<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\Medium;

/**
 * Covers the metadata-aware query methods on AbstractMedia — filterBy/where/
 * findBy/sortBy/groupBy/withMeta — plus the hasMeta/metaKeys accessors on the
 * medium. Fixtures live in tests/fake/media-metadata: photo1-3 carry
 * `.meta.yaml` sidecars (rating/copyright/tags), photo4 has none.
 */
class MediaMetadataQueryTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var Media */
    protected $media;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->media = new Media(GRAV_ROOT . '/tests/fake/media-metadata');
    }

    /** @param iterable $collection @return string[] */
    private function names($collection): array
    {
        $names = [];
        foreach ($collection as $name => $_) {
            $names[] = $name;
        }
        sort($names);

        return $names;
    }

    public function testCollectionLoadsAllFixtures(): void
    {
        $this->assertSame(
            ['photo1.jpg', 'photo2.jpg', 'photo3.jpg', 'photo4.jpg'],
            $this->names($this->media->all())
        );
    }

    public function testFilterByNumericComparison(): void
    {
        $this->assertSame(['photo1.jpg', 'photo2.jpg'], $this->names($this->media->filterBy('rating', 3, '>=')));
        $this->assertSame(['photo1.jpg'], $this->names($this->media->filterBy('rating', 5, '==')));
        // photo4 has no rating, so it is absent from every comparison result.
        $this->assertSame(['photo3.jpg'], $this->names($this->media->filterBy('rating', 3, '<')));
        $this->assertSame(['photo1.jpg', 'photo2.jpg', 'photo3.jpg'], $this->names($this->media->filterBy('rating', 0, '>=')));
    }

    public function testMissingFieldNeverMatches(): void
    {
        // A medium without the queried field is excluded from filter results,
        // including inequality — you cannot compare an absent value.
        // Only John Smith's photo2: photo1/photo3 are Jane Doe, photo4 absent.
        $this->assertSame(['photo2.jpg'], $this->names($this->media->filterBy('copyright', 'Jane Doe', '!=')));
    }

    public function testFilterByStringEquality(): void
    {
        $this->assertSame(['photo1.jpg', 'photo3.jpg'], $this->names($this->media->filterBy('copyright', 'Jane Doe')));
        $this->assertSame([], $this->names($this->media->filterBy('copyright', 'Nobody')));
    }

    public function testFilterByListOperators(): void
    {
        // contains: the tags list holds the value.
        $this->assertSame(['photo1.jpg', 'photo2.jpg'], $this->names($this->media->filterBy('tags', 'sunset', 'contains')));
        // in: the tags list intersects the given set.
        $this->assertSame(['photo2.jpg', 'photo3.jpg'], $this->names($this->media->filterBy('tags', ['city', 'mountain'], 'in')));
    }

    public function testFilterByRejectsUnknownOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->media->filterBy('rating', 3, '=~');
    }

    public function testWhereAndsCriteria(): void
    {
        $this->assertSame(
            ['photo1.jpg'],
            $this->names($this->media->where(['copyright' => 'Jane Doe', 'rating' => 5]))
        );
        // Array value becomes an `in` set.
        $this->assertSame(
            ['photo1.jpg', 'photo3.jpg'],
            $this->names($this->media->where(['copyright' => 'Jane Doe', 'rating' => [1, 5]]))
        );
    }

    public function testChainingReturnsCollection(): void
    {
        $result = $this->media->filterBy('rating', 3, '>=')->filterBy('tags', 'sunset', 'contains');
        $this->assertInstanceOf(Media::class, $result);
        $this->assertSame(['photo1.jpg', 'photo2.jpg'], $this->names($result));
    }

    public function testFindByReturnsFirstMatchOrNull(): void
    {
        $found = $this->media->findBy('rating', 5, '>=');
        $this->assertInstanceOf(Medium::class, $found);
        $this->assertSame('photo1.jpg', $found->filename);
        $this->assertNull($this->media->findBy('rating', 99, '>='));
    }

    public function testSortByOrdersAndSinksEmptiesLast(): void
    {
        // photo4 has no rating and must land last in both directions.
        $desc = array_keys(iterator_to_array($this->media->sortBy('rating', 'desc')));
        $this->assertSame(['photo1.jpg', 'photo2.jpg', 'photo3.jpg', 'photo4.jpg'], $desc);

        $asc = array_keys(iterator_to_array($this->media->sortBy('rating', 'asc')));
        $this->assertSame(['photo3.jpg', 'photo2.jpg', 'photo1.jpg', 'photo4.jpg'], $asc);
    }

    public function testGroupByScalarField(): void
    {
        $groups = $this->media->groupBy('copyright');
        $this->assertSame(['photo1.jpg', 'photo3.jpg'], $this->names($groups['Jane Doe']));
        $this->assertSame(['photo2.jpg'], $this->names($groups['John Smith']));
        // photo4 (no copyright) groups under the empty-string key.
        $this->assertSame(['photo4.jpg'], $this->names($groups['']));
    }

    public function testGroupByMultiValuedField(): void
    {
        $groups = $this->media->groupBy('tags');
        // An image tagged with several values appears under each.
        $this->assertSame(['photo1.jpg', 'photo2.jpg'], $this->names($groups['sunset']));
        $this->assertSame(['photo1.jpg'], $this->names($groups['beach']));
        $this->assertSame(['photo2.jpg'], $this->names($groups['mountain']));
    }

    public function testWithMetaDropsBareFiles(): void
    {
        $this->assertSame(['photo1.jpg', 'photo2.jpg', 'photo3.jpg'], $this->names($this->media->withMeta()));
    }

    public function testHasMetaAndMetaKeys(): void
    {
        $withMeta = $this->media->get('photo1.jpg');
        $this->assertTrue($withMeta->hasMeta());
        $this->assertSame(['rating', 'copyright', 'tags'], $withMeta->metaKeys());

        $bare = $this->media->get('photo4.jpg');
        $this->assertFalse($bare->hasMeta());
        $this->assertSame([], $bare->metaKeys());
    }
}
