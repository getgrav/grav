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