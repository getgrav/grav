<?php

use Codeception\Util\Fixtures;
use Cron\CronExpression;
use Grav\Common\Grav;
use Grav\Common\Scheduler\Job;

/**
 * Grav's {% switch %} tag, its cron() function and the scheduler, after the switch node stopped
 * taking the tag name Twig no longer uses and cron expressions moved from the deprecated
 * CronExpression::factory() to the constructor.
 */
class TwigSwitchAndCronTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['locator']->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $this->grav['twig']->init();
    }

    private function render(string $template, array $context = []): string
    {
        // A unique comment keeps Twig's compile cache from skipping the parser and node classes.
        return $this->grav['twig']->twig()->createTemplate($template . '{# ' . uniqid('', true) . ' #}')->render($context);
    }

    public function testSwitchPicksTheMatchingCase(): void
    {
        $template = '{% switch value %}{% case 1 %}one{% case 2 or 3 %}two-or-three{% default %}other{% endswitch %}';

        self::assertSame('one', $this->render($template, ['value' => 1]));
        self::assertSame('two-or-three', $this->render($template, ['value' => 3]));
        self::assertSame('other', $this->render($template, ['value' => 9]));
        self::assertSame('', $this->render('{% switch value %}{% case 1 %}one{% endswitch %}', ['value' => 2]), 'No default renders nothing');
    }

    public function testCronFunctionParsesExpressionsAndAliases(): void
    {
        self::assertSame('0 0 * * *', $this->render("{{ cron('@daily').getExpression() }}"));
        self::assertSame('*/5 * * * *', $this->render("{{ cron('*/5 * * * *').getExpression() }}"));
        self::assertSame('none', $this->render("{{ cron('not a cron') is null ? 'none' : 'some' }}"), 'An invalid expression gives null instead of an error');
    }

    public function testJobFallsBackToEveryMinuteForAnInvalidExpression(): void
    {
        $job = new Job('echo hi');
        $job->at('not a cron');
        self::assertTrue($job->isDue(new DateTime('2026-11-20 10:17:00')), 'Invalid expressions run every minute, as before');

        $daily = new Job('echo hi');
        $daily->at('@daily');
        self::assertTrue($daily->isDue(new DateTime('2026-11-20 00:00:00')));
        self::assertFalse($daily->isDue(new DateTime('2026-11-20 10:17:00')));
        self::assertInstanceOf(CronExpression::class, $daily->getCronExpression());
    }

    public function testSchedulerServiceCreatesTheScheduler(): void
    {
        $container = new \Pimple\Container();
        (new \Grav\Common\Service\SchedulerServiceProvider())->register($container);

        self::assertInstanceOf(\Grav\Common\Scheduler\Scheduler::class, $container['scheduler']);
    }
}
