<?php

use Codeception\Util\Fixtures;
use Grav\Common\Page\Markdown\Excerpts;

/**
 * The query string of a Markdown link or image is split into actions; the cost has to stay linear in their number.
 */
class ExcerptsQueryCostTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $query;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::get('grav')();

        $this->query = implode('&', array_map('dechex', range(0, 39999)));
    }

    public function testCostOfLinkQueryIsLinear(): void
    {
        $excerpts = new Excerpts(null, ['markdown' => [], 'images' => []]);
        $excerpt = ['element' => ['name' => 'a', 'attributes' => ['href' => 'https://example.com/x?target=_blank&' . $this->query]]];

        $start = microtime(true);
        $excerpt = $excerpts->processLinkExcerpt($excerpt);

        self::assertLessThan(1.0, microtime(true) - $start, '40,000 query params took several seconds when each one copied the array');
        self::assertSame('_blank', $excerpt['element']['attributes']['target']);
    }

    public function testCostOfMediaActionsIsLinear(): void
    {
        // Stands in for a Medium: every unknown action goes through __call(), as the URL-querystring passthrough does.
        $recorder = new class {
            /** @var int */
            public $called = 0;

            public function __call($name, $args)
            {
                $this->called++;

                return $this;
            }

            public function urlHash($hash)
            {
                return $this;
            }
        };
        $excerpts = new Excerpts(null, ['markdown' => [], 'images' => []]);

        $start = microtime(true);
        $excerpts->processMediaActions($recorder, 'image.png?' . $this->query);

        self::assertLessThan(1.0, microtime(true) - $start, '40,000 media actions took several seconds when each one copied the array');
        self::assertSame(40000, $recorder->called);
    }
}
