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

    public function test_a_queue_is_found_from_its_full_class_name()
    {
        $this->assertEquals(0, $this->flush(ReminderQueue::class));
    }

    public function test_a_queue_is_found_from_an_unambiguous_short_name()
    {
        $this->assertEquals(0, $this->flush('NewsletterQueue'));
    }

    public function test_a_queue_is_found_from_a_namespace_suffix()
    {
        $this->assertEquals(0, $this->flush('Billing\ReminderQueue'));
    }

    /** The first class whose name merely ended with the given value was launched, whatever else matched. */
    public function test_an_ambiguous_short_name_lists_the_candidates()
    {
        $this->expectExceptionMessageMatches('/Mailing\\\\ReminderQueue.*Billing\\\\ReminderQueue|Billing\\\\ReminderQueue.*Mailing\\\\ReminderQueue/');

        $this->flush('ReminderQueue');
    }

    /** `letterQueue` used to launch `NewsletterQueue` : only a whole class name segment matches. */
    public function test_a_partial_class_name_does_not_match()
    {
        $this->expectExceptionMessage('Could not find a queue class named [letterQueue]');

        $this->flush('letterQueue');
    }
}
