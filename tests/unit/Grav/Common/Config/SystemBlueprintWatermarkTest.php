<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprints;
use Symfony\Component\Yaml\Yaml;

/**
 * A user config that only sets `images.watermark.watermark_all` wiped the rest of the
 * `watermark` block (image, position, scale), because the system blueprint had no
 * watermark fields and an unknown nested block is replaced, not merged.
 */
class SystemBlueprintWatermarkTest extends \Codeception\Test\Unit
{
    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();
    }

    public function testSettingOnlyWatermarkAllKeepsTheOtherWatermarkSettings(): void
    {
        $defaults = Yaml::parseFile(SYSTEM_DIR . 'config/system.yaml');
        $blueprint = (new Blueprints(SYSTEM_DIR . 'blueprints/'))->get('config/system');

        $merged = $blueprint->mergeData($defaults, ['images' => ['watermark' => ['watermark_all' => true]]]);

        $watermark = $merged['images']['watermark'];
        $this->assertTrue($watermark['watermark_all']);
        $this->assertSame('system://images/watermark.png', $watermark['image']);
        $this->assertSame(33, $watermark['scale']);
        $this->assertSame('center', $watermark['position_x']);
        $this->assertSame('center', $watermark['position_y']);
    }
}
