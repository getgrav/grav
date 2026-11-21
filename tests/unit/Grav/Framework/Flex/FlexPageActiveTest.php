<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Pages\FlexPageCollection;
use Grav\Framework\Flex\Pages\FlexPageIndex;
use Grav\Framework\Flex\Pages\FlexPageObject;

/**
 * Flex pages answer active() and activeChild() from the site's route map like regular pages:
 * one route lookup per call, and the active page's ancestors worked out once per URI.
 */
class FlexPageActiveTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();
        $this->folder = 'tmp/flex-page-active-' . uniqid('', false);
    }

    protected function tearDown(): void
    {
        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    public static function objectClasses(): array
    {
        return [
            'site page object' => [PageObject::class],
            'framework page object' => [FlexPageObject::class],
        ];
    }

    /**
     * @dataProvider objectClasses
     */
    public function testActiveAndActiveChildFollowTheUri(string $objectClass): void
    {
        $directory = $this->directory(['01.blog', '01.blog/01.post', '01.blog/01.post/gallery', '02.about'], $objectClass);

        $grav = Grav::instance();
        $grav['locator']->addPath('page', '', $this->folder, false);
        /** @var \Grav\Common\Page\Pages $pages */
        $pages = $grav['pages'];
        $pages->register();
        $pages->init();

        // Flex keys drop the numeric folder prefixes.
        $blog = $directory->getObject('blog');
        self::assertInstanceOf($objectClass, $blog);
        $post = $directory->getObject('blog/post');
        $gallery = $directory->getObject('blog/post/gallery');
        $about = $directory->getObject('about');

        $grav['uri']->initializeWithUrl('http://example.com/blog/post/gallery')->init();
        self::assertTrue($gallery->active());
        self::assertFalse($post->active());
        self::assertTrue($blog->activeChild());
        self::assertTrue($post->activeChild());
        self::assertFalse($gallery->activeChild(), 'The active page is not its own ancestor');
        self::assertFalse($about->active());
        self::assertFalse($about->activeChild());

        $grav['uri']->initializeWithUrl('http://example.com/about')->init();
        self::assertTrue($about->active());
        self::assertFalse($blog->activeChild());
        self::assertFalse($gallery->active());

        $grav['uri']->initializeWithUrl('http://example.com/nowhere')->init();
        self::assertFalse($about->active());
        self::assertFalse($blog->activeChild());
    }

    /**
     * @param string[] $folders
     */
    private function directory(array $folders, string $objectClass): FlexDirectory
    {
        foreach ($folders as $folder) {
            $path = GRAV_ROOT . "/{$this->folder}/{$folder}";
            mkdir($path, 0777, true);
            file_put_contents("{$path}/default.md", "---\ntitle: " . basename($folder) . "\n---\n");
        }

        $data = ['object' => $objectClass, 'storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]];
        if ($objectClass === FlexPageObject::class) {
            // The site's page index and collection only hold site page objects.
            $data['collection'] = FlexPageCollection::class;
            $data['index'] = FlexPageIndex::class;
        }

        return new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => $data,
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);
    }
}
