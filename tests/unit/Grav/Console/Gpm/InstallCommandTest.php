<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Console\Gpm\InstallCommand;

define('EXCEPTION_BAD_FORMAT', 1);
define('EXCEPTION_INCOMPATIBLE_VERSIONS', 2);

class GpmStub extends stdClass
{
    public function findPackage($packageName)
    {
        if (isset($this->data[$packageName])) {
            return $this->data[$packageName];
        }

    }

    public function findPackages()
    {
        return $this->data;
    }
}

/**
 * Class InstallCommandTest
 */
class InstallCommandTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var InstallCommand */
    protected $installCommand;

    /** @var GpmStub */
    protected $gpm;

    protected function _before()
    {
        $this->grav = Fixtures::get('grav');
        $this->installCommand = new InstallCommand();

        $this->gpm = new GpmStub();
    }

    protected function _after()
    {
    }

    public function testCalculateMergedDependenciesOfPackages()
    {
        //////////////////////////////////////////////////////////////////////////////////////////
        // First working example
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->gpm->data = [
            'admin' => (object)[
                'dependencies_versions' => [
                    ["name" => "grav", "version" => ">=1.0.10"],
                    ["name" => "form", "version" => "~2.0"],
                    ["name" => "login", "version" => ">=2.0"],
                    ["name" => "errors", "version" => "*"],
                    ["name" => "problems"],
                ]
            ],
            'test' => (object)[
                'dependencies_versions' => [
                   ["name" => "errors", "version" => ">=1.0"]
                ]
            ],
            'grav',
            'form' => (object)[
                'dependencies_versions' => [
                    ["name" => "errors", "version" => ">=3.2"]
                ]
            ]


        ];
        $this->installCommand->setGpm($this->gpm);

        $packages = ['admin', 'test'];

//        dump($this->gpm->findPackages());exit();

        $dependencies = $this->installCommand->calculateMergedDependenciesOfPackages($packages);

        $this->assertTrue(is_array($dependencies));
        $this->assertSame(5, count($dependencies));

        $this->assertTrue($dependencies['grav'] == '>=1.0.10');
        $this->assertTrue(isset($dependencies['errors']));
        $this->assertTrue(isset($dependencies['problems']));

        //////////////////////////////////////////////////////////////////////////////////////////
        // Second working example
        //////////////////////////////////////////////////////////////////////////////////////////
        $packages = ['admin', 'form'];

        $dependencies = $this->installCommand->calculateMergedDependenciesOfPackages($packages);
        $this->assertTrue(is_array($dependencies));
        $this->assertSame(5, count($dependencies));
        $this->assertTrue($dependencies['errors'] == '>=3.2');

        //////////////////////////////////////////////////////////////////////////////////////////
        // Third working example
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->gpm->data = [

            'admin' => (object)[
                'dependencies_versions' => [
                    ["name" => "errors", "version" => ">=4.0"],
                ]
            ],
            'test' => (object)[
                'dependencies_versions' => [
                    ["name" => "errors", "version" => ">=1.0"]
                ]
            ],
            'another' => (object)[
                'dependencies_versions' => [
                    ["name" => "errors", "version" => ">=3.2"]
                ]
            ]

        ];
        $this->installCommand->setGpm($this->gpm);

        $packages = ['admin', 'test', 'another'];


        $dependencies = $this->installCommand->calculateMergedDependenciesOfPackages($packages);
        $this->assertTrue(is_array($dependencies));
        $this->assertSame(1, count($dependencies));
        $this->assertTrue($dependencies['errors'] == '>=4.0');

        //////////////////////////////////////////////////////////////////////////////////////////
        // Raise exception if no version is specified
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->gpm->data = [

            'admin' => (object)[
                'dependencies_versions' => [
                    ["name" => "errors", "version" => ">=4.0"],
                ]
            ],
            'test' => (object)[
                'dependencies_versions' => [
                    ["name" => "errors", "version" => ">="]
                ]
            ],

        ];
        $this->installCommand->setGpm($this->gpm);
        $packages = ['admin', 'test'];

        try {
            $this->installCommand->calculateMergedDependenciesOfPackages($packages);
            $this->fail("Expected Exception not thrown");
        } catch (Exception $e) {
            $this->assertEquals(EXCEPTION_BAD_FORMAT, $e->getCode());
            $this->assertStringStartsWith("Bad format for version of dependency", $e->getMessage());
        }

        //////////////////////////////////////////////////////////////////////////////////////////
        // Raise exception if incompatible versions are specified
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->gpm->data = [
                'admin' => (object)[
                    'dependencies_versions' => [
                        ["name" => "errors", "version" => "~4.0"],
                    ]
                ],
                'test' => (object)[
                    'dependencies_versions' => [
                        ["name" => "errors", "version" => "~3.0"]
                    ]
                ],
        ];
        $this->installCommand->setGpm($this->gpm);
        $packages = ['admin', 'test'];

        try {
            $this->installCommand->calculateMergedDependenciesOfPackages($packages);
            $this->fail("Expected Exception not thrown");
        } catch (Exception $e) {
            $this->assertEquals(EXCEPTION_INCOMPATIBLE_VERSIONS, $e->getCode());
            $this->assertStringEndsWith("required in two incompatible versions", $e->getMessage());
        }
    }

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