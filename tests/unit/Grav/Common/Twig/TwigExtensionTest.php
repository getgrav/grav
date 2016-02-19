<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Twig\TwigExtension;

/**
 * Class TwigExtensionTest
 */
class TwigExtensionTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var  TwigExtension $twig_ext */
    protected $twig_ext;

    protected function _before()
    {
        $this->grav = Fixtures::get('grav');
        $this->twig_ext = new TwigExtension();
    }

    public function testArrayKeyValue()
    {
        $this->assertSame(['meat' => 'steak'],
            $this->twig_ext->arrayKeyValueFunc('meat', 'steak'));
        $this->assertSame(['fruit' => 'apple', 'meat' => 'steak'],
            $this->twig_ext->arrayKeyValueFunc('meat', 'steak', ['fruit' => 'apple']));
    }

}
