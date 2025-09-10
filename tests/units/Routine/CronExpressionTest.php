<?php

namespace Cube\Tests\Units\Routine;

use Cube\Routine\CronExpression;
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
}
