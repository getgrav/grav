<?php

use Grav\Common\Language\LanguageCodes;

/**
 * Class ParsedownTest
 */
class LanguageCodesTest extends \Codeception\TestCase\Test
{
    public function testRtl(): void
    {
        self::assertSame(
            'ltr',
            LanguageCodes::getOrientation('en')
        );
        self::assertSame(
            'rtl',
            LanguageCodes::getOrientation('ar')
        );
        self::assertSame(
            'rtl',
            LanguageCodes::getOrientation('he')
        );
        self::assertTrue(LanguageCodes::isRtl('ar'));
        self::assertFalse(LanguageCodes::isRtl('fr'));
    }
}
