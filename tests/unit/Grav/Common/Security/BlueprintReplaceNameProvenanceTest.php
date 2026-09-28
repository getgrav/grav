<?php

use Grav\Common\Data\Blueprint;

/**
 * Records whether an untrusted `data-*@` directive that was relocated by a
 * `replace-name@` merge action reached the dynamic-data sink.
 */
class BlueprintReplaceNameProbe
{
    /** @var bool */
    public static $called = false;

    public static function reset(): void
    {
        self::$called = false;
    }

    /**
     * @param mixed ...$args
     * @return array<string,string>
     */
    public static function options(...$args): array
    {
        self::$called = true;

        return ['x' => 'y'];
    }
}

/**
 * Class BlueprintReplaceNameProvenanceTest
 *
 * Untrusted blueprint input has `replace-name@` removed before it is merged, so a
 * field from a page-authored override is always attributed at its own path and
 * keeps its untrusted provenance.
 *
 * A trusted base blueprint extended with an untrusted, page-authored form override
 * models FlexForm::getBlueprint() / FlexDirectoryForm::getBlueprint(), which append
 * a page-frontmatter form to a file-defined directory blueprint.
 *
 * Naming convention: test{Method}_{issue}_{description}
 */
class BlueprintReplaceNameProvenanceTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BlueprintReplaceNameProbe::reset();
    }

    /**
     * @return array<string,bool>
     */
    protected function dynamicTrustOf(Blueprint $blueprint): array
    {
        $property = new ReflectionProperty(Blueprint::class, 'dynamicTrust');

        return (array) $property->getValue($blueprint);
    }

    /**
     * Run a callback with the vendor deepMerge()'s harmless "Undefined array key
     * name" notice suppressed, so the test measures trust attribution rather than a
     * pre-existing warning Codeception would otherwise escalate to an error.
     *
     * @param callable $fn
     * @return void
     */
    protected function withoutVendorNotices(callable $fn): void
    {
        set_error_handler(static function (int $errno): bool {
            return ($errno & (E_WARNING | E_NOTICE | E_DEPRECATED)) !== 0;
        });
        try {
            $fn();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * An untrusted override relocates its directive to a NEW top-level
     * key via `replace-name@`. The provider must not run.
     */
    public function testInit_RelocatedUntrustedDirectiveIsNotExecuted(): void
    {
        $provider = '\\' . BlueprintReplaceNameProbe::class . '::options';

        // Trusted, file-shaped base blueprint.
        $blueprint = new Blueprint(null, ['form' => ['fields' => ['title' => ['type' => 'text']]]], true);

        // Untrusted, page-authored override that renames `title` to a new key and
        // smuggles a `data-options@` directive along with it.
        $this->withoutVendorNotices(function () use ($blueprint, $provider) {
            $blueprint->extend(['form' => ['fields' => ['title' => [
                'replace-name@' => 1,
                'name' => 'pwned',
                'data-options@' => [$provider, 'user/data'],
            ]]]], true);

            $blueprint->init();
        });

        self::assertFalse(
            BlueprintReplaceNameProbe::$called,
            'A page-authored provider relocated via replace-name@ must not be invoked'
        );

        foreach ($this->dynamicTrustOf($blueprint) as $path => $trusted) {
            self::assertFalse($trusted, "Directive at {$path} came from untrusted input and must stay untrusted");
        }
    }

    /**
     * Sibling: relocating onto an EXISTING trusted dynamic field's key must not let
     * the untrusted directive overwrite it and inherit that field's trust.
     */
    public function testInit_RelocationOntoExistingTrustedFieldIsNotExecuted(): void
    {
        $provider = '\\' . BlueprintReplaceNameProbe::class . '::options';

        $blueprint = new Blueprint(null, ['form' => ['fields' => ['topping' => ['type' => 'select']]]], true);

        $this->withoutVendorNotices(function () use ($blueprint, $provider) {
            $blueprint->extend(['form' => ['fields' => ['topping' => [
                'replace-name@' => 1,
                'name' => 'topping',
                'data-options@' => [$provider, 'user/data'],
            ]]]], true);

            $blueprint->init();
        });

        self::assertFalse(
            BlueprintReplaceNameProbe::$called,
            'A page-authored provider relocated onto an existing field must not be invoked'
        );
    }

    /**
     * Negative control: a trusted source may still use replace-name@ and its
     * provider is honoured. Guards against the fix over-stripping legitimate use.
     */
    public function testInit_TrustedRelocationStillRuns(): void
    {
        $provider = '\\' . BlueprintReplaceNameProbe::class . '::options';

        $blueprint = new Blueprint(null, ['form' => ['fields' => ['title' => ['type' => 'text']]]], true);

        $this->withoutVendorNotices(function () use ($blueprint, $provider) {
            $blueprint->extend(['form' => ['fields' => ['title' => [
                'replace-name@' => 1,
                'name' => 'renamed',
                'data-options@' => $provider,
            ]]]], true, true); // trusted = true

            $blueprint->init();
        });

        self::assertTrue(
            BlueprintReplaceNameProbe::$called,
            'A trusted blueprint using replace-name@ must still invoke its provider'
        );
    }
}
