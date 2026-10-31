<?php

use Codeception\Util\Fixtures;
use Doctrine\Common\Collections\Criteria;
use Grav\Common\Grav;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Object\ArrayObject;
use Grav\Framework\Object\Collection\ObjectExpressionVisitor;
use Grav\Framework\Object\ObjectCollection;
use Grav\Framework\Object\PropertyObject;

/**
 * A collection sort or filter field resolves to a property, a get or is getter or one of the listed
 * read-only methods (`route`, `url`, `modified`...), and never to any other method of the object.
 */
class CollectionFieldNameSecurityTest extends \PHPUnit\Framework\TestCase
{
    public function testSortFieldDoesNotCallMethods(): void
    {
        $collection = $this->collection();
        $collection->orderBy(['delete' => 'ASC']);

        foreach ($collection as $object) {
            self::assertFalse($object->deleted, 'sorting by "delete" must not call delete()');
        }
    }

    public function testFilterFieldDoesNotCallMethods(): void
    {
        $collection = $this->collection();
        $collection->matching(Criteria::create()->where(Criteria::expr()->eq('delete', 'x')));
        $collection->matching(Criteria::create()->where(Criteria::expr()->eq('lower(delete)', 'x')));

        foreach ($collection as $object) {
            self::assertFalse($object->deleted, 'filtering by "delete" must not call delete()');
        }
    }

    public function testPropertiesAndGettersStillSort(): void
    {
        self::assertSame(['a', 'b'], $this->collection()->orderBy(['name' => 'ASC'])->getKeys());
        self::assertSame(['b', 'a'], $this->collection()->orderBy(['initial' => 'DESC'])->getKeys());
        self::assertSame(['b', 'a'], $this->collection()->orderBy(['missing' => 'ASC'])->getKeys());
    }

    public function testFixedPropertiesFallBackToGetters(): void
    {
        $object = new class (['name' => 'bob']) extends PropertyObject {
            protected $name;

            public function getInitial(): string
            {
                return $this->name[0];
            }
        };

        self::assertSame('bob', ObjectExpressionVisitor::getObjectFieldValue($object, 'name'));
        self::assertSame('b', ObjectExpressionVisitor::getObjectFieldValue($object, 'initial'));
        self::assertNull(ObjectExpressionVisitor::getObjectFieldValue($object, 'missing'));
    }

    public function testCalculatedPagePropertiesStillResolve(): void
    {
        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $flex = new Flex([], ['object' => $grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);

        $header = ['title' => 'Getting Started', 'date' => '2020-01-02', 'visible' => false];
        $page = $flex->getDirectory('pages')->createObject(['header' => $header], 'docs/01.start');

        self::assertSame('Getting Started', ObjectExpressionVisitor::getObjectFieldValue($page, 'title'));
        self::assertSame('getting started', ObjectExpressionVisitor::getObjectFieldValue($page, 'lower(title)'));
        self::assertSame(strtotime('2020-01-02'), ObjectExpressionVisitor::getObjectFieldValue($page, 'date'));
        self::assertSame('start', ObjectExpressionVisitor::getObjectFieldValue($page, 'slug'));
        self::assertSame('01.', ObjectExpressionVisitor::getObjectFieldValue($page, 'order'));
        self::assertFalse(ObjectExpressionVisitor::getObjectFieldValue($page, 'visible'));
        self::assertSame('docs/01.start', ObjectExpressionVisitor::getObjectFieldValue($page, 'key'));
        self::assertSame($page->getTimestamp(), ObjectExpressionVisitor::getObjectFieldValue($page, 'timestamp'));
        self::assertSame($page->modified(), ObjectExpressionVisitor::getObjectFieldValue($page, 'modified'));
        self::assertSame($page->rawRoute(), ObjectExpressionVisitor::getObjectFieldValue($page, 'rawRoute'));
        self::assertSame($page->path(), ObjectExpressionVisitor::getObjectFieldValue($page, 'path'));
    }

    public function testReadOnlyFieldMethodsStillResolve(): void
    {
        $object = new class {
            public function route(): string
            {
                return '/blog/post';
            }

            public function url(): string
            {
                return 'https://example.com/blog/post';
            }

            public function getRoute(): string
            {
                return 'not used';
            }
        };

        self::assertSame('/blog/post', ObjectExpressionVisitor::getObjectFieldValue($object, 'route'));
        self::assertSame('https://example.com/blog/post', ObjectExpressionVisitor::getObjectFieldValue($object, 'url'));
    }

    private function collection(): ObjectCollection
    {
        $object = static fn(string $name) => new class (['name' => $name]) extends ArrayObject {
            public bool $deleted = false;

            public function delete()
            {
                $this->deleted = true;

                return $this;
            }

            public function getInitial(): string
            {
                return $this->getProperty('name')[0];
            }
        };

        return new ObjectCollection(['b' => $object('bob'), 'a' => $object('alice')]);
    }
}
