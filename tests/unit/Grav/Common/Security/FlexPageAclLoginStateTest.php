<?php

use Grav\Common\Data\Blueprint;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\User\DataUser\User;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexDirectory;

/**
 * PageObject with the Flex directory and storage stubbed out, so the page ACL can
 * be driven without booting Flex.
 */
class LoginStateStubPageObject extends PageObject
{
    public ?UserInterface $stubActiveUser = null;
    public ?object $stubParent = null;
    public array $stubPermissions = [];
    public bool $stubExists = true;

    public function __construct(array $elements = [], $key = '', ?FlexDirectory $directory = null, bool $validate = false)
    {
    }

    public function getKey()
    {
        return 'page';
    }

    public function exists(): bool
    {
        return $this->stubExists;
    }

    public function root($var = null): bool
    {
        return false;
    }

    public function parent(?PageInterface $var = null)
    {
        return $this->stubParent;
    }

    public function getNestedProperty($property, $default = null, $separator = null)
    {
        return $this->stubPermissions[$property] ?? $default;
    }

    protected function getActiveUser(): ?UserInterface
    {
        return $this->stubActiveUser;
    }
}

class FlexPageAclLoginStateTest extends \PHPUnit\Framework\TestCase
{
    private function member(bool $authorized): UserInterface
    {
        $user = new User(['username' => 'member', 'groups' => ['editors']], $this->createMock(Blueprint::class));
        $user->authenticated = true;
        $user->authorized = $authorized;

        return $user;
    }

    private function page(): LoginStateStubPageObject
    {
        $page = new LoginStateStubPageObject();
        $page->stubPermissions = ['header.permissions.groups' => ['editors' => 'crud']];

        return $page;
    }

    public function testGroupRuleAppliesToLoggedInSessionUser(): void
    {
        $page = $this->page();
        $page->stubActiveUser = $this->member(true);

        self::assertTrue($page->isParentAuthorized('update', 'admin'));
    }

    public function testGroupRuleSkipsSessionUserWaitingForSecondFactor(): void
    {
        $page = $this->page();
        $page->stubActiveUser = $this->member(false);

        self::assertNull($page->isParentAuthorized('update', 'admin'));
    }

    public function testGroupRuleAppliesToUserPassedInExplicitly(): void
    {
        self::assertTrue($this->page()->isParentAuthorized('update', 'admin', $this->member(false)));
    }

    public function testCreatingPageAsksParentAboutSessionUserAsSessionUser(): void
    {
        $parent = new class {
            public array $asked = [];

            public function isAuthorized(string $action, ?string $scope = null, ?UserInterface $user = null): ?bool
            {
                $this->asked[] = $user;

                return true;
            }
        };

        $user = $this->member(false);
        $page = new LoginStateStubPageObject();
        $page->stubExists = false;
        $page->stubParent = $parent;
        $page->stubActiveUser = $user;

        $page->isAuthorized('create', 'admin');
        $page->isAuthorized('create', 'admin', $user);

        self::assertSame([null, $user], $parent->asked);
    }
}
