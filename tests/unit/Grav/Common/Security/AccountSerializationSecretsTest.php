<?php

use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Common\User\DataUser\User;
use PHPUnit\Framework\TestCase;

/**
 * UserObject with Flex stubbed out, so jsonSerialize() can run on plain elements.
 */
class SerializationStubUserObject extends UserObject
{
    public array $stubElements = [];

    public function __construct(array $elements = [], $key = '', $directory = null, bool $validate = false)
    {
        $this->stubElements = $elements;
    }

    protected function getElements()
    {
        return $this->stubElements;
    }
}

/**
 * Serializing an account (json_encode, |json_encode, API output) must leave out
 * the password hash, the 2FA secret and any pending one-time tokens, for both
 * the regular and the Flex account classes.
 */
class AccountSerializationSecretsTest extends TestCase
{
    private const ACCOUNT = [
        'username' => 'admin',
        'email' => 'admin@example.com',
        'fullname' => 'Admin',
        'hashed_password' => '$2y$10$hash',
        'twofa_secret' => 'TWOFA',
        'reset' => 'token::1234567890',
        'email_change_cancel_token' => 'cancel-token',
    ];

    private const SECRETS = ['hashed_password', 'twofa_secret', 'reset', 'email_change_cancel_token'];

    public function testRegularAccountLeavesOutSecrets(): void
    {
        $this->assertPublicFieldsOnly((new User(self::ACCOUNT))->jsonSerialize());
    }

    public function testFlexAccountLeavesOutSecrets(): void
    {
        $this->assertPublicFieldsOnly((new SerializationStubUserObject(self::ACCOUNT))->jsonSerialize());
    }

    private function assertPublicFieldsOnly(array $data): void
    {
        foreach (self::SECRETS as $field) {
            self::assertArrayNotHasKey($field, $data, "{$field} must not be serialized");
        }
        self::assertSame('admin', $data['username']);
        self::assertSame('admin@example.com', $data['email']);
    }
}
