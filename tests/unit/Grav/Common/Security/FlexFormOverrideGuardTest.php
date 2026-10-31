<?php

use Grav\Common\Data\Blueprint;
use Grav\Framework\Flex\FlexForm;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;

/**
 * Class FlexFormOverrideGuardTest
 *
 * A Flex object caches the blueprint it validates and filters update() with, and
 * hands that same instance to FlexForm::getBlueprint(). Page-authored field
 * overrides (`form.fields` in a page's frontmatter) must only reach the form's own
 * copy: merged into the object's blueprint they can declare a guarded field again
 * and drop its `security@` / `disabled` guard, so a non-super `admin.users.update`
 * operator could save `admin.super: true` onto a group or account.
 *
 * Naming convention: test{Method}_{issue}_{description}
 */
class FlexFormOverrideGuardTest extends \PHPUnit\Framework\TestCase
{
    /**
     * What Blueprint::dynamicSecurity() leaves behind for a user who fails
     * `security@` on the field.
     */
    protected function guardedBlueprint(): Blueprint
    {
        $blueprint = new Blueprint(null, ['form' => ['fields' => [
            'description' => ['type' => 'text'],
            'access' => ['type' => 'array', 'validate' => ['ignore' => true]],
        ]]], true);

        return $blueprint->init();
    }

    /**
     * FlexForm (name '') for an object that returns `$blueprint`, with `$fields`
     * as the page-authored overrides.
     */
    protected function formWithOverrides(Blueprint $blueprint, array $fields): FlexForm
    {
        $object = $this->createMock(FlexObjectInterface::class);
        $object->method('getBlueprint')->willReturn($blueprint);

        $form = (new ReflectionClass(FlexForm::class))->newInstanceWithoutConstructor();
        foreach (['name' => '', 'object' => $object, 'form' => ['fields' => $fields]] as $property => $value) {
            (new ReflectionProperty(FlexForm::class, $property))->setValue($form, $value);
        }

        return $form;
    }

    /**
     * @return array<string,array{0:array}>
     */
    public static function providerOverrides(): array
    {
        return [
            'declared again in another section' => [
                ['more' => ['type' => 'section', 'fields' => ['access' => ['type' => 'array']]]],
            ],
            'overridden in place' => [
                ['access' => ['type' => 'array', 'validate' => ['ignore' => false]]],
            ],
        ];
    }

    /**
     * @dataProvider providerOverrides
     */
    public function testGetBlueprint_OverridesDoNotReachTheObjectsBlueprint(array $fields): void
    {
        $blueprint = $this->guardedBlueprint();
        $items = $blueprint->toArray();

        $form = $this->formWithOverrides($blueprint, $fields);
        $formBlueprint = $form->getBlueprint();

        self::assertNotSame($blueprint, $formBlueprint);
        self::assertSame($items, $blueprint->toArray());
        self::assertSame(
            ['description' => 'ok'],
            $blueprint->filter(['description' => 'ok', 'access' => ['admin' => ['super' => true]]])
        );
    }

    public function testGetBlueprint_OverridesStillChangeTheForm(): void
    {
        $blueprint = $this->guardedBlueprint();

        $form = $this->formWithOverrides($blueprint, ['extra' => ['type' => 'text']]);
        $formBlueprint = $form->getBlueprint();

        self::assertSame('text', $formBlueprint->get('form/fields/extra/type'));
        self::assertNull($blueprint->get('form/fields/extra'));
        self::assertSame($formBlueprint, $form->getBlueprint());
    }

    public function testGetBlueprint_NoOverridesReturnsTheObjectsBlueprint(): void
    {
        $blueprint = $this->guardedBlueprint();

        $form = $this->formWithOverrides($blueprint, []);
        (new ReflectionProperty(FlexForm::class, 'form'))->setValue($form, null);

        self::assertSame($blueprint, $form->getBlueprint());
    }
}
