<?php

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;
use Doctrine\Common\Collections\Expr\ExpressionVisitor;
use Doctrine\Common\Collections\Order;
use Grav\Framework\Object\ArrayObject;
use Grav\Framework\Object\Collection\ObjectExpressionVisitor;
use Grav\Framework\Object\ObjectCollection;

/**
 * ObjectCollection::matching() filters and sorts through ObjectExpressionVisitor. It builds on
 * Doctrine's abstract ExpressionVisitor, not on ClosureExpressionVisitor, which Doctrine marks
 * final; the AND / OR / NOT handling it used to inherit has to keep answering the same.
 */
class ObjectCollectionMatchingTest extends \PHPUnit\Framework\TestCase
{
    public function testVisitorBuildsOnTheAbstractDoctrineVisitor(): void
    {
        self::assertInstanceOf(ExpressionVisitor::class, new ObjectExpressionVisitor());
        self::assertNotInstanceOf(ClosureExpressionVisitor::class, new ObjectExpressionVisitor());
        self::assertSame(5, (new ObjectExpressionVisitor())->walkValue(new \Doctrine\Common\Collections\Expr\Value(5)));
    }

    public function testComparisonsCombineWithAndOrNot(): void
    {
        $expr = Criteria::expr();

        self::assertSame(['carol', 'dave'], $this->keys($expr->andX($expr->gte('age', 30), $expr->eq('team', 'red'))));
        self::assertSame(['alice', 'carol', 'dave'], $this->keys($expr->orX($expr->eq('team', 'red'), $expr->lt('age', 26))));
        self::assertSame(['bob', 'erin'], $this->keys($expr->not($expr->eq('team', 'red'))));
        self::assertSame(['bob'], $this->keys($expr->andX(
            $expr->not($expr->eq('team', 'red')),
            $expr->orX($expr->startsWith('name', 'B'), $expr->in('age', [99]))
        )));
        self::assertSame(['alice', 'carol'], $this->keys($expr->andX($expr->contains('lower(name)', 'a'), $expr->neq('age', 41))));
        self::assertSame([], $this->keys($expr->andX($expr->eq('team', 'red'), $expr->eq('team', 'blue'))));
    }

    public function testFilterAndOrderingTogether(): void
    {
        $expr = Criteria::expr();
        $criteria = Criteria::create()
            ->where($expr->orX($expr->eq('team', 'red'), $expr->eq('team', 'blue')))
            ->orderBy(['age' => Order::Descending, 'name' => Order::Ascending]);

        self::assertSame(['dave', 'bob', 'carol', 'erin', 'alice'], $this->collection()->matching($criteria)->getKeys());

        $limited = Criteria::create()->where($expr->eq('team', 'red'))->orderBy(['name' => Order::Ascending])->setFirstResult(1)->setMaxResults(1);
        self::assertSame(['carol'], $this->collection()->matching($limited)->getKeys());
    }

    private function keys($expression): array
    {
        return $this->collection()->matching(Criteria::create()->where($expression))->getKeys();
    }

    private function collection(): ObjectCollection
    {
        $object = static fn(string $name, int $age, string $team) => new ArrayObject(['name' => $name, 'age' => $age, 'team' => $team]);

        return new ObjectCollection([
            'alice' => $object('Alice', 25, 'red'),
            'bob' => $object('Bob', 34, 'blue'),
            'carol' => $object('Carol', 34, 'red'),
            'dave' => $object('Dave', 41, 'red'),
            'erin' => $object('Erin', 29, 'blue'),
        ]);
    }
}
