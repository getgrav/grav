<?php

use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\FlexFormFlash;
use Grav\Framework\Flex\Interfaces\FlexInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;

/**
 * FlexFormFlash reading a fixed index file, so init() can be driven without a session.
 */
class StoredStubFlexFormFlash extends FlexFormFlash
{
    public static array $stored = [];

    protected function loadStoredForm(): ?array
    {
        return self::$stored;
    }
}

/**
 * Class FlexFormFlashObjectTest
 *
 * A form for a new object continues from the object kept in its flash. The flash id comes
 * from the request (__unique_form_id__), so the flash may have been written by a form of
 * another Flex type: its object must only be restored into the directory it was taken from,
 * and a form for a new object must not become a form for an existing one.
 */
class FlexFormFlashObjectTest extends \PHPUnit\Framework\TestCase
{
    /** @var FlexDirectory&\PHPUnit\Framework\MockObject\MockObject */
    private $directory;
    /** @var FlexObjectInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $blank;
    /** @var FlexObjectInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $stored;

    private function store(string $type, bool $exists): void
    {
        $this->directory = $this->createMock(FlexDirectory::class);
        $this->directory->method('getFlexType')->willReturn('user-accounts');

        $this->blank = $this->createMock(FlexObjectInterface::class);
        $this->blank->method('exists')->willReturn(false);
        $this->blank->method('getFlexDirectory')->willReturn($this->directory);

        $this->stored = $this->createMock(FlexObjectInterface::class);
        $this->stored->method('exists')->willReturn($exists);
        $this->stored->method('getFlexDirectory')->willReturn($this->directory);
        $this->directory->method('createObjectFromStorage')->willReturn($this->stored);

        StoredStubFlexFormFlash::$stored = [
            'form' => 'x',
            'object' => ['type' => $type, 'key' => 'admin', 'serialized' => ['__META' => ['storage_key' => 'admin']]],
        ];
    }

    public function testUnsavedObjectOfTheSameDirectoryIsRestored(): void
    {
        $this->store('user-accounts', false);

        self::assertSame($this->stored, (new StoredStubFlexFormFlash(['object' => $this->blank]))->getObject());
    }

    public function testObjectOfAnotherDirectoryIsNotRestored(): void
    {
        $this->store('user-groups', false);
        $this->directory->expects(self::never())->method('createObjectFromStorage');

        self::assertSame($this->blank, (new StoredStubFlexFormFlash(['object' => $this->blank]))->getObject());

        $flash = new StoredStubFlexFormFlash(['directory' => $this->directory]);
        self::assertNull($flash->getObject());
        self::assertSame($this->directory, $flash->getDirectory());
    }

    public function testNewObjectDoesNotTurnIntoAnExistingOne(): void
    {
        $this->store('user-accounts', true);

        self::assertSame($this->blank, (new StoredStubFlexFormFlash(['object' => $this->blank]))->getObject());
    }

    public function testFlashOnItsOwnRestoresIntoTheStoredDirectory(): void
    {
        $this->store('user-accounts', true);

        $flex = $this->createMock(FlexInterface::class);
        $flex->expects(self::once())->method('getDirectory')->with('user-accounts')->willReturn($this->directory);

        self::assertSame($this->stored, (new StoredStubFlexFormFlash(['flex' => $flex]))->getObject());
    }
}
