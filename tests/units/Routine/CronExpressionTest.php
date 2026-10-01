<?php

namespace Cube\Tests\Units\Routine;

use Cube\Routine\CronExpression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class CronExpressionTest extends TestCase
{
    public function testMatches()
    {
        $pointer = new \DateTime('2024-01-01 00:00:00');
        $end = new \DateTime('2024-01-07 23:59:59');

        $expressions = [
            'minutes' => new CronExpression('* * * * *'),
            'hourly' => new CronExpression('0 * * * *'),
            'daily' => new CronExpression('0 0 * * *'),
            'at_noon' => new CronExpression('0 12 * * *'),
            'on_sunday' => new CronExpression('0 0 * * 0'),
            'every_20_minutes' => new CronExpression('*/20 * * * *'), // Tests Step
            'from_9_to_17_the_week' => new CronExpression('0 9-17 * * 1-5'), // Tests Range
            'every_5_minutes_the_weekend' => new CronExpression('*/5 * * * 0,6'), // Tests sets of value
        ];

        $counters = [];
        foreach ($expressions as $name => $_) {
            $counters[$name] = 0;
        }

        while ($pointer <= $end) {
            foreach ($expressions as $name => $expression) {
                if ($expression->matches($pointer)) {
                    ++$counters[$name];
                }
            }

            $pointer->add(\DateInterval::createFromDateString('1 minute'));
        }

        $this->assertEquals($counters, [
            'minutes' => 60 * 24 * 7,
            'hourly' => 24 * 7,
            'daily' => 7,
            'at_noon' => 7,
            'on_sunday' => 1,
            'every_20_minutes' => 3 * 24 * 7,
            'from_9_to_17_the_week' => 9 * 5,
            'every_5_minutes_the_weekend' => (60 / 5) * 24 * 2,
        ]);
    }

    /**
     * date() answers in the default timezone : reading the fields through the DateTime is
     * what makes an 8:30 schedule mean 8:30 wherever the application lives.
     */
    public function testTheTimezoneOfTheGivenDateIsHonored()
    {
        $tokyo = new \DateTime('2026-08-14 08:30:00', new \DateTimeZone('Asia/Tokyo'));

        $this->assertTrue((new CronExpression('30 8 * * *'))->matches($tokyo));
        $this->assertFalse((new CronExpression('30 23 * * *'))->matches($tokyo));
    }

    public function testAStringDateIsAccepted()
    {
        $expression = new CronExpression('30 8 * * *');

        $this->assertTrue($expression->matches('2026-08-14 08:30:00'));
        $this->assertFalse($expression->matches('2026-08-14 08:31:00'));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function getInvalidExpressions(): array
    {
        return [
            'four fields' => ['0 0 * *'],
            'six fields' => ['0 0 * * * 2026'],
            'trailing text' => ['0 0 * * * and then some'],
            'empty' => [''],
            'unknown value type' => ['nope * * * *'],
            'zero step' => ['*/0 * * * *'],
            'step wider than the minutes' => ['*/70 * * * *'],
            'minute out of bounds' => ['60 * * * *'],
            'hour out of bounds' => ['0 24 * * *'],
            'day of the month out of bounds' => ['0 0 32 * *'],
            'month out of bounds' => ['0 0 * 13 *'],
            'day of the week out of bounds' => ['0 0 * * 7'],
            'reversed range' => ['10-5 * * * *'],
        ];
    }

    #[DataProvider('getInvalidExpressions')]
    public function testAnInvalidExpressionIsRefused(string $expression)
    {
        $this->expectException(\InvalidArgumentException::class);

        new CronExpression($expression);
    }

    public function testARangeCanHoldASingleValue()
    {
        $expression = new CronExpression('5-5 * * * *');

        $this->assertTrue($expression->matches('2026-08-14 10:05:00'));
        $this->assertFalse($expression->matches('2026-08-14 10:06:00'));
    }

    public function testEveryMinute()
    {
        $this->assertTrue(CronExpression::everyMinute()->matches('2026-08-14 10:07:00'));

        $everyFive = CronExpression::everyMinute(5);
        $this->assertTrue($everyFive->matches('2026-08-14 10:05:00'));
        $this->assertFalse($everyFive->matches('2026-08-14 10:07:00'));
    }

    public function testEveryHour()
    {
        $this->assertTrue(CronExpression::everyHour()->matches('2026-08-14 10:00:00'));
        $this->assertFalse(CronExpression::everyHour()->matches('2026-08-14 10:30:00'));

        $everyTwo = CronExpression::everyHour(2);
        $this->assertTrue($everyTwo->matches('2026-08-14 10:00:00'));
        $this->assertFalse($everyTwo->matches('2026-08-14 11:00:00'));
    }

    public function testEveryDayOfTheMonth()
    {
        $this->assertTrue(CronExpression::everyDayOfTheMonth()->matches('2026-08-14 00:00:00'));
        $this->assertFalse(CronExpression::everyDayOfTheMonth()->matches('2026-08-14 00:01:00'));

        $everyThree = CronExpression::everyDayOfTheMonth(3);
        $this->assertTrue($everyThree->matches('2026-08-15 00:00:00'));
        $this->assertFalse($everyThree->matches('2026-08-14 00:00:00'));
    }

    public function testEveryDayOfTheWeek()
    {
        // 2026-08-16 is a sunday, day 0 of the week
        $this->assertTrue(CronExpression::everyDayOfTheWeek()->matches('2026-08-16 00:00:00'));

        $everyTwo = CronExpression::everyDayOfTheWeek(2);
        $this->assertTrue($everyTwo->matches('2026-08-16 00:00:00'));
        $this->assertFalse($everyTwo->matches('2026-08-17 00:00:00'));
    }

    /** A step counts from zero, so a step of 2 on the 1-based day of the month skips the 1st. */
    public function testADayOfTheMonthStepStartsOnTheFirst()
    {
        $everyTwo = new CronExpression('0 0 */2 * *');

        $this->assertTrue($everyTwo->matches('2026-08-01 00:00:00'));
        $this->assertFalse($everyTwo->matches('2026-08-02 00:00:00'));
    }

    /** A step counts from zero, so a step of 6 on the 1-based month never fires in January. */
    public function testAMonthStepStartsOnJanuary()
    {
        $everySix = new CronExpression('0 0 1 */6 *');

        $this->assertTrue($everySix->matches('2026-01-01 00:00:00'));
        $this->assertTrue($everySix->matches('2026-07-01 00:00:00'));
        $this->assertFalse($everySix->matches('2026-06-01 00:00:00'));
    }
}
