<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Validation;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Common\Twig\Extension\GravExtension;
use Grav\Common\Utils;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\Pages\FlexPageObject;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Date readers reject strings beyond the shared date length limit.
 */
class DateFieldLengthTest extends \PHPUnit\Framework\TestCase
{
    protected const SITE = 'tests/_output/date-field-length-site';

    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    protected function tearDown(): void
    {
        Folder::delete(GRAV_ROOT . '/' . self::SITE);
        parent::tearDown();
    }

    public function testUtilsRefusesOverlongAndNonScalarValues(): void
    {

        self::assertSame(strtotime('2021-05-01'), Utils::strtotime('2021-05-01'));
        self::assertFalse(Utils::strtotime('not a date'));
        self::assertFalse(Utils::strtotime(['2021-05-01']));
        self::assertFalse(Utils::strtotime(null));
        self::assertFalse(Utils::strtotime(self::overlongDate()));

        self::assertSame(strtotime('2021-05-01'), Utils::date2timestamp('2021-05-01', '!Y-m-d'));
        self::assertFalse(Utils::date2timestamp(['2021-05-01']));
        self::assertFalse(Utils::date2timestamp(self::overlongDate()));
        self::assertFalse(Utils::date2timestamp(self::overlongDate(), 'Y-m-d'));

    }

    public function testPageCollectionDateRangeField(): void
    {
        foreach (['events' => []] + self::headers() as $slug => $header) {
            $dir = GRAV_ROOT . '/' . self::SITE . '/user/pages/' . ($slug === 'events' ? $slug : "events/{$slug}");
            Folder::create($dir);
            file_put_contents("{$dir}/default.md", "---\n" . json_encode(['title' => ucfirst($slug)] + $header) . "\n---\nbody\n");
        }

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', self::SITE . '/user/pages', false);
        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $pages->init();

        $collection = $pages->getCollection([
            'items' => ['@page.children' => '/events'],
            'dateRange' => ['start' => '2020-01-01', 'field' => 'header.event_date'],
        ]);

        self::assertSame(['Fair'], $this->titles($collection));
    }

    public function testFlexPageCollectionDateRangeField(): void
    {
        $directory = $this->flex()->getDirectory('pages');
        self::assertNotNull($directory);

        $objects = [];
        foreach (self::headers() as $slug => $header) {
            $objects[$slug] = $directory->createObject(['header' => ['title' => ucfirst($slug)] + $header], $slug);
        }

        $collection = $directory->createCollection($objects)->dateRange('2020-01-01', null, 'header.event_date');

        self::assertSame(['Fair'], $this->titles($collection));
    }

    public function testFlexPageDateFieldsCountOverlongValuesAsNoDate(): void
    {
        $directory = $this->flex()->getDirectory('pages');
        self::assertNotNull($directory);

        /** @var FlexPageObject $page */
        $page = $directory->createObject(['header' => ['title' => 'Page', 'date' => self::overlongDate()]], 'page');
        $page->setProperty('created_date', self::overlongDate());

        self::assertSame(0, $page->getCreated_Timestamp());
        self::assertSame($page->modified(), $page->date());
    }

    public function testNicetimeRefusesOverlongStrings(): void
    {
        $extension = new GravExtension();

        self::assertSame($extension->nicetimeFunc('not a date'), $extension->nicetimeFunc(self::overlongDate()));
    }

    public function testDateValidatorsEnforceTheLengthLimit(): void
    {
        foreach (['typeDatetime', 'typeDatetimeLocal'] as $method) {
            self::assertTrue(Validation::$method('2021-05-01', [], []));
            self::assertTrue(Validation::$method('2021-05-01' . str_repeat(' ', 246), [], []));
            self::assertFalse(Validation::$method(self::overlongDate(), [], []));
            self::assertFalse(Validation::$method(self::overlongDate(), ['format' => 'Y-m-d'], []));
            self::assertTrue(Validation::$method(1619827200, [], []));
            self::assertTrue(Validation::$method(new DateTimeImmutable('2021-05-01'), [], []));
        }
    }

    public function testDateFilterRejectsOverlongStringsBeforeFormatting(): void
    {
        $this->grav['config']->set('system.pages.dateformat.default', 'Y-m-d');
        $field = ['type' => 'datetime', 'validate' => ['type' => 'datetime']];
        self::assertSame('2021-05-01', Validation::filter('2021-05-01', $field));
        self::assertSame('', Validation::filter(self::overlongDate(), $field));
        self::assertSame('2021-05-01', Validation::filter(1619827200, $field));
        self::assertSame('2021-05-01', Validation::filter(new DateTimeImmutable('2021-05-01'), $field));
    }

    public function testDateConversionPreservesTimestampsAndDateObjects(): void
    {
        self::assertSame(1619827200, Utils::date2timestamp(1619827200));
        self::assertSame(1619827200, Utils::date2timestamp(1619827200.0));
        $date = new DateTimeImmutable('2021-05-01T00:00:00Z');
        self::assertSame($date->getTimestamp(), Utils::date2timestamp($date));
    }

    public function testModularCollectionIgnoresOverlongDatesDuringInitialization(): void
    {
        $root = GRAV_ROOT . '/' . self::SITE . '/user/pages/landing';
        Folder::create($root . '/_entry');
        $header = ['title' => 'Landing', 'content' => ['items' => '@self.modular', 'dateRange' => ['start' => '2020-01-01', 'field' => 'header.event_date']]];
        file_put_contents($root . '/modular.md', "---\n" . json_encode($header) . "\n---\n");
        file_put_contents($root . '/_entry/text.md', "---\n" . json_encode(['title' => 'Entry', 'event_date' => self::overlongDate()]) . "\n---\nentry");
        $this->grav['locator']->addPath('page', '', self::SITE . '/user/pages', false);
        $this->grav['pages']->init();
        self::assertCount(0, $this->grav['pages']->find('/landing', true)->collection());
    }

    protected function flex(): Flex
    {
        $flex = new Flex([], ['object' => $this->grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);

        return $flex;
    }

    protected static function headers(): array
    {
        return [
            'fair' => ['event_date' => '2021-05-01'],
            'old' => ['event_date' => '2001-05-01'],
            'none' => [],
            'list' => ['event_date' => ['2021-05-01']],
            'overlongDate' => ['event_date' => self::overlongDate()],
        ];
    }

    protected static function overlongDate(): string
    {
        return '2021-05-01' . str_repeat(' ', 247);
    }

    protected function titles(iterable $collection): array
    {
        $titles = [];
        foreach ($collection as $page) {
            $titles[] = $page->title();
        }

        return $titles;
    }
}
