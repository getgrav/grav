<?php

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;

/**
 * Class InstallCommandTest
 */
class BlueprintTest extends \PHPUnit\Framework\TestCase
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

        $message = "Having extra key wrong in your data is deprecated with blueprint having 'validation: strict'";
        $deprecationMessages = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecationMessages): bool {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationMessages[] = $errstr;
                return true;
            }

            return false;
        });

        try {
            $blueprint->validate(['test' => 'string', 'wrong' => 'field']);
        } finally {
            restore_error_handler();
        }

        self::assertContains($message, $deprecationMessages);
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
