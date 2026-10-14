<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use Cube\Console\Commands\Migration\Rollback;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class MigrateRollbackTest extends TestCase
{
    /** A non-numeric or zero --steps must be refused before any migration is reverted. */
    public function testAnInvalidStepCountIsRefused()
    {
        ob_start();
        $exitCodes = [
            (new Rollback())->execute(Args::fromArgv(['--steps=all'])),
            (new Rollback())->execute(Args::fromArgv(['--steps=0'])),
        ];
        $output = ob_get_clean();

        $this->assertEquals([1, 1], $exitCodes);
        $this->assertStringContainsString('--steps expects a positive integer, got [all]', $output);
    }
}
