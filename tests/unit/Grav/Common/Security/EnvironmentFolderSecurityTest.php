<?php

use Grav\Common\Config\Setup;
use PHPUnit\Framework\TestCase;

/**
 * In the Grav 1.6 layout (no `user/env/`) the environment folder is
 * `user/<Host header>`. A host named after a folder that already has a job,
 * such as `pages`, must not turn that folder into a configuration overlay.
 */
class EnvironmentFolderSecurityTest extends TestCase
{
    private const USER = GRAV_WEBROOT . '/' . GRAV_USER_PATH;

    private ?string $environment;

    /** @var string[] */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (is_dir(self::USER . '/env')) {
            self::markTestSkipped('user/env exists, so the Grav 1.6 layout is not in use');
        }
        $this->environment = Setup::$environment;
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->created) as $folder) {
            @rmdir($folder);
        }
        Setup::$environment = $this->environment;
        parent::tearDown();
    }

    /**
     * @dataProvider streamFolders
     */
    public function testStreamFolderIsNotAnEnvironment(string $host): void
    {
        $this->folder(explode('/', $host)[0]);
        $this->folder($host);

        self::assertSame(['' => []], $this->environmentPrefixes($host));
    }

    public static function streamFolders(): array
    {
        return [
            ['pages'], ['data'], ['accounts'], ['config'], ['plugins'], ['themes'],
            ['media'], ['images'], ['blueprints'], ['languages'],
            ['pages/config'], ['../' . GRAV_USER_PATH . '/pages'],
        ];
    }

    public function testOwnFolderIsStillAnEnvironment(): void
    {
        // Shares its first characters with a stream folder on purpose.
        $host = 'pages.example-' . bin2hex(random_bytes(4)) . '.test';
        $this->folder($host);

        self::assertSame(['' => ["user://{$host}"]], $this->environmentPrefixes($host));
    }

    public function testHostThatIsNotASingleFolderNameIsNotAnEnvironment(): void
    {
        $base = 'env-name-' . bin2hex(random_bytes(4));
        $this->folder($base);
        $this->folder($base . '/sub');
        $this->folder('.' . $base);
        $this->folder($base . '\\sub');

        self::assertSame(['' => []], $this->environmentPrefixes($base . '/sub'));
        self::assertSame(['' => []], $this->environmentPrefixes('.' . $base));
        self::assertSame(['' => []], $this->environmentPrefixes($base . '\\sub'));
        self::assertSame(['' => []], $this->environmentPrefixes(''));
        // The plain folder itself is still a valid environment.
        self::assertSame(['' => ["user://{$base}"]], $this->environmentPrefixes($base));
    }

    public function testMissingFolderIsNotAnEnvironment(): void
    {
        $host = 'missing-' . bin2hex(random_bytes(4)) . '.test';

        self::assertSame(['' => []], $this->environmentPrefixes($host));
    }

    private function environmentPrefixes(string $host): array
    {
        Setup::$environment = $host;

        return (new Setup([]))->get('streams.schemes.environment.prefixes');
    }

    private function folder(string $name): void
    {
        $folder = self::USER . '/' . $name;
        if (!is_dir($folder) && mkdir($folder)) {
            $this->created[] = $folder;
        }
    }
}
