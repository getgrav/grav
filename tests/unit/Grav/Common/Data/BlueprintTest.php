<?php

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;

/**
 * Class InstallCommandTest
 */
class BlueprintTest extends \Codeception\TestCase\Test
{
    /**
     */
    public function testValidateStrict(): void
    {
        $blueprint = $this->loadBlueprint('strict');

        $blueprint->validate(['test' => 'string']);
    }

    /**
     * @depends testValidateStrict
     */
    public function testValidateStrictRequired(): void
    {
        $blueprint = $this->loadBlueprint('strict');

        $this->expectException(\Grav\Common\Data\ValidationException::class);
        $blueprint->validate([]);
    }

    /**
     * @depends testValidateStrict
     */
    public function testValidateStrictExtra(): void
    {
        $blueprint = $this->loadBlueprint('strict');

        $blueprint->validate(['test' => 'string', 'wrong' => 'field']);
    }

    /**
     * @depends testValidateStrict
     */
    public function testValidateStrictExtraException(): void
    {
        $blueprint = $this->loadBlueprint('strict');

        /** @var Config $config */
        $config = Grav::instance()['config'];
        $var = 'system.strict_mode.blueprint_strict_compat';
        $config->set($var, false);

        $this->expectException(\Grav\Common\Data\ValidationException::class);
        $blueprint->validate(['test' => 'string', 'wrong' => 'field']);

        $config->set($var, true);
    }

    /**
     * @param string $filename
     * @return Blueprint
     */
    protected function loadBlueprint($filename): Blueprint
    {
        $blueprint = new Blueprint('strict');
        $blueprint->setContext(dirname(__DIR__, 3). '/data/blueprints');
        $blueprint->load()->init();

        return $blueprint;
    }
}
