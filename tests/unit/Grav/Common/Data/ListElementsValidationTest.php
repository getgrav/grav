<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Validation;
use Grav\Common\Data\ValidationException;
use Grav\Common\Grav;
use Symfony\Component\Yaml\Yaml;

/**
 * Regression test for #4337.
 *
 * A `list` whose only item field is an `elements` field made the API throw a
 * TypeError from `Validation::typeList()` on save. The `element` children were
 * filed at the blueprint root instead of beside their `elements` field, so the
 * list's `*` item rule never got a parent entry and `getProperty()` returned
 * `fields: {"*": null}`.
 *
 * The schema half of the fix is in `rockettheme/toolbox`, so this guards the
 * composer pin as much as it guards `typeList()`.
 */
class ListElementsValidationTest extends \PHPUnit\Framework\TestCase
{
    /** @var mixed */
    private $formFieldTypes;

    private const BLUEPRINT = <<<'YAML'
header.sections:
  type: list
  fields:
    .type:
      type: elements
      options: { text: Text, quote: Quote }
      fields:
        text:
          type: element
          fields:
            .heading: { type: text }
        quote:
          type: element
          fields:
            .quote: { type: textarea }
YAML;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $grav();

        // What flex-objects registers for a `list`.
        $plugins = Grav::instance()['plugins'];
        $this->formFieldTypes = $plugins->formFieldTypes;
        $plugins->formFieldTypes = ['list' => ['array' => true]];
    }

    protected function tearDown(): void
    {
        Grav::instance()['plugins']->formFieldTypes = $this->formFieldTypes;

        parent::tearDown();
    }

    public function testListOfElementsSavesWithoutATypeError(): void
    {
        $field = $this->blueprint()->schema()->getProperty('header.sections');

        $messages = Validation::validate([['type' => 'text', 'text' => ['heading' => 'A']]], $field);

        self::assertSame([], $messages);
    }

    /**
     * Whatever the schema looks like, a sub-field without a rule is skipped
     * rather than handed to `validate()` as null.
     */
    public function testListSkipsSubFieldsThatHaveNoRule(): void
    {
        $field = [
            'type' => 'list',
            'name' => 'header.sections',
            'array' => true,
            'fields' => ['*' => null, 'title' => ['type' => 'text', 'name' => 'title']],
        ];

        self::assertSame([], Validation::validate([['title' => 'A']], $field));
    }

    public function testListItemRuleExistsWhenTheOnlyChildIsAnElementsField(): void
    {
        $field = $this->blueprint()->schema()->getProperty('header.sections');

        self::assertIsArray($field['fields']['*']);
    }

    public function testElementChildrenAreFiledUnderTheListItem(): void
    {
        $items = $this->blueprint()->schema()->getState()['items'];

        self::assertArrayHasKey('header.sections.*.text.heading', $items);
        self::assertArrayHasKey('header.sections.*.quote.quote', $items);
        // They used to land at the root, where they collide with a real field.
        self::assertArrayNotHasKey('text.heading', $items);
        self::assertArrayNotHasKey('quote.quote', $items);
    }

    public function testElementChildrenAreValidatedInsideTheList(): void
    {
        $blueprint = $this->blueprint();
        $data = ['header' => ['sections' => [['type' => 'text', 'text' => ['heading' => 'A']]]]];

        $blueprint->validate($data);
        self::assertSame($data, $blueprint->filter($data));

        $data['header']['sections'][0]['text']['heading'] = ['not', 'a', 'string'];
        $this->expectException(ValidationException::class);
        $blueprint->validate($data);
    }

    private function blueprint(): Blueprint
    {
        $blueprint = new Blueprint('check', ['form' => ['fields' => Yaml::parse(self::BLUEPRINT)]], true);

        return $blueprint->init();
    }
}
