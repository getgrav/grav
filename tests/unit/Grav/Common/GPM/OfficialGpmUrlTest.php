<?php

use Codeception\Util\Fixtures;
use Grav\Common\GPM\GPM;
use Grav\Common\Grav;

/**
 * Official package URLs require HTTPS on the standard port.
 */
class OfficialGpmUrlTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var mixed */
    protected $official_gpm_only;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->official_gpm_only = $this->grav['config']->get('system.gpm.official_gpm_only');
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('system.gpm.official_gpm_only', $this->official_gpm_only);
        parent::tearDown();
    }

    /**
     * @dataProvider providerOfficialUrls
     */
    public function testIsOfficialUrl_AcceptsHttpsOnTheOfficialHost(string $url): void
    {
        self::assertTrue(GPM::isOfficialUrl($url), "Should accept: $url");
    }

    public static function providerOfficialUrls(): array
    {
        return [
            ['https://getgrav.org/download/plugins/form/9.1.0'],
            ['HTTPS://GetGrav.org:443/download/plugins/form/9.1.0'],
            ['https://getgrav.org/download/plugins/form/9.1.0?d=abc'],
        ];
    }

    /**
     * @dataProvider providerUnofficialUrls
     */
    public function testIsOfficialUrl_RefusesEverythingElse(string $url, string $description): void
    {
        self::assertFalse(GPM::isOfficialUrl($url), "Should refuse: $description");
    }

    public static function providerUnofficialUrls(): array
    {
        return [
            ['http://getgrav.org/download/plugins/form/9.1.0', 'plain http on the official host'],
            ['http://getgrav.org:8081/download/plugins/form/9.1.0', 'plain http on another port'],
            ['ftp://getgrav.org/form.zip', 'other scheme'],
            ['//getgrav.org/form.zip', 'no scheme'],
            ['https://getgrav.org:8443/download/plugins/form/9.1.0', 'https on a non-standard port'],
            ['https://example.com/form.zip', 'other host'],
            ['https://getgrav.org.example.com/form.zip', 'host prefixed with the official name'],
            ['https://getgrav.org@example.com/form.zip', 'official name as userinfo'],
            ['https://example.com/#@getgrav.org/form.zip', 'official name in the fragment'],
            ['https:///form.zip', 'no host'],
            ['', 'empty'],
        ];
    }

    public function testDownloadPackage_RefusesPlainHttpBeforeRequesting(): void
    {
        $this->grav['config']->set('system.gpm.official_gpm_only', true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only official GPM URLs are allowed');

        GPM::downloadPackage('http://getgrav.org/download/plugins/form/9.1.0', sys_get_temp_dir() . '/grav-official-gpm-test');
    }

    public function testDownloadPackage_RefusesOtherHostsOverHttps(): void
    {
        $this->grav['config']->set('system.gpm.official_gpm_only', true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only official GPM URLs are allowed');

        GPM::downloadPackage('https://example.com/download/plugins/form/9.1.0', sys_get_temp_dir() . '/grav-official-gpm-test');
    }
}
