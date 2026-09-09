<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;

/**
 * Regression test for #4271.
 *
 * The Content tab and the markdown `content` field inside it are both keyed
 * `content`. Blueprint fields are flattened into a single map keyed by name,
 * containers included, so the two share a slot and only one survives. The tab
 * used to win, which left every rule the blueprint sets on a page body unused.
 *
 * The fix is in `rockettheme/toolbox`, so this guards the composer pin as much
 * as it guards the blueprint: nothing in Grav's own tree stops the two from
 * colliding again. The tab keeps its name, because every page blueprint in the
 * wild extends it.
 *
 * Based on the test in #4289 by @wakqasahmed.
 */
class PageBlueprintContentTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $grav();
    }

    /**
     * @dataProvider pageBlueprintProvider
     */
    public function testContentFieldIsNotShadowedByItsTab(string $type): void
    {
        $items = $this->schemaItems($type);

        self::assertArrayHasKey('content', $items);
        self::assertSame('markdown', $items['content']['type']);
        self::assertSame('textarea', $items['content']['validate']['type'] ?? null);
    }

    /**
     * A page body has no length ceiling: `max: 0` opts out of the default
     * multiline guard, which used to reject long pages (#3643). That opt-out
     * only takes effect now that the field is reachable at all.
     */
    public function testPageBodyHasNoLengthCeiling(): void
    {
        $items = $this->schemaItems('default');

        self::assertSame(0, $items['content']['validate']['max'] ?? null);
    }

    /**
     * The tab is still a real container. Resolving the collision must not cost
     * the form its Content tab.
     */
    public function testContentTabKeepsItsNameAndItsChildren(): void
    {
        $blueprint = $this->loadBlueprint('default');
        $tabs = $blueprint->get('form/fields/tabs/fields');

        self::assertArrayHasKey('content', $tabs);
        self::assertSame('tab', $tabs['content']['type']);
        self::assertArrayHasKey('content', $tabs['content']['fields']);
    }

    public function pageBlueprintProvider(): array
    {
        return [
            'default' => ['default'],
            'modular' => ['modular'],
        ];
    }

    private function loadBlueprint(string $type): Blueprint
    {
        $blueprint = new Blueprint($type);
        $blueprint->setContext('blueprints://pages');

        return $blueprint->load()->init();
    }

    private function schemaItems(string $type): array
    {
        return $this->loadBlueprint($type)->schema()->getState()['items'];
    }
}
