<?php

use Codeception\Util\Fixtures;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;

/**
 * FlexObject::update($data, $files) takes `$files` as `[field => [filename => upload|null]]`,
 * where null queues a delete. MediaUploadTrait::deleteFile() follows a folder in the name
 * (`original/photo.jpg`) and the folder of a page also holds its child pages, so a queued
 * delete has to be a plain filename, like an upload.
 */
class FlexMediaDeleteNameTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();
    }

    /**
     * @dataProvider providerBadNames
     */
    public function testPageRefusesQueuedDelete(string $name): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(400);

        $this->createObject('pages')->update([], ['files' => [$name => null]]);
    }

    /**
     * @dataProvider providerBadNames
     */
    public function testUserRefusesQueuedDelete(string $name): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(400);

        $this->createObject('user-accounts')->update([], ['avatar' => [$name => null]]);
    }

    public static function providerBadNames(): array
    {
        return [
            'file of a child page' => ['rules/seal.png'],
            'parent folder' => ['../seal.png'],
            'backslash' => ['rules\\seal.png'],
            'property separator' => ["seal\npng"],
            'page file' => ['default.md'],
        ];
    }

    /**
     * @dataProvider providerTypes
     */
    public function testPlainNameIsStillQueued(string $type, string $field): void
    {
        $object = $this->createObject($type);
        $object->update([], [$field => ['seal.png' => null, '2024' => null]]);

        self::assertSame(['seal.png', '2024'], array_map('strval', array_keys($object->__debugInfo()['uploads:private'])));
    }

    public static function providerTypes(): array
    {
        return [
            'page' => ['pages', 'files'],
            'user' => ['user-accounts', 'avatar'],
        ];
    }

    private function createObject(string $type): FlexObjectInterface
    {
        $directory = new FlexDirectory($type, "blueprints://flex/{$type}.yaml", ['enabled' => true]);
        $meta = ['key' => 'notes', 'storage_key' => 'notes', 'storage_timestamp' => 1];

        return $directory->createObject(['__META' => $meta], 'notes');
    }
}
