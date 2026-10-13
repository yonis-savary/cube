<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use Cube\Console\Commands\Test;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Utils\Path;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TestCommandTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('test-command-');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    public function testPhpUnitExitCodeAndArgumentsAreForwarded()
    {
        $bin = $this->storage->child('vendor/bin');
        $bin->write('phpunit', "#!/bin/sh\necho \"\$@\"\nexit 3\n");
        chmod($bin->path('phpunit'), 0755);

        $previousProject = Path::getProjectPath();
        Path::resolveProjectPath($this->storage->getRoot());

        try {
            ob_start();
            $exitCode = (new Test())->execute(Args::fromArgv(['--filter=ProductTest', 'Tests/Unit']));
            $output = ob_get_clean();
        } finally {
            Path::resolveProjectPath($previousProject);
        }

        $this->assertEquals(3, $exitCode);
        $this->assertStringContainsString('--filter ProductTest', $output);
        $this->assertStringContainsString('Tests/Unit', $output);
    }
}
