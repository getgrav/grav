<?php

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;

/**
 * Sites unpacked from the same release zip share the APCu prefix Composer baked into
 * vendor/composer/autoload_real.php, and APCu is shared by every PHP-FPM pool of one master.
 * One site's cached class path was then included by the others (grav#4392, YunoHost
 * /var/www/grav and /var/www/grav__2). The prefix has to be unique per install root.
 */
class AutoloaderApcuPrefixTest extends \PHPUnit\Framework\TestCase
{
    private const BAKED = '2fd8d2a6b25586e49984';

    protected function tearDown(): void
    {
        Grav::resetInstance();
        parent::tearDown();
    }

    public function testTwoInstallsOfOneReleaseGetDifferentPrefixes(): void
    {
        $a = $this->loader(self::BAKED);
        $b = $this->loader(self::BAKED);

        Grav::scopeApcuPrefix($a, '/var/www/grav');
        Grav::scopeApcuPrefix($b, '/var/www/grav__2');

        self::assertStringStartsWith(self::BAKED, $a->getApcuPrefix());
        self::assertNotSame($a->getApcuPrefix(), $b->getApcuPrefix());
    }

    public function testScopingIsStableAndIdempotent(): void
    {
        $a = $this->loader(self::BAKED);
        Grav::scopeApcuPrefix($a, '/var/www/grav');
        $first = $a->getApcuPrefix();
        Grav::scopeApcuPrefix($a, '/var/www/grav');

        $b = $this->loader(self::BAKED);
        Grav::scopeApcuPrefix($b, '/var/www/grav');

        self::assertSame($first, $a->getApcuPrefix());
        self::assertSame($first, $b->getApcuPrefix());
    }

    public function testLoaderWithoutApcuIsLeftAlone(): void
    {
        $loader = $this->loader(null);
        Grav::scopeApcuPrefix($loader, '/var/www/grav');

        self::assertNull($loader->getApcuPrefix());
    }

    public function testGravInstanceScopesTheLoaderItIsGiven(): void
    {
        Grav::resetInstance();
        $loader = $this->loader(self::BAKED);
        Grav::instance(['loader' => $loader]);

        self::assertSame(self::BAKED . '-' . substr(md5(GRAV_ROOT), 0, 12), $loader->getApcuPrefix());
    }

    /**
     * ClassLoader drops the prefix when APCu is not loaded, so keep it ourselves.
     */
    private function loader(?string $prefix): ClassLoader
    {
        $loader = new class extends ClassLoader {
            public ?string $prefix = null;

            public function setApcuPrefix($apcuPrefix)
            {
                $this->prefix = $apcuPrefix;
            }

            public function getApcuPrefix()
            {
                return $this->prefix;
            }
        };
        $loader->setApcuPrefix($prefix);

        return $loader;
    }
}
