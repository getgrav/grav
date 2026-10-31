<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Media\Traits\ImageDecodingTrait;
use Grav\Common\Media\Traits\ImageFetchPriorityTrait;
use Grav\Common\Media\Traits\ImageLoadingTrait;

/**
 * The loading, decoding and fetchpriority actions take a closed set of keywords.
 * A value outside that set is not written to the image; `auto` leaves the
 * attribute out.
 */
class ImageKeywordAttributesTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    private function medium(): object
    {
        return new class {
            use ImageLoadingTrait;
            use ImageDecodingTrait;
            use ImageFetchPriorityTrait;

            public $attributes = [];
        };
    }

    public static function keywords(): array
    {
        return [
            'loading lazy' => ['loading', 'lazy', 'lazy'],
            'loading eager' => ['loading', 'eager', 'eager'],
            'loading is case insensitive' => ['loading', 'Lazy', 'lazy'],
            'decoding sync' => ['decoding', 'sync', 'sync'],
            'decoding async' => ['decoding', 'async', 'async'],
            'fetchpriority high' => ['fetchpriority', 'high', 'high'],
            'fetchpriority low' => ['fetchpriority', 'low', 'low'],
        ];
    }

    /**
     * @dataProvider keywords
     */
    public function testKeywordsAreKept(string $action, string $value, string $expected): void
    {
        $medium = $this->medium();
        $medium->{$action}($value);

        self::assertSame([$action => $expected], $medium->attributes);
    }

    public static function notKeywords(): array
    {
        $values = ['auto', 'high" onload="x', '/Abc7b7b20Xyz/', 'lazy ', 'other', ''];
        $cases = [];
        foreach (['loading', 'decoding', 'fetchpriority'] as $action) {
            foreach ($values as $value) {
                $cases["{$action} {$value}"] = [$action, $value];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider notKeywords
     */
    public function testOtherValuesAreNotWritten(string $action, string $value): void
    {
        $medium = $this->medium();
        $medium->{$action}($value);

        self::assertSame([], $medium->attributes);
    }
}
