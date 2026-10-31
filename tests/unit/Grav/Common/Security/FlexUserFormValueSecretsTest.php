<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User;
use Grav\Framework\Flex\Flex;

/**
 * The fields of a Flex form can be written in page frontmatter and every field is filled with
 * getFormValue(<field name>). Credentials and one-time tokens of an account are not form values,
 * and do not serialize either.
 */
class FlexUserFormValueSecretsTest extends \PHPUnit\Framework\TestCase
{
    private const SECRET_FIELDS = [
        'hashed_password' => '$2y$10$abcdefghijklmnopqrstuuJ9nYQqf0pZ0bBkn5a5l3cGQ2rQ3xV9.',
        'secret' => 'ABCDEFGH',
        'twofa_secret' => 'ABCDEFGH',
        'reset' => '0123456789abcdef0123456789abcdef::1900000000',
        'activation_token' => '0123456789abcdef0123456789abcdef::1900000000',
        'email_change_token' => '0123456789abcdef0123456789abcdef::1900000000',
        'email_change_cancel_token' => '0123456789abcdef0123456789abcdef',
        'magic_login' => 'hash::1900000000',
    ];

    public function testFormValuesLeaveOutCredentialsAndOneTimeTokens(): void
    {
        $user = $this->createFlexUser();
        $fields = array_fill_keys([...array_keys(self::SECRET_FIELDS), 'email'], ['type' => 'text']);
        $form = $user->getForm('card', ['form' => ['fields' => $fields]]);

        self::assertSame('jdoe@example.com', $user->getFormValue('email'));
        self::assertSame('jdoe@example.com', $form->getValue('email'));
        foreach (self::SECRET_FIELDS as $name => $value) {
            self::assertNull($user->getFormValue($name), $name);
            self::assertNull($form->getValue($name), $name);
            // The account itself keeps them: the plugins which issued a token read it back.
            self::assertSame($value, $user->get($name), $name);
        }
    }

    public function testSerializedAccountLeavesOutCredentialsAndOneTimeTokens(): void
    {
        $flexUser = $this->createFlexUser();
        $dataUser = new User(self::SECRET_FIELDS + ['email' => 'jdoe@example.com']);

        foreach ([$flexUser->jsonSerialize(), $flexUser->toArray(), $dataUser->jsonSerialize(), $dataUser->toArray()] as $serialized) {
            self::assertSame('jdoe@example.com', $serialized['email']);
            foreach (array_keys(self::SECRET_FIELDS) as $name) {
                self::assertArrayNotHasKey($name, $serialized);
            }
        }
    }

    private function createFlexUser()
    {
        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $flex = new Flex([], ['object' => $grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('user-accounts', 'blueprints://flex/user-accounts.yaml', ['enabled' => true]);

        return $flex->getDirectory('user-accounts')->createObject(self::SECRET_FIELDS + ['email' => 'jdoe@example.com'], 'jdoe');
    }
}
