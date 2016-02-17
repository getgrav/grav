<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Console\Gpm\InstallCommand;

/**
 * Class InstallCommandTest
 */
class InstallCommandTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    protected function _before()
    {
        $this->grav = Fixtures::get('grav');
        $this->installCommand = new InstallCommand();
    }

    protected function _after()
    {
    }

    public function testTest()
    {
        //////////////////////////////////////////////////////////////////////////////////////////
        //First example
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->data = [
            [
                'admin' => (object)[
                    'dependencies_versions' => [
                        (object)["name" => "grav", "version" => ">=1.0.10"],
                        (object)["name" => "form", "version" => "~2.0"],
                        (object)["name" => "login", "version" => ">=2.0"],
                        (object)["name" => "errors", "version" => "*"],
                        (object)["name" => "problems"],
                    ]
                ],
                'test' => (object)[
                    'dependencies_versions' => [
                        (object)["name" => "errors", "version" => ">=1.0"]
                    ]
                ]
            ]
        ];

        $dependencies = $this->installCommand->calculateMergedDependenciesOfPackages($this->data);

        $this->assertTrue(is_array($dependencies));
        $this->assertTrue(count($dependencies) == 5);

        $this->assertTrue($dependencies['grav'] == '>=1.0.10');
        $this->assertTrue($dependencies['errors'] == '>=1.0');
        $this->assertFalse($dependencies['errors'] == '*');
        $this->assertTrue($dependencies['problems'] == '*');

        //////////////////////////////////////////////////////////////////////////////////////////
        //Second example
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->data = [
            [
                'admin' => (object)[
                    'dependencies_versions' => [
                        (object)["name" => "errors", "version" => "*"],
                    ]
                ],
                'test' => (object)[
                    'dependencies_versions' => [
                        (object)["name" => "errors", "version" => ">=1.0"]
                    ]
                ],
                'another' => (object)[
                    'dependencies_versions' => [
                        (object)["name" => "errors", "version" => ">=3.2"]
                    ]
                ]
            ]
        ];

        $dependencies = $this->installCommand->calculateMergedDependenciesOfPackages($this->data);

        $this->assertTrue(is_array($dependencies));
        $this->assertTrue(count($dependencies) == 1);
        $this->assertTrue($dependencies['errors'] == '>=3.2');

        //////////////////////////////////////////////////////////////////////////////////////////
        //Second example
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->data = [
            [
                'admin' => (object)[
                    'dependencies_versions' => [
                        (object)["name" => "errors", "version" => ">=4.0"],
                    ]
                ],
                'test' => (object)[
                    'dependencies_versions' => [
                        (object)["name" => "errors", "version" => ">=1.0"]
                    ]
                ],
                'another' => (object)[
                    'dependencies_versions' => [
                        (object)["name" => "errors", "version" => ">=3.2"]
                    ]
                ]
            ]
        ];

        $dependencies = $this->installCommand->calculateMergedDependenciesOfPackages($this->data);

        $this->assertTrue(is_array($dependencies));
        $this->assertTrue(count($dependencies) == 1);
        $this->assertTrue($dependencies['errors'] == '>=4.0');

    public function testVersionFormatIsNextSignificantRelease()
    {
        $this->assertFalse($this->installCommand->versionFormatIsNextSignificantRelease('>=1.0'));
        $this->assertFalse($this->installCommand->versionFormatIsNextSignificantRelease('>=2.3.4'));
        $this->assertFalse($this->installCommand->versionFormatIsNextSignificantRelease('>=2.3.x'));
        $this->assertFalse($this->installCommand->versionFormatIsNextSignificantRelease('1.0'));
        $this->assertTrue($this->installCommand->versionFormatIsNextSignificantRelease('~2.3.x'));
        $this->assertTrue($this->installCommand->versionFormatIsNextSignificantRelease('~2.0'));
    }

    public function testVersionFormatIsEqualOrHigher()
    {
        $this->assertTrue($this->installCommand->versionFormatIsEqualOrHigher('>=1.0'));
        $this->assertTrue($this->installCommand->versionFormatIsEqualOrHigher('>=2.3.4'));
        $this->assertTrue($this->installCommand->versionFormatIsEqualOrHigher('>=2.3.x'));
        $this->assertFalse($this->installCommand->versionFormatIsEqualOrHigher('~2.3.x'));
        $this->assertFalse($this->installCommand->versionFormatIsEqualOrHigher('1.0'));
    }

    public function testCheckNextSignificantReleasesAreCompatible()
    {
        /*
         * ~1.0     is equivalent to >=1.0 < 2.0.0
         * ~1.2     is equivalent to >=1.2 <2.0.0
         * ~1.2.3   is equivalent to >=1.2.3 <1.3.0
         */
        $this->assertTrue($this->installCommand->checkNextSignificantReleasesAreCompatible('1.0', '1.2'));
        $this->assertTrue($this->installCommand->checkNextSignificantReleasesAreCompatible('1.2', '1.0'));
        $this->assertTrue($this->installCommand->checkNextSignificantReleasesAreCompatible('1.0', '1.0.10'));
        $this->assertTrue($this->installCommand->checkNextSignificantReleasesAreCompatible('1.1', '1.1.10'));
        $this->assertTrue($this->installCommand->checkNextSignificantReleasesAreCompatible('30.0', '30.10'));
        $this->assertTrue($this->installCommand->checkNextSignificantReleasesAreCompatible('1.0', '1.1.10'));
        $this->assertTrue($this->installCommand->checkNextSignificantReleasesAreCompatible('1.0', '1.8'));
        $this->assertTrue($this->installCommand->checkNextSignificantReleasesAreCompatible('1.0.1', '1.1'));

        $this->assertFalse($this->installCommand->checkNextSignificantReleasesAreCompatible('1.0', '2.2'));
        $this->assertFalse($this->installCommand->checkNextSignificantReleasesAreCompatible('0.9.99', '1.0.0'));
        $this->assertFalse($this->installCommand->checkNextSignificantReleasesAreCompatible('0.9.99', '1.0.10'));
        $this->assertFalse($this->installCommand->checkNextSignificantReleasesAreCompatible('0.9.99', '1.0.10.2'));
    }


    public function testCalculateVersionNumberFromDependencyVersion()
    {
        $this->assertSame('2.0', $this->installCommand->calculateVersionNumberFromDependencyVersion('>=2.0'));
        $this->assertSame('2.0.2', $this->installCommand->calculateVersionNumberFromDependencyVersion('>=2.0.2'));
        $this->assertSame('2.0.2', $this->installCommand->calculateVersionNumberFromDependencyVersion('~2.0.2'));
        $this->assertSame('1', $this->installCommand->calculateVersionNumberFromDependencyVersion('~1'));
        $this->assertSame(null, $this->installCommand->calculateVersionNumberFromDependencyVersion(''));
        $this->assertSame(null, $this->installCommand->calculateVersionNumberFromDependencyVersion('*'));
        $this->assertSame(null, $this->installCommand->calculateVersionNumberFromDependencyVersion('2.0.2'));
    }
}