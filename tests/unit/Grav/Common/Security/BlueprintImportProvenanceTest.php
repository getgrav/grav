<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;

/**
 * Class BlueprintImportProvenanceTest
 *
 * An `import@` directive in page-authored input (a form in page frontmatter,
 * a Flex form override) may name a blueprint under blueprints:// and nothing
 * else: no filesystem path, no `..`, no stream and no `context:` outside
 * blueprints://. Blueprint files and blueprints declared trusted in PHP keep
 * the unrestricted resolution.
 *
 * A trusted base blueprint extended with an untrusted, page-authored form
 * override models FlexForm::getBlueprint() / FlexDirectoryForm::getBlueprint().
 *
 * Naming convention: test{Method}_{issue}_{description}
 */
class BlueprintImportProvenanceTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $dir;

    /** @var string The same file again, on the tmp:// stream. */
    protected $streamDir;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav')();

        $this->dir = sys_get_temp_dir() . '/grav-import-provenance-' . getmypid();
        @mkdir($this->dir);
        file_put_contents($this->dir . '/outside.yaml', "marker: OUTSIDE-MARKER\n");

        $this->streamDir = $grav['locator']->findResource('tmp://', true, true) . '/' . basename($this->dir);
        @mkdir($this->streamDir, 0775, true);
        file_put_contents($this->streamDir . '/outside.yaml', "marker: OUTSIDE-MARKER\n");
    }

    protected function tearDown(): void
    {
        foreach ([$this->dir, $this->streamDir] as $dir) {
            @unlink($dir . '/outside.yaml');
            @rmdir($dir);
        }

        parent::tearDown();
    }

    /**
     * Ways to spell an `import@` of a file that is not a blueprint. The file
     * lives outside blueprints://.
     *
     * @return array<string,array{callable}>
     */
    public static function fileImportProvider(): array
    {
        return [
            'absolute path' => [static fn (string $dir) => ['type' => "{$dir}/outside.yaml:"]],
            'string form' => [static fn (string $dir) => "{$dir}/outside.yaml:"],
            'relative path' => [static fn (string $dir) => ['type' => str_repeat('../', substr_count((string) getcwd(), '/')) . ltrim($dir, '/') . '/outside.yaml:']],
            'context outside blueprints://' => [static fn (string $dir) => ['type' => 'outside:', 'context' => $dir]],
            'context stream' => [static fn (string $dir) => ['type' => 'outside:', 'context' => 'tmp://' . basename($dir)]],
            'context traversal' => [static fn (string $dir) => ['type' => 'outside:', 'context' => 'blueprints://../../..' . $dir]],
        ];
    }

    /**
     * The shape the Form plugin builds from page frontmatter.
     *
     * @param array|string $import
     * @return array
     */
    protected static function selectImporting($import): array
    {
        return ['form' => ['fields' => ['picked' => ['type' => 'select', 'options' => ['import@' => $import]]]]];
    }

    /**
     * @dataProvider fileImportProvider
     * @param callable $import
     */
    public function testLoad_PageFormImportCannotNameAFile(callable $import): void
    {
        $blueprint = new Blueprint('zzprobe', self::selectImporting($import($this->dir)));
        $blueprint->load()->init();

        self::assertFalse($blueprint->isTrusted());
        self::assertStringNotContainsString('OUTSIDE-MARKER', json_encode($blueprint->toArray()));
    }

    /**
     * @dataProvider fileImportProvider
     * @param callable $import
     */
    public function testExtend_PageOverrideImportCannotNameAFile(callable $import): void
    {
        $blueprint = (new Blueprint('user/account'))->load();
        $blueprint->extend(self::selectImporting($import($this->dir)), true);
        $blueprint->init();

        self::assertTrue($blueprint->isTrusted(), 'A file-defined blueprint keeps its own trust');
        self::assertStringNotContainsString('OUTSIDE-MARKER', json_encode($blueprint->toArray()));
    }

    /**
     * Negative control: a page form may still import a blueprint under
     * blueprints://, with or without a context that stays inside it.
     */
    public function testLoad_PageFormImportCanNameABlueprint(): void
    {
        $blueprint = new Blueprint('zzprobe', self::selectImporting('user/account:form/fields'));
        $blueprint->load()->init();

        self::assertNotEmpty($blueprint->get('form/fields/picked/options'));

        $blueprint = new Blueprint('zzprobe', self::selectImporting(['type' => 'partials/security:form/fields', 'context' => 'blueprints://pages']));
        $blueprint->load()->init();

        self::assertNotEmpty($blueprint->get('form/fields/picked/options'));
    }

    /**
     * Negative control: a page form import without a context resolves in the
     * context the blueprint was given in PHP, and the imported blueprint resolves
     * its own relative imports in that same context, as the vendor import does.
     */
    public function testLoad_PageFormImportKeepsRelativeContext(): void
    {
        $root = $this->dir . '/blueprints';
        @mkdir($root . '/zzctx', 0775, true);
        file_put_contents($root . '/zzctx/child.yaml', <<<'YAML'
form:
  fields:
    child_field:
      type: text
    nested:
      type: section
      import@:
        type: partial
YAML);
        file_put_contents($root . '/zzctx/partial.yaml', "form:\n  fields:\n    partial_field:\n      type: text\n");
        Grav\Common\Grav::instance()['locator']->addPath('blueprints', '', $root);

        try {
            $blueprint = new Blueprint('zzprobe', ['form' => ['fields' => ['imported' => ['type' => 'section', 'import@' => 'child']]]]);
            $blueprint->setContext('blueprints://zzctx');
            $blueprint->load()->init();

            self::assertFalse($blueprint->isTrusted());
            self::assertSame('text', $blueprint->get('form/fields/imported/fields/child_field/type'));
            self::assertSame('text', $blueprint->get('form/fields/imported/fields/nested/fields/partial_field/type'));
        } finally {
            @unlink($root . '/zzctx/child.yaml');
            @unlink($root . '/zzctx/partial.yaml');
            @rmdir($root . '/zzctx');
            @rmdir($root);
        }
    }

    /**
     * Negative control: a blueprint that declares itself trusted keeps the
     * unrestricted path and context resolution blueprint files rely on.
     */
    public function testLoad_DeclaredTrustedImportKeepsPathAndContext(): void
    {
        $blueprint = new Blueprint('zzprobe', self::selectImporting(['type' => 'outside:', 'context' => $this->dir]), true);
        $blueprint->load()->init();

        self::assertSame(['marker' => 'OUTSIDE-MARKER'], $blueprint->get('form/fields/picked/options'));
    }

    /**
     * Negative control: a trusted override merged into a file-defined blueprint
     * may still import from any context.
     */
    public function testExtend_TrustedOverrideImportKeepsContext(): void
    {
        $blueprint = (new Blueprint('user/account'))->load();
        $blueprint->extend(self::selectImporting(['type' => 'outside:', 'context' => $this->dir]), true, true);
        $blueprint->init();

        self::assertSame(['marker' => 'OUTSIDE-MARKER'], $blueprint->get('form/fields/picked/options'));
    }
}
