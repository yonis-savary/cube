<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use Cube\Console\Commands\Queue;
use Cube\Tests\Units\Console\Classes\Billing\ReminderQueue;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class QueueTest extends TestCase
{
    protected function flush(string $queue): int
    {
        return Queue::call(Args::fromArgv(['--queue', $queue, '--flush']));
    }

    public function testAQueueIsFoundFromItsFullClassName()
    {
        $this->assertEquals(0, $this->flush(ReminderQueue::class));
    }

    public function testAQueueIsFoundFromAnUnambiguousShortName()
    {
        $this->assertEquals(0, $this->flush('NewsletterQueue'));
    }

    public function testAQueueIsFoundFromANamespaceSuffix()
    {
        $this->assertEquals(0, $this->flush('Billing\ReminderQueue'));
    }

    /** The first class whose name merely ended with the given value was launched, whatever else matched. */
    public function testAnAmbiguousShortNameListsTheCandidates()
    {
        $this->expectExceptionMessageMatches('/Mailing\\\ReminderQueue.*Billing\\\ReminderQueue|Billing\\\ReminderQueue.*Mailing\\\ReminderQueue/');

        $this->flush('ReminderQueue');
    }

    /** `letterQueue` used to launch `NewsletterQueue` : only a whole class name segment matches. */
    public function testAPartialClassNameDoesNotMatch()
    {
        $this->expectExceptionMessage('Could not find a queue class named [letterQueue]');

        $this->flush('letterQueue');
    }
}
