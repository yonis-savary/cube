<?php

namespace Cube\Routine;

use Cube\Core\Autoloader;
use Cube\Routine\Cron\CronValue;

class CronExpression
{
    protected CronValue $min;
    protected CronValue $hour;
    protected CronValue $dayOfTheMonth;
    protected CronValue $month;
    protected CronValue $dayOfTheWeek;

    /**
     * @param string $expression Reminder, expression is made of min, hour, dayOfTheMonth, month, dayOfTheWeek
     */
    public function __construct(
        string $expression
    ) {
        if (!preg_match('/([^ ]+ ){4}([^ ]+)/', $expression)) {
            throw new \InvalidArgumentException('Given expression is not a standart cron expression');
        }

        list($min, $hour, $dayOfTheMonth, $month, $dayOfTheWeek) = explode(' ', $expression);

        $values = [&$min, &$hour, &$dayOfTheMonth, &$month, &$dayOfTheWeek];

        foreach ($values as &$value) {
            $value = $this->transformValueToCronValue($value);
        }
        /** @var CronValue $min */
        /** @var CronValue $hour */
        /** @var CronValue $dayOfTheMonth */
        /** @var CronValue $month */
        /** @var CronValue $dayOfTheWeek */

        $this->min = $min;
        $this->hour = $hour;
        $this->dayOfTheMonth = $dayOfTheMonth;
        $this->month = $month;
        $this->dayOfTheWeek = $dayOfTheWeek;

        $this->assertValueIsInBounds($this->min, 0, 59, 'minute');
        $this->assertValueIsInBounds($this->hour, 0, 23, 'hour');
        $this->assertValueIsInBounds($this->dayOfTheMonth, 1, 31, 'day of the month');
        $this->assertValueIsInBounds($this->month, 1, 12, 'month');
        $this->assertValueIsInBounds($this->dayOfTheWeek, 0, 6, 'day of the week');
    }

    public static function everyMinute(int $step = 1): self
    {
        $expr = 1 == $step ? '*' : "*/{$step}";

        return new self("{$expr} * * * *");
    }

    public static function everyHour(int $step = 1): self
    {
        $expr = 1 == $step ? '*' : "*/{$step}";

        return new self("0 {$expr} * * *");
    }

    public static function everyDayOfTheMonth(int $step = 1): self
    {
        $expr = 1 == $step ? '*' : "*/{$step}";

        return new self("0 0 {$expr} * *");
    }

    public static function everyDayOfTheWeek(int $step = 1): self
    {
        $expr = 1 == $step ? '*' : "*/{$step}";

        return new self("0 0 * * {$expr}");
    }

    public function matches(\DateTime|string $datetime = 'now')
    {
        if (is_string($datetime)) {
            $datetime = new \DateTime($datetime);
        }

        $timestamp = $datetime->getTimestamp();

        return
            $this->min->matches(date('i', $timestamp))
            && $this->hour->matches(date('H', $timestamp))
            && $this->dayOfTheMonth->matches(date('d', $timestamp))
            && $this->month->matches(date('m', $timestamp))
            && $this->dayOfTheWeek->matches(date('w', $timestamp));
    }

    protected function transformValueToCronValue(string $value): CronValue
    {
        $classes = Autoloader::classesThatImplements(CronValue::class);

        /** @var CronValue $class */
        foreach ($classes as $class) {
            if ($class::accepts($value)) {
                return new $class($value);
            }
        }

        throw new \InvalidArgumentException("Could not determine type of Cron value from [{$value}] tried [".join(', ', $classes).']');
    }

    protected function assertValueIsInBounds(CronValue $cronValue, int $min, int $max, string $label): void
    {
        foreach ($cronValue->getHeldValues() as $value) {
            if (!($min <= $value && $value <= $max)) {
                throw new \InvalidArgumentException("The {$label} value must be between {$min} and {$max}");
            }
        }
    }
}
