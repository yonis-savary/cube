<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Env\Logger\StdOutLogger;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Tests\Units\Env\Classes\SpyLogger;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
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

    public function testANewFileStartsWithAHeaderRow()
    {
        $file = $this->newLogFile();
        new Logger($file, $this->storage);

        $this->assertEquals([['Datetime', 'Level', 'Message']], $this->rowsOf($file));
    }

    public function testAMessageIsInterpolatedWithItsContext()
    {
        $file = $this->newLogFile();
        $logger = new Logger($file, $this->storage);

        $logger->info('Order {order} was shipped', ['order' => 1024]);

        $this->assertEquals('Order 1024 was shipped', $this->lastRowOf($file)[2]);
    }

    public function testTheLevelIsWrittenInUpperCase()
    {
        $file = $this->newLogFile();
        $logger = new Logger($file, $this->storage);

        $logger->warning('Stock is low');

        $this->assertEquals('WARNING', $this->lastRowOf($file)[1]);
    }

    public function testTheDatetimeHoldsMilliseconds()
    {
        $file = $this->newLogFile();
        $logger = new Logger($file, $this->storage);

        $logger->info('Order shipped');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}$/',
            $this->lastRowOf($file)[0]
        );
    }

    public function testAMessageHoldingASeparatorStaysInOneColumn()
    {
        $file = $this->newLogFile();
        $logger = new Logger($file, $this->storage);

        $logger->info("Order\t1024\tshipped");

        $row = $this->lastRowOf($file);

        $this->assertCount(3, $row);
        $this->assertEquals("Order\t1024\tshipped", $row[2]);
    }

    public function testAMultilineMessageBecomesOneRowPerLine()
    {
        $file = $this->newLogFile();
        $logger = new Logger($file, $this->storage);

        $logger->error("First line\nSecond line");

        $rows = $this->rowsOf($file);

        $this->assertCount(3, $rows); // header + two lines
        $this->assertEquals('First line', $rows[1][2]);
        $this->assertEquals('Second line', $rows[2][2]);
    }

    public function testForFileHandsBackTheSameLogger()
    {
        $file = $this->newLogFile();

        $this->assertSame(
            Logger::forFile($file, $this->storage),
            Logger::forFile($file, $this->storage)
        );
    }

    public function testASecondLoggerOnTheSameFileIsRefused()
    {
        $file = $this->newLogFile();
        Logger::forFile($file, $this->storage);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($this->storage->path($file));

        new Logger($file, $this->storage);
    }

    public function testAttachedLoggersReceiveEveryMessage()
    {
        $spy = new SpyLogger();
        $logger = new Logger($this->newLogFile(), $this->storage);
        $logger->attach($spy);

        $logger->info('Order {order} was shipped', ['order' => 1024]);

        $this->assertEquals([['info', 'Order 1024 was shipped']], $spy->records);
    }

    public function testAnAttachedLoggerCanBeRestrictedToSomeLevels()
    {
        $spy = new SpyLogger();
        $logger = new Logger($this->newLogFile(), $this->storage);
        $logger->attach($spy, ['error', 'critical']);

        $logger->info('Order shipped');
        $logger->error('Order lost');
        $logger->critical('Warehouse on fire');

        $this->assertEquals(['error', 'critical'], $spy->levels());
    }

    public function testLogThrowableWritesTheTypeTheMessageAndTheTrace()
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

    public function testTheNullLoggerWritesNowhere()
    {
        $logger = new NullLogger();
        $logger->error('Order lost');

        $this->assertEquals([], $this->storage->files());
    }

    /** forFile() hands back the base logger already registered for cube.csv instead of a subclass instance. */
    public function testALoggerSubclassGetsAnInstanceOfItsOwnType()
    {
        Logger::getInstance();

        try {
            $this->assertInstanceOf(StdOutLogger::class, StdOutLogger::getInstance());
        } finally {
            StdOutLogger::removeInstance();
        }
    }

    /** Every StdOutLogger shares one stdout stream, and the first one destroyed closes it for all. */
    public function testDestroyingAStdoutLoggerKeepsTheSharedStreamOpen()
    {
        (fn () => new StdOutLogger())();

        $logger = new StdOutLogger();
        $stream = (new \ReflectionProperty(StdOutLogger::class, 'stream'))->getValue($logger);

        $this->assertTrue(is_resource($stream));
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

    /** At shutdown, PHP may close the stream before destroying a logger kept in Logger::$loggers : fclose() threw on it */
    public function testALoggerWhoseStreamIsAlreadyClosedIsDestroyedQuietly()
    {
        $logger = new Logger('closed.csv', $this->storage);
        fclose((fn () => $this->stream)->call($logger));

        $logger->__destruct();

        $this->assertNotSame($logger, Logger::forFile('closed.csv', $this->storage));
    }
}
