<?php

/**
 * Tests for the environment name Setup publishes when it comes from the request
 * host. The name is used for the file names in cache://compiled, so a host that
 * has no environment folder must not get a name of its own.
 *
 * The unit suite defines GRAV_CLI, which skips the host lookup, so each scenario
 * runs in a child PHP process started in a throwaway Grav root.
 */
class SetupEnvironmentTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    private $root;

    protected function setUp(): void
    {
        $this->root = GRAV_ROOT . '/tests/_output/grav-setup-env-' . uniqid('', true);
        mkdir($this->root . '/user/env/site.example/config', 0777, true);
        mkdir($this->root . '/user/config');
    }

    protected function tearDown(): void
    {
        \Grav\Common\Filesystem\Folder::delete($this->root);
    }

    public function testHostWithEnvironmentFolderKeepsItsName(): void
    {
        self::assertSame('site.example', $this->environment('site.example'));
    }

    public function testHostsWithoutEnvironmentFolderShareOneName(): void
    {
        $name = $this->environment('one.example');

        self::assertStringStartsWith('@', $name);
        self::assertSame($name, $this->environment('two.example'));
        self::assertSame($name, $this->environment('127.0.0.1'));
    }

    public function testRelativeEnvironmentsPathIsCovered(): void
    {
        $server = ['GRAV_ENVIRONMENTS_PATH' => 'user/env'];

        self::assertSame('site.example', $this->environment('site.example', $server));
        self::assertStringStartsWith('@', $this->environment('one.example', $server));
    }

    public function testConfiguredEnvironmentKeepsItsName(): void
    {
        self::assertSame('staging', $this->environment('one.example', ['GRAV_ENVIRONMENT' => 'staging']));

        file_put_contents($this->root . '/setup.php', '<?php return ["environment" => "named"];');
        self::assertSame('named', $this->environment('one.example'));
    }

    public function testSitesWithTheirOwnUserFolderStayApart(): void
    {
        file_put_contents($this->root . '/setup.php', '<?php
            $site = $environment === "a.example" ? "a" : "b";
            return ["streams" => ["schemes" => ["user" => ["type" => "ReadOnlyStream", "prefixes" => ["" => ["sites/$site"]]]]]];
        ');

        $name = $this->environment('b.example');

        self::assertNotSame($name, $this->environment('a.example'));
        self::assertSame($name, $this->environment('c.example'));
    }

    /**
     * Boot Setup for a web request to the given host and return its environment name.
     *
     * @param string $host
     * @param array<string,string> $server Values set in $_SERVER before the autoloader runs.
     * @return string
     */
    private function environment(string $host, array $server = []): string
    {
        $script = $this->root . '/child.php';
        file_put_contents($script, '<?php
            foreach (json_decode($argv[3], true) as $k => $v) { $_SERVER[$k] = $v; }
            require $argv[1] . "/vendor/autoload.php";
            $request = new \Nyholm\Psr7\ServerRequest("GET", "http://" . $argv[2] . "/");
            $setup = (new \Grav\Common\Config\Setup(["request" => $request]))->init();
            echo json_encode(["environment" => $setup->environment]);
        ');

        $cmd = [PHP_BINARY, $script, dirname(__DIR__, 5), $host, json_encode((object)$server)];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
        self::assertIsResource($proc);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        proc_close($proc);

        $decoded = json_decode($stdout, true);
        self::assertIsArray($decoded, "child failed:\n{$stdout}\n{$stderr}");

        return $decoded['environment'];
    }
}
