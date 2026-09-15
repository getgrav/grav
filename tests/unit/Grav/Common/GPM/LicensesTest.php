<?php

use Grav\Common\Data\Data;
use Grav\Common\GPM\Common\Package;
use Grav\Common\GPM\Licenses;
use RocketTheme\Toolbox\File\FileInterface;

/**
 * Class LicensesTest
 *
 * Covers which key a package is handed, which is the difference between an
 * add-on a customer owns and one the admin offers to sell them again.
 */
class LicensesTest extends \PHPUnit\Framework\TestCase
{
    /** @var ReflectionProperty */
    protected $fileProperty;

    /** @var mixed */
    protected $originalFile;

    protected function setUp(): void
    {
        $this->fileProperty = new ReflectionProperty(Licenses::class, 'file');
        $this->originalFile = $this->fileProperty->isInitialized() ? $this->fileProperty->getValue() : null;
    }

    protected function tearDown(): void
    {
        if ($this->originalFile === null) {
            $this->fileProperty->setValue(null, null);
        } else {
            $this->fileProperty->setValue(null, $this->originalFile);
        }
    }

    /**
     * Stand the licence file in for an in-memory one, so the resolver can be
     * exercised without a Grav locator or anything on disk.
     *
     * @param array<string, string> $licenses slug => key
     * @return void
     */
    protected function storedLicenses(array $licenses)
    {
        $file = $this->createMock(FileInterface::class);
        $file->method('content')->willReturn(['licenses' => $licenses]);

        $this->fileProperty->setValue(null, $file);
    }

    /**
     * @param string $slug
     * @param array|null $premium
     * @return Package
     */
    protected function package($slug, $premium = null)
    {
        $data = ['slug' => $slug];
        if ($premium !== null) {
            $data['premium'] = $premium;
        }

        return new Package(new Data($data), 'plugins');
    }

    public function testResolveReturnsTheKeyFiledUnderTheSlug()
    {
        $this->storedLicenses(['kahunacart' => 'KC-AAAA-BBBB-CCCC-DDDD']);

        self::assertSame('KC-AAAA-BBBB-CCCC-DDDD', Licenses::resolve('kahunacart'));
    }

    public function testResolveIsCaseInsensitiveOnTheSlug()
    {
        $this->storedLicenses(['kahunacart' => 'KC-AAAA-BBBB-CCCC-DDDD']);

        self::assertSame('KC-AAAA-BBBB-CCCC-DDDD', Licenses::resolve('KahunaCart'));
    }

    public function testResolveFallsBackToTheLicenseProduct()
    {
        // The customer pasted one key, against the product they bought. The
        // provider is a package inside that licence and has no key of its own.
        $this->storedLicenses(['kahunacart' => 'KC-AAAA-BBBB-CCCC-DDDD']);

        $license = Licenses::resolve('kahunacart-stripe', ['license_product' => 'kahunacart']);

        self::assertSame('KC-AAAA-BBBB-CCCC-DDDD', $license);
    }

    public function testResolvePrefersTheSlugsOwnKeyOverTheProducts()
    {
        $this->storedLicenses([
            'kahunacart' => 'KC-AAAA-BBBB-CCCC-DDDD',
            'kahunacart-stripe' => 'KC-EEEE-FFFF-GGGG-HHHH',
        ]);

        $license = Licenses::resolve('kahunacart-stripe', ['license_product' => 'kahunacart']);

        self::assertSame('KC-EEEE-FFFF-GGGG-HHHH', $license);
    }

    public function testResolveFindsNothingWhenNeitherIsLicensed()
    {
        $this->storedLicenses(['something-else' => 'KC-AAAA-BBBB-CCCC-DDDD']);

        self::assertSame('', Licenses::resolve('kahunacart-stripe', ['license_product' => 'kahunacart']));
    }

    public function testResolveReadsPremiumMetadataAsAnObjectToo()
    {
        $this->storedLicenses(['kahunacart' => 'KC-AAAA-BBBB-CCCC-DDDD']);

        $premium = (object)['license_product' => 'kahunacart'];

        self::assertSame('KC-AAAA-BBBB-CCCC-DDDD', Licenses::resolve('kahunacart-stripe', $premium));
    }

    /**
     * An installed package's blueprint says `premium: true` and names no
     * product. That must resolve to nothing rather than raise.
     */
    public function testResolveTreatsPremiumMetadataThatNamesNoProductAsNamingNone()
    {
        $this->storedLicenses(['kahunacart' => 'KC-AAAA-BBBB-CCCC-DDDD']);

        self::assertSame('', Licenses::resolve('kahunacart-stripe', true));
        self::assertSame('', Licenses::resolve('kahunacart-stripe', null));
        self::assertSame('', Licenses::resolve('kahunacart-stripe', []));
    }

    public function testForPackageResolvesFromThePackagesOwnMetadata()
    {
        $this->storedLicenses(['kahunacart' => 'KC-AAAA-BBBB-CCCC-DDDD']);

        $package = $this->package('kahunacart-stripe', ['license_product' => 'kahunacart']);

        self::assertSame('KC-AAAA-BBBB-CCCC-DDDD', Licenses::forPackage($package));
    }

    public function testForPackageWithoutAPackageFindsNothing()
    {
        $this->storedLicenses(['kahunacart' => 'KC-AAAA-BBBB-CCCC-DDDD']);

        self::assertSame('', Licenses::forPackage(null));
        self::assertSame('', Licenses::forPackage(false));
    }
}
