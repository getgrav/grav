<?php

use Grav\Common\Data\Blueprint;

/**
 * Class InstallCommandTest
 */
class BlueprintTest extends \Codeception\TestCase\Test
{
    /**
     */
    public function testValidateStrict()
    {
        $blueprint = $this->loadBlueprint('strict');

        $blueprint->validate(['test' => 'string']);
    }

    /**
     * @depends testValidateStrict
     * @expectedException Grav\Common\Data\ValidationException
     */
    public function testValidateStrictRequired()
    {
        $blueprint = $this->loadBlueprint('strict');

        $blueprint->validate([]);
    }

    /**
     * @depends testValidateStrict
     * @expectedException Grav\Common\Data\ValidationException
     */
    public function testValidateStrictExtra()
    {
        $blueprint = $this->loadBlueprint('strict');

        $blueprint->validate(['test' => 'string', 'wrong' => 'field']);
        die();
    }

    /**
     * @param string $filename
     * @return Blueprint
     */
    protected function loadBlueprint($filename)
    {
        $blueprint = new Blueprint('strict');
        $blueprint->setContext(dirname(__DIR__, 3). '/data/blueprints');
        $blueprint->load()->init();

        return $blueprint;
    }
}
