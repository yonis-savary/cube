<?php

namespace Cube\Tests\Units\Queue;

use Cube\Env\Storage;
use Cube\Queue\Drivers\LocalDiskQueueDriver;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use PHPUnit\Framework\TestCase;

class LocalDiskQueueDriverTest extends TestCase
{
    use HasTemporaryStorage;

    protected LocalDiskQueueDriver $driver;

    protected function setUp(): void
    {
        $identifier = uniqid('local-disk-queue-test-');

        $this->driver = new LocalDiskQueueDriver();
        $this->driver->setIdentifier($identifier);
        $this->storage = Storage::getInstance()->child('Queues')->child($identifier);
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    /** A job file caught before its content is written unserializes to false, which next() cannot return. */
    public function test_an_empty_job_file_gives_nothing_to_process()
    {
        $this->storage->write(uniqid(time().'-'), '');

        $this->assertNull($this->driver->next());
    }
}
