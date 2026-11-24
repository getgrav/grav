<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Taxonomy;

/**
 * Class TaxonomyTest
 */
class TaxonomyTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    public function testTaxonomySetterStoresArrays(): void
    {
        $taxonomy = new Taxonomy($this->grav);

        $this->assertSame(['tag' => ['a' => ['page' => []]]], $taxonomy->taxonomy(['tag' => ['a' => ['page' => []]]]));
    }

    public function testTaxonomySetterIgnoresNonArrays(): void
    {
        $taxonomy = new Taxonomy($this->grav);
        $map = ['tag' => ['a' => ['page' => []]]];
        $taxonomy->taxonomy($map);

        $this->assertSame($map, $taxonomy->taxonomy(new \ArrayObject(['config' => ['x' => ['secret' => []]]])));
        $this->assertSame($map, $taxonomy->taxonomy($this->grav));
        $this->assertSame($map, $taxonomy->taxonomy('string'));
        $this->assertCount(0, $taxonomy->findTaxonomy(['config' => ['x']]));
    }
}
