<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;

/**
 * Class BlueprintExtendsProvenanceTest
 *
 * Regression coverage for the follow-up to the 2.0.15 provenance change
 * (commit c51987f5b), reported on getgrav/grav-plugin-email#193.
 *
 * 2.0.15 replaced the dynamic-provider allowlist with trust by provenance: a
 * blueprint read off disk may call any `data-*@` provider, while a form defined
 * in page frontmatter stays on the strict allowlist. One case was missed.
 *
 * `BlueprintForm::load()` loads an inheriting blueprint parent-first:
 *
 *     $data = $this->doLoad($files, $extends);   // [...parents, ownContent]
 *     $this->items = (array)array_shift($data);  // parent becomes $this->items
 *     foreach ($data as $content) {
 *         $this->extend($content, true);         // the file's OWN content, as an array
 *     }
 *
 * so a blueprint using `extends@` hands its own fields back to itself as a bare
 * array — which `Blueprint::extend()` inferred as page-authored and refused.
 * Every theme page blueprint is affected, because they essentially all start
 * with `extends@: default`.
 *
 * Naming convention: test{Method}_{issue}_{description}
 */
class BlueprintExtendsProvenanceTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->dir = sys_get_temp_dir() . '/grav-bp-provenance-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /**
     * @param string $name
     * @param string $body
     * @return string absolute path
     */
    protected function write(string $name, string $body): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $body);

        return $path;
    }

    /** A field whose provider is deliberately NOT on the static allowlist. */
    protected function fieldYaml(): string
    {
        return <<<'YAML'

form:
  fields:
    header.engine:
      type: select
      data-options@: '\Grav\Common\Data\BlueprintProvenanceProbe::engineOptions'
YAML;
    }

    /**
     * @param Blueprint $blueprint
     * @return array<string,bool>
     */
    protected function dynamicTrustOf(Blueprint $blueprint): array
    {
        $property = new ReflectionProperty(Blueprint::class, 'dynamicTrust');

        return (array) $property->getValue($blueprint);
    }

    /**
     * The regression: a blueprint that resolves a parent must keep trust on its
     * own directives. Before the fix every entry came back false.
     */
    public function testLoad_email193_ExtendsKeepsOwnDirectivesTrusted(): void
    {
        $this->write('bpparent.yaml', "title: Parent\n");
        $child = $this->write('bpchild.yaml', "title: Child\nextends@: bpparent\n" . $this->fieldYaml());

        $blueprint = new Blueprint($child);
        $blueprint->setContext($this->dir);
        $blueprint->load();

        $trust = $this->dynamicTrustOf($blueprint);

        self::assertNotSame([], $trust, 'extend() should have attributed the blueprint own fields');
        foreach ($trust as $path => $trusted) {
            self::assertTrue($trusted, "Directive at {$path} came off disk and must stay trusted");
        }
    }

    /**
     * The negative control that the fix must not weaken: a form defined in page
     * frontmatter arrives as injected items, never reaches extend() during
     * load(), and stays on the strict allowlist.
     */
    public function testLoad_email193_FrontmatterFormStaysUntrusted(): void
    {
        $blueprint = new Blueprint(null, [
            'form' => ['fields' => ['header.engine' => [
                'type' => 'select',
                'data-options@' => '\Grav\Common\Data\BlueprintProvenanceProbe::engineOptions',
            ]]],
        ]);
        $blueprint->load();

        self::assertFalse(
            $blueprint->isTrusted(),
            'A blueprint built from injected items is page-authored and must not be trusted'
        );
    }

    /**
     * Second negative control: an array spliced in by runtime code after the
     * loader has finished is still page-authored, and must stay refused even
     * though the blueprint it is spliced into is trusted.
     */
    public function testExtend_email193_RuntimeSpliceStaysUntrusted(): void
    {
        $file = $this->write('bpplain.yaml', "title: Plain\n" . $this->fieldYaml());

        $blueprint = new Blueprint($file);
        $blueprint->setContext($this->dir);
        $blueprint->load();
        $blueprint->extend([
            'form' => ['fields' => ['header.spliced' => [
                'type' => 'select',
                'data-options@' => '\Grav\Common\Data\BlueprintProvenanceProbe::engineOptions',
            ]]],
        ], true);

        $trust = $this->dynamicTrustOf($blueprint);

        $spliced = null;
        foreach ($trust as $path => $trusted) {
            if (str_contains($path, 'header.spliced')) {
                $spliced = $trusted;
            }
        }

        self::assertNotNull($spliced, 'The spliced field should have been attributed');
        self::assertFalse($spliced, 'A runtime splice is page-authored and must stay untrusted');
    }
}
