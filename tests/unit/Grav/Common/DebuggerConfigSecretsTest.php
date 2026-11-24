<?php

use Clockwork\Storage\FileStorage;
use Codeception\Util\Fixtures;
use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Security;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

/**
 * DebugBar prints the Config and Plugins panels into every HTML page, for any
 * visitor, and Clockwork stores the same two trees in its profiles. Neither may
 * carry the site's credentials: `system.debugger.token` alone opens every stored
 * profile on `/__clockwork/`.
 */
class DebuggerConfigSecretsTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    protected function tearDown(): void
    {
        $this->grav['debugger']->enabled(false);
        parent::tearDown();
    }

    private function censor(array $tree, string $path, array $config = []): array
    {
        $method = new ReflectionMethod(Debugger::class, 'censorDeniedPaths');
        $denied = Security::effectiveConfigDeniedPaths(new Config($config));

        return $method->invoke($this->grav['debugger'], $tree, $path, $denied);
    }

    public function testPathsHiddenFromSandboxedTwigAreCensored(): void
    {
        $system = [
            'debugger' => ['enabled' => true, 'provider' => 'debugbar', 'token' => 'dbg-secret'],
            'cache' => ['redis' => ['server' => 'localhost', 'password' => 'redis-secret']],
            // Credentials travel as userinfo here, under a name no pattern would catch.
            'http' => ['proxy_url' => 'http://user:pass@proxy:3128'],
            'gpm' => ['proxy_url' => 'http://user:pass@proxy:3128', 'releases' => 'stable'],
        ];

        $this->assertSame(
            [
                'debugger' => ['enabled' => true, 'provider' => 'debugbar', 'token' => 'CENSORED'],
                'cache' => ['redis' => ['server' => 'localhost', 'password' => 'CENSORED']],
                'http' => ['proxy_url' => 'CENSORED'],
                'gpm' => ['proxy_url' => 'CENSORED', 'releases' => 'stable'],
            ],
            $this->censor($system, 'system')
        );
    }

    public function testPathsAddedBySiteAreCensored(): void
    {
        $system = ['shop' => ['gateway' => ['merchant' => 'm-1', 'signature' => 's-1'], 'currency' => 'EUR']];
        $config = ['security' => ['twig_sandbox' => ['config_denied_paths' => ['system.shop.gateway']]]];

        $this->assertSame(
            ['shop' => ['gateway' => 'CENSORED', 'currency' => 'EUR']],
            $this->censor($system, 'system', $config)
        );
    }

    public function testPluginSettingsAreCensoredAsAWhole(): void
    {
        $plugins = ['email' => ['enabled' => true, 'mailer' => ['smtp' => ['password' => 'smtp-secret']]]];

        $this->assertSame(['CENSORED' => true], $this->censor($plugins, 'plugins'));
    }

    public function testDebugBarPanelsDoNotCarrySecrets(): void
    {
        $config = $this->grav['config'];
        $config->set('system.debugger.enabled', true);
        $config->set('system.debugger.provider', 'debugbar');
        $config->set('system.debugger.censored', false);
        $config->set('system.debugger.token', 'dbg-secret');
        $config->set('system.cache.redis.password', 'redis-secret');
        $config->set('system.http.proxy_url', 'http://user:proxy-secret@proxy:3128');
        $config->set('plugins.email.mailer.smtp.password', 'smtp-secret');

        $debugger = $this->grav['debugger'];
        $debugger->init();

        $panels = json_encode([
            $debugger->getCollector('Config')->collect(),
            $debugger->getCollector('Plugins')->collect(),
        ], JSON_THROW_ON_ERROR);

        foreach (['dbg-secret', 'redis-secret', 'proxy-secret', 'smtp-secret'] as $secret) {
            $this->assertFalse(str_contains($panels, $secret), "{$secret} is printed in the debug bar");
        }
        // The rest of the configuration is still there to debug with.
        $this->assertTrue(str_contains($panels, 'CENSORED') && str_contains($panels, 'debugbar'));
    }

    public function testClockworkConfigurationLogDoesNotCarrySecrets(): void
    {
        $config = $this->grav['config'];
        $config->set('system.debugger.enabled', true);
        $config->set('system.debugger.provider', 'clockwork');
        $config->set('system.debugger.censored', false);
        $config->set('system.debugger.token', 'dbg-secret');
        $config->set('plugins.email.mailer.smtp.password', 'smtp-secret');

        $debugger = $this->grav['debugger'];
        $debugger->init();

        $folder = sys_get_temp_dir() . '/grav-clockwork-' . bin2hex(random_bytes(6));
        $storage = new FileStorage($folder);
        $debugger->getClockwork()->setStorage($storage);

        try {
            $debugger->logRequest(new ServerRequest('GET', '/'), new Response());

            $profile = $storage->latest()->toArray();
        } finally {
            Folder::delete($folder);
        }

        // The "System Configuration" and "Plugins Configuration" entries.
        $stored = json_encode($profile['log'], JSON_THROW_ON_ERROR);
        foreach (['dbg-secret', 'smtp-secret'] as $secret) {
            $this->assertFalse(str_contains($stored, $secret), "{$secret} is stored in the profile");
        }
        $this->assertTrue(str_contains($stored, 'CENSORED') && str_contains($stored, 'clockwork'));
    }

    public function testLoginEventsAreRecordedWithoutCredentials(): void
    {
        $config = $this->grav['config'];
        $config->set('system.debugger.enabled', true);
        $config->set('system.debugger.provider', 'clockwork');
        $config->set('system.debugger.token', 'dbg-secret');

        $debugger = $this->grav['debugger'];
        $debugger->init();

        $user = new \Grav\Common\Data\Data(['username' => 'admin', 'hashed_password' => 'hash-secret']);
        $login = new class(['credentials' => ['username' => 'admin', 'password' => 'login-secret'], 'user' => $user]) extends \RocketTheme\Toolbox\Event\Event {
            public function __debugInfo(): array
            {
                return get_object_vars($this);
            }
        };
        $other = new class(['value' => 'kept']) extends \RocketTheme\Toolbox\Event\Event {
            public function __debugInfo(): array
            {
                return get_object_vars($this);
            }
        };

        $dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
        $debugger->addEvent('onUserLoginAuthenticate', $login, $dispatcher);
        $debugger->addEvent('onPageInitialized', $other, $dispatcher);

        $events = $debugger->getClockwork()->request()->events;
        $this->assertSame('admin', $events[0]['data']['username'] ?? null);
        $stored = json_encode($events, JSON_THROW_ON_ERROR | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $this->assertFalse(str_contains($stored, 'login-secret') || str_contains($stored, 'hash-secret'));
        // Any other event is still recorded with its data.
        $this->assertTrue(str_contains($stored, 'kept'));
    }
}
