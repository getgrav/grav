<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Page;

/**
 * Class PageFormsFalsyNameTest
 *
 * Page::getForms() keeps a form whatever its name is, but Page::addForms() used
 * to test the name for truth and silently dropped forms named '0', '' and the
 * like.
 *
 * The Form plugin copies the forms of a module to the page which displays the
 * module with addForms(), and checks made on the displayed page (Flex Objects
 * authorization for one) only see the forms which arrived there. A form which
 * is dropped on the way is still rendered, but it is never checked.
 */
class PageFormsFalsyNameTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function providerNames(): array
    {
        return [
            'regular name' => ['card'],
            'numeric name' => ['5'],
            'zero string' => ['0'],
            'empty string' => [''],
            'zero integer' => [0],
            'false' => [false],
        ];
    }

    /**
     * @dataProvider providerNames
     * @param mixed $name
     */
    public function testModuleFormReachesParentPage($name): void
    {
        $module = $this->page(['form' => ['name' => $name, 'type' => 'flex']]);
        $forms = $module->getForms();
        self::assertCount(1, $forms);

        // This is what the Form plugin does to a form inside a module.
        $parent = $this->page();
        $parent->addForms($forms);

        self::assertSame($forms, $parent->getForms());
    }

    /**
     * @dataProvider providerNames
     * @param mixed $name
     */
    public function testOverrideIsRespected($name): void
    {
        $page = $this->page(['form' => ['name' => $name, 'type' => 'flex']]);
        $key = key($page->getForms());

        $page->addForms([['name' => $name, 'type' => 'other']], false);
        self::assertCount(1, $page->getForms());
        self::assertSame('flex', $page->getForms()[$key]['type']);

        $page->addForms([['name' => $name, 'type' => 'other']]);
        self::assertCount(1, $page->getForms());
        self::assertSame('other', $page->getForms()[$key]['type']);
    }

    public function testUnnamedFormOnPageWithoutSlugIsKept(): void
    {
        // Neither a name nor a slug to fall back to: getForms() files such a form under ''.
        $module = $this->page(['form' => ['type' => 'flex']]);
        $forms = $module->getForms();
        self::assertSame([''], array_keys($forms));

        $parent = $this->page();
        $parent->addForms($forms);

        self::assertSame($forms, $parent->getForms());
    }

    public function testNonArrayFormsAreStillIgnored(): void
    {
        $page = $this->page();
        $page->addForms(['card' => null, 'other' => 'string', 0 => false]);

        self::assertSame([], $page->getForms());
    }

    /**
     * @param array $header
     * @return Page
     */
    private function page(array $header = []): Page
    {
        $page = new Page();
        $page->header((object)(['title' => 'Page'] + $header));

        return $page;
    }
}
