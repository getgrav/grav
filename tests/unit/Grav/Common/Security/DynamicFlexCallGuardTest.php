<?php

use Grav\Framework\Flex\FlexDirectory;

/**
 * A `flex-*@` directive from page frontmatter may only ask whether the object
 * exists. A directive from a blueprint file may call any method.
 */
class DynamicFlexCallGuardTest extends \PHPUnit\Framework\TestCase
{
    public function testUntrustedDirectiveCannotCallOtherMethods(): void
    {
        $object = new DynamicFlexCallGuardObject();

        foreach ([['setStorageKey', 'other'], ['delete'], ['exists', 'extra']] as $params) {
            $field = ['type' => 'text'];
            $this->callFlexField($field, 'default', $params, $object, false);

            self::assertSame(['type' => 'text'], $field);
        }

        self::assertSame([], $object->calls);
    }

    public function testUntrustedDirectiveCanAskIfTheObjectExists(): void
    {
        $object = new DynamicFlexCallGuardObject();
        $field = ['type' => 'text'];
        $this->callFlexField($field, 'disabled', ['!exists'], $object, false);

        self::assertSame(['exists'], $object->calls);
        self::assertFalse($field['disabled']);
    }

    public function testTrustedDirectiveCanCallAnyMethod(): void
    {
        $object = new DynamicFlexCallGuardObject();
        $field = ['type' => 'text'];
        $this->callFlexField($field, 'default', ['setStorageKey', 'other'], $object, true);

        self::assertSame(['setStorageKey'], $object->calls);
    }

    private function callFlexField(array &$field, string $property, array $params, object $object, bool $trusted): void
    {
        $class = new ReflectionClass(FlexDirectory::class);
        $method = $class->getMethod('dynamicFlexField');
        $method->setAccessible(true);
        $call = ['action' => 'flex', 'params' => $params, 'object' => $object, 'trusted' => $trusted];
        $method->invokeArgs($class->newInstanceWithoutConstructor(), [&$field, $property, $call]);
    }
}

class DynamicFlexCallGuardObject
{
    /** @var string[] */
    public $calls = [];

    public function exists(): bool
    {
        $this->calls[] = 'exists';

        return true;
    }

    public function setStorageKey($key = null): string
    {
        $this->calls[] = 'setStorageKey';

        return (string)$key;
    }

    public function delete(): bool
    {
        $this->calls[] = 'delete';

        return true;
    }
}
