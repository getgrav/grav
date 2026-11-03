<?php

use Grav\Common\Config\WebrootPublisher;
use Grav\Common\Filesystem\Folder;

/**
 * Publishing the URL-linked files of system/ into the webroot of the public webroot layout.
 */
class WebrootPublisherTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    private $root;
    /** @var string */
    private $source;
    /** @var string */
    private $target;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/grav-publish-' . bin2hex(random_bytes(6));
        $this->source = $this->root . '/system';
        $this->target = $this->root . '/public/system';
        $this->write('system/assets/jquery/jquery.min.js', 'jquery');
        $this->write('system/assets/debugger/clockwork.css', 'css');
        $this->write('system/images/media/thumb-pdf.png', 'png');
        // Never published: wrong type, or a folder that isn't linked by URL.
        $this->write('system/assets/secret.php', '<?php');
        $this->write('system/assets/notes.yaml', 'a: 1');
        $this->write('system/images/page.html.twig', '{{ x }}');
        $this->write('system/config/system.yaml', 'a: 1');
        $this->write('system/templates/logo.png', 'png');
    }

    protected function tearDown(): void
    {
        Folder::delete($this->root);
    }

    public function testSyncPublishesOnlyStaticFilesOfTheLinkedFolders(): void
    {
        $publisher = new WebrootPublisher($this->source, $this->target);
        $plan = $publisher->sync();

        self::assertSame(['assets/debugger/clockwork.css', 'assets/jquery/jquery.min.js', 'images/media/thumb-pdf.png'], $plan['copy']);
        self::assertSame('jquery', file_get_contents($this->target . '/assets/jquery/jquery.min.js'));
        self::assertFileDoesNotExist($this->target . '/assets/secret.php');
        self::assertFileDoesNotExist($this->target . '/assets/notes.yaml');
        self::assertFileDoesNotExist($this->target . '/images/page.html.twig');
        self::assertDirectoryDoesNotExist($this->target . '/config');
        self::assertDirectoryDoesNotExist($this->target . '/templates');
        self::assertFileExists($this->target . '/' . WebrootPublisher::MANIFEST);
        self::assertTrue($publisher->isInSync());
    }

    public function testPlanFindsMissingChangedAndRemovedFiles(): void
    {
        $publisher = new WebrootPublisher($this->source, $this->target);
        $publisher->sync();

        // An upgrade changes one file, adds one and drops one.
        $this->write('system/assets/jquery/jquery.min.js', 'jquery 4');
        $this->write('system/assets/new.js', 'new');
        unlink($this->source . '/images/media/thumb-pdf.png');

        $plan = $publisher->plan();
        self::assertSame(['assets/new.js'], $plan['copy']);
        self::assertSame(['assets/jquery/jquery.min.js'], $plan['update']);
        self::assertSame(['images/media/thumb-pdf.png'], $plan['delete']);
        self::assertFalse($publisher->isInSync($plan));

        // Planning changes nothing.
        self::assertSame('jquery', file_get_contents($this->target . '/assets/jquery/jquery.min.js'));

        $publisher->sync();
        self::assertTrue($publisher->isInSync());
        self::assertSame('jquery 4', file_get_contents($this->target . '/assets/jquery/jquery.min.js'));
        self::assertFileExists($this->target . '/assets/new.js');
        self::assertFileDoesNotExist($this->target . '/images/media/thumb-pdf.png');
        // The folder the removed file leaves empty goes too.
        self::assertDirectoryDoesNotExist($this->target . '/images/media');
    }

    public function testEditedCopyIsOutOfSync(): void
    {
        $publisher = new WebrootPublisher($this->source, $this->target);
        $publisher->sync();
        file_put_contents($this->target . '/assets/debugger/clockwork.css', 'edited');

        $plan = $publisher->plan();
        self::assertSame(['assets/debugger/clockwork.css'], $plan['update']);
        self::assertFalse($publisher->isInSync($plan));
    }

    public function testFilesItDidNotPublishAreLeftAlone(): void
    {
        $publisher = new WebrootPublisher($this->source, $this->target);
        $publisher->sync();
        $this->write('public/system/assets/mine.js', 'mine');

        $plan = $publisher->plan();
        self::assertSame(['assets/mine.js'], $plan['extra']);
        self::assertSame([], $plan['delete']);
        self::assertTrue($publisher->isInSync($plan));

        $publisher->sync();
        self::assertFileExists($this->target . '/assets/mine.js');

        // remove() takes away what it published and reports the rest.
        self::assertSame(['assets/mine.js'], $publisher->remove());
        self::assertFileExists($this->target . '/assets/mine.js');
        self::assertFileDoesNotExist($this->target . '/assets/jquery/jquery.min.js');
        self::assertFileDoesNotExist($this->target . '/' . WebrootPublisher::MANIFEST);
    }

    public function testRemoveDeletesTheWholeCopy(): void
    {
        $publisher = new WebrootPublisher($this->source, $this->target);
        $publisher->sync();

        self::assertSame([], $publisher->remove());
        self::assertDirectoryDoesNotExist($this->target);
        self::assertDirectoryExists($this->root . '/public');
        self::assertFileExists($this->source . '/assets/jquery/jquery.min.js');
    }

    public function testManifestPathsOutsideThePublishedFoldersAreIgnored(): void
    {
        $this->write('public/keep.txt', 'keep');
        $this->write('public/system/assets/old.js', 'old');
        $manifest = ['files' => ['../../keep.txt' => 'x', 'assets/../../keep.txt' => 'x', 'config/system.yaml' => 'x', 'assets/old.js' => 'x']];
        $this->write('public/system/' . WebrootPublisher::MANIFEST, json_encode($manifest));

        $publisher = new WebrootPublisher($this->source, $this->target);
        self::assertSame(['assets/old.js'], array_keys($publisher->manifest()));
        self::assertSame(['assets/old.js'], $publisher->plan()['delete']);

        $publisher->sync();
        self::assertFileExists($this->root . '/public/keep.txt');
        self::assertFileDoesNotExist($this->target . '/assets/old.js');
    }

    public function testIsPublishable(): void
    {
        self::assertTrue(WebrootPublisher::isPublishable('assets/jquery/jquery-3.x.min.js'));
        self::assertTrue(WebrootPublisher::isPublishable('images/media/thumb-pdf.PNG'));
        self::assertFalse(WebrootPublisher::isPublishable('assets/x.php'));
        self::assertFalse(WebrootPublisher::isPublishable('assets/x.yaml'));
        self::assertFalse(WebrootPublisher::isPublishable('config/x.css'));
        self::assertFalse(WebrootPublisher::isPublishable('assets/../config/x.css'));
        self::assertFalse(WebrootPublisher::isPublishable('/assets/x.css'));
        self::assertFalse(WebrootPublisher::isPublishable('assets'));
    }

    private function write(string $relative, string $content): void
    {
        $file = $this->root . '/' . $relative;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $content);
    }
}
