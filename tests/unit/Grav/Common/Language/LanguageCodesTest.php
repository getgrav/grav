<?php

use Grav\Common\Language\LanguageCodes;


/**
 * Class ParsedownTest
 */
class LanguageCodesTest extends \Codeception\TestCase\Test
{
    public function testRtl()
    {
        $this->assertSame('ltr',
            LanguageCodes::getOrientation('en'));
        $this->assertSame('rtl',
            LanguageCodes::getOrientation('ar'));
        $this->assertSame('rtl',
            LanguageCodes::getOrientation('he'));
        $this->assertTrue(LanguageCodes::isRtl('ar'));
        $this->assertFalse(LanguageCodes::isRtl('fr'));

    }

}
