<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Tests\Units\Env\Classes\SpyLogger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('logger-test-');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    public function test_a_new_file_starts_with_a_header_row()
    {
        $file = $this->newLogFile();
        new Logger($file, $this->storage);

        $this->assertEquals([['Datetime', 'Level', 'Message']], $this->rowsOf($file));
    }

    public function test_a_message_is_interpolated_with_its_context()
    {
        $file = $this->newLogFile();
        $logger = new Logger($file, $this->storage);

        $logger->info('Order {order} was shipped', ['order' => 1024]);

        $this->assertEquals('Order 1024 was shipped', $this->lastRowOf($file)[2]);
    }

    public function test_the_level_is_written_in_upper_case()
    {
        $file = $this->newLogFile();
        $logger = new Logger($file, $this->storage);

        $logger->warning('Stock is low');

        $this->assertEquals('WARNING', $this->lastRowOf($file)[1]);
    }

    public function test_the_datetime_holds_milliseconds()
    {
        $file = $this->newLogFile();
        $logger = new Logger($file, $this->storage);

        $logger->info('Order shipped');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}$/',
            $this->lastRowOf($file)[0]
        );
    }

    public function test_a_message_holding_a_separator_stays_in_one_column()
    {
        $file = $this->newLogFile();
        $logger = new Logger($file, $this->storage);

        $logger->info("Order\t1024\tshipped");

        $row = $this->lastRowOf($file);

        $this->assertCount(3, $row);
        $this->assertEquals("Order\t1024\tshipped", $row[2]);
    }

    public function test_a_multiline_message_becomes_one_row_per_line()
    {
        $file = $this->newLogFile();
        $logger = new Logger($file, $this->storage);

        $logger->error("First line\nSecond line");

        $rows = $this->rowsOf($file);

        $this->assertCount(3, $rows); // header + two lines
        $this->assertEquals('First line', $rows[1][2]);
        $this->assertEquals('Second line', $rows[2][2]);
    }

    public function test_for_file_hands_back_the_same_logger()
    {
        $file = $this->newLogFile();

        $this->assertSame(
            Logger::forFile($file, $this->storage),
            Logger::forFile($file, $this->storage)
        );
    }

    public function test_a_second_logger_on_the_same_file_is_refused()
    {
        $file = $this->newLogFile();
        Logger::forFile($file, $this->storage);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($this->storage->path($file));

        new Logger($file, $this->storage);
    }

    public function test_attached_loggers_receive_every_message()
    {
        $spy = new SpyLogger();
        $logger = new Logger($this->newLogFile(), $this->storage);
        $logger->attach($spy);

        $logger->info('Order {order} was shipped', ['order' => 1024]);

        $this->assertEquals([['info', 'Order 1024 was shipped']], $spy->records);
    }

    public function test_an_attached_logger_can_be_restricted_to_some_levels()
    {
        $spy = new SpyLogger();
        $logger = new Logger($this->newLogFile(), $this->storage);
        $logger->attach($spy, ['error', 'critical']);

        $logger->info('Order shipped');
        $logger->error('Order lost');
        $logger->critical('Warehouse on fire');

        $this->assertEquals(['error', 'critical'], $spy->levels());
    }

    public function test_log_throwable_writes_the_type_the_message_and_the_trace()
    {
        $spy = new SpyLogger();
        $logger = new Logger($this->newLogFile(), $this->storage);
        $logger->attach($spy);

        $logger->logThrowable(new \InvalidArgumentException('Unknown warehouse'));

        $messages = array_column($spy->records, 1);

        $this->assertStringContainsString(\InvalidArgumentException::class, $messages[0]);
        $this->assertStringContainsString('Unknown warehouse', $messages[0]);
        $this->assertStringContainsString(__FILE__, $messages[1]);
        $this->assertEquals(['error'], array_unique($spy->levels()));
    }

    public function test_the_null_logger_writes_nowhere()
    {
        $logger = new NullLogger();
        $logger->error('Order lost');

        $this->assertEquals([], $this->storage->files());
    }

    protected function newLogFile(): string
    {
        return uniqid('log-').'.csv';
    }

    /** @return array<int,string[]> */
    protected function rowsOf(string $file): array
    {
        $lines = array_filter(explode("\n", $this->storage->read($file)), 'strlen');

        return array_map(fn ($line) => str_getcsv($line, "\t", "'", '\\'), $lines);
    }

    /** @return string[] */
    protected function lastRowOf(string $file): array
    {
        $rows = $this->rowsOf($file);

        return end($rows);
    }
}
