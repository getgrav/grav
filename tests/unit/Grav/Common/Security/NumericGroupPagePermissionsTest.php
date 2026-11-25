<?php

use Grav\Common\User\DataUser\User as DataUser;
use Grav\Framework\Flex\Pages\Traits\PageAuthorsTrait;

/**
 * A group may be named with digits only (`2024`). PHP turns such a name into an integer
 * when it is used as an array key, so the `permissions.groups` rules of a page have to be
 * matched against the groups of the account by their string value. Otherwise the rule is
 * skipped and the page falls back to the account wide permissions.
 */
class NumericGroupPagePermissionsTest extends \PHPUnit\Framework\TestCase
{
    private function memberOf(array $groups): DataUser
    {
        $user = new DataUser(['state' => 'enabled', 'groups' => $groups, 'access' => ['site' => ['login' => true]]]);
        $user->set('authenticated', true);
        $user->set('authorized', true);

        return $user;
    }

    private function pageFor(array $groups): object
    {
        return new class($groups) {
            use PageAuthorsTrait;

            public function __construct(private array $groups)
            {
            }

            public function getNestedProperty($property, $default = null)
            {
                return $property === 'header.permissions.groups' ? $this->groups : $default;
            }

            public function root(): bool
            {
                return false;
            }

            public function getKey(): string
            {
                return 'budget';
            }

            public function parent()
            {
                return null;
            }
        };
    }

    public function testNumericGroupNameIsAnIntegerKey(): void
    {
        // The premise of the other tests: quoting the name in the page header does not keep it a string.
        self::assertSame([2024], array_keys(['2024' => '-ud']));
    }

    public function testDenyForNumericGroupApplies(): void
    {
        $page = $this->pageFor(['2024' => '-ud']);

        self::assertFalse($page->isParentAuthorized('update', 'admin', $this->memberOf(['2024'])));
        self::assertFalse($page->isParentAuthorized('delete', 'admin', $this->memberOf(['interns', '2024'])));
    }

    public function testAllowForNumericGroupApplies(): void
    {
        $page = $this->pageFor(['2024' => 'ru']);

        self::assertTrue($page->isParentAuthorized('update', 'admin', $this->memberOf(['2024'])));
        self::assertNull($page->isParentAuthorized('delete', 'admin', $this->memberOf(['2024'])));
    }

    public function testAccountListingTheGroupAsAnIntegerIsStillAMember(): void
    {
        self::assertFalse($this->pageFor(['2024' => '-ud'])->isParentAuthorized('update', 'admin', $this->memberOf([2024])));
        self::assertTrue($this->pageFor(['2024' => 'ru'])->isParentAuthorized('update', 'admin', $this->memberOf(['interns', 2024])));
    }

    public function testDenyForNumericGroupBeatsAllowFromAnotherGroup(): void
    {
        $page = $this->pageFor(['interns' => 'ru', '2024' => '-u']);

        self::assertTrue($page->isParentAuthorized('update', 'admin', $this->memberOf(['interns'])));
        self::assertFalse($page->isParentAuthorized('update', 'admin', $this->memberOf(['interns', '2024'])));
    }

    public function testNonMembersAreNotAffected(): void
    {
        $page = $this->pageFor(['2024' => '-ud']);

        self::assertNull($page->isParentAuthorized('update', 'admin', $this->memberOf([])));
        self::assertNull($page->isParentAuthorized('update', 'admin', $this->memberOf(['2025'])));
        self::assertNull($page->isParentAuthorized('update', 'admin', $this->memberOf(['02024', '2024 '])));
        // Only names count as groups: no warnings, no matches from values such as `true` or a nested list.
        self::assertNull($this->pageFor(['1' => '-ud'])->isParentAuthorized('update', 'admin', $this->memberOf([true, ['1'], 1.0, null])));
    }
}
