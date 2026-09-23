<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Flex pages list `key` in their search fields. A page has no `key` property, so the search has to
 * match the page key (its route) itself, while other Flex types keep searching as before.
 */
class FlexPageSearchTest extends \PHPUnit\Framework\TestCase
{
    /** @var Flex */
    protected $flex;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $this->flex = new Flex([], ['object' => $grav['config']->get('system.flex', [])]);
        $this->flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);
        $this->flex->addDirectoryType('user-accounts', 'blueprints://flex/user-accounts.yaml', ['enabled' => true]);
    }

    public function testPageSearchMatchesTheKey(): void
    {
        $directory = $this->directory('pages');
        self::assertContains('key', $directory->getSearchProperties());

        $page = $directory->createObject(['header' => ['title' => 'Getting Started']], 'docs/start/getting-started');

        self::assertGreaterThan(0, $page->search('start/getting'));
        self::assertGreaterThan(0, $page->searchProperty('key', 'docs/start'));
        self::assertGreaterThan(0, $page->search('Getting Started'), 'Other fields are still searched');
        self::assertSame(0.0, $page->search('checkout'));
    }

    public function testOtherTypesKeepTheirSearch(): void
    {
        $directory = $this->directory('user-accounts');
        self::assertContains('key', $directory->getSearchProperties());

        $user = $directory->createObject(['email' => 'someone@example.com'], 'jdoe');

        self::assertSame(0.0, $user->searchProperty('key', 'jdoe'));
        self::assertGreaterThan(0, $user->search('someone@'));
    }

    protected function directory(string $type): FlexDirectory
    {
        $directory = $this->flex->getDirectory($type);
        self::assertNotNull($directory);

        return $directory;
    }
}
