<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;

/**
 * Regression test for #4271: the Content tab and the markdown `content` field
 * in the default page blueprint were both keyed `content`, so BlueprintSchema
 * flattened them into the same slot and the tab (added after its own fields
 * during parsing) always won, leaving the field's `validate` config
 * unreachable.
 */
class PageBlueprintContentTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();
    }

    public function testContentFieldIsNotShadowedByTab(): void
    {
        $blueprint = new Blueprint('default');
        $blueprint->setContext('blueprints://pages');
        $blueprint->load()->init();

        $items = $blueprint->schema()->getState()['items'];

        self::assertArrayHasKey('content', $items);
        self::assertSame('markdown', $items['content']['type']);
        self::assertSame('textarea', $items['content']['validate']['type'] ?? null);

        self::assertArrayHasKey('content_tab', $items);
        self::assertSame('tab', $items['content_tab']['type']);
    }
}
